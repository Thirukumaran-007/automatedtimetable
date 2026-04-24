<?php
require_once __DIR__ . '/_helpers.php';

$cohorts = db()->query('SELECT * FROM cohorts ORDER BY cohort_id DESC')->fetchAll();

$days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday'];
$daysAll = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
$periodHeaders = [
  ['08:45', '09:45'],
  ['09:45', '10:45'],
  ['11:05', '12:05'],
  ['12:05', '13:05'],
  ['13:55', '14:45'],
  ['15:00', '15:50'],
  ['15:50', '16:40'],
];

$cohortId = (int)($_GET['cohort_id'] ?? ($cohorts[0]['cohort_id'] ?? 0));

$classLabel = '';
$slots = [];
$grid = []; // [day][periodIndex] => list of entries
$facultyAnalysis = []; // [faculty][]

if ($cohortId) {
  $stmt = db()->prepare('SELECT * FROM cohorts WHERE cohort_id=?');
  $stmt->execute([$cohortId]);
  $co = $stmt->fetch();
  if ($co) {
    $classLabel = $co['department'] . ' | Y' . $co['year_level'] . ' S' . $co['semester_no'] . ' | ' . $co['term'] . ' | Sec ' . $co['section'];
  }

  $stmt = db()->prepare(
    "SELECT t.day_of_week, t.start_time, t.end_time, c.course_code, c.course_title, r.room_code, f.full_name
     FROM timetable_slots t
     JOIN class_offerings co ON co.class_id=t.class_id
     JOIN courses c ON c.course_id=co.course_id
     JOIN rooms r ON r.room_id=t.room_id
     JOIN faculty f ON f.faculty_id=t.faculty_id
     WHERE co.cohort_id=?
     ORDER BY f.full_name, t.day_of_week, t.start_time, c.course_code"
  );
  $stmt->execute([$cohortId]);
  $slots = $stmt->fetchAll();

  foreach ($slots as $s) {
    $day = (int)$s['day_of_week'];
    $start = substr($s['start_time'], 0, 5);
    $end = substr($s['end_time'], 0, 5);

    $periodText = '-';
    foreach ($periodHeaders as $idx => [$hs, $he]) {
      if ($hs === $start && $he === $end) {
        $grid[$day][$idx][] = $s;
        $periodText = 'P' . ($idx + 1);
      }
    }

    $faculty = (string)$s['full_name'];
    $facultyAnalysis[$faculty][] = [
      'day_no' => $day,
      'day_name' => $daysAll[$day] ?? (string)$day,
      'period' => $periodText,
      'time' => $start . ' - ' . $end,
      'subject' => $s['course_code'] . ' - ' . $s['course_title'],
      'room' => $s['room_code'],
    ];
  }

  foreach ($facultyAnalysis as $faculty => $items) {
    usort($items, function ($a, $b) {
      if ($a['day_no'] === $b['day_no']) {
        return strcmp($a['time'], $b['time']);
      }
      return $a['day_no'] <=> $b['day_no'];
    });
    $facultyAnalysis[$faculty] = $items;
  }
  ksort($facultyAnalysis);
}

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Print Timetable</title>
  <link href="<?= h(url_for('/assets/bootstrap.min.css')) ?>" rel="stylesheet">
  <style>
    @media print {
      .no-print { display: none !important; }
      body { background: #fff; }
      .page-break { page-break-before: always; }
    }
  </style>
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between no-print mb-3">
    <div>
      <a class="btn btn-outline-secondary" href="<?= h(url_for('/cohort_timetable.php') . '?cohort_id=' . (string)$cohortId) ?>">Back</a>
    </div>
    <div class="d-flex gap-2">
      <form method="get" class="d-flex gap-2">
        <select class="form-select" name="cohort_id" onchange="this.form.submit()">
          <?php foreach ($cohorts as $c): ?>
            <option value="<?= h((string)$c['cohort_id']) ?>" <?= (int)$c['cohort_id'] === $cohortId ? 'selected' : '' ?>>
              <?= h($c['department'] . ' | Y' . $c['year_level'] . ' S' . $c['semester_no'] . ' | ' . $c['term'] . ' | Sec ' . $c['section']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
      <button class="btn btn-primary" onclick="window.print()">Print</button>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <h4 class="mb-1">Class Timetable</h4>
      <div class="text-muted mb-3"><?= h($classLabel ?: 'No class group selected') ?></div>

      <div class="table-responsive">
        <table class="table table-bordered table-sm text-center align-middle">
          <thead class="table-light">
            <tr>
              <th style="min-width: 120px;">Day</th>
              <?php foreach ($periodHeaders as $i => $p): ?>
                <th>Period <?= $i + 1 ?><div class="small text-muted"><?= h($p[0] . '-' . $p[1]) ?></div></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($days as $d => $dayName): ?>
              <tr>
                <th class="text-start"><?= h($dayName) ?></th>
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

      <div class="text-muted small mt-3">Generated on <?= h(date('Y-m-d H:i')) ?></div>
    </div>
  </div>

  <div class="card shadow-sm page-break">
    <div class="card-body">
      <h5 class="mb-2">Faculty Analysis</h5>
      <div class="text-muted small mb-3">Faculty-wise period list with day and time for this class group.</div>

      <?php if (!$facultyAnalysis): ?>
        <div class="text-muted">No scheduled periods found for this class group.</div>
      <?php else: ?>
        <?php foreach ($facultyAnalysis as $facultyName => $items): ?>
          <div class="d-flex justify-content-between align-items-center mt-3 mb-1">
            <h6 class="mb-0"><?= h($facultyName) ?></h6>
            <span class="badge text-bg-secondary"><?= h((string)count($items)) ?> periods</span>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-bordered">
              <thead class="table-light">
                <tr>
                  <th style="width: 120px;">Period</th>
                  <th style="width: 140px;">Day</th>
                  <th style="width: 170px;">Time</th>
                  <th>Subject</th>
                  <th style="width: 120px;">Room</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $it): ?>
                  <tr>
                    <td><?= h($it['period']) ?></td>
                    <td><?= h($it['day_name']) ?></td>
                    <td><?= h($it['time']) ?></td>
                    <td><?= h($it['subject']) ?></td>
                    <td><?= h($it['room']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
