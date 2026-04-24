<?php
require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        // Provide a clearer next step for common setup issues.
        $msg = $e->getMessage();

        // Typical on Render when DB_* env vars are missing and defaults point to localhost.
        $isConnRefused = str_contains($msg, 'Connection refused') || (string)$e->getCode() === '2002';
        if ($isConnRefused) {
			$hostForLog = DB_HOST;
			// Avoid leaking credentials if someone mistakenly sets DB_HOST to a URL like mysql://user:pass@host
			if (str_contains($hostForLog, '://') || str_contains($hostForLog, '@')) {
				$parsed = parse_url($hostForLog);
				if (is_array($parsed) && isset($parsed['host'])) {
					$hostForLog = (string)$parsed['host'];
				}
			}
            $hint = "Database connection refused. This usually means DB_HOST/DB_PORT point to the wrong place (often localhost). " .
				"Current values: DB_HOST=" . $hostForLog . ", DB_PORT=" . DB_PORT . ", DB_NAME=" . DB_NAME . ", DB_USER=" . DB_USER . ". " .
                "On Render, set env vars DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASS to your Railway public TCP proxy host+port, then redeploy.";
            throw new RuntimeException($hint, 0, $e);
        }

        if (str_contains($msg, "Unknown database '" . DB_NAME . "'")) {
            $setupUrl = url_for('/setup.php');
            throw new RuntimeException(
                "Database '" . DB_NAME . "' not found. Import sql/timetable.sql in phpMyAdmin, or run setup at: " . $setupUrl,
                0,
                $e
            );
        }
        throw $e;
    }
}

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
