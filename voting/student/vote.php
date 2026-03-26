<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('student');
$pageTitle = 'Vote - ' . SITE_NAME;

$db  = getDB();
$sid = $_SESSION['student_id'];
$uid = $_SESSION['user_id'];
$eid = (int)($_GET['election_id'] ?? 0);

if (!$eid) { flash('error','Invalid election.'); header('Location: elections.php'); exit; }

// Load election
$election = $db->prepare("SELECT * FROM elections WHERE id=? AND status='active'");
$election->execute([$eid]);
$election = $election->fetch();
if (!$election) { flash('error','This election is not currently active.'); header('Location: elections.php'); exit; }

// Check already voted
$existing = $db->prepare("SELECT id FROM votes WHERE student_id=? AND election_id=? AND is_valid=1");
$existing->execute([$sid, $eid]);
if ($existing->fetch()) { flash('info','You have already voted in this election.'); header('Location: elections.php'); exit; }

// Check verified
if (!$_SESSION['verified']) { flash('warning','Your account needs admin verification before you can vote.'); header('Location: dashboard.php'); exit; }

// Load candidates
$candidates = $db->prepare("SELECT * FROM candidates WHERE election_id=? ORDER BY name");
$candidates->execute([$eid]);
$candidates = $candidates->fetchAll();

// Handle vote submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $cid = (int)($_POST['candidate_id'] ?? 0);

    // Re-validate before writing
    $cCheck = $db->prepare("SELECT id FROM candidates WHERE id=? AND election_id=?");
    $cCheck->execute([$cid, $eid]);
    if (!$cCheck->fetch()) { $error = 'Invalid candidate selection.'; }

    if (empty($error)) {
        try {
            $db->beginTransaction();
            // Double check (race condition prevention)
            $dblChk = $db->prepare("SELECT id FROM votes WHERE student_id=? AND election_id=? AND is_valid=1 FOR UPDATE");
            $dblChk->execute([$sid, $eid]);
            if ($dblChk->fetch()) {
                $db->rollBack();
                flash('error','You have already voted in this election.');
                header('Location: elections.php'); exit;
            }
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $ins = $db->prepare("INSERT INTO votes (student_id, candidate_id, election_id, ip_address) VALUES (?,?,?,?)");
            $ins->execute([$sid, $cid, $eid, $ip]);
            $vote_id = $db->lastInsertId();
            $db->commit();
            logAction('VOTE_CAST', "Election ID: $eid, Candidate ID: $cid");
            sendNotification($uid, "Your vote has been recorded for election: " . $election['title']);
            header("Location: vote_receipt.php?vote_id=$vote_id");
            exit;
        } catch (Exception $e) {
            $db->rollBack(); $error = 'Vote failed. Please try again.';
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container">
  <div class="page-header">
    <h1><i class="fas fa-vote-yea"></i> Cast Your Vote</h1>
    <p><?= clean($election['title']) ?></p>
    <small>Deadline: <?= formatDateTime($election['end_time']) ?></small>
  </div>

  <?php if (isset($error)): ?>
    <div class="alert alert-error">✗ <?= clean($error) ?></div>
  <?php endif; ?>

  <div class="vote-warning">
    <i class="fas fa-exclamation-triangle"></i>
    <strong>Important:</strong> Your vote is final and cannot be changed without admin approval. Choose carefully.
  </div>

  <form method="POST" id="voteForm" class="vote-form">
    <?= csrf_field() ?>
    <div class="candidates-grid">
      <?php foreach ($candidates as $c): ?>
      <label class="candidate-card" for="cand_<?= $c['id'] ?>">
        <input type="radio" name="candidate_id" id="cand_<?= $c['id'] ?>" value="<?= $c['id'] ?>" required>
        <div class="candidate-card__inner">
          <div class="candidate-avatar">
            <?php if ($c['photo'] && file_exists('../../uploads/' . $c['photo'])): ?>
              <img src="<?= BASE_URL ?>uploads/<?= clean($c['photo']) ?>" alt="<?= clean($c['name']) ?>">
            <?php else: ?>
              <i class="fas fa-user-circle"></i>
            <?php endif; ?>
          </div>
          <div class="candidate-info">
            <h4><?= clean($c['name']) ?></h4>
            <p><?= clean($c['description'] ?? '') ?></p>
          </div>
          <div class="candidate-select-icon"><i class="fas fa-check-circle"></i></div>
        </div>
      </label>
      <?php endforeach; ?>
    </div>

    <div class="vote-actions">
      <a href="elections.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back
      </a>
      <button type="button" class="btn btn-primary btn-lg" onclick="confirmVote()">
        <i class="fas fa-check"></i> Submit Vote
      </button>
    </div>
  </form>

  <!-- Confirmation Modal -->
  <div class="modal-overlay" id="voteModal">
    <div class="modal">
      <div class="modal-icon"><i class="fas fa-vote-yea"></i></div>
      <h3>Confirm Your Vote</h3>
      <p>You are about to vote for: <strong id="selectedCandidateName">—</strong></p>
      <p class="modal-warning">This action cannot be undone without admin approval.</p>
      <div class="modal-actions">
        <button onclick="closeModal()" class="btn btn-secondary">Cancel</button>
        <button onclick="document.getElementById('voteForm').submit()" class="btn btn-primary">Confirm Vote</button>
      </div>
    </div>
  </div>
</div>
<script>
function confirmVote() {
  const selected = document.querySelector('input[name="candidate_id"]:checked');
  if (!selected) { alert('Please select a candidate before voting.'); return; }
  const label = document.querySelector('label[for="cand_'+selected.value+'"] h4').innerText;
  document.getElementById('selectedCandidateName').innerText = label;
  document.getElementById('voteModal').classList.add('active');
}
function closeModal() {
  document.getElementById('voteModal').classList.remove('active');
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
