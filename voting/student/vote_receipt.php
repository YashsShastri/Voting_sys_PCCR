<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('student');
$pageTitle = 'Vote Receipt - ' . SITE_NAME;

$db  = getDB();
$sid = $_SESSION['student_id'];
$vid = (int)($_GET['vote_id'] ?? 0);

$vote = $db->prepare("
    SELECT v.*, e.title as election_title, c.name as candidate_name, s.name as student_name, s.roll_no
    FROM votes v
    JOIN elections e ON v.election_id=e.id
    JOIN candidates c ON v.candidate_id=c.id
    JOIN students s ON v.student_id=s.id
    WHERE v.id=? AND v.student_id=?
");
$vote->execute([$vid, $sid]);
$vote = $vote->fetch();

if (!$vote) { flash('error','Vote receipt not found.'); header('Location: dashboard.php'); exit; }

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container receipt-container">
  <div class="receipt-card" id="receipt">
    <div class="receipt-header">
      <div class="receipt-logo"><i class="fas fa-shield-alt"></i></div>
      <h2>Vote Receipt</h2>
      <p class="receipt-college">PCCOER Secure Voting System</p>
      <div class="receipt-verified"><i class="fas fa-check-circle"></i> Vote Successfully Recorded</div>
    </div>
    <div class="receipt-body">
      <table class="receipt-table">
        <tr><td><strong>Receipt ID</strong></td><td>#<?= str_pad($vid, 8, '0', STR_PAD_LEFT) ?></td></tr>
        <tr><td><strong>Student Name</strong></td><td><?= clean($vote['student_name']) ?></td></tr>
        <tr><td><strong>Roll Number</strong></td><td><?= clean($vote['roll_no']) ?></td></tr>
        <tr><td><strong>Election</strong></td><td><?= clean($vote['election_title']) ?></td></tr>
        <tr><td><strong>Voted For</strong></td><td><?= clean($vote['candidate_name']) ?></td></tr>
        <tr><td><strong>Date &amp; Time</strong></td><td><?= formatDateTime($vote['timestamp']) ?></td></tr>
        <tr><td><strong>Status</strong></td><td><span class="badge badge-success">Valid</span></td></tr>
      </table>
    </div>
    <div class="receipt-footer">
      <p><i class="fas fa-lock"></i> This vote is securely stored and audited.</p>
      <small>Reference Hash: <?= substr(hash('sha256', $vid . $vote['student_name'] . $vote['timestamp']), 0, 16) ?>...</small>
    </div>
  </div>

  <div class="receipt-actions">
    <button onclick="window.print()" class="btn btn-primary">
      <i class="fas fa-download"></i> Download / Print Receipt
    </button>
    <a href="dashboard.php" class="btn btn-secondary">
      <i class="fas fa-home"></i> Back to Dashboard
    </a>
    <a href="history.php" class="btn btn-outline">
      <i class="fas fa-history"></i> View History
    </a>
  </div>
</div>
<style>
@media print {
  nav, footer, .receipt-actions { display: none !important; }
  .receipt-card { box-shadow: none; border: 2px solid #333; }
}
</style>
<?php include __DIR__ . '/../includes/footer.php'; ?>
