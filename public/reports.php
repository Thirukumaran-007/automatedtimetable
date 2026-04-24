<?php
require_once __DIR__ . '/_helpers.php';

// Faculty workload (weekly scheduled hours)
$facultyWorkload = db()->query(
    "SELECT f.faculty_id, f.full_name, f.department,
            COALESCE(SUM(TIMESTAMPDIFF(MINUTE, t.start_time, t.end_time))/60, 0) AS hours_scheduled,
            f.max_weekly_hours
     FROM faculty f
     LEFT JOIN timetable_slots t ON t.faculty_id=f.faculty_id
     GROUP BY f.faculty_id
     ORDER BY hours_scheduled DESC, f.full_name"
)->fetchAll();

// Room utilization (hours booked)
$roomUtil = db()->query(
    "SELECT r.room_id, r.room_code, r.building, r.capacity,
            COALESCE(SUM(TIMESTAMPDIFF(MINUTE, t.start_time, t.end_time))/60, 0) AS hours_booked
     FROM rooms r
     LEFT JOIN timetable_slots t ON t.room_id=r.room_id
     GROUP BY r.room_id
     ORDER BY hours_booked DESC, r.room_code"
)->fetchAll();

// Class utilization (class = cohort)
$classUtil = db()->query(
    "SELECT ch.cohort_id,
      ch.department, ch.year_level, ch.semester_no, ch.section, ch.term,
      ch.expected_strength,
      (SELECT COUNT(*)
       FROM timetable_slots t
       JOIN class_offerings co ON co.class_id=t.class_id
       WHERE co.cohort_id=ch.cohort_id) AS slots_count
     FROM cohorts ch
     ORDER BY ch.cohort_id DESC"
)->fetchAll();

ob_start();
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0">Reports</h4>
  <a class="btn btn-outline-secondary" href="<?= h(url_for('/schedule.php')) ?>">Back to Schedule</a>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <h6 class="mb-2">Faculty Workload</h6>
    <div class="table-responsive">
      <table class="table table-sm table-striped">
        <thead><tr><th>Faculty</th><th>Dept</th><th>Hours Scheduled</th><th>Max Weekly</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($facultyWorkload as $f):
            $hours = (float)$f['hours_scheduled'];
            $max = (int)$f['max_weekly_hours'];
            $pct = $max > 0 ? ($hours / $max) * 100 : 0;
            $status = $pct > 100 ? 'Overloaded' : ($pct >= 80 ? 'High' : 'OK');
          ?>
            <tr>
              <td><?= h($f['full_name']) ?></td>
              <td><?= h($f['department']) ?></td>
              <td><?= h(number_format($hours, 1)) ?></td>
              <td><?= h((string)$max) ?></td>
              <td><?= h($status) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <h6 class="mb-2">Room Utilization</h6>
    <div class="table-responsive">
      <table class="table table-sm table-striped">
        <thead><tr><th>Room</th><th>Building</th><th>Capacity</th><th>Hours Booked</th></tr></thead>
        <tbody>
          <?php foreach ($roomUtil as $r): ?>
            <tr>
              <td><?= h($r['room_code']) ?></td>
              <td><?= h($r['building']) ?></td>
              <td><?= h((string)$r['capacity']) ?></td>
              <td><?= h(number_format((float)$r['hours_booked'], 1)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <h6 class="mb-2">Class Utilization</h6>
    <div class="table-responsive">
      <table class="table table-sm table-striped">
        <thead><tr><th>Class</th><th>Term</th><th>Expected</th><th>Slots</th></tr></thead>
        <tbody>
          <?php foreach ($classUtil as $c): ?>
            <tr>
              <td><?= h($c['department'] . ' | Y' . $c['year_level'] . ' S' . $c['semester_no'] . ' | Sec ' . $c['section']) ?></td>
              <td><?= h($c['term']) ?></td>
              <td><?= h((string)$c['expected_strength']) ?></td>
              <td><?= h((string)$c['slots_count']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
$title = 'Reports - ' . APP_NAME;
require __DIR__ . '/_layout.php';
