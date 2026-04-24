<?php
require_once __DIR__ . '/_helpers.php';

// If migration not applied yet, show friendly instructions.
try {
    db()->query('SELECT 1 FROM cohorts LIMIT 1');
} catch (Throwable $e) {
    $msg = "Class-group tables are missing. Import the migration SQL in phpMyAdmin: sql/migrations/2026_03_26_year_semester_cohorts.sql";
    ob_start();
    ?>
    <div class="alert alert-warning">
      <?= h($msg) ?><br>
      Quick link: <a href="<?= h(url_for('/setup.php')) ?>">Setup</a>
    </div>
    <?php
    $content = ob_get_clean();
    $title = 'Class Groups - ' . APP_NAME;
    require __DIR__ . '/_layout.php';
    exit;
}

$flash = (string)($_GET['msg'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $stmt = db()->prepare(
                'INSERT INTO cohorts(department,year_level,semester_no,term,section,batch_year,expected_strength) VALUES (?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                trim($_POST['department'] ?? ''),
                (int)($_POST['year_level'] ?? 1),
                (int)($_POST['semester_no'] ?? 1),
                trim($_POST['term'] ?? ''),
                trim($_POST['section'] ?? ''),
                (int)($_POST['batch_year'] ?? (int)date('Y')),
                (int)($_POST['expected_strength'] ?? 40),
            ]);
            redirect(url_for('/cohorts.php') . '?msg=' . urlencode('Class group created.'));
        } elseif ($action === 'delete') {
            $stmt = db()->prepare('DELETE FROM cohorts WHERE cohort_id=?');
            $stmt->execute([(int)($_POST['cohort_id'] ?? 0)]);
            redirect(url_for('/cohorts.php') . '?msg=' . urlencode('Class group deleted.'));
        }
    } catch (Throwable $e) {
        $flash = 'Error: ' . $e->getMessage();
    }
}

$departments = ['IT','CSE','ECE','EEE','MECH','CIVIL','MBA','MCA','CSBS','CSY'];

$rows = db()->query('SELECT * FROM cohorts ORDER BY cohort_id DESC')->fetchAll();

ob_start();
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0">Class Groups</h4>
  <a class="btn btn-outline-primary" href="<?= h(url_for('/cohort_subjects.php')) ?>">Select Subjects</a>
</div>

<?php if ($flash): ?>
  <div class="alert alert-info"><?= h($flash) ?></div>
<?php endif; ?>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <h6 class="mb-2">Create Class Group (Department + Year/Sem + Term + Section)</h6>
    <form method="post" class="row g-2">
      <input type="hidden" name="action" value="create">
      <div class="col-md-2">
        <select class="form-select" name="department" required>
          <option value="">Dept</option>
          <?php foreach ($departments as $d): ?>
            <option><?= h($d) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-1"><input class="form-control" type="number" min="1" max="4" name="year_level" value="2" required title="Year"></div>
      <div class="col-md-1"><input class="form-control" type="number" min="1" max="8" name="semester_no" value="4" required title="Semester"></div>
      <div class="col-md-3"><input class="form-control" name="term" value="2025-26 Even" required placeholder="Term"></div>
      <div class="col-md-1"><input class="form-control" name="section" value="A" required placeholder="Sec"></div>
      <div class="col-md-2"><input class="form-control" type="number" min="2000" max="2100" name="batch_year" value="<?= h((string)date('Y')) ?>" required placeholder="Batch"></div>
      <div class="col-md-1"><input class="form-control" type="number" min="1" max="1000" name="expected_strength" value="60" required placeholder="Strength"></div>
      <div class="col-md-1 d-grid"><button class="btn btn-primary">Create</button></div>
    </form>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <h6 class="mb-2">Existing Class Groups</h6>
    <div class="table-responsive">
      <table class="table table-sm table-striped">
        <thead>
          <tr>
            <th>ID</th><th>Dept</th><th>Year</th><th>Sem</th><th>Term</th><th>Sec</th><th>Batch</th><th>Strength</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= h((string)$r['cohort_id']) ?></td>
              <td><?= h($r['department']) ?></td>
              <td><?= h((string)$r['year_level']) ?></td>
              <td><?= h((string)$r['semester_no']) ?></td>
              <td><?= h($r['term']) ?></td>
              <td><?= h($r['section']) ?></td>
              <td><?= h((string)$r['batch_year']) ?></td>
              <td><?= h((string)$r['expected_strength']) ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="<?= h(url_for('/cohort_subjects.php') . '?cohort_id=' . (string)$r['cohort_id']) ?>">Manage</a>
                <form method="post" style="display:inline" onsubmit="return confirm('Delete this class group?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="cohort_id" value="<?= h((string)$r['cohort_id']) ?>">
                  <button class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
            <tr><td colspan="9" class="text-muted">No class groups yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
$title = 'Class Groups - ' . APP_NAME;
require __DIR__ . '/_layout.php';
