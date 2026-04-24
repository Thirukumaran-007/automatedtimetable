<?php
require_once __DIR__ . '/_helpers.php';

$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  try {
    if ($action === 'create') {
      $stmt = db()->prepare('INSERT INTO faculty(full_name,email,department,phone,max_weekly_hours) VALUES (?,?,?,?,?)');
      $stmt->execute([
        trim($_POST['full_name'] ?? ''),
        trim($_POST['email'] ?? ''),
        trim($_POST['department'] ?? ''),
        trim($_POST['phone'] ?? '') ?: null,
        (int)($_POST['max_weekly_hours'] ?? 16),
      ]);
      redirect(url_for('/faculty.php') . '?msg=' . urlencode('Faculty added.'));
    } elseif ($action === 'update') {
      $stmt = db()->prepare('UPDATE faculty SET full_name=?, email=?, department=?, phone=?, max_weekly_hours=? WHERE faculty_id=?');
      $stmt->execute([
        trim($_POST['full_name'] ?? ''),
        trim($_POST['email'] ?? ''),
        trim($_POST['department'] ?? ''),
        trim($_POST['phone'] ?? '') ?: null,
        (int)($_POST['max_weekly_hours'] ?? 16),
        (int)($_POST['faculty_id'] ?? 0),
      ]);
      redirect(url_for('/faculty.php') . '?msg=' . urlencode('Faculty updated.'));
    } elseif ($action === 'delete') {
      $facultyId = (int)($_POST['faculty_id'] ?? 0);
      $pdo = db();
      $pdo->beginTransaction();

      $hasTableStmt = $pdo->prepare(
        'SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
      );

      foreach (['timetable_slots', 'faculty_assignments', 'cohort_faculty'] as $tbl) {
        $hasTableStmt->execute([$tbl]);
        $exists = (int)($hasTableStmt->fetch()['c'] ?? 0) > 0;
        if ($exists) {
          $stmt = $pdo->prepare('DELETE FROM ' . $tbl . ' WHERE faculty_id=?');
          $stmt->execute([$facultyId]);
        }
      }

      $stmt = $pdo->prepare('DELETE FROM faculty WHERE faculty_id=?');
      $stmt->execute([$facultyId]);
      $pdo->commit();
      redirect(url_for('/faculty.php') . '?msg=' . urlencode('Faculty deleted with related data.'));
    }
  } catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
      $pdo->rollBack();
    }
    $raw = $e->getMessage();
    if (
      $action === 'delete' &&
      (stripos($raw, 'foreign key constraint') !== false || stripos($raw, 'integrity constraint violation') !== false)
    ) {
      $flash = 'Error: Cannot delete this faculty member because they are assigned to class groups or timetable slots. Remove those assignments first, then try again.';
    } else {
      $flash = 'Error: ' . $raw;
    }
  }
}

$flash = $flash ?: (string)($_GET['msg'] ?? '');

$editId = (int)($_GET['edit'] ?? 0);
$editRow = null;
if ($editId) {
  $stmt = db()->prepare('SELECT * FROM faculty WHERE faculty_id=?');
  $stmt->execute([$editId]);
  $editRow = $stmt->fetch() ?: null;
}

$rows = db()->query('SELECT * FROM faculty ORDER BY faculty_id DESC')->fetchAll();

ob_start();
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0">Faculty</h4>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form method="post" class="row g-2">
      <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'create' ?>">
      <?php if ($editRow): ?>
        <input type="hidden" name="faculty_id" value="<?= h((string)$editRow['faculty_id']) ?>">
      <?php endif; ?>
      <div class="col-md-3"><input required class="form-control" name="full_name" placeholder="Full name" value="<?= h((string)($editRow['full_name'] ?? '')) ?>"></div>
      <div class="col-md-3"><input required type="email" class="form-control" name="email" placeholder="Email" value="<?= h((string)($editRow['email'] ?? '')) ?>"></div>
      <div class="col-md-2"><input required class="form-control" name="department" placeholder="Department" value="<?= h((string)($editRow['department'] ?? '')) ?>"></div>
      <div class="col-md-2"><input class="form-control" name="phone" placeholder="Phone" value="<?= h((string)($editRow['phone'] ?? '')) ?>"></div>
      <div class="col-md-1"><input type="number" min="1" max="40" class="form-control" name="max_weekly_hours" value="<?= h((string)($editRow['max_weekly_hours'] ?? 16)) ?>" title="Max weekly hours"></div>
      <div class="col-md-1 d-grid">
        <button class="btn btn-<?= $editRow ? 'success' : 'primary' ?>"><?= $editRow ? 'Update' : 'Add' ?></button>
      </div>
      <?php if ($editRow): ?>
        <div class="col-12">
          <a class="btn btn-sm btn-outline-secondary" href="<?= h(url_for('/faculty.php')) ?>">Cancel edit</a>
        </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-sm table-striped">
        <thead>
          <tr>
            <th>ID</th><th>Name</th><th>Email</th><th>Dept</th><th>Phone</th><th>Max Hours</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= h((string)$r['faculty_id']) ?></td>
              <td><?= h($r['full_name']) ?></td>
              <td><?= h($r['email']) ?></td>
              <td><?= h($r['department']) ?></td>
              <td><?= h((string)($r['phone'] ?? '')) ?></td>
              <td><?= h((string)$r['max_weekly_hours']) ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="<?= h(url_for('/faculty.php') . '?edit=' . (string)$r['faculty_id']) ?>">Edit</a>
                <form method="post" onsubmit="return confirm('Delete this faculty?')" style="display:inline">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="faculty_id" value="<?= h((string)$r['faculty_id']) ?>">
                  <button class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
$title = 'Faculty - ' . APP_NAME;
require __DIR__ . '/_layout.php';
