<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('student');
$pageTitle = 'My Voting History - ' . SITE_NAME;

$db  = getDB();
$sid = $_SESSION['student_id'];

// All valid votes with election and candidate info
$votes = $db->prepare("
    SELECT v.id, v.timestamp, v.ip_address, v.is_valid,
           e.title as election_title, e.status as election_status,
           c.name as candidate_name
    FROM votes v
    JOIN elections e ON v.election_id = e.id
    JOIN candidates c ON v.candidate_id = c.id
    WHERE v.student_id = ?
    ORDER BY v.timestamp DESC
");
$votes->execute([$sid]);
$votes = $votes->fetchAll();

// Stats
$total_elections = $db->query("SELECT COUNT(*) FROM elections")->fetchColumn();
$total_voted     = count(array_filter($votes, fn($v) => $v['is_valid']));
$participation   = $total_elections > 0 ? round(($total_voted / $total_elections) * 100) : 0;

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container">
  <div class="page-header">
    <h1><i class="fas fa-history"></i> My Voting History</h1>
    <p>A complete record of all your votes</p>
  </div>

  <div class="stats-grid stats-grid--compact">
    <div class="stat-card stat-card--blue">
      <div class="stat-icon"><i class="fas fa-check-square"></i></div>
      <div class="stat-info"><div class="stat-value"><?= $total_voted ?></div><div class="stat-label">Total Votes Cast</div></div>
    </div>
    <div class="stat-card stat-card--purple">
      <div class="stat-icon"><i class="fas fa-chart-pie"></i></div>
      <div class="stat-info"><div class="stat-value"><?= $participation ?>%</div><div class="stat-label">Participation Rate</div></div>
    </div>
    <div class="stat-card stat-card--green">
      <div class="stat-icon"><i class="fas fa-poll"></i></div>
      <div class="stat-info"><div class="stat-value"><?= $total_elections ?></div><div class="stat-label">Total Elections</div></div>
    </div>
  </div>

  <!-- Participation Bar -->
  <div class="card">
    <div class="card-header"><h3><i class="fas fa-chart-line"></i> Participation Rate</h3></div>
    <div class="card-body">
      <div class="progress-bar-wrap">
        <div class="progress-bar-fill" style="width:<?= $participation ?>%">
          <?= $participation ?>%
        </div>
      </div>
    </div>
  </div>

  <?php if (empty($votes)): ?>
    <div class="empty-state large">
      <i class="fas fa-ballot-check"></i>
      <p>You haven't cast any votes yet. <a href="elections.php">Browse elections →</a></p>
    </div>
  <?php else: ?>
  <div class="card">
    <div class="card-header">
      <h3>Vote Records</h3>
      <span class="badge badge-blue"><?= count($votes) ?> total</span>
    </div>
    <div class="card-body">
      <table class="data-table">
        <thead>
          <tr><th>Election</th><th>Voted For</th><th>Date &amp; Time</th><th>Status</th><th>Receipt</th></tr>
        </thead>
        <tbody>
        <?php foreach ($votes as $v): ?>
          <tr>
            <td><?= clean($v['election_title']) ?><br><small class="text-muted"><?= ucfirst($v['election_status']) ?></small></td>
            <td><?= clean($v['candidate_name']) ?></td>
            <td><?= formatDateTime($v['timestamp']) ?></td>
            <td>
              <?php if ($v['is_valid']): ?>
                <span class="badge badge-success">Valid</span>
              <?php else: ?>
                <span class="badge badge-danger">Invalidated</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($v['is_valid']): ?>
                <a href="vote_receipt.php?vote_id=<?= $v['id'] ?>" class="btn btn-outline btn-xs">
                  <i class="fas fa-receipt"></i> View
                </a>
              <?php else: ?>
                <span class="text-muted">N/A</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
