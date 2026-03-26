<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('student');
$pageTitle = 'Revote Status - ' . SITE_NAME;

$db  = getDB();
$sid = $_SESSION['student_id'];

$requests = $db->prepare("
    SELECT r.*, e.title as election_title
    FROM revote_requests r
    JOIN elections e ON r.election_id = e.id
    WHERE r.student_id = ?
    ORDER BY r.requested_at DESC
");
$requests->execute([$sid]);
$requests = $requests->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container">
  <div class="page-header">
    <h1><i class="fas fa-redo"></i> Revote Request Status</h1>
    <p>Track the status of your revote requests</p>
  </div>
  <a href="revote_request.php" class="btn btn-primary mb-2"><i class="fas fa-plus"></i> New Request</a>
  <?php if (empty($requests)): ?>
    <div class="empty-state large"><i class="fas fa-inbox"></i><p>No revote requests submitted yet.</p></div>
  <?php else: ?>
  <div class="card">
    <div class="card-body">
      <table class="data-table">
        <thead><tr><th>Election</th><th>Reason</th><th>Status</th><th>Admin Note</th><th>Submitted</th></tr></thead>
        <tbody>
        <?php foreach ($requests as $r): ?>
          <tr>
            <td><?= clean($r['election_title']) ?></td>
            <td><?= clean(substr($r['reason'], 0, 60)) ?>...</td>
            <td>
              <?php
              $cls = ['pending'=>'warning','approved'=>'success','rejected'=>'danger'];
              $ic  = ['pending'=>'clock','approved'=>'check-circle','rejected'=>'times-circle'];
              ?>
              <span class="badge badge-<?= $cls[$r['status']] ?>"><i class="fas fa-<?= $ic[$r['status']] ?>"></i> <?= ucfirst($r['status']) ?></span>
            </td>
            <td><?= clean($r['admin_note'] ?? '-') ?></td>
            <td><?= formatDateTime($r['requested_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
