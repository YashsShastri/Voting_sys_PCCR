<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('admin');
$pageTitle = 'Complaints - ' . SITE_NAME;

$db = getDB();

// Handle reply
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $cid   = (int)($_POST['complaint_id'] ?? 0);
    $reply = clean($_POST['admin_reply'] ?? '');
    $status = $_POST['status'] ?? 'resolved';
    $db->prepare("UPDATE complaints SET admin_reply=?, status=? WHERE id=?")->execute([$reply, $status, $cid]);
    logAction('COMPLAINT_REPLY', "Replied to complaint ID $cid.");
    flash('success', 'Reply saved.'); header('Location: complaints.php'); exit;
}

$complaints = $db->query("SELECT c.*, u.email FROM complaints c JOIN users u ON c.user_id=u.id ORDER BY FIELD(c.status,'open','resolved','closed'), c.created_at DESC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container">
  <div class="page-header"><h1><i class="fas fa-flag"></i> Manage Complaints</h1></div>
  <?php if (empty($complaints)): ?>
    <div class="empty-state large"><i class="fas fa-check-circle"></i><p>No complaints filed.</p></div>
  <?php endif; ?>
  <?php foreach ($complaints as $c): ?>
  <div class="card mb-2">
    <div class="card-header">
      <div>
        <strong><?= clean($c['subject']) ?></strong> —
        <small class="text-muted"><?= clean($c['email']) ?></small>
      </div>
      <span class="badge badge-<?= ['open'=>'warning','resolved'=>'success','closed'=>'muted'][$c['status']] ?>"><?= ucfirst($c['status']) ?></span>
    </div>
    <div class="card-body">
      <p><?= clean($c['description']) ?></p>
      <?php if ($c['admin_reply']): ?>
        <div class="admin-reply"><strong>Admin Reply:</strong> <?= clean($c['admin_reply']) ?></div>
      <?php endif; ?>
      <?php if ($c['status'] === 'open'): ?>
      <form method="POST" style="margin-top:1rem">
        <?= csrf_field() ?>
        <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">
        <div class="form-group">
          <textarea name="admin_reply" class="form-control" rows="3" placeholder="Type your reply..." required></textarea>
        </div>
        <select name="status" class="form-control form-control--inline">
          <option value="resolved">Mark Resolved</option>
          <option value="closed">Close</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-reply"></i> Reply</button>
      </form>
      <?php endif; ?>
      <small class="text-muted">Filed: <?= formatDateTime($c['created_at']) ?></small>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
