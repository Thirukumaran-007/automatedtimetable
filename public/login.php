<?php
require_once __DIR__ . '/_helpers.php';

ensure_session_started();

$error = '';
$notice = (string)($_GET['msg'] ?? '');
$next = sanitize_next_path((string)req('next', ''), url_for('/index.php'));

try {
    ensure_admin_table(db());
} catch (Throwable $e) {
    $error = 'Error: ' . $e->getMessage();
}

if (admin_is_logged_in()) {
    redirect($next);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
    } else {
        $stmt = db()->prepare('SELECT admin_id, username, password_hash FROM admin_users WHERE username=? LIMIT 1');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        $valid = is_array($admin) && password_verify($password, (string)($admin['password_hash'] ?? ''));
        if (!$valid) {
            $error = 'Invalid username or password.';
        } else {
            admin_login($admin);
            redirect($next);
        }
    }
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login - <?= h(APP_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= h(url_for('/assets/bootstrap.min.css')) ?>" rel="stylesheet">
  <link href="<?= h(url_for('/assets/styles.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="bg-orb bg-orb-a" aria-hidden="true"></div>
<div class="bg-orb bg-orb-b" aria-hidden="true"></div>

<main class="container py-5" style="max-width: 520px;">
  <div class="card shadow-sm border-0 admin-hero-card">
    <div class="card-body p-4 p-lg-5">
      <div class="admin-kicker"><i class="bi bi-shield-lock"></i> Admin Access</div>
      <h4 class="mb-1">Sign In</h4>
      <p class="text-muted mb-4">Enter your admin account credentials to access the timetable dashboard.</p>

      <?php if ($notice !== ''): ?>
        <div class="alert alert-success"><?= h($notice) ?></div>
      <?php endif; ?>
      <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= h($error) ?></div>
      <?php endif; ?>

      <form method="post" novalidate>
        <input type="hidden" name="next" value="<?= h($next) ?>">
        <div class="mb-3">
          <label class="form-label" for="username">Username</label>
          <input class="form-control" id="username" name="username" maxlength="80" required autocomplete="username">
        </div>
        <div class="mb-3">
          <label class="form-label" for="password">Password</label>
          <input class="form-control" id="password" type="password" name="password" required autocomplete="current-password">
        </div>
        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-box-arrow-in-right"></i>Login</button>
      </form>

      <div class="small text-muted mt-3">
        First time setup? Run <a href="<?= h(url_for('/setup.php')) ?>">database setup</a> and use the default admin from
        <code>ADMIN_USER</code>/<code>ADMIN_PASS</code> env vars.
      </div>
    </div>
  </div>
</main>
</body>
</html>
