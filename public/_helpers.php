<?php
require_once __DIR__ . '/../app/db.php';

function redirect(string $path): void {
    header('Location: ' . $path);
    exit;
}

function req(string $key, $default = '') {
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function configure_session_storage(): void {
    static $configured = false;
    if ($configured) {
        return;
    }
    $configured = true;

    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    if (!empty($_SERVER['HTTPS']) && (string)$_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }

    try {
        $pdo = db();
        ensure_admin_sessions_table($pdo);
        $handler = new class($pdo) implements SessionHandlerInterface {
            private PDO $pdo;

            public function __construct(PDO $pdo) {
                $this->pdo = $pdo;
            }

            public function open(string $path, string $name): bool {
                return true;
            }

            public function close(): bool {
                return true;
            }

            public function read(string $id): string|false {
                $stmt = $this->pdo->prepare('SELECT session_data FROM admin_sessions WHERE session_id=? LIMIT 1');
                $stmt->execute([$id]);
                $row = $stmt->fetch();
                return (string)($row['session_data'] ?? '');
            }

            public function write(string $id, string $data): bool {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO admin_sessions(session_id, session_data, last_activity) VALUES (?,?,CURRENT_TIMESTAMP) '
                    . 'ON DUPLICATE KEY UPDATE session_data=VALUES(session_data), last_activity=CURRENT_TIMESTAMP'
                );
                return $stmt->execute([$id, $data]);
            }

            public function destroy(string $id): bool {
                $stmt = $this->pdo->prepare('DELETE FROM admin_sessions WHERE session_id=?');
                return $stmt->execute([$id]);
            }

            public function gc(int $max_lifetime): int|false {
                $cutoff = date('Y-m-d H:i:s', time() - $max_lifetime);
                $stmt = $this->pdo->prepare('DELETE FROM admin_sessions WHERE last_activity < ?');
                $stmt->execute([$cutoff]);
                return $stmt->rowCount();
            }
        };
        session_set_save_handler($handler, true);
    } catch (Throwable $e) {
        // Fallback to PHP defaults if DB is not available yet.
    }
}

function sanitize_next_path(string $path, ?string $fallback = null): string {
    $fallback = $fallback ?? url_for('/index.php');
    $path = trim($path);
    if ($path === '') {
        return $fallback;
    }

    $parts = parse_url($path);
    if ($parts === false) {
        return $fallback;
    }

    // Block absolute/external URLs.
    foreach (['scheme', 'host', 'user', 'pass', 'port'] as $part) {
        if (isset($parts[$part])) {
            return $fallback;
        }
    }

    $targetPath = (string)($parts['path'] ?? '');
    if ($targetPath === '' || $targetPath[0] !== '/') {
        return $fallback;
    }

    if (str_starts_with($targetPath, '//')) {
        return $fallback;
    }

    $base = app_base_path();
    if ($base !== '' && $targetPath !== $base && !str_starts_with($targetPath, $base . '/')) {
        return $fallback;
    }

    $query = isset($parts['query']) ? ('?' . $parts['query']) : '';
    return $targetPath . $query;
}

function ensure_session_started(): void {
    if (session_status() === PHP_SESSION_NONE) {
        configure_session_storage();
        session_start();
    }
}

function ensure_admin_sessions_table(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS admin_sessions ('
        . 'session_id VARCHAR(128) PRIMARY KEY,'
        . 'session_data MEDIUMTEXT NOT NULL,'
        . 'last_activity TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        . ') ENGINE=InnoDB'
    );

    $done = true;
}

function ensure_admin_table(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS admin_users ('
        . 'admin_id INT AUTO_INCREMENT PRIMARY KEY,'
        . 'username VARCHAR(80) NOT NULL UNIQUE,'
        . 'password_hash VARCHAR(255) NOT NULL,'
        . 'created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,'
        . 'updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        . ') ENGINE=InnoDB'
    );

    $count = (int)($pdo->query('SELECT COUNT(*) c FROM admin_users')->fetch()['c'] ?? 0);
    if ($count === 0) {
        $defaultUser = (string)(env_str('ADMIN_USER') ?? 'admin');
        $defaultPass = (string)(env_str('ADMIN_PASS') ?? 'admin123');
        $hash = password_hash($defaultPass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO admin_users(username, password_hash) VALUES (?,?)');
        $stmt->execute([$defaultUser, $hash]);
    }

    $done = true;
}

function admin_is_logged_in(): bool {
    ensure_session_started();
    return !empty($_SESSION['admin_id']);
}

function admin_username(): string {
    ensure_session_started();
    return (string)($_SESSION['admin_username'] ?? 'Admin');
}

function admin_id(): int {
    ensure_session_started();
    return (int)($_SESSION['admin_id'] ?? 0);
}

function admin_login(array $admin): void {
    ensure_session_started();
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)($admin['admin_id'] ?? 0);
    $_SESSION['admin_username'] = (string)($admin['username'] ?? 'Admin');
}

function admin_set_username(string $username): void {
    ensure_session_started();
    $_SESSION['admin_username'] = $username;
}

function admin_logout(): void {
    ensure_session_started();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'] ?? '/',
            $params['domain'] ?? '',
            (bool)($params['secure'] ?? false),
            (bool)($params['httponly'] ?? false)
        );
    }
    session_destroy();
}

function require_admin_login(): void {
    ensure_session_started();
    ensure_admin_table(db());

    if (admin_is_logged_in()) {
        return;
    }

    $next = sanitize_next_path((string)($_SERVER['REQUEST_URI'] ?? ''), url_for('/index.php'));
    redirect(url_for('/login.php') . '?next=' . urlencode($next));
}

if (PHP_SAPI !== 'cli') {
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $publicScripts = ['login.php', 'setup.php', 'logout.php'];
    if (!in_array($script, $publicScripts, true)) {
        require_admin_login();
    }
}
