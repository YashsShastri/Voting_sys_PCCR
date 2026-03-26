<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('student');
$pageTitle = 'Notifications - ' . SITE_NAME;

$db  = getDB();
$uid = $_SESSION['user_id'];

// Mark all as read
$db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$uid]);

$notifs = $db->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC");
$notifs->execute([$uid]);
$notifs = $notifs->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container">
  <div class="page-header">
    <h1><i class="fas fa-bell"></i> Notifications</h1>
  </div>
  <?php if (empty($notifs)): ?>
    <div class="empty-state large"><i class="fas fa-inbox"></i><p>No notifications yet.</p></div>
  <?php else: ?>
  <div class="notifications-list">
    <?php foreach ($notifs as $n): ?>
    <div class="notif-card">
      <div class="notif-icon"><i class="fas fa-bell"></i></div>
      <div class="notif-body">
        <p><?= clean($n['message']) ?></p>
        <small class="text-muted"><?= formatDateTime($n['created_at']) ?></small>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
