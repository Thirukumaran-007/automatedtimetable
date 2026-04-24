<?php
require_once __DIR__ . '/_helpers.php';

$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  try {
    if ($action === 'create') {
      $stmt = db()->prepare('INSERT INTO rooms(room_code,building,capacity,room_type,active) VALUES (?,?,?,?,?)');
      $stmt->execute([
        trim($_POST['room_code'] ?? ''),
        trim($_POST['building'] ?? ''),
        (int)($_POST['capacity'] ?? 40),
        $_POST['room_type'] ?? 'Classroom',
        isset($_POST['active']) ? 1 : 0,
      ]);
      redirect(url_for('/rooms.php') . '?msg=' . urlencode('Room added.'));
    } elseif ($action === 'update') {
      $stmt = db()->prepare('UPDATE rooms SET room_code=?, building=?, capacity=?, room_type=?, active=? WHERE room_id=?');
      $stmt->execute([
        trim($_POST['room_code'] ?? ''),
        trim($_POST['building'] ?? ''),
        (int)($_POST['capacity'] ?? 40),
        $_POST['room_type'] ?? 'Classroom',
        isset($_POST['active']) ? 1 : 0,
        (int)($_POST['room_id'] ?? 0),
      ]);
      redirect(url_for('/rooms.php') . '?msg=' . urlencode('Room updated.'));
    } elseif ($action === 'delete') {
      $stmt = db()->prepare('DELETE FROM rooms WHERE room_id=?');
      $stmt->execute([(int)($_POST['room_id'] ?? 0)]);
      redirect(url_for('/rooms.php') . '?msg=' . urlencode('Room deleted.'));
    }
  } catch (Throwable $e) {
    $flash = 'Error: ' . $e->getMessage();
  }
}

$flash = $flash ?: (string)($_GET['msg'] ?? '');

$editId = (int)($_GET['edit'] ?? 0);
$editRow = null;
if ($editId) {
  $stmt = db()->prepare('SELECT * FROM rooms WHERE room_id=?');
  $stmt->execute([$editId]);
  $editRow = $stmt->fetch() ?: null;
}

$rows = db()->query('SELECT * FROM rooms ORDER BY room_id DESC')->fetchAll();

ob_start();
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0">Rooms</h4>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form method="post" class="row g-2">
      <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'create' ?>">
      <?php if ($editRow): ?>
        <input type="hidden" name="room_id" value="<?= h((string)$editRow['room_id']) ?>">
      <?php endif; ?>
      <div class="col-md-2"><input required class="form-control" name="room_code" placeholder="Room code" value="<?= h((string)($editRow['room_code'] ?? '')) ?>"></div>
      <div class="col-md-3"><input required class="form-control" name="building" placeholder="Building" value="<?= h((string)($editRow['building'] ?? '')) ?>"></div>
      <div class="col-md-2"><input required type="number" min="1" max="1000" class="form-control" name="capacity" value="<?= h((string)($editRow['capacity'] ?? 40)) ?>" placeholder="Capacity"></div>
      <div class="col-md-3">
        <select class="form-select" name="room_type">
          <?php $rt = (string)($editRow['room_type'] ?? 'Classroom'); ?>
          <option <?= $rt==='Classroom'?'selected':'' ?>>Classroom</option>
          <option <?= $rt==='Lab'?'selected':'' ?>>Lab</option>
          <option <?= $rt==='Seminar'?'selected':'' ?>>Seminar</option>
          <option <?= $rt==='Auditorium'?'selected':'' ?>>Auditorium</option>
        </select>
      </div>
      <div class="col-md-1 d-flex align-items-center">
        <div class="form-check">
          <?php $isActive = (int)($editRow['active'] ?? 1) === 1; ?>
          <input class="form-check-input" type="checkbox" name="active" id="active" <?= $isActive ? 'checked' : '' ?>>
          <label class="form-check-label" for="active">Active</label>
        </div>
      </div>
      <div class="col-md-1 d-grid"><button class="btn btn-<?= $editRow ? 'success' : 'primary' ?>"><?= $editRow ? 'Update' : 'Add' ?></button></div>
      <?php if ($editRow): ?>
        <div class="col-12"><a class="btn btn-sm btn-outline-secondary" href="<?= h(url_for('/rooms.php')) ?>">Cancel edit</a></div>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-sm table-striped">
        <thead>
          <tr>
            <th>ID</th><th>Code</th><th>Building</th><th>Cap.</th><th>Type</th><th>Active</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= h((string)$r['room_id']) ?></td>
              <td><?= h($r['room_code']) ?></td>
              <td><?= h($r['building']) ?></td>
              <td><?= h((string)$r['capacity']) ?></td>
              <td><?= h($r['room_type']) ?></td>
              <td><?= $r['active'] ? 'Yes' : 'No' ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="<?= h(url_for('/rooms.php') . '?edit=' . (string)$r['room_id']) ?>">Edit</a>
                <form method="post" onsubmit="return confirm('Delete this room?')" style="display:inline">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="room_id" value="<?= h((string)$r['room_id']) ?>">
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
<?php
$content = ob_get_clean();
$title = 'Rooms - ' . APP_NAME;
require __DIR__ . '/_layout.php';
