<?php
require_once __DIR__ . '/_helpers.php';

$flash = '';

$cohorts = db()->query('SELECT * FROM cohorts ORDER BY cohort_id DESC')->fetchAll();

$faculty = db()->query('SELECT faculty_id, full_name, department FROM faculty ORDER BY full_name')->fetchAll();
$rooms = db()->query('SELECT room_id, room_code, building FROM rooms WHERE active=1 ORDER BY room_code')->fetchAll();

$selectedCohortId = (int)($_GET['cohort_id'] ?? ($cohorts[0]['cohort_id'] ?? 0));

// Courses are filtered to those selected for the cohort.
$courses = [];
if ($selectedCohortId) {
  $stmt = db()->prepare(
    "SELECT c.course_id, c.course_code, c.course_title, c.weekly_hours
     FROM cohort_courses cc
     JOIN courses c ON c.course_id=cc.course_id
     WHERE cc.cohort_id=?
     ORDER BY c.course_code"
  );
  $stmt->execute([$selectedCohortId]);
  $courses = $stmt->fetchAll();
}

$selectedCourseId = (int)($_GET['course_id'] ?? ($courses[0]['course_id'] ?? 0));

$validFacultyIds = array_fill_keys(array_map(fn($r) => (int)$r['faculty_id'], $faculty), true);
$validRoomIds = array_fill_keys(array_map(fn($r) => (int)$r['room_id'], $rooms), true);
$validCohortIds = array_fill_keys(array_map(fn($r) => (int)$r['cohort_id'], $cohorts), true);
$validCourseIds = array_fill_keys(array_map(fn($r) => (int)$r['course_id'], $courses), true);

if ($selectedCohortId && !isset($validCohortIds[$selectedCohortId])) {
  $selectedCohortId = (int)($cohorts[0]['cohort_id'] ?? 0);
}
if ($selectedCourseId && !isset($validCourseIds[$selectedCourseId])) {
  $selectedCourseId = (int)($courses[0]['course_id'] ?? 0);
}

// Ensure there's a class_offering row for (cohort, course).
// This keeps timetable_slots model unchanged, but the UI is cohort+subject based.
$selectedClassId = 0;
if ($selectedCohortId && $selectedCourseId) {
  $pdo = db();
  $stmt = $pdo->prepare('SELECT class_id FROM class_offerings WHERE cohort_id=? AND course_id=? LIMIT 1');
  $stmt->execute([$selectedCohortId, $selectedCourseId]);
  $selectedClassId = (int)($stmt->fetch()['class_id'] ?? 0);

  if (!$selectedClassId) {
    $stmt = $pdo->prepare('SELECT * FROM cohorts WHERE cohort_id=?');
    $stmt->execute([$selectedCohortId]);
    $cohort = $stmt->fetch();
    if ($cohort) {
      $stmtCreate = $pdo->prepare(
        'INSERT INTO class_offerings(course_id,cohort_id,section,term,batch_year,expected_strength) VALUES (?,?,?,?,?,?)'
      );
      $stmtCreate->execute([
        $selectedCourseId,
        $selectedCohortId,
        (string)$cohort['section'],
        (string)$cohort['term'],
        (int)$cohort['batch_year'],
        (int)$cohort['expected_strength'],
      ]);
      $selectedClassId = (int)$pdo->lastInsertId();
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add_slot') {
          $cohortId = (int)($_POST['cohort_id'] ?? 0);
          $courseId = (int)($_POST['course_id'] ?? 0);
          $classId = (int)($_POST['class_id'] ?? 0);
          $facultyId = (int)($_POST['faculty_id'] ?? 0);
          $roomId = (int)($_POST['room_id'] ?? 0);
          $dayOfWeek = (int)($_POST['day_of_week'] ?? 1);
          $startTime = $_POST['start_time'] ?? '10:00';
          $endTime = $_POST['end_time'] ?? '11:00';

          if (!$cohortId || !isset($validCohortIds[$cohortId]) || !$courseId || !isset($validCourseIds[$courseId])) {
            throw new RuntimeException('Please select a valid class and subject and try again.');
          }
          if (!$facultyId || !isset($validFacultyIds[$facultyId])) {
            throw new RuntimeException('Please select a valid faculty and try again.');
          }
          if (!$roomId || !isset($validRoomIds[$roomId])) {
            throw new RuntimeException('Please select a valid room and try again.');
          }

          if (!$classId) {
            throw new RuntimeException('Subject offering not found for this class. Try reloading the page.');
          }

            $stmt = db()->prepare('INSERT INTO timetable_slots(class_id,faculty_id,room_id,day_of_week,start_time,end_time) VALUES (?,?,?,?,?,?)');
            $stmt->execute([
            $classId,
            $facultyId,
            $roomId,
            $dayOfWeek,
            $startTime,
            $endTime,
            ]);
            $flash = 'Slot added (conflicts are blocked automatically).';
            $selectedCohortId = $cohortId;
            $selectedCourseId = $courseId;
        } elseif ($action === 'delete_slot') {
            $stmt = db()->prepare('DELETE FROM timetable_slots WHERE slot_id=?');
            $stmt->execute([(int)($_POST['slot_id'] ?? 0)]);
            $flash = 'Slot deleted.';
        }
    } catch (Throwable $e) {
        $flash = 'Error: ' . $e->getMessage();
    }
}

$days = [1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'];

$slots = [];
if ($selectedClassId) {
    $stmt = db()->prepare(
        "SELECT t.slot_id, t.day_of_week, t.start_time, t.end_time,
                r.room_code, f.full_name
         FROM timetable_slots t
         JOIN rooms r ON r.room_id=t.room_id
         JOIN faculty f ON f.faculty_id=t.faculty_id
         WHERE t.class_id=?
         ORDER BY t.day_of_week, t.start_time"
    );
    $stmt->execute([$selectedClassId]);
    $slots = $stmt->fetchAll();
}

ob_start();
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0">Schedule</h4>
  <a class="btn btn-outline-primary" href="<?= h(url_for('/timetable.php')) ?>">Generate Timetable</a>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-8">
        <label class="form-label">Select Class Group</label>
        <select class="form-select" name="cohort_id" onchange="this.form.submit()">
          <?php foreach ($cohorts as $c): ?>
            <option value="<?= h((string)$c['cohort_id']) ?>" <?= (int)$c['cohort_id'] === $selectedCohortId ? 'selected' : '' ?>>
              <?= h($c['department'] . ' | Y' . $c['year_level'] . ' S' . $c['semester_no'] . ' | ' . $c['term'] . ' | Sec ' . $c['section']) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <label class="form-label mt-2">Select Subject</label>
        <select class="form-select" name="course_id" onchange="this.form.submit()">
          <?php foreach ($courses as $c): ?>
            <option value="<?= h((string)$c['course_id']) ?>" <?= (int)$c['course_id'] === $selectedCourseId ? 'selected' : '' ?>>
              <?= h($c['course_code'] . ' - ' . $c['course_title']) ?>
            </option>
          <?php endforeach; ?>
          <?php if (!$courses): ?>
            <option value="">No subjects selected for this class</option>
          <?php endif; ?>
        </select>
      </div>
      <div class="col-md-4 text-end">
        <a class="btn btn-outline-secondary" href="<?= h(url_for('/reports.php')) ?>">View Reports</a>
        <a class="btn btn-outline-primary" href="<?= h(url_for('/print_timetable.php') . '?cohort_id=' . (string)$selectedCohortId) ?>">Print</a>
      </div>
    </form>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="mb-2">Add Slot</h6>
        <form method="post" class="row g-2">
          <input type="hidden" name="action" value="add_slot">
          <input type="hidden" name="cohort_id" value="<?= h((string)$selectedCohortId) ?>">
          <input type="hidden" name="course_id" value="<?= h((string)$selectedCourseId) ?>">
          <input type="hidden" name="class_id" value="<?= h((string)$selectedClassId) ?>">

          <div class="col-6">
            <label class="form-label">Faculty</label>
            <select class="form-select" name="faculty_id" required>
              <?php foreach ($faculty as $f): ?>
                <option value="<?= h((string)$f['faculty_id']) ?>"><?= h($f['full_name'] . ' (' . $f['department'] . ')') ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-6">
            <label class="form-label">Room</label>
            <select class="form-select" name="room_id" required>
              <?php foreach ($rooms as $r): ?>
                <option value="<?= h((string)$r['room_id']) ?>"><?= h($r['room_code'] . ' - ' . $r['building']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-4">
            <label class="form-label">Day</label>
            <select class="form-select" name="day_of_week">
              <?php foreach ($days as $k=>$v): ?>
                <option value="<?= h((string)$k) ?>"><?= h($v) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-4">
            <label class="form-label">Start</label>
            <input type="time" class="form-control" name="start_time" value="10:00">
          </div>

          <div class="col-4">
            <label class="form-label">End</label>
            <input type="time" class="form-control" name="end_time" value="11:00">
          </div>

          <div class="col-12 d-grid">
            <button class="btn btn-primary">Add Slot</button>
          </div>
        </form>
        <div class="text-muted small mt-2">Conflicts are blocked for room, faculty, and class (DB triggers).</div>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="mb-2">Slots for Selected Class</h6>
        <div class="table-responsive">
          <table class="table table-sm table-striped">
            <thead>
              <tr><th>Day</th><th>Time</th><th>Room</th><th>Faculty</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach ($slots as $s): ?>
                <tr>
                  <td><?= h($days[(int)$s['day_of_week']] ?? (string)$s['day_of_week']) ?></td>
                  <td><?= h(substr($s['start_time'],0,5) . ' - ' . substr($s['end_time'],0,5)) ?></td>
                  <td><?= h($s['room_code']) ?></td>
                  <td><?= h($s['full_name']) ?></td>
                  <td class="text-end">
                    <form method="post" style="display:inline" onsubmit="return confirm('Delete this slot?')">
                      <input type="hidden" name="action" value="delete_slot">
                      <input type="hidden" name="slot_id" value="<?= h((string)$s['slot_id']) ?>">
                      <button class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$slots): ?>
                <tr><td colspan="5" class="text-muted">No slots scheduled for this class yet.</td></tr>
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
$title = 'Schedule - ' . APP_NAME;
require __DIR__ . '/_layout.php';
