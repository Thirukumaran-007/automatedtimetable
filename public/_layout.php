<?php
require_once __DIR__ . '/_helpers.php';

$title = $title ?? APP_NAME;
$flash = isset($flash) ? (string)$flash : '';
$currentPage = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$navActive = static function (array $pages) use ($currentPage): string {
  return in_array($currentPage, $pages, true) ? ' active' : '';
};
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= h(url_for('/assets/bootstrap.min.css')) ?>" rel="stylesheet">
  <link href="<?= h(url_for('/assets/styles.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="bg-orb bg-orb-a" aria-hidden="true"></div>
<div class="bg-orb bg-orb-b" aria-hidden="true"></div>

<nav class="navbar navbar-expand-lg app-navbar">
  <div class="container app-shell">
    <a class="navbar-brand app-brand" href="<?= h(url_for('/index.php')) ?>"><?= h(APP_NAME) ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav ms-auto align-items-lg-center">
        <li class="nav-item"><a class="nav-link<?= $navActive(['faculty.php']) ?>" href="<?= h(url_for('/faculty.php')) ?>"><i class="bi bi-person-workspace"></i>Faculty</a></li>
        <li class="nav-item"><a class="nav-link<?= $navActive(['students.php']) ?>" href="<?= h(url_for('/students.php')) ?>"><i class="bi bi-people"></i>Students</a></li>
        <li class="nav-item"><a class="nav-link<?= $navActive(['courses.php']) ?>" href="<?= h(url_for('/courses.php')) ?>"><i class="bi bi-journal-text"></i>Courses</a></li>
        <li class="nav-item"><a class="nav-link<?= $navActive(['cohorts.php','cohort_subjects.php']) ?>" href="<?= h(url_for('/cohorts.php')) ?>"><i class="bi bi-diagram-3"></i>Class Groups</a></li>
        <li class="nav-item"><a class="nav-link<?= $navActive(['rooms.php']) ?>" href="<?= h(url_for('/rooms.php')) ?>"><i class="bi bi-door-open"></i>Rooms</a></li>
        <li class="nav-item"><a class="nav-link<?= $navActive(['schedule.php']) ?>" href="<?= h(url_for('/schedule.php')) ?>"><i class="bi bi-calendar-week"></i>Schedule</a></li>
        <li class="nav-item"><a class="nav-link<?= $navActive(['reports.php']) ?>" href="<?= h(url_for('/reports.php')) ?>"><i class="bi bi-graph-up-arrow"></i>Reports</a></li>
        <li class="nav-item"><a class="btn btn-sm btn-brand ms-lg-2<?= $navActive(['cohort_timetable.php','timetable.php','print_timetable.php']) ?>" href="<?= h(url_for('/cohort_timetable.php')) ?>"><i class="bi bi-magic"></i>Generate Timetable</a></li>
        <li class="nav-item"><a class="nav-link<?= $navActive(['change_password.php']) ?>" href="<?= h(url_for('/change_password.php')) ?>"><i class="bi bi-key"></i>Account</a></li>
        <li class="nav-item"><span class="navbar-text text-light small px-lg-2"><i class="bi bi-person-circle me-1"></i><?= h(admin_username()) ?></span></li>
        <li class="nav-item ms-lg-1">
          <form method="post" action="<?= h(url_for('/logout.php')) ?>" class="m-0">
            <button class="btn btn-sm btn-outline-light" type="submit"><i class="bi bi-box-arrow-right"></i>Logout</button>
          </form>
        </li>
      </ul>
    </div>
  </div>
</nav>

<main class="container app-shell py-4 py-lg-5">
  <?php if ($flash !== ''): ?>
    <noscript><div class="alert alert-info app-alert"><?= h($flash) ?></div></noscript>
  <?php endif; ?>
  <?= $content ?? '' ?>
</main>

<footer class="container app-shell pb-4">
  <div class="small text-center text-secondary">Built for fast, conflict-aware timetable planning.</div>
</footer>

<script src="<?= h(url_for('/assets/bootstrap.bundle.min.js')) ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  (function () {
    const flashMessage = <?= json_encode($flash, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    if (!flashMessage) {
      return;
    }

    document.querySelectorAll('.alert.alert-info').forEach((el) => {
      if ((el.textContent || '').trim() === flashMessage.trim()) {
        el.style.display = 'none';
      }
    });

    let icon = 'success';
    let title = 'Completed';
    let text = flashMessage;

    if (/^error\s*:/i.test(flashMessage)) {
      icon = 'error';
      title = 'Action Failed';
      text = flashMessage.replace(/^error\s*:\s*/i, '');
    } else if (/warning|missing|invalid|could not|not found/i.test(flashMessage)) {
      icon = 'warning';
      title = 'Attention';
    } else if (/generated|created|saved|updated|deleted|assigned|added|applied|imported|completed/i.test(flashMessage)) {
      icon = 'success';
      title = 'Success';
    }

    if (window.Swal) {
      const isError = icon === 'error';
      Swal.fire({
        icon,
        title,
        text,
        timer: isError ? undefined : 2500,
        timerProgressBar: !isError,
        confirmButtonColor: '#1d4ed8',
        background: '#f7fbff',
        customClass: {
          popup: 'app-swal-popup',
          title: 'app-swal-title'
        }
      });
    } else {
      window.alert(flashMessage);
    }
  })();
</script>
</body>
</html>
