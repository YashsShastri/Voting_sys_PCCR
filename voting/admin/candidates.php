<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('admin');
$pageTitle = 'Manage Candidates - ' . SITE_NAME;

$db  = getDB();
$eid = (int)($_GET['election_id'] ?? 0);
if (!$eid) { flash('error','Select an election first.'); header('Location: elections.php'); exit; }

$election = $db->prepare("SELECT * FROM elections WHERE id=?");
$election->execute([$eid]);
$election = $election->fetch();
if (!$election) { flash('error','Election not found.'); header('Location: elections.php'); exit; }

// Delete candidate
if (isset($_GET['delete'])) {
    $db->prepare("DELETE FROM candidates WHERE id=? AND election_id=?")->execute([(int)$_GET['delete'], $eid]);
    logAction('CANDIDATE_DELETED', "Candidate ID " . (int)$_GET['delete']);
    flash('success', 'Candidate deleted.'); header("Location: candidates.php?election_id=$eid"); exit;
}

$errors = [];
$editCandidate = null;
if (isset($_GET['edit'])) {
    $s = $db->prepare("SELECT * FROM candidates WHERE id=? AND election_id=?");
    $s->execute([(int)$_GET['edit'], $eid]);
    $editCandidate = $s->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = clean($_POST['name'] ?? '');
    $desc = clean($_POST['description'] ?? '');
    $cid  = (int)($_POST['candidate_id'] ?? 0);

    if (empty($name)) $errors[] = 'Name is required.';

    // Handle photo upload
    $photo = $editCandidate['photo'] ?? null;
    if (!empty($_FILES['photo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif'])) {
            $errors[] = 'Invalid photo format. Use JPG, PNG, or GIF.';
        } elseif ($_FILES['photo']['size'] > 2*1024*1024) {
            $errors[] = 'Photo too large (max 2MB).';
        } else {
            $filename = 'cand_' . time() . '_' . rand(100,999) . '.' . $ext;
            move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/../uploads/' . $filename);
            $photo = $filename;
        }
    }

    if (empty($errors)) {
        if ($cid) {
            $db->prepare("UPDATE candidates SET name=?, description=?, photo=? WHERE id=? AND election_id=?")->execute([$name, $desc, $photo, $cid, $eid]);
            logAction('CANDIDATE_UPDATED', "Candidate ID $cid updated.");
            flash('success', 'Candidate updated.');
        } else {
            $db->prepare("INSERT INTO candidates (election_id, name, description, photo) VALUES (?,?,?,?)")->execute([$eid, $name, $desc, $photo]);
            logAction('CANDIDATE_ADDED', "New candidate: $name for election ID $eid");
            flash('success', 'Candidate added.');
        }
        header("Location: candidates.php?election_id=$eid"); exit;
    }
}

$candidates = $db->prepare("SELECT c.*, COUNT(v.id) as votes FROM candidates c LEFT JOIN votes v ON v.candidate_id=c.id AND v.election_id=? AND v.is_valid=1 WHERE c.election_id=? GROUP BY c.id ORDER BY c.name");
$candidates->execute([$eid, $eid]);
$candidates = $candidates->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container">
  <div class="page-header">
    <a href="elections.php" class="back-link"><i class="fas fa-arrow-left"></i> Elections</a>
    <h1><i class="fas fa-users"></i> Candidates — <?= clean($election['title']) ?></h1>
  </div>
  <?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $e): ?><li><?= clean($e) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <div class="card mb-3">
    <div class="card-header"><h3><?= $editCandidate ? 'Edit Candidate' : 'Add Candidate' ?></h3></div>
    <div class="card-body">
      <form method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <?php if ($editCandidate): ?><input type="hidden" name="candidate_id" value="<?= $editCandidate['id'] ?>"><?php endif; ?>
        <div class="form-group">
          <label>Candidate Name</label>
          <input type="text" name="name" class="form-control" required value="<?= clean($editCandidate['name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Description / Manifesto</label>
          <textarea name="description" class="form-control" rows="4"><?= clean($editCandidate['description'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
          <label>Photo (optional, max 2MB)</label>
          <input type="file" name="photo" class="form-control" accept="image/*">
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= $editCandidate ? 'Update' : 'Add Candidate' ?></button>
        <?php if ($editCandidate): ?><a href="candidates.php?election_id=<?= $eid ?>" class="btn btn-secondary">Cancel</a><?php endif; ?>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h3>Candidates (<?= count($candidates) ?>)</h3></div>
    <div class="card-body">
      <table class="data-table">
        <thead><tr><th>Photo</th><th>Name</th><th>Description</th><th>Votes</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($candidates as $c): ?>
          <tr>
            <td>
              <?php if ($c['photo']): ?>
                <img src="<?= BASE_URL ?>uploads/<?= clean($c['photo']) ?>" class="thumb-sm" alt="">
              <?php else: ?>
                <i class="fas fa-user-circle fa-2x text-muted"></i>
              <?php endif; ?>
            </td>
            <td><strong><?= clean($c['name']) ?></strong></td>
            <td><?= clean(substr($c['description'] ?? '', 0, 60)) ?>...</td>
            <td><span class="badge badge-blue"><?= $c['votes'] ?></span></td>
            <td class="action-btns">
              <a href="candidates.php?election_id=<?= $eid ?>&edit=<?= $c['id'] ?>" class="btn btn-secondary btn-xs"><i class="fas fa-edit"></i></a>
              <a href="candidates.php?election_id=<?= $eid ?>&delete=<?= $c['id'] ?>" class="btn btn-danger btn-xs" onclick="return confirm('Delete this candidate?')"><i class="fas fa-trash"></i></a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
