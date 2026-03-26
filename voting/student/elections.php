<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('student');
$pageTitle = 'Elections - ' . SITE_NAME;

$db  = getDB();
$sid = $_SESSION['student_id'];
$tab = $_GET['tab'] ?? 'active'; // active | upcoming | past

$elections = $db->query("SELECT e.*, COUNT(DISTINCT c.id) as candidate_count, COUNT(DISTINCT v.id) as vote_count FROM elections e LEFT JOIN candidates c ON c.election_id=e.id LEFT JOIN votes v ON v.election_id=e.id AND v.is_valid=1 GROUP BY e.id ORDER BY e.start_time DESC")->fetchAll();

// Group by status
$grouped = ['active'=>[], 'upcoming'=>[], 'completed'=>[]];
foreach ($elections as $el) { $grouped[$el['status']][] = $el; }

// Votes cast by this student
$myVotes = $db->prepare("SELECT election_id FROM votes WHERE student_id=? AND is_valid=1");
$myVotes->execute([$sid]);
$myVoteIds = array_column($myVotes->fetchAll(), 'election_id');

// Student verified?
$verified = $_SESSION['verified'] ?? 0;

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container">
  <div class="page-header">
    <h1><i class="fas fa-poll"></i> Elections</h1>
    <p>Browse active, upcoming, and past elections</p>
  </div>

  <!-- Tab Bar -->
  <div class="tab-bar">
    <a href="?tab=active" class="tab <?= $tab==='active' ? 'tab--active' : '' ?>">
      <i class="fas fa-circle text-green"></i> Active (<?= count($grouped['active']) ?>)
    </a>
    <a href="?tab=upcoming" class="tab <?= $tab==='upcoming' ? 'tab--active' : '' ?>">
      <i class="fas fa-clock"></i> Upcoming (<?= count($grouped['upcoming']) ?>)
    </a>
    <a href="?tab=past" class="tab <?= $tab==='completed' ? 'tab--active' : '' ?>">
      <i class="fas fa-check"></i> Past (<?= count($grouped['completed']) ?>)
    </a>
  </div>

  <?php
  $statusMap = ['active'=>'active','upcoming'=>'upcoming','past'=>'completed'];
  $current   = $grouped[$statusMap[$tab] ?? 'active'] ?? [];
  ?>

  <?php if (empty($current)): ?>
    <div class="empty-state large"><i class="fas fa-calendar-times"></i><p>No <?= $tab ?> elections found.</p></div>
  <?php else: ?>
  <div class="election-cards-grid">
    <?php foreach ($current as $el): ?>
    <div class="election-card">
      <div class="election-card__header">
        <span class="status-badge status-<?= $el['status'] ?>"><?= ucfirst($el['status']) ?></span>
        <h3><?= clean($el['title']) ?></h3>
        <p><?= clean($el['description'] ?? '') ?></p>
      </div>
      <div class="election-card__meta">
        <div class="meta-item"><i class="fas fa-play-circle"></i><span><?= formatDateTime($el['start_time']) ?></span></div>
        <div class="meta-item"><i class="fas fa-stop-circle"></i><span><?= formatDateTime($el['end_time']) ?></span></div>
        <div class="meta-item"><i class="fas fa-users"></i><span><?= $el['candidate_count'] ?> Candidates</span></div>
        <div class="meta-item"><i class="fas fa-check-square"></i><span><?= $el['vote_count'] ?> Votes Cast</span></div>
      </div>
      <div class="election-card__actions">
        <a href="candidates.php?election_id=<?= $el['id'] ?>" class="btn btn-outline btn-sm">
          <i class="fas fa-eye"></i> View Candidates
        </a>
        <?php if ($el['status'] === 'active'): ?>
          <?php if (in_array($el['id'], $myVoteIds)): ?>
            <span class="badge badge-success"><i class="fas fa-check"></i> Voted</span>
            <a href="revote_request.php?election_id=<?= $el['id'] ?>" class="btn btn-secondary btn-sm">
              <i class="fas fa-redo"></i> Request Revote
            </a>
          <?php elseif ($verified): ?>
            <a href="vote.php?election_id=<?= $el['id'] ?>" class="btn btn-primary btn-sm">
              <i class="fas fa-vote-yea"></i> Cast Vote
            </a>
          <?php else: ?>
            <span class="badge badge-warning">Verify account to vote</span>
          <?php endif; ?>
        <?php elseif ($el['status'] === 'completed'): ?>
          <a href="candidates.php?election_id=<?= $el['id'] ?>&results=1" class="btn btn-outline btn-sm">
            <i class="fas fa-chart-bar"></i> View Results
          </a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
