<?php
require_once __DIR__ . '/_helpers.php';

// Deprecated: This project now treats a "Class" as a Cohort (student group).
// Use the Cohorts page for creating classes, selecting subjects, and assigning faculty.
redirect(url_for('/cohorts.php'));

$flash = '';

function fetchOptions(string $sql): array {
    return db()->query($sql)->fetchAll();
}

$courses = fetchOptions('SELECT course_id, course_code, course_title FROM courses ORDER BY course_code');
$faculty = fetchOptions('SELECT faculty_id, full_name, department FROM faculty ORDER BY full_name');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create_class') {
            $stmt = db()->prepare('INSERT INTO class_offerings(course_id,section,term,batch_year,expected_strength) VALUES (?,?,?,?,?)');
            $stmt->execute([
                (int)($_POST['course_id'] ?? 0),
                trim($_POST['section'] ?? ''),
                trim($_POST['term'] ?? ''),
                (int)($_POST['batch_year'] ?? (int)date('Y')),
                (int)($_POST['expected_strength'] ?? 40),
            ]);
            $flash = 'Class offering created.';
        } elseif ($action === 'delete_class') {
            $stmt = db()->prepare('DELETE FROM class_offerings WHERE class_id=?');
            $stmt->execute([(int)($_POST['class_id'] ?? 0)]);
            $flash = 'Class offering deleted.';
        } elseif ($action === 'assign_faculty') {
            $stmt = db()->prepare('INSERT INTO faculty_assignments(class_id,faculty_id,role) VALUES (?,?,?)');
            $stmt->execute([
                (int)($_POST['class_id'] ?? 0),
                (int)($_POST['faculty_id'] ?? 0),
                $_POST['role'] ?? 'Primary',
            ]);
            $flash = 'Faculty assigned to class.';
        } elseif ($action === 'remove_faculty_assignment') {
            $stmt = db()->prepare('DELETE FROM faculty_assignments WHERE assignment_id=?');
            $stmt->execute([(int)($_POST['assignment_id'] ?? 0)]);
            $flash = 'Faculty assignment removed.';
        }
    } catch (Throwable $e) {
        $flash = 'Error: ' . $e->getMessage();
    }
}

$classes = db()->query(
    "SELECT co.class_id, c.course_code, c.course_title, co.section, co.term, co.batch_year, co.expected_strength
     FROM class_offerings co
     JOIN courses c ON c.course_id = co.course_id
     ORDER BY co.class_id DESC"
)->fetchAll();

$selectedClassId = (int)($_GET['class_id'] ?? ($classes[0]['class_id'] ?? 0));

$assignments = [];
if ($selectedClassId) {
    $stmt = db()->prepare(
        "SELECT fa.assignment_id, f.full_name, f.department, fa.role
         FROM faculty_assignments fa
         JOIN faculty f ON f.faculty_id=fa.faculty_id
         WHERE fa.class_id=?
         ORDER BY fa.role, f.full_name"
    );
    $stmt->execute([$selectedClassId]);
    $assignments = $stmt->fetchAll();

}

ob_start();
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0">Classes</h4>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <h6 class="mb-2">Create Class Offering</h6>
    <form method="post" class="row g-2">
      <input type="hidden" name="action" value="create_class">
      <div class="col-md-4">
        <select class="form-select" name="course_id" required>
          <option value="">Select course</option>
          <?php foreach ($courses as $c): ?>
            <option value="<?= h((string)$c['course_id']) ?>"><?= h($c['course_code'] . ' - ' . $c['course_title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2"><input required class="form-control" name="section" placeholder="Section (A/B)"></div>
      <div class="col-md-3"><input required class="form-control" name="term" placeholder="Term (e.g., 2025-26 Even)"></div>
      <div class="col-md-1"><input required type="number" min="2000" max="2100" class="form-control" name="batch_year" value="<?= h((string)date('Y')) ?>" title="Batch year"></div>
      <div class="col-md-1"><input required type="number" min="1" max="1000" class="form-control" name="expected_strength" value="40" title="Expected strength"></div>
      <div class="col-md-1 d-grid"><button class="btn btn-primary">Create</button></div>
    </form>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="mb-2">All Class Offerings</h6>
        <div class="table-responsive">
          <table class="table table-sm table-striped">
            <thead>
              <tr>
                <th>ID</th><th>Course</th><th>Section</th><th>Term</th><th>Batch</th><th>Strength</th><th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($classes as $cl): ?>
                <tr <?= $selectedClassId === (int)$cl['class_id'] ? 'class="table-primary"' : '' ?>>
                  <td><?= h((string)$cl['class_id']) ?></td>
                  <td>
                    <a href="?class_id=<?= h((string)$cl['class_id']) ?>" class="text-decoration-none">
                      <?= h($cl['course_code']) ?>
                    </a>
                    <div class="text-muted small"><?= h($cl['course_title']) ?></div>
                  </td>
                  <td><?= h($cl['section']) ?></td>
                  <td><?= h($cl['term']) ?></td>
                  <td><?= h((string)$cl['batch_year']) ?></td>
                  <td><?= h((string)$cl['expected_strength']) ?></td>
                  <td class="text-end">
                    <form method="post" onsubmit="return confirm('Delete this class offering?')" style="display:inline">
                      <input type="hidden" name="action" value="delete_class">
                      <input type="hidden" name="class_id" value="<?= h((string)$cl['class_id']) ?>">
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
  </div>

  <div class="col-lg-5">
    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <h6 class="mb-2">Assign Faculty (Selected Class #<?= h((string)$selectedClassId) ?>)</h6>
        <form method="post" class="row g-2">
          <input type="hidden" name="action" value="assign_faculty">
          <input type="hidden" name="class_id" value="<?= h((string)$selectedClassId) ?>">
          <div class="col-7">
            <select class="form-select" name="faculty_id" required <?= $selectedClassId ? '' : 'disabled' ?>>
              <option value="">Select faculty</option>
              <?php foreach ($faculty as $f): ?>
                <option value="<?= h((string)$f['faculty_id']) ?>"><?= h($f['full_name'] . ' (' . $f['department'] . ')') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-3">
            <select class="form-select" name="role" <?= $selectedClassId ? '' : 'disabled' ?>>
              <option>Primary</option>
              <option>Co-Teacher</option>
              <option>Guest</option>
            </select>
          </div>
          <div class="col-2 d-grid">
            <button class="btn btn-primary" <?= $selectedClassId ? '' : 'disabled' ?>>Add</button>
          </div>
        </form>

        <div class="table-responsive mt-3">
          <table class="table table-sm">
            <thead><tr><th>Faculty</th><th>Role</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($assignments as $a): ?>
                <tr>
                  <td><?= h($a['full_name']) ?><div class="text-muted small"><?= h($a['department']) ?></div></td>
                  <td><?= h($a['role']) ?></td>
                  <td class="text-end">
                    <form method="post" style="display:inline">
                      <input type="hidden" name="action" value="remove_faculty_assignment">
                      <input type="hidden" name="assignment_id" value="<?= h((string)$a['assignment_id']) ?>">
                      <button class="btn btn-sm btn-outline-danger">Remove</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$assignments): ?>
                <tr><td colspan="3" class="text-muted">No faculty assigned.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</div>
<?php
$content = ob_get_clean();
$title = 'Classes - ' . APP_NAME;
require __DIR__ . '/_layout.php';
