<?php
require_once __DIR__ . '/_helpers.php';

// Guard: migration not applied
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
    $title = 'Class Group Subjects - ' . APP_NAME;
    require __DIR__ . '/_layout.php';
    exit;
}

$flash = (string)($_GET['msg'] ?? '');

$cohorts = db()->query('SELECT * FROM cohorts ORDER BY cohort_id DESC')->fetchAll();
$cohortId = (int)($_GET['cohort_id'] ?? ($cohorts[0]['cohort_id'] ?? 0));

$cohort = null;
if ($cohortId) {
    $stmt = db()->prepare('SELECT * FROM cohorts WHERE cohort_id=?');
    $stmt->execute([$cohortId]);
    $cohort = $stmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $cohortId = (int)($_POST['cohort_id'] ?? $cohortId);

    try {
        if ($action === 'save_subjects') {
            $selected = $_POST['course_ids'] ?? [];
            if (!is_array($selected)) $selected = [];

            $pdo = db();
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('DELETE FROM cohort_courses WHERE cohort_id=?');
            $stmt->execute([$cohortId]);

            $stmt = $pdo->prepare('INSERT INTO cohort_courses(cohort_id,course_id) VALUES (?,?)');
            foreach ($selected as $cid) {
                $stmt->execute([$cohortId, (int)$cid]);
            }
            $pdo->commit();

            redirect(url_for('/cohort_subjects.php') . '?cohort_id=' . $cohortId . '&msg=' . urlencode('Subjects saved.'));
        } elseif ($action === 'assign_faculty') {
            $stmt = db()->prepare(
                'INSERT INTO cohort_faculty(cohort_id,course_id,faculty_id) VALUES (?,?,?) '
                . 'ON DUPLICATE KEY UPDATE faculty_id=VALUES(faculty_id)'
            );
            $stmt->execute([
                $cohortId,
                (int)($_POST['course_id'] ?? 0),
                (int)($_POST['faculty_id'] ?? 0),
            ]);
            redirect(url_for('/cohort_subjects.php') . '?cohort_id=' . $cohortId . '&msg=' . urlencode('Faculty assigned.'));
        } elseif ($action === 'update_hours') {
          $courseId = (int)($_POST['course_id'] ?? 0);
          $weeklyHours = (int)($_POST['weekly_hours'] ?? 0);

          if ($courseId <= 0) {
            throw new RuntimeException('Invalid course.');
          }
          if ($weeklyHours < 1 || $weeklyHours > 12) {
            throw new RuntimeException('Weekly hours must be between 1 and 12.');
          }

          $stmt = db()->prepare('UPDATE courses SET weekly_hours=? WHERE course_id=?');
          $stmt->execute([$weeklyHours, $courseId]);

            redirect(url_for('/cohort_subjects.php') . '?cohort_id=' . $cohortId . '&msg=' . urlencode('Weekly hours updated.'));
    } elseif ($action === 'create_subject') {
      if (!$cohortId) {
        throw new RuntimeException('Select a class group first.');
      }
      $stmt = db()->prepare('SELECT * FROM cohorts WHERE cohort_id=?');
      $stmt->execute([$cohortId]);
      $c = $stmt->fetch();
      if (!$c) {
        throw new RuntimeException('Invalid class group.');
      }

      $code = trim((string)($_POST['course_code'] ?? ''));
      $title = trim((string)($_POST['course_title'] ?? ''));
      $dept = trim((string)($_POST['department'] ?? $c['department'] ?? ''));
      $weeklyHours = (int)($_POST['weekly_hours'] ?? 3);

      if ($code === '' || $title === '' || $dept === '') {
        throw new RuntimeException('Course code, title, and department are required.');
      }
      if ($weeklyHours < 1 || $weeklyHours > 12) {
        throw new RuntimeException('Weekly hours must be between 1 and 12.');
      }

      $ins = db()->prepare('INSERT INTO courses(course_code,course_title,department,year_level,semester_no,weekly_hours) VALUES (?,?,?,?,?,?)');
      $ins->execute([
        $code,
        $title,
        $dept,
        (int)$c['year_level'],
        (int)$c['semester_no'],
        $weeklyHours,
      ]);

      db()->prepare('INSERT IGNORE INTO cohort_courses(cohort_id,course_id) VALUES (?, LAST_INSERT_ID())')->execute([$cohortId]);
      redirect(url_for('/cohort_subjects.php') . '?cohort_id=' . $cohortId . '&msg=' . urlencode('Subject added.'));
        }
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        $flash = 'Error: ' . $e->getMessage();
    }
}

$flash = $flash ?: '';

$courses = [];
$selectedCourseIds = [];
$faculty = db()->query('SELECT faculty_id, full_name, department FROM faculty ORDER BY full_name')->fetchAll();
$facultyByDept = [];
foreach ($faculty as $f) {
    $facultyByDept[$f['department']][] = $f;
}

$assigned = [];

if ($cohort) {
    // Show all subjects for this year/sem (college-wide), then filter by dept in UI by default
    $stmt = db()->prepare('SELECT * FROM courses WHERE year_level=? AND semester_no=? ORDER BY department, course_code');
    $stmt->execute([(int)$cohort['year_level'], (int)$cohort['semester_no']]);
    $courses = $stmt->fetchAll();

    $stmt = db()->prepare('SELECT course_id FROM cohort_courses WHERE cohort_id=?');
    $stmt->execute([$cohortId]);
    $selectedCourseIds = array_map(fn($r) => (int)$r['course_id'], $stmt->fetchAll());

    $stmt = db()->prepare('SELECT course_id, faculty_id FROM cohort_faculty WHERE cohort_id=?');
    $stmt->execute([$cohortId]);
    foreach ($stmt->fetchAll() as $r) {
        $assigned[(int)$r['course_id']] = (int)$r['faculty_id'];
    }
}

ob_start();
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0">Class Group Subjects & Faculty</h4>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= h(url_for('/cohorts.php')) ?>">Back</a>
    <a class="btn btn-outline-primary" href="<?= h(url_for('/cohort_timetable.php') . '?cohort_id=' . (string)$cohortId) ?>">Generate Timetable</a>
  </div>
</div>

<?php if ($flash): ?>
  <div class="alert alert-info"><?= h($flash) ?></div>
<?php endif; ?>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-6">
        <label class="form-label">Select Class Group</label>
        <select class="form-select" name="cohort_id" onchange="this.form.submit()">
          <?php foreach ($cohorts as $c): ?>
            <option value="<?= h((string)$c['cohort_id']) ?>" <?= (int)$c['cohort_id'] === $cohortId ? 'selected' : '' ?>>
              <?= h($c['department'] . ' | Y' . $c['year_level'] . ' S' . $c['semester_no'] . ' | ' . $c['term'] . ' | Sec ' . $c['section']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <?php if ($cohort): ?>
          <div class="text-muted small">Pick subjects (Year <?= h((string)$cohort['year_level']) ?> / Sem <?= h((string)$cohort['semester_no']) ?>) then assign faculty.</div>
        <?php else: ?>
          <div class="text-muted small">Create a class group first.</div>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<?php if (!$cohort): ?>
  <div class="alert alert-warning">No class group selected.</div>
<?php else: ?>
  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-body">
          <h6 class="mb-2">Select Subjects (All Departments, Year/Sem)</h6>
      <div class="mb-3">
        <div class="text-muted small mb-2">No subjects? Add a new subject tagged for this class group's Year/Sem.</div>
        <form method="post" class="row g-2">
          <input type="hidden" name="action" value="create_subject">
          <input type="hidden" name="cohort_id" value="<?= h((string)$cohortId) ?>">
          <div class="col-4"><input required class="form-control form-control-sm" name="course_code" placeholder="Code" /></div>
          <div class="col-8"><input required class="form-control form-control-sm" name="course_title" placeholder="Title" /></div>
          <div class="col-6"><input required class="form-control form-control-sm" name="department" value="<?= h((string)$cohort['department']) ?>" /></div>
          <div class="col-3"><input type="number" min="1" max="12" class="form-control form-control-sm" name="weekly_hours" value="3" /></div>
          <div class="col-3 d-grid"><button class="btn btn-sm btn-outline-primary">Add</button></div>
          <div class="col-12 small text-muted">Or manage all subjects in <a href="<?= h(url_for('/courses.php')) ?>">Courses</a> (set Year/Sem).</div>
        </form>
      </div>
          <form method="post">
            <input type="hidden" name="action" value="save_subjects">
            <input type="hidden" name="cohort_id" value="<?= h((string)$cohortId) ?>">
            <div class="table-responsive" style="max-height: 520px; overflow:auto;">
              <table class="table table-sm table-striped">
                <thead><tr><th></th><th>Code</th><th>Title</th><th>Dept</th><th>Hrs</th></tr></thead>
                <tbody>
                  <?php foreach ($courses as $s):
                    $checked = in_array((int)$s['course_id'], $selectedCourseIds, true);
                    $hint = $s['department'] === $cohort['department'] ? ' (Dept)' : '';
                  ?>
                    <tr>
                      <td><input class="form-check-input" type="checkbox" name="course_ids[]" value="<?= h((string)$s['course_id']) ?>" <?= $checked ? 'checked' : '' ?>></td>
                      <td><?= h($s['course_code']) ?></td>
                      <td><?= h($s['course_title']) ?></td>
                      <td><?= h($s['department'] . $hint) ?></td>
                      <td><?= h((string)$s['weekly_hours']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (!$courses): ?>
					<tr><td colspan="5" class="text-muted">No subjects tagged for this Year/Sem. Add a subject above, or update courses.year_level & courses.semester_no in Courses.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <button class="btn btn-primary">Save Subjects</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-body">
          <h6 class="mb-2">Assign Faculty (for selected subjects)</h6>
          <div class="text-muted small mb-2">Recommended: choose faculty from the same department. If you generated a timetable with auto-assign enabled, refresh this page to see auto-filled faculty.</div>

          <div class="table-responsive" style="max-height: 520px; overflow:auto;">
            <table class="table table-sm table-striped">
              <thead><tr><th>Subject</th><th>Dept</th><th>Hrs/wk</th><th>Faculty</th><th></th></tr></thead>
              <tbody>
                <?php
                  $selectedSubjects = array_filter($courses, fn($s) => in_array((int)$s['course_id'], $selectedCourseIds, true));
                ?>
                <?php foreach ($selectedSubjects as $s):
                  $cid = (int)$s['course_id'];
                  $dept = (string)$s['department'];
                  $opts = $facultyByDept[$dept] ?? $faculty;
                  $current = $assigned[$cid] ?? 0;
                ?>
                  <tr <?= $current ? '' : 'class="table-warning"' ?>>
                    <td><?= h($s['course_code']) ?><div class="text-muted small"><?= h($s['course_title']) ?></div></td>
                    <td><?= h($dept) ?></td>
                    <td style="min-width: 140px;">
                      <form method="post" class="d-flex gap-2 align-items-center">
                        <input type="hidden" name="action" value="update_hours">
                        <input type="hidden" name="cohort_id" value="<?= h((string)$cohortId) ?>">
                        <input type="hidden" name="course_id" value="<?= h((string)$cid) ?>">
                        <input type="number" min="1" max="12" name="weekly_hours" class="form-control form-control-sm" style="max-width: 90px;" value="<?= h((string)$s['weekly_hours']) ?>" required>
                        <button class="btn btn-sm btn-outline-primary">Save</button>
                      </form>
                    </td>
                    <td>
                      <form method="post" class="d-flex gap-2">
                        <input type="hidden" name="action" value="assign_faculty">
                        <input type="hidden" name="cohort_id" value="<?= h((string)$cohortId) ?>">
                        <input type="hidden" name="course_id" value="<?= h((string)$cid) ?>">
                        <select class="form-select form-select-sm" name="faculty_id" required>
                          <option value="">Select</option>
                          <?php foreach ($opts as $f): ?>
                            <option value="<?= h((string)$f['faculty_id']) ?>" <?= (int)$f['faculty_id'] === (int)$current ? 'selected' : '' ?>>
                              <?= h($f['full_name'] . ' (' . $f['department'] . ')') ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                        <button class="btn btn-sm btn-primary">Save</button>
                      </form>
                    </td>
                    <td class="text-muted small"><?= $current ? 'OK' : 'Missing' ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$selectedSubjects): ?>
                  <tr><td colspan="5" class="text-muted">Select subjects first (left table) and click Save Subjects.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

        </div>
      </div>
    </div>
  </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
$title = 'Class Group Subjects - ' . APP_NAME;
require __DIR__ . '/_layout.php';
