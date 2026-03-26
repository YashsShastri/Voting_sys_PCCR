<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('admin');
$pageTitle = 'Manage Elections - ' . SITE_NAME;

$db  = getDB();
$uid = $_SESSION['user_id'];

// Handle actions
if (isset($_GET['stop'])) {
    $eid = (int)$_GET['stop'];
    $db->prepare("UPDATE elections SET status='completed' WHERE id=?")->execute([$eid]);
    logAction('ELECTION_STOPPED', "Election ID $eid manually stopped.");
    flash('success', 'Election stopped.'); header('Location: elections.php'); exit;
}
if (isset($_GET['delete']) && isset($_GET['confirm'])) {
    $eid = (int)$_GET['delete'];
    $db->prepare("DELETE FROM elections WHERE id=?")->execute([$eid]);
    logAction('ELECTION_DELETED', "Election ID $eid deleted.");
    flash('success', 'Election deleted.'); header('Location: elections.php'); exit;
}

// Create or Edit election
$editElection = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM elections WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editElection = $stmt->fetch();
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $title  = clean($_POST['title'] ?? '');
    $desc   = clean($_POST['description'] ?? '');
    $start  = $_POST['start_time'] ?? '';
    $end    = $_POST['end_time'] ?? '';
    $eid    = (int)($_POST['election_id'] ?? 0);

    if (empty($title)) $errors[] = 'Title is required.';
    if (empty($start) || empty($end)) $errors[] = 'Start and end time are required.';
    if (!empty($start) && !empty($end) && strtotime($end) <= strtotime($start)) $errors[] = 'End time must be after start time.';

    if (empty($errors)) {
        $now = date('Y-m-d H:i:s');
        $status = strtotime($start) > time() ? 'upcoming' : (strtotime($end) > time() ? 'active' : 'completed');
        if ($eid) {
            $db->prepare("UPDATE elections SET title=?, description=?, start_time=?, end_time=?, status=? WHERE id=?")->execute([$title, $desc, $start, $end, $status, $eid]);
            logAction('ELECTION_UPDATED', "Election ID $eid updated.");
            flash('success', 'Election updated.');
        } else {
            $db->prepare("INSERT INTO elections (title, description, start_time, end_time, status, created_by) VALUES (?,?,?,?,?,?)")->execute([$title, $desc, $start, $end, $status, $uid]);
            logAction('ELECTION_CREATED', "New election: $title");
            flash('success', 'Election created.');
        }
        header('Location: elections.php'); exit;
    }
}

$elections = $db->query("SELECT e.*, COUNT(DISTINCT c.id) as candidates, COUNT(DISTINCT v.id) as votes FROM elections e LEFT JOIN candidates c ON c.election_id=e.id LEFT JOIN votes v ON v.election_id=e.id AND v.is_valid=1 GROUP BY e.id ORDER BY e.start_time DESC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container">
  <div class="page-header">
    <h1><i class="fas fa-poll"></i> Manage Elections</h1>
    <button class="btn btn-primary" onclick="document.getElementById('createForm').scrollIntoView()">
      <i class="fas fa-plus"></i> New Election
    </button>
  </div>

  <!-- Create/Edit Form -->
  <div class="card mb-3" id="createForm">
    <div class="card-header"><h3><?= $editElection ? 'Edit Election' : 'Create New Election' ?></h3></div>
    <div class="card-body">
      <?php if ($errors): ?>
        <div class="alert alert-error"><ul><?php foreach ($errors as $e): ?><li><?= clean($e) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>
      <form method="POST">
        <?= csrf_field() ?>
        <?php if ($editElection): ?>
          <input type="hidden" name="election_id" value="<?= $editElection['id'] ?>">
        <?php endif; ?>
        <div class="form-grid-2">
          <div class="form-group form-group--full">
            <label>Election Title</label>
            <input type="text" name="title" class="form-control" placeholder="e.g. Student Council Election 2025" required
                   value="<?= clean($editElection['title'] ?? $_POST['title'] ?? '') ?>">
          </div>
          <div class="form-group form-group--full">
            <label>Description</label>
            <textarea name="description" class="form-control" rows="3"><?= clean($editElection['description'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label>Start Time</label>
            <input type="datetime-local" name="start_time" class="form-control" required
                   value="<?= $editElection ? date('Y-m-d\TH:i', strtotime($editElection['start_time'])) : '' ?>">
          </div>
          <div class="form-group">
            <label>End Time</label>
            <input type="datetime-local" name="end_time" class="form-control" required
                   value="<?= $editElection ? date('Y-m-d\TH:i', strtotime($editElection['end_time'])) : '' ?>">
          </div>
        </div>
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-save"></i> <?= $editElection ? 'Update Election' : 'Create Election' ?>
        </button>
        <?php if ($editElection): ?>
          <a href="elections.php" class="btn btn-secondary">Cancel</a>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <!-- Elections Table -->
  <div class="card">
    <div class="card-header"><h3>All Elections</h3></div>
    <div class="card-body">
      <table class="data-table">
        <thead>
          <tr><th>Title</th><th>Status</th><th>Candidates</th><th>Votes</th><th>Start</th><th>End</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($elections as $el): ?>
          <tr>
            <td><strong><?= clean($el['title']) ?></strong></td>
            <td><span class="status-badge status-<?= $el['status'] ?>"><?= ucfirst($el['status']) ?></span></td>
            <td><?= $el['candidates'] ?></td>
            <td><?= $el['votes'] ?></td>
            <td><?= formatDateTime($el['start_time']) ?></td>
            <td><?= formatDateTime($el['end_time']) ?></td>
            <td class="action-btns">
              <a href="candidates.php?election_id=<?= $el['id'] ?>" class="btn btn-outline btn-xs" title="Candidates"><i class="fas fa-users"></i></a>
              <a href="elections.php?edit=<?= $el['id'] ?>" class="btn btn-secondary btn-xs" title="Edit"><i class="fas fa-edit"></i></a>
              <a href="live_results.php?election_id=<?= $el['id'] ?>" class="btn btn-primary btn-xs" title="Results"><i class="fas fa-chart-bar"></i></a>
              <a href="export.php?election_id=<?= $el['id'] ?>" class="btn btn-outline btn-xs" title="Export"><i class="fas fa-download"></i></a>
              <?php if ($el['status'] === 'active'): ?>
                <a href="elections.php?stop=<?= $el['id'] ?>" class="btn btn-danger btn-xs" onclick="return confirm('Stop this election?')" title="Stop"><i class="fas fa-stop"></i></a>
              <?php endif; ?>
              <a href="elections.php?delete=<?= $el['id'] ?>&confirm=1" class="btn btn-danger btn-xs" onclick="return confirm('Delete this election? This cannot be undone.')" title="Delete"><i class="fas fa-trash"></i></a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
