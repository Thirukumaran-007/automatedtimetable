<?php
require_once __DIR__ . '/../app/config.php';

// Minimal helper to create the database and import schema.
// NOTE: This requires that the MySQL user has permissions to CREATE DATABASE.

function output(string $html): void {
    echo $html;
}

function h(string $value): string {
	return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$rootDsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4';

$error = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = new PDO($rootDsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

		if (!preg_match('/^[A-Za-z0-9_]+$/', DB_NAME)) {
			throw new RuntimeException('Invalid DB_NAME. Use only letters, numbers, underscore.');
		}

        // Choose which SQL to run:
    // - If DB exists and core tables exist, apply migration.
    // - Otherwise, import the full base schema.
        $migrationPath = realpath(__DIR__ . '/../sql/migrations/2026_03_26_year_semester_cohorts.sql');
    $schemaPath = realpath(__DIR__ . '/../sql/timetable_schema.sql');
    $basePath = $schemaPath ?: realpath(__DIR__ . '/../sql/timetable.sql');

        $dbExistsStmt = $pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME=?');
        $dbExistsStmt->execute([DB_NAME]);
        $dbExists = (bool)$dbExistsStmt->fetch();

		$hasCoreTables = false;
		if ($dbExists) {
			$coreStmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=? AND table_name=? LIMIT 1');
			$coreStmt->execute([DB_NAME, 'faculty']);
			$hasCoreTables = (bool)$coreStmt->fetch();
		}

		// Ensure a database is selected for scripts that use DATABASE() checks.
		$pdo->exec('USE `' . DB_NAME . '`');

		if ($dbExists && $hasCoreTables) {
          if (!$migrationPath || !is_file($migrationPath)) {
            throw new RuntimeException('Migration file not found: sql/migrations/2026_03_26_year_semester_cohorts.sql');
          }
          $sql = file_get_contents($migrationPath);
          if ($sql === false) {
            throw new RuntimeException('Failed to read migration file.');
          }
        } else {
          if (!$basePath || !is_file($basePath)) {
				throw new RuntimeException('SQL file not found: sql/timetable_schema.sql');
          }
          $sql = file_get_contents($basePath);
          if ($sql === false) {
            throw new RuntimeException('Failed to read SQL file.');
          }
        }
        if ($sql === false) {
            throw new RuntimeException('Failed to read SQL file.');
        }

		// Hosted DBs (Railway) usually provide a fixed DB (often named "railway").
		// Strip any hardcoded "USE ctms;" statements to avoid switching DBs.
		$sql = preg_replace('/^\s*USE\s+ctms\s*;\s*$/mi', '', $sql);

        // Reliable import for scripts that include DELIMITER $$ ... $$ blocks (triggers).
        // Strategy:
        // 1) Execute all SQL before first DELIMITER $$
        // 2) Extract and execute each $$-terminated block as-is (after stripping the wrapper)
        // 3) Execute remaining SQL after DELIMITER ;

        $sql = str_replace("\r\n", "\n", $sql);

        $parts = preg_split('/^\s*DELIMITER\s+\$\$\s*$/mi', $sql);
        $before = $parts[0] ?? '';
        $afterDelimiter = $parts[1] ?? '';

        $execSemiSeparated = function (string $chunk) use ($pdo): void {
          $chunk = preg_replace('/^\s*--.*$/m', '', $chunk);
          $chunk = trim($chunk);
          if ($chunk === '') {
            return;
          }
          $stmts = array_filter(array_map('trim', preg_split('/;\s*\n/', $chunk)));
          foreach ($stmts as $s) {
            if ($s === '') continue;
            $pdo->exec($s);
          }
        };

        // 1) pre-trigger statements
        $execSemiSeparated($before);

        // 2) trigger blocks + remainder
        if ($afterDelimiter !== '') {
          // Split into trigger blocks by $$ terminator
          $chunks = preg_split('/\$\$\s*\n/', $afterDelimiter);
          foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') continue;

            // Stop at the DELIMITER ; marker; anything after should be semicolon separated.
            if (preg_match('/^\s*DELIMITER\s+;\s*$/mi', $chunk)) {
              continue;
            }

            // If chunk contains DELIMITER ;, split there.
            if (preg_match('/^\s*DELIMITER\s+;\s*$/mi', $chunk)) {
              $execSemiSeparated($chunk);
              continue;
            }

            // Remove any DELIMITER lines inside the chunk
            $chunk = preg_replace('/^\s*DELIMITER\s+;\s*$/mi', '', $chunk);
            $chunk = preg_replace('/^\s*DELIMITER\s+\$\$\s*$/mi', '', $chunk);

            // If this looks like a CREATE TRIGGER block, execute it as a whole.
            if (stripos($chunk, 'CREATE TRIGGER') !== false) {
              $pdo->exec($chunk);
            } else {
              // Otherwise treat it as normal semicolon-separated SQL.
              $execSemiSeparated($chunk);
            }
          }
        }

        $ok = $dbExists
          ? "Migration applied. Open: <a href=\"" . h(url_for('/cohorts.php')) . "\">Class Groups</a>"
          : "Setup complete. Open the app: <a href=\"" . h(url_for('/index.php')) . "\">Dashboard</a>";
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CTMS Setup</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width: 820px;">
  <div class="card shadow-sm">
    <div class="card-body">
      <h4 class="mb-2">CTMS Database Setup</h4>
      <p class="text-muted">Imports the base CTMS schema into <b><?= h(DB_NAME) ?></b> (recommended for Railway). If core tables already exist, applies the class-group migration.</p>

      <?php if ($error): ?>
        <div class="alert alert-danger">Error: <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($ok): ?>
        <div class="alert alert-success"><?= $ok ?></div>
      <?php else: ?>
        <form method="post">
          <button class="btn btn-primary" onclick="return confirm('Run database setup now?')">Run Setup</button>
        </form>
      <?php endif; ?>

      <hr>
      <div class="small text-muted">
		If this fails due to permissions, import manually using your MySQL client (HeidiSQL / mysql): run <code>sql/timetable_schema.sql</code> in the <b><?= h(DB_NAME) ?></b> database.
      </div>
    </div>
  </div>
</div>
</body>
</html>
