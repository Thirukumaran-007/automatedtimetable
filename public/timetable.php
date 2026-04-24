<?php
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../app/timetable_generator.php';

$flash = '';
$genDetails = null;

$cohorts = db()->query('SELECT * FROM cohorts ORDER BY cohort_id DESC')->fetchAll();

$rooms = db()->query('SELECT room_id, room_code, building FROM rooms WHERE active=1 ORDER BY room_code')->fetchAll();

$cohortId = (int)($_GET['cohort_id'] ?? ($cohorts[0]['cohort_id'] ?? 0));
$roomId = (int)($_GET['room_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
  $cohortId = (int)($_POST['cohort_id'] ?? $cohortId);
    $roomId = (int)($_POST['room_id'] ?? $roomId);

  $result = TimetableGenerator::generateForCohort($cohortId, $roomId, TimetableGenerator::defaultPeriods(), TimetableGenerator::MODE_FILL_ALL);
    if ($result['ok']) {
    if (isset($result['filled'], $result['total'])) {
      $flash = 'Timetable generated: ' . (int)$result['filled'] . '/' . (int)$result['total'] . ' periods filled.';
    } else {
      $flash = 'Timetable generated: ' . (int)$result['created'] . ' periods.';
    }
    $genDetails = $result;
    } else {
        $flash = 'Error: ' . $result['error'];
    }
}

// Period headers used for display (teaching periods only)
$periodHeaders = [
    ['08:45','09:45'],
    ['09:45','10:45'],
    ['11:05','12:05'],
    ['12:05','13:05'],
    ['13:55','14:45'],
    ['15:00','15:50'],
    ['15:50','16:40'],
];

$days = [1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday'];

$cohortInfo = null;
if ($cohortId) {
  $stmt = db()->prepare('SELECT * FROM cohorts WHERE cohort_id=?');
  $stmt->execute([$cohortId]);
  $cohortInfo = $stmt->fetch() ?: null;
}

$grid = []; // [day][periodIndex] => list of entries (stacked if multiple)
if ($cohortId) {
    $stmt = db()->prepare(
    "SELECT t.day_of_week, t.start_time, t.end_time, c.course_code, r.room_code, f.full_name
         FROM timetable_slots t
         JOIN class_offerings co ON co.class_id=t.class_id
         JOIN courses c ON c.course_id=co.course_id
         JOIN rooms r ON r.room_id=t.room_id
         JOIN faculty f ON f.faculty_id=t.faculty_id
     WHERE co.cohort_id=?
     ORDER BY t.day_of_week, t.start_time, c.course_code"
    );
  $stmt->execute([$cohortId]);
    foreach ($stmt->fetchAll() as $s) {
        $d = (int)$s['day_of_week'];
        $st = substr($s['start_time'], 0, 5);
        $et = substr($s['end_time'], 0, 5);
        foreach ($periodHeaders as $idx => [$hs,$he]) {
            if ($hs === $st && $he === $et) {
        $grid[$d][$idx][] = $s;
            }
        }
    }
}

// Summary table: course -> faculty -> number of periods
$summary = [];
if ($cohortId) {
    $stmt = db()->prepare(
        "SELECT c.course_code, c.course_title, f.full_name, COUNT(*) AS periods
         FROM timetable_slots t
         JOIN class_offerings co ON co.class_id=t.class_id
         JOIN courses c ON c.course_id=co.course_id
         JOIN faculty f ON f.faculty_id=t.faculty_id
     WHERE co.cohort_id=?
         GROUP BY c.course_code, c.course_title, f.full_name"
    );
  $stmt->execute([$cohortId]);
    $summary = $stmt->fetchAll();
}

ob_start();
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0">Timetable</h4>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= h(url_for('/schedule.php')) ?>">Manual Schedule</a>
    <a class="btn btn-outline-primary" href="<?= h(url_for('/print_timetable.php') . '?cohort_id=' . (string)$cohortId) ?>">Print</a>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-7">
        <label class="form-label">Class (Cohort)</label>
        <select class="form-select" name="cohort_id" onchange="this.form.submit()">
          <?php foreach ($cohorts as $c): ?>
            <option value="<?= h((string)$c['cohort_id']) ?>" <?= (int)$c['cohort_id'] === $cohortId ? 'selected' : '' ?>>
              <?= h($c['department'] . ' | Y' . $c['year_level'] . ' S' . $c['semester_no'] . ' | ' . $c['term'] . ' | Sec ' . $c['section']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-5">
        <label class="form-label">Room</label>
        <select class="form-select" name="room_id" onchange="this.form.submit()">
          <option value="0" <?= $roomId === 0 ? 'selected' : '' ?>>Auto (any available room)</option>
          <?php foreach ($rooms as $r): ?>
            <option value="<?= h((string)$r['room_id']) ?>" <?= (int)$r['room_id'] === $roomId ? 'selected' : '' ?>>
              <?= h($r['room_code'] . ' - ' . $r['building']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>

    <form method="post" class="mt-3">
      <input type="hidden" name="action" value="generate">
      <input type="hidden" name="cohort_id" value="<?= h((string)$cohortId) ?>">
      <input type="hidden" name="room_id" value="<?= h((string)$roomId) ?>">
      <button class="btn btn-primary" onclick="return confirm('Generate timetable for this class? Existing slots will be replaced.')">Generate Timetable</button>
      <?php if ($flash): ?>
        <span class="ms-2 text-muted"><?= h($flash) ?></span>
      <?php endif; ?>
    </form>

    <?php
    if ($cohortId && is_array($genDetails) && isset($genDetails['unfilled']) && (int)$genDetails['unfilled'] > 0) {
        $daysShort = [1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri'];
        $periods = TimetableGenerator::defaultPeriods();
        $stmt = db()->prepare(
            'SELECT t.day_of_week, t.start_time, t.end_time '
            . 'FROM timetable_slots t '
            . 'JOIN class_offerings co ON co.class_id=t.class_id '
            . 'WHERE co.cohort_id=?'
        );
        $stmt->execute([$cohortId]);
        $filledSet = [];
        foreach ($stmt->fetchAll() as $r) {
            $filledSet[(int)$r['day_of_week'] . '|' . substr($r['start_time'],0,8) . '|' . substr($r['end_time'],0,8)] = true;
        }
        $unfilled = [];
        foreach ($periods as $p) {
            [$d,$s,$e] = $p;
            $key = (int)$d . '|' . $s . '|' . $e;
            if (!isset($filledSet[$key])) {
                $unfilled[] = ($daysShort[(int)$d] ?? (string)$d) . ' ' . substr($s,0,5) . '-' . substr($e,0,5);
            }
        }
        $top = [];
        foreach (($genDetails['errors'] ?? []) as $msg) {
            $top[$msg] = ($top[$msg] ?? 0) + 1;
        }
        arsort($top);
        $top = array_slice($top, 0, 3, true);
        ?>
        <div class="alert alert-warning mt-3 mb-0">
          <div class="fw-semibold mb-1">Some periods could not be filled</div>
          <?php if ($unfilled): ?>
            <div class="small"><span class="fw-semibold">Unfilled:</span> <?= h(implode(', ', array_slice($unfilled, 0, 12))) ?><?= count($unfilled) > 12 ? '…' : '' ?></div>
          <?php endif; ?>
          <?php if ($top): ?>
            <div class="small mt-2"><span class="fw-semibold">Top conflicts:</span>
              <?php foreach ($top as $m => $cnt): ?>
                <div><?= h($cnt . '× ' . $m) ?></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <?php
    }
    ?>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="d-flex justify-content-between">
      <div>
        <div class="fw-semibold">Class Timetable</div>
        <div class="text-muted small">
          <?= h(($cohortInfo['department'] ?? '') . ' | Y' . ($cohortInfo['year_level'] ?? '') . ' S' . ($cohortInfo['semester_no'] ?? '') . ' | ' . ($cohortInfo['term'] ?? '') . ' | Sec ' . ($cohortInfo['section'] ?? '')) ?>
        </div>
      </div>
      <div class="text-muted small">Room: <?= h((string)($rooms ? ($rooms[array_search($roomId, array_column($rooms, 'room_id'))]['room_code'] ?? '') : '')) ?></div>
    </div>

    <div class="table-responsive mt-3">
      <table class="table table-bordered table-sm text-center">
        <thead class="table-light">
          <tr>
            <th style="min-width: 120px;">Day</th>
            <?php foreach ($periodHeaders as $i => $p): ?>
              <th>Period <?= $i+1 ?><div class="small text-muted"><?= h($p[0] . '-' . $p[1]) ?></div></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($days as $d => $name): ?>
            <tr>
              <th class="text-start"><?= h($name) ?></th>
              <?php foreach ($periodHeaders as $idx => $_):
                $cells = $grid[$d][$idx] ?? [];
              ?>
                <td>
                  <?php if ($cells): ?>
                    <?php foreach ($cells as $cell): ?>
                      <div class="fw-semibold"><?= h($cell['course_code']) ?></div>
                      <div class="small text-muted"><?= h($cell['full_name']) ?></div>
                      <div class="small"><?= h($cell['room_code']) ?></div>
                      <hr class="my-1">
                    <?php endforeach; ?>
                  <?php else: ?>
                    <span class="text-muted">-</span>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <h6 class="mb-2">Course / Faculty Summary</h6>
    <div class="table-responsive">
      <table class="table table-sm table-striped">
        <thead><tr><th>Course Code</th><th>Course Name</th><th>Faculty Name</th><th>No. of Periods</th></tr></thead>
        <tbody>
          <?php foreach ($summary as $s): ?>
            <tr>
              <td><?= h($s['course_code']) ?></td>
              <td><?= h($s['course_title']) ?></td>
              <td><?= h($s['full_name']) ?></td>
              <td><?= h((string)$s['periods']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$summary): ?>
            <tr><td colspan="4" class="text-muted">No periods scheduled yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
$title = 'Timetable - ' . APP_NAME;
require __DIR__ . '/_layout.php';
