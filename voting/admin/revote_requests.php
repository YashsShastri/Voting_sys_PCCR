<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('admin');
$pageTitle = 'Revote Requests - ' . SITE_NAME;

$db = getDB();

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $rid    = (int)($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $note   = clean($_POST['admin_note'] ?? '');

    if ($rid && in_array($action, ['approved','rejected'])) {
        $req = $db->prepare("SELECT r.*, s.user_id FROM revote_requests r JOIN students s ON r.student_id=s.id WHERE r.id=?");
        $req->execute([$rid]);
        $req = $req->fetch();

        if ($req) {
            $db->prepare("UPDATE revote_requests SET status=?, admin_note=?, resolved_at=NOW() WHERE id=?")->execute([$action, $note, $rid]);

            if ($action === 'approved') {
                // Invalidate the existing vote
                $db->prepare("UPDATE votes SET is_valid=0 WHERE student_id=? AND election_id=?")->execute([$req['student_id'], $req['election_id']]);
                sendNotification($req['user_id'], "Your revote request has been APPROVED. You may now vote again in the election.");
                logAction('REVOTE_APPROVED', "Request ID $rid approved. Previous vote invalidated.");
            } else {
                sendNotification($req['user_id'], "Your revote request was rejected. Reason: " . ($note ?: 'No reason provided.'));
                logAction('REVOTE_REJECTED', "Request ID $rid rejected.");
            }
            flash('success', "Request $action.");
        }
        header('Location: revote_requests.php'); exit;
    }
}

$requests = $db->query("
    SELECT r.*, e.title as election_title, s.name as student_name, s.roll_no
    FROM revote_requests r
    JOIN elections e ON r.election_id=e.id
    JOIN students s ON r.student_id=s.id
    ORDER BY FIELD(r.status,'pending','approved','rejected'), r.requested_at DESC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container">
  <div class="page-header">
    <h1><i class="fas fa-redo"></i> Revote Requests</h1>
  </div>
  <div class="card">
    <div class="card-body">
      <?php if (empty($requests)): ?>
        <div class="empty-state"><i class="fas fa-inbox"></i><p>No revote requests found.</p></div>
      <?php endif; ?>
      <?php foreach ($requests as $r): ?>
      <div class="revote-card revote-card--<?= $r['status'] ?>">
        <div class="revote-card__header">
          <div>
            <strong><?= clean($r['student_name']) ?></strong> (<?= clean($r['roll_no']) ?>)
            <span class="badge badge-<?= ['pending'=>'warning','approved'=>'success','rejected'=>'danger'][$r['status']] ?>"><?= ucfirst($r['status']) ?></span>
          </div>
          <small><?= formatDateTime($r['requested_at']) ?></small>
        </div>
        <div class="revote-card__body">
          <p><strong>Election:</strong> <?= clean($r['election_title']) ?></p>
          <p><strong>Reason:</strong> <?= clean($r['reason']) ?></p>
          <?php if ($r['admin_note']): ?>
            <p><strong>Admin Note:</strong> <?= clean($r['admin_note']) ?></p>
          <?php endif; ?>
        </div>
        <?php if ($r['status'] === 'pending'): ?>
        <div class="revote-card__actions">
          <form method="POST" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
            <input type="text" name="admin_note" class="form-control form-control--inline" placeholder="Optional admin note...">
            <button type="submit" name="action" value="approved" class="btn btn-success btn-sm">
              <i class="fas fa-check"></i> Approve
            </button>
            <button type="submit" name="action" value="rejected" class="btn btn-danger btn-sm"
                    onclick="return confirm('Reject this request?')">
              <i class="fas fa-times"></i> Reject
            </button>
          </form>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
