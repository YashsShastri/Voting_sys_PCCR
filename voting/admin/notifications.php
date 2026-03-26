<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('admin');
$pageTitle = 'Send Notifications - ' . SITE_NAME;

$db  = getDB();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $target  = $_POST['target'] ?? 'all';
    $message = clean($_POST['message'] ?? '');
    if (empty($message)) $errors[] = 'Message is required.';
    if (empty($errors)) {
        if ($target === 'all') {
            $users = $db->query("SELECT id FROM users WHERE role='student'")->fetchAll(PDO::FETCH_COLUMN);
        } elseif ($target === 'unverified') {
            $users = $db->query("SELECT u.id FROM users u JOIN students s ON s.user_id=u.id WHERE s.verified=0")->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $users = [$target];
        }
        foreach ($users as $uid) sendNotification($uid, $message);
        logAction('NOTIFICATION_SENT', "Sent to " . count($users) . " users: $message");
        flash('success', 'Notification sent to ' . count($users) . ' users.');
        header('Location: notifications.php'); exit;
    }
}

$students = $db->query("SELECT u.id, s.name, u.email FROM students s JOIN users u ON s.user_id=u.id ORDER BY s.name")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container">
  <div class="page-header"><h1><i class="fas fa-bell"></i> Send Notifications</h1></div>
  <?php if ($errors): ?><div class="alert alert-error"><?= implode('<br>', array_map('clean', $errors)) ?></div><?php endif; ?>
  <div class="card">
    <div class="card-header"><h3>Compose Notification</h3></div>
    <div class="card-body">
      <form method="POST">
        <?= csrf_field() ?>
        <div class="form-group">
          <label>Send To</label>
          <select name="target" class="form-control">
            <option value="all">All Students</option>
            <option value="unverified">Unverified Students Only</option>
            <?php foreach ($students as $s): ?>
              <option value="<?= $s['id'] ?>"><?= clean($s['name']) ?> (<?= clean($s['email']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Message</label>
          <textarea name="message" class="form-control" rows="5" placeholder="Enter notification message..." required></textarea>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send Notification</button>
      </form>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
