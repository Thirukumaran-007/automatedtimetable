<?php
require_once __DIR__ . '/_helpers.php';

$schemaMissing = false;
$schemaError = '';

try {
  $stats = [
    'faculty' => db()->query('SELECT COUNT(*) c FROM faculty')->fetch()['c'] ?? 0,
    'courses' => db()->query('SELECT COUNT(*) c FROM courses')->fetch()['c'] ?? 0,
    'classes' => db()->query('SELECT COUNT(*) c FROM cohorts')->fetch()['c'] ?? 0,
    'rooms' => db()->query('SELECT COUNT(*) c FROM rooms')->fetch()['c'] ?? 0,
    'slots' => db()->query('SELECT COUNT(*) c FROM timetable_slots')->fetch()['c'] ?? 0,
  ];

  $adminStats = [
    'active_labs' => db()->query("SELECT COUNT(*) c FROM rooms WHERE active=1 AND room_type='Lab'")->fetch()['c'] ?? 0,
    'unassigned_subjects' => db()->query(
      'SELECT COUNT(*) c '
      . 'FROM cohort_courses cc '
      . 'LEFT JOIN cohort_faculty cf ON cf.cohort_id=cc.cohort_id AND cf.course_id=cc.course_id '
      . 'WHERE cf.cohort_faculty_id IS NULL'
    )->fetch()['c'] ?? 0,
    'pending_groups' => db()->query(
      'SELECT COUNT(*) c '
      . 'FROM cohorts ch '
      . 'WHERE NOT EXISTS ('
      . '  SELECT 1 FROM class_offerings co '
      . '  JOIN timetable_slots t ON t.class_id=co.class_id '
      . '  WHERE co.cohort_id=ch.cohort_id'
      . ')'
    )->fetch()['c'] ?? 0,
  ];
} catch (PDOException $e) {
  // Common on fresh hosted DBs: schema not imported yet.
  $msg = $e->getMessage();
  $schemaMissing = str_contains($msg, "doesn't exist") || str_contains((string)$e->getCode(), '42S02');
  $schemaError = $msg;
  $stats = [
    'faculty' => 0,
    'courses' => 0,
    'classes' => 0,
    'rooms' => 0,
    'slots' => 0,
  ];
  $adminStats = [
    'active_labs' => 0,
    'unassigned_subjects' => 0,
    'pending_groups' => 0,
  ];
}

ob_start();
?>
<div class="row g-3">
  <div class="col-12">
    <div class="card shadow-sm border-0 admin-hero-card">
      <div class="card-body">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
          <div>
            <div class="admin-kicker"><i class="bi bi-shield-lock"></i> Admin Dashboard</div>
            <h4 class="mb-1">Timetable Assigner Control Center</h4>
            <p class="text-muted mb-0">Run timetable operations, monitor pending class groups, and complete assignment workflow from one place.</p>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-primary" href="<?= h(url_for('/cohorts.php')) ?>"><i class="bi bi-diagram-3"></i>Class Groups</a>
            <a class="btn btn-outline-primary" href="<?= h(url_for('/cohort_subjects.php')) ?>"><i class="bi bi-link-45deg"></i>Subject Mapping</a>
            <a class="btn btn-primary" href="<?= h(url_for('/cohort_timetable.php')) ?>"><i class="bi bi-magic"></i>Generate Timetable</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php if ($schemaMissing): ?>
  <div class="col-12">
    <div class="alert alert-warning mb-0">
      <b>Database schema not found.</b>
      Import <span class="badge text-bg-secondary">sql/timetable.sql</span> into your MySQL database, or run
      <a href="<?= h(url_for('/setup.php')) ?>">Setup</a>.
      <div class="small text-muted mt-1">Details: <?= h($schemaError) ?></div>
    </div>
  </div>
  <?php endif; ?>

  <?php foreach ($stats as $k => $v): ?>
  <div class="col-6 col-lg-2">
    <div class="card shadow-sm stat-card">
      <div class="card-body text-center">
        <div class="text-muted text-uppercase small mb-1"><?= h($k) ?></div>
        <div class="display-6" style="font-size:2rem"><?= h((string)$v) ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>

  <div class="col-12">
    <div class="card shadow-sm admin-monitor-card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="mb-0">Admin Monitoring</h5>
          <span class="badge text-bg-secondary">Live Overview</span>
        </div>
        <div class="row g-3">
          <div class="col-md-4">
            <div class="admin-monitor-item">
              <div class="admin-monitor-title">Active Lab Rooms</div>
              <div class="admin-monitor-value"><?= h((string)$adminStats['active_labs']) ?></div>
              <div class="small text-muted">Used automatically for lab subjects</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="admin-monitor-item">
              <div class="admin-monitor-title">Subjects Without Faculty</div>
              <div class="admin-monitor-value"><?= h((string)$adminStats['unassigned_subjects']) ?></div>
              <div class="small text-muted">Map faculty before generation</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="admin-monitor-item">
              <div class="admin-monitor-title">Class Groups Pending Timetable</div>
              <div class="admin-monitor-value"><?= h((string)$adminStats['pending_groups']) ?></div>
              <div class="small text-muted">No scheduled slots yet</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="mb-3">Admin Workflow</h5>
        <div class="workflow-grid">
          <div class="workflow-step">
            <div class="workflow-step-no">01</div>
            <div class="workflow-step-body">
              <div class="fw-semibold">Create Class Groups</div>
              <div class="small text-muted">Define class, year, semester, term, and section.</div>
              <a class="small" href="<?= h(url_for('/cohorts.php')) ?>">Open Class Groups</a>
            </div>
          </div>
          <div class="workflow-step">
            <div class="workflow-step-no">02</div>
            <div class="workflow-step-body">
              <div class="fw-semibold">Map Subjects and Faculty</div>
              <div class="small text-muted">Attach courses to each class group and assign teaching faculty.</div>
              <a class="small" href="<?= h(url_for('/cohort_subjects.php')) ?>">Open Subjects and Faculty</a>
            </div>
          </div>
          <div class="workflow-step">
            <div class="workflow-step-no">03</div>
            <div class="workflow-step-body">
              <div class="fw-semibold">Generate and Review</div>
              <div class="small text-muted">Generate all periods, inspect conflicts, and print final timetable.</div>
              <a class="small" href="<?= h(url_for('/cohort_timetable.php')) ?>">Open Generator</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="mb-3">Quick Admin Actions</h6>
        <div class="row g-2">
          <div class="col-6 col-md-3"><a class="btn btn-outline-primary w-100" href="<?= h(url_for('/faculty.php')) ?>"><i class="bi bi-person-workspace"></i>Manage Faculty</a></div>
          <div class="col-6 col-md-3"><a class="btn btn-outline-primary w-100" href="<?= h(url_for('/courses.php')) ?>"><i class="bi bi-journal-text"></i>Manage Courses</a></div>
          <div class="col-6 col-md-3"><a class="btn btn-outline-primary w-100" href="<?= h(url_for('/rooms.php')) ?>"><i class="bi bi-door-open"></i>Manage Rooms</a></div>
          <div class="col-6 col-md-3"><a class="btn btn-outline-primary w-100" href="<?= h(url_for('/reports.php')) ?>"><i class="bi bi-graph-up-arrow"></i>View Reports</a></div>
          <div class="col-6 col-md-3"><a class="btn btn-outline-primary w-100" href="<?= h(url_for('/change_password.php')) ?>"><i class="bi bi-key"></i>Account Settings</a></div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
$title = 'Admin Dashboard - ' . APP_NAME;
require __DIR__ . '/_layout.php';
