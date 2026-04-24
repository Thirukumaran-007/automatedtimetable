<?php
require_once __DIR__ . '/_helpers.php';

$flash = (string)($_GET['msg'] ?? '');
$usernameValue = admin_username();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'change_username') {
            $newUsername = trim((string)($_POST['new_username'] ?? ''));
            $usernameValue = $newUsername !== '' ? $newUsername : $usernameValue;

            if ($newUsername === '') {
                throw new RuntimeException('Username is required.');
            }
            if (!preg_match('/^[A-Za-z0-9_.-]{3,80}$/', $newUsername)) {
                throw new RuntimeException('Username must be 3-80 characters and contain only letters, numbers, dot, underscore, or hyphen.');
            }

            $existsStmt = db()->prepare('SELECT admin_id FROM admin_users WHERE username=? AND admin_id<>? LIMIT 1');
            $existsStmt->execute([$newUsername, admin_id()]);
            if ($existsStmt->fetch()) {
                throw new RuntimeException('That username is already in use.');
            }

            $updateUser = db()->prepare('UPDATE admin_users SET username=? WHERE admin_id=?');
            $updateUser->execute([$newUsername, admin_id()]);
            admin_set_username($newUsername);

            redirect(url_for('/change_password.php') . '?msg=' . urlencode('Username updated successfully.'));
        } elseif ($action === 'change_password') {
            $currentPassword = (string)($_POST['current_password'] ?? '');
            $newPassword = (string)($_POST['new_password'] ?? '');
            $confirmPassword = (string)($_POST['confirm_password'] ?? '');

            if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
                throw new RuntimeException('All password fields are required.');
            }
            if (strlen($newPassword) < 8) {
                throw new RuntimeException('New password must be at least 8 characters.');
            }
            if ($newPassword !== $confirmPassword) {
                throw new RuntimeException('New password and confirmation do not match.');
            }

            $stmt = db()->prepare('SELECT admin_id, password_hash FROM admin_users WHERE admin_id=? LIMIT 1');
            $stmt->execute([admin_id()]);
            $admin = $stmt->fetch();

            if (!$admin || !password_verify($currentPassword, (string)$admin['password_hash'])) {
                throw new RuntimeException('Current password is incorrect.');
            }

            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $update = db()->prepare('UPDATE admin_users SET password_hash=? WHERE admin_id=?');
            $update->execute([$newHash, admin_id()]);

            redirect(url_for('/change_password.php') . '?msg=' . urlencode('Password updated successfully.'));
        } else {
            throw new RuntimeException('Invalid account action.');
        }
    } catch (Throwable $e) {
        $flash = 'Error: ' . $e->getMessage();
    }
}

ob_start();
?>
<div class="row g-3">
  <div class="col-12">
    <div class="card shadow-sm border-0 admin-hero-card">
      <div class="card-body">
        <div class="admin-kicker"><i class="bi bi-shield-check"></i> Account Security</div>
        <h4 class="mb-1">Admin Account Settings</h4>
        <p class="text-muted mb-0">
          Logged in as <strong><?= h(admin_username()) ?></strong>. You can update both username and password here.
        </p>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="mb-3">Update Username</h6>
        <form method="post" class="row g-2">
          <input type="hidden" name="action" value="change_username">
          <div class="col-12">
            <label class="form-label" for="new_username">New Username</label>
            <input class="form-control" id="new_username" name="new_username" minlength="3" maxlength="80" required value="<?= h($usernameValue) ?>">
          </div>
          <div class="col-12 d-grid mt-1">
            <button class="btn btn-primary" type="submit"><i class="bi bi-person-check"></i>Save Username</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="mb-3">Update Password</h6>
        <form method="post" class="row g-2" autocomplete="off">
          <input type="hidden" name="action" value="change_password">
          <div class="col-12">
            <label class="form-label" for="current_password">Current Password</label>
            <input class="form-control" id="current_password" type="password" name="current_password" required autocomplete="current-password">
          </div>
          <div class="col-12">
            <label class="form-label" for="new_password">New Password</label>
            <input class="form-control" id="new_password" type="password" name="new_password" minlength="8" required autocomplete="new-password">
          </div>
          <div class="col-12">
            <label class="form-label" for="confirm_password">Confirm New Password</label>
            <input class="form-control" id="confirm_password" type="password" name="confirm_password" minlength="8" required autocomplete="new-password">
          </div>
          <div class="col-12 d-grid mt-1">
            <button class="btn btn-primary" type="submit"><i class="bi bi-key"></i>Save New Password</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="mb-2">Account Guidelines</h6>
        <ul class="mb-0 text-muted">
          <li>Username: 3-80 characters, letters/numbers/dot/underscore/hyphen only.</li>
          <li>Use at least 8 characters.</li>
          <li>Avoid reusing old or shared passwords.</li>
          <li>Prefer a mix of letters, numbers, and symbols.</li>
        </ul>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
$title = 'Change Password - ' . APP_NAME;
require __DIR__ . '/_layout.php';
