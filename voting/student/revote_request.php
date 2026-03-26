<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('student');
$pageTitle = 'Request Revote - ' . SITE_NAME;

$db  = getDB();
$sid = $_SESSION['student_id'];
$uid = $_SESSION['user_id'];
$eid = (int)($_GET['election_id'] ?? 0);

// Get elections the student voted in (where no pending revote request exists)
$elections = $db->prepare("
    SELECT e.id, e.title FROM votes v
    JOIN elections e ON v.election_id=e.id
    WHERE v.student_id=? AND v.is_valid=1
    AND e.id NOT IN (
        SELECT election_id FROM revote_requests WHERE student_id=? AND status='pending'
    )
    ORDER BY e.start_time DESC
");
$elections->execute([$sid, $sid]);
$elections = $elections->fetchAll();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $election_id = (int)($_POST['election_id'] ?? 0);
    $reason      = clean($_POST['reason'] ?? '');

    if (!$election_id) $errors[] = 'Please select an election.';
    if (strlen($reason) < 20) $errors[] = 'Please provide a reason of at least 20 characters.';

    if (empty($errors)) {
        // Verify student has voted
        $v = $db->prepare("SELECT id FROM votes WHERE student_id=? AND election_id=? AND is_valid=1");
        $v->execute([$sid, $election_id]);
        if (!$v->fetch()) { $errors[] = 'You have not voted in this election.'; }
    }

    if (empty($errors)) {
        $ins = $db->prepare("INSERT INTO revote_requests (student_id, election_id, reason) VALUES (?,?,?)");
        $ins->execute([$sid, $election_id, $reason]);
        // Notify admin
        $adminId = $db->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetchColumn();
        if ($adminId) sendNotification($adminId, "New revote request from student ID $sid for election ID $election_id.");
        logAction('REVOTE_REQUEST', "Student requested revote for election ID $election_id");
        flash('success', 'Revote request submitted. You will be notified when reviewed.');
        header('Location: revote_status.php'); exit;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container">
  <div class="page-header">
    <h1><i class="fas fa-redo"></i> Request a Revote</h1>
    <p>Submit a request to vote again. Admin approval is required.</p>
  </div>
  <?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $e): ?><li><?= clean($e) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <?php if (empty($elections)): ?>
    <div class="alert alert-info">You have no eligible elections for a revote request. (You may already have a pending request.)</div>
  <?php else: ?>
  <div class="card">
    <div class="card-header"><h3>Revote Request Form</h3></div>
    <div class="card-body">
      <form method="POST">
        <?= csrf_field() ?>
        <div class="form-group">
          <label>Select Election</label>
          <select name="election_id" class="form-control" required>
            <option value="">-- Select Election --</option>
            <?php foreach ($elections as $e): ?>
            <option value="<?= $e['id'] ?>" <?= ($eid==$e['id']) ? 'selected':'' ?>><?= clean($e['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Reason for Revote</label>
          <textarea name="reason" class="form-control" rows="5" placeholder="Explain clearly why you need to change your vote (min 20 characters)..." required></textarea>
        </div>
        <div class="form-notice">
          <i class="fas fa-info-circle"></i>
          If approved, your previous vote will be <strong>invalidated</strong> and you will be allowed to vote again.
        </div>
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-paper-plane"></i> Submit Request
        </button>
        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
      </form>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
