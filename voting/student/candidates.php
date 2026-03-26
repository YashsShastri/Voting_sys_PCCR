<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('student');
$pageTitle = 'Candidates - ' . SITE_NAME;

$db  = getDB();
$sid = $_SESSION['student_id'];
$eid = (int)($_GET['election_id'] ?? 0);
$showResults = isset($_GET['results']);

if (!$eid) { flash('error','Invalid election.'); header('Location: elections.php'); exit; }

$election = $db->prepare("SELECT * FROM elections WHERE id=?");
$election->execute([$eid]);
$election = $election->fetch();
if (!$election) { flash('error','Election not found.'); header('Location: elections.php'); exit; }

// Candidates with vote count (only show counts for completed elections or results view)
$candidates = $db->prepare("
    SELECT c.*, COUNT(v.id) as vote_count
    FROM candidates c
    LEFT JOIN votes v ON v.candidate_id=c.id AND v.election_id=? AND v.is_valid=1
    WHERE c.election_id=?
    GROUP BY c.id
    ORDER BY vote_count DESC
");
$candidates->execute([$eid, $eid]);
$candidates = $candidates->fetchAll();
$totalVotes = array_sum(array_column($candidates, 'vote_count'));

// My vote in this election
$myVote = $db->prepare("SELECT candidate_id FROM votes WHERE student_id=? AND election_id=? AND is_valid=1");
$myVote->execute([$sid, $eid]);
$myVoteId = $myVote->fetchColumn();

$canShowResults = ($election['status'] === 'completed') || $showResults;

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container">
  <div class="page-header">
    <a href="elections.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Elections</a>
    <h1><i class="fas fa-users"></i> <?= clean($election['title']) ?></h1>
    <p><?= clean($election['description'] ?? '') ?></p>
    <div class="election-meta-bar">
      <span class="status-badge status-<?= $election['status'] ?>"><?= ucfirst($election['status']) ?></span>
      <span><i class="fas fa-play-circle"></i> <?= formatDateTime($election['start_time']) ?></span>
      <span><i class="fas fa-stop-circle"></i> <?= formatDateTime($election['end_time']) ?></span>
      <span><i class="fas fa-check-square"></i> <?= $totalVotes ?> votes total</span>
    </div>
  </div>

  <?php if ($myVoteId): ?>
    <div class="alert alert-success">
      <i class="fas fa-check-circle"></i> You have voted in this election.
      <a href="revote_request.php?election_id=<?= $eid ?>">Request revote?</a>
    </div>
  <?php endif; ?>

  <div class="candidates-results-grid">
    <?php foreach ($candidates as $idx => $c): ?>
    <div class="candidate-result-card <?= ($myVoteId == $c['id']) ? 'candidate-result-card--mine' : '' ?>">
      <?php if ($canShowResults && $idx === 0 && $totalVotes > 0): ?>
        <div class="winner-badge"><i class="fas fa-trophy"></i> Leading</div>
      <?php endif; ?>
      <div class="candidate-avatar-lg">
        <?php if ($c['photo']): ?>
          <img src="<?= BASE_URL ?>uploads/<?= clean($c['photo']) ?>" alt="<?= clean($c['name']) ?>">
        <?php else: ?>
          <i class="fas fa-user-circle"></i>
        <?php endif; ?>
      </div>
      <h3><?= clean($c['name']) ?></h3>
      <p><?= clean($c['description'] ?? '') ?></p>
      <?php if ($myVoteId == $c['id']): ?>
        <div class="my-vote-label"><i class="fas fa-check"></i> Your Vote</div>
      <?php endif; ?>
      <?php if ($canShowResults): ?>
        <div class="vote-result">
          <div class="vote-count"><?= $c['vote_count'] ?> votes</div>
          <div class="vote-bar-wrap">
            <?php $pct = $totalVotes > 0 ? round(($c['vote_count']/$totalVotes)*100) : 0; ?>
            <div class="vote-bar-fill" style="width:<?= $pct ?>%"></div>
          </div>
          <div class="vote-pct"><?= $pct ?>%</div>
        </div>
      <?php endif; ?>
      <?php if ($election['status'] === 'active' && !$myVoteId && $_SESSION['verified']): ?>
      <a href="vote.php?election_id=<?= $eid ?>" class="btn btn-primary btn-sm mt-1">
        <i class="fas fa-vote-yea"></i> Vote
      </a>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ($canShowResults && $totalVotes > 0): ?>
  <div class="card mt-3">
    <div class="card-header"><h3><i class="fas fa-chart-bar"></i> Results Chart</h3></div>
    <div class="card-body"><canvas id="resultsChart" height="80"></canvas></div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
  const ctx = document.getElementById('resultsChart').getContext('2d');
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: [<?= implode(',', array_map(fn($c) => '"' . addslashes($c['name']) . '"', $candidates)) ?>],
      datasets: [{
        label: 'Votes',
        data: [<?= implode(',', array_column($candidates, 'vote_count')) ?>],
        backgroundColor: ['#6366f1','#8b5cf6','#06b6d4','#10b981','#f59e0b'],
        borderRadius: 8,
      }]
    },
    options: { responsive: true, plugins: { legend: { display: false } } }
  });
  </script>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
