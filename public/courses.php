<?php
require_once __DIR__ . '/_helpers.php';

$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

	$yearLevelRaw = trim((string)($_POST['year_level'] ?? ''));
	$semesterNoRaw = trim((string)($_POST['semester_no'] ?? ''));
	$yearLevel = $yearLevelRaw === '' ? null : (int)$yearLevelRaw;
	$semesterNo = $semesterNoRaw === '' ? null : (int)$semesterNoRaw;
	if ($yearLevel !== null && ($yearLevel < 1 || $yearLevel > 4)) {
		$yearLevel = null;
	}
	if ($semesterNo !== null && ($semesterNo < 1 || $semesterNo > 8)) {
		$semesterNo = null;
	}

  try {
    if ($action === 'create') {
      $stmt = db()->prepare('INSERT INTO courses(course_code,course_title,department,year_level,semester_no,weekly_hours) VALUES (?,?,?,?,?,?)');
      $stmt->execute([
        trim($_POST['course_code'] ?? ''),
        trim($_POST['course_title'] ?? ''),
        trim($_POST['department'] ?? ''),
			$yearLevel,
			$semesterNo,
        (int)($_POST['weekly_hours'] ?? 3),
      ]);
      redirect(url_for('/courses.php') . '?msg=' . urlencode('Course added.'));
    } elseif ($action === 'update') {
      $stmt = db()->prepare('UPDATE courses SET course_code=?, course_title=?, department=?, year_level=?, semester_no=?, weekly_hours=? WHERE course_id=?');
      $stmt->execute([
        trim($_POST['course_code'] ?? ''),
        trim($_POST['course_title'] ?? ''),
        trim($_POST['department'] ?? ''),
			$yearLevel,
			$semesterNo,
        (int)($_POST['weekly_hours'] ?? 3),
        (int)($_POST['course_id'] ?? 0),
      ]);
      redirect(url_for('/courses.php') . '?msg=' . urlencode('Course updated.'));
    } elseif ($action === 'delete') {
      $stmt = db()->prepare('DELETE FROM courses WHERE course_id=?');
      $stmt->execute([(int)($_POST['course_id'] ?? 0)]);
      redirect(url_for('/courses.php') . '?msg=' . urlencode('Course deleted.'));
    }
  } catch (Throwable $e) {
    $flash = 'Error: ' . $e->getMessage();
  }
}

$flash = $flash ?: (string)($_GET['msg'] ?? '');

$editId = (int)($_GET['edit'] ?? 0);
$editRow = null;
if ($editId) {
  $stmt = db()->prepare('SELECT * FROM courses WHERE course_id=?');
  $stmt->execute([$editId]);
  $editRow = $stmt->fetch() ?: null;
}

$rows = db()->query('SELECT * FROM courses ORDER BY course_id DESC')->fetchAll();

ob_start();
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0">Courses</h4>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form method="post" class="row g-2">
      <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'create' ?>">
      <?php if ($editRow): ?>
        <input type="hidden" name="course_id" value="<?= h((string)$editRow['course_id']) ?>">
      <?php endif; ?>
      <div class="col-md-2"><input required class="form-control" name="course_code" placeholder="Code (e.g., CS201)" value="<?= h((string)($editRow['course_code'] ?? '')) ?>"></div>
      <div class="col-md-4"><input required class="form-control" name="course_title" placeholder="Title" value="<?= h((string)($editRow['course_title'] ?? '')) ?>"></div>
      <div class="col-md-3"><input required class="form-control" name="department" placeholder="Department" value="<?= h((string)($editRow['department'] ?? '')) ?>"></div>
		<div class="col-md-1"><input type="number" min="1" max="4" class="form-control" name="year_level" placeholder="Year" value="<?= h((string)($editRow['year_level'] ?? '')) ?>" title="Year level (optional)"></div>
		<div class="col-md-1"><input type="number" min="1" max="8" class="form-control" name="semester_no" placeholder="Sem" value="<?= h((string)($editRow['semester_no'] ?? '')) ?>" title="Semester no (optional)"></div>
      <div class="col-md-2"><input type="number" min="1" max="12" class="form-control" name="weekly_hours" value="<?= h((string)($editRow['weekly_hours'] ?? 3)) ?>" title="Weekly hours"></div>
      <div class="col-md-1 d-grid"><button class="btn btn-<?= $editRow ? 'success' : 'primary' ?>"><?= $editRow ? 'Update' : 'Add' ?></button></div>
      <?php if ($editRow): ?>
        <div class="col-12"><a class="btn btn-sm btn-outline-secondary" href="<?= h(url_for('/courses.php')) ?>">Cancel edit</a></div>
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
			<th>ID</th><th>Code</th><th>Title</th><th>Dept</th><th>Year</th><th>Sem</th><th>Weekly Hours</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= h((string)$r['course_id']) ?></td>
              <td><?= h($r['course_code']) ?></td>
              <td><?= h($r['course_title']) ?></td>
              <td><?= h($r['department']) ?></td>
				<td><?= h((string)($r['year_level'] ?? '')) ?></td>
				<td><?= h((string)($r['semester_no'] ?? '')) ?></td>
              <td><?= h((string)$r['weekly_hours']) ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="<?= h(url_for('/courses.php') . '?edit=' . (string)$r['course_id']) ?>">Edit</a>
                <form method="post" onsubmit="return confirm('Delete this course?')" style="display:inline">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="course_id" value="<?= h((string)$r['course_id']) ?>">
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
$title = 'Courses - ' . APP_NAME;
require __DIR__ . '/_layout.php';
