<?php
require_once __DIR__ . '/_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url_for('/index.php'));
}

admin_logout();
redirect(url_for('/login.php') . '?msg=' . urlencode('Logged out successfully.'));
