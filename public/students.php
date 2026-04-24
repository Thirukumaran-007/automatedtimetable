<?php
require_once __DIR__ . '/_helpers.php';

$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  try {
    if ($action === 'create') {
      $stmt = db()->prepare('INSERT INTO students(roll_no,full_name,email,program,year_level) VALUES (?,?,?,?,?)');
      $stmt->execute([
        trim($_POST['roll_no'] ?? ''),
        trim($_POST['full_name'] ?? ''),
        trim($_POST['email'] ?? ''),
        trim($_POST['program'] ?? ''),
        (int)($_POST['year_level'] ?? 1),
      ]);
      redirect(url_for('/students.php') . '?msg=' . urlencode('Student added.'));
    } elseif ($action === 'update') {
      $stmt = db()->prepare('UPDATE students SET roll_no=?, full_name=?, email=?, program=?, year_level=? WHERE student_id=?');
      $stmt->execute([
        trim($_POST['roll_no'] ?? ''),
        trim($_POST['full_name'] ?? ''),
        trim($_POST['email'] ?? ''),
        trim($_POST['program'] ?? ''),
        (int)($_POST['year_level'] ?? 1),
        (int)($_POST['student_id'] ?? 0),
      ]);
      redirect(url_for('/students.php') . '?msg=' . urlencode('Student updated.'));
    } elseif ($action === 'delete') {
      $stmt = db()->prepare('DELETE FROM students WHERE student_id=?');
      $stmt->execute([(int)($_POST['student_id'] ?? 0)]);
      redirect(url_for('/students.php') . '?msg=' . urlencode('Student deleted.'));
    }
  } catch (Throwable $e) {
    $flash = 'Error: ' . $e->getMessage();
  }
}

$flash = $flash ?: (string)($_GET['msg'] ?? '');

$editId = (int)($_GET['edit'] ?? 0);
$editRow = null;
if ($editId) {
  $stmt = db()->prepare('SELECT * FROM students WHERE student_id=?');
  $stmt->execute([$editId]);
  $editRow = $stmt->fetch() ?: null;
}

$rows = db()->query('SELECT * FROM students ORDER BY student_id DESC')->fetchAll();

ob_start();
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0">Students</h4>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form method="post" class="row g-2">
      <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'create' ?>">
      <?php if ($editRow): ?>
        <input type="hidden" name="student_id" value="<?= h((string)$editRow['student_id']) ?>">
      <?php endif; ?>
      <div class="col-md-2"><input required class="form-control" name="roll_no" placeholder="Roll no" value="<?= h((string)($editRow['roll_no'] ?? '')) ?>"></div>
      <div class="col-md-3"><input required class="form-control" name="full_name" placeholder="Full name" value="<?= h((string)($editRow['full_name'] ?? '')) ?>"></div>
      <div class="col-md-3"><input required type="email" class="form-control" name="email" placeholder="Email" value="<?= h((string)($editRow['email'] ?? '')) ?>"></div>
      <div class="col-md-3"><input required class="form-control" name="program" placeholder="Program" value="<?= h((string)($editRow['program'] ?? '')) ?>"></div>
      <div class="col-md-1"><input required type="number" min="1" max="8" class="form-control" name="year_level" value="<?= h((string)($editRow['year_level'] ?? 1)) ?>" title="Year"></div>
      <div class="col-md-12 d-grid d-md-flex gap-2">
        <button class="btn btn-<?= $editRow ? 'success' : 'primary' ?>"><?= $editRow ? 'Update' : 'Add' ?></button>
        <?php if ($editRow): ?>
          <a class="btn btn-outline-secondary" href="<?= h(url_for('/students.php')) ?>">Cancel edit</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-sm table-striped">
        <thead>
          <tr>
            <th>ID</th><th>Roll</th><th>Name</th><th>Email</th><th>Program</th><th>Year</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= h((string)$r['student_id']) ?></td>
              <td><?= h($r['roll_no']) ?></td>
              <td><?= h($r['full_name']) ?></td>
              <td><?= h($r['email']) ?></td>
              <td><?= h($r['program']) ?></td>
              <td><?= h((string)$r['year_level']) ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="<?= h(url_for('/students.php') . '?edit=' . (string)$r['student_id']) ?>">Edit</a>
                <form method="post" onsubmit="return confirm('Delete this student?')" style="display:inline">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="student_id" value="<?= h((string)$r['student_id']) ?>">
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
$title = 'Students - ' . APP_NAME;
require __DIR__ . '/_layout.php';
