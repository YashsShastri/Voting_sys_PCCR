<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('student');
$pageTitle = 'Report Issue - ' . SITE_NAME;

$db  = getDB();
$uid = $_SESSION['user_id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $subject = clean($_POST['subject'] ?? '');
    $desc    = clean($_POST['description'] ?? '');
    if (empty($subject)) $errors[] = 'Subject is required.';
    if (strlen($desc) < 20) $errors[] = 'Please describe the issue in at least 20 characters.';
    if (empty($errors)) {
        $db->prepare("INSERT INTO complaints (user_id, subject, description) VALUES (?,?,?)")->execute([$uid, $subject, $desc]);
        logAction('COMPLAINT_RAISED', "Subject: $subject");
        flash('success', 'Issue reported. Admin will review it shortly.');
        header('Location: complaint.php'); exit;
    }
}

// My past complaints
$complaints = $db->prepare("SELECT * FROM complaints WHERE user_id=? ORDER BY created_at DESC");
$complaints->execute([$uid]);
$complaints = $complaints->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container">
  <div class="page-header">
    <h1><i class="fas fa-flag"></i> Report an Issue</h1>
  </div>
  <?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $e): ?><li><?= clean($e) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <div class="card mb-3">
    <div class="card-header"><h3>Submit a Complaint</h3></div>
    <div class="card-body">
      <form method="POST">
        <?= csrf_field() ?>
        <div class="form-group">
          <label>Subject</label>
          <input type="text" name="subject" class="form-control" placeholder="Brief issue title" required value="<?= clean($_POST['subject'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea name="description" class="form-control" rows="5" placeholder="Describe the issue in detail..." required></textarea>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit</button>
      </form>
    </div>
  </div>

  <?php if ($complaints): ?>
  <div class="card">
    <div class="card-header"><h3>My Previous Complaints</h3></div>
    <div class="card-body">
      <table class="data-table">
        <thead><tr><th>Subject</th><th>Status</th><th>Admin Reply</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($complaints as $c): ?>
          <tr>
            <td><?= clean($c['subject']) ?></td>
            <td><span class="badge badge-<?= ['open'=>'warning','resolved'=>'success','closed'=>'muted'][$c['status']] ?>"><?= ucfirst($c['status']) ?></span></td>
            <td><?= clean($c['admin_reply'] ?? '-') ?></td>
            <td><?= formatDateTime($c['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
