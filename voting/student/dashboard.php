<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('student');
$pageTitle = 'Dashboard - ' . SITE_NAME;

$db = getDB();
$sid = $_SESSION['student_id'];
$uid = $_SESSION['user_id'];

// Student info
$student = $db->prepare("SELECT s.*, u.email FROM students s JOIN users u ON s.user_id=u.id WHERE s.id=?");
$student->execute([$sid]);
$student = $student->fetch();

// Active elections
$active = $db->query("SELECT e.*, (SELECT COUNT(*) FROM votes WHERE election_id=e.id AND is_valid=1) as vote_count FROM elections e WHERE e.status='active' ORDER BY e.end_time ASC")->fetchAll();

// Has voted?
$votedIds = [];
if ($active) {
    $eids = implode(',', array_column($active, 'id'));
    $v = $db->prepare("SELECT election_id FROM votes WHERE student_id=? AND election_id IN ($eids) AND is_valid=1");
    $v->execute([$sid]);
    $votedIds = array_column($v->fetchAll(), 'election_id');
}

// Past elections the student voted in
$pastVoted = $db->prepare("SELECT e.title, v.timestamp, c.name as candidate_name FROM votes v JOIN elections e ON v.election_id=e.id JOIN candidates c ON v.candidate_id=c.id WHERE v.student_id=? AND v.is_valid=1 ORDER BY v.timestamp DESC LIMIT 5");
$pastVoted->execute([$sid]);
$pastVoted = $pastVoted->fetchAll();

// Unread notifications
$notifs = $db->prepare("SELECT * FROM notifications WHERE user_id=? AND is_read=0 ORDER BY created_at DESC LIMIT 5");
$notifs->execute([$uid]);
$notifs = $notifs->fetchAll();

// Revote requests pending
$revoteReq = $db->prepare("SELECT COUNT(*) FROM revote_requests WHERE student_id=? AND status='pending'");
$revoteReq->execute([$sid]);
$pendingRevotes = $revoteReq->fetchColumn();

// Participation stats
$totalElections = $db->query("SELECT COUNT(*) FROM elections WHERE status='completed'")->fetchColumn();
$votedElections = $db->prepare("SELECT COUNT(*) FROM votes v JOIN elections e ON v.election_id=e.id WHERE v.student_id=? AND v.is_valid=1 AND e.status='completed'");
$votedElections->execute([$sid]);
$votedElections = $votedElections->fetchColumn();
$participation = $totalElections > 0 ? round(($votedElections / $totalElections) * 100) : 0;

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container">
  <!-- Welcome Banner -->
  <div class="welcome-banner">
    <div class="welcome-text">
      <h2>Hello, <?= clean($student['name']) ?> 👋</h2>
      <p><?= clean($student['department']) ?> &bull; Division <?= clean($student['division']) ?> &bull; <?= clean($student['roll_no']) ?></p>
    </div>
    <div class="welcome-status">
      <?php if (!$student['verified']): ?>
        <span class="badge badge-warning"><i class="fas fa-clock"></i> Pending Verification</span>
      <?php else: ?>
        <span class="badge badge-success"><i class="fas fa-check-circle"></i> Verified</span>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!$student['verified']): ?>
  <div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i>
    Your account is pending admin verification. You can browse elections but cannot vote yet.
  </div>
  <?php endif; ?>

  <!-- Stats Row -->
  <div class="stats-grid">
    <div class="stat-card stat-card--blue">
      <div class="stat-icon"><i class="fas fa-poll"></i></div>
      <div class="stat-info">
        <div class="stat-value"><?= count($active) ?></div>
        <div class="stat-label">Active Elections</div>
      </div>
    </div>
    <div class="stat-card stat-card--green">
      <div class="stat-icon"><i class="fas fa-check-square"></i></div>
      <div class="stat-info">
        <div class="stat-value"><?= $votedElections ?></div>
        <div class="stat-label">Votes Cast</div>
      </div>
    </div>
    <div class="stat-card stat-card--purple">
      <div class="stat-icon"><i class="fas fa-chart-pie"></i></div>
      <div class="stat-info">
        <div class="stat-value"><?= $participation ?>%</div>
        <div class="stat-label">Participation Rate</div>
      </div>
    </div>
    <div class="stat-card stat-card--orange">
      <div class="stat-icon"><i class="fas fa-redo"></i></div>
      <div class="stat-info">
        <div class="stat-value"><?= $pendingRevotes ?></div>
        <div class="stat-label">Pending Revote Requests</div>
      </div>
    </div>
  </div>

  <div class="dashboard-grid">
    <!-- Active Elections -->
    <div class="dashboard-card">
      <div class="card-header">
        <h3><i class="fas fa-vote-yea"></i> Active Elections</h3>
        <a href="elections.php" class="card-link">View All</a>
      </div>
      <div class="card-body">
        <?php if (empty($active)): ?>
          <div class="empty-state"><i class="fas fa-calendar-times"></i><p>No active elections right now.</p></div>
        <?php else: ?>
          <?php foreach ($active as $el): ?>
          <div class="election-item">
            <div class="election-info">
              <strong><?= clean($el['title']) ?></strong>
              <small><i class="fas fa-clock"></i> Ends: <?= formatDateTime($el['end_time']) ?></small>
              <small><i class="fas fa-users"></i> <?= $el['vote_count'] ?> votes cast</small>
            </div>
            <div class="election-action">
              <?php if (in_array($el['id'], $votedIds)): ?>
                <span class="badge badge-success"><i class="fas fa-check"></i> Voted</span>
              <?php elseif ($student['verified']): ?>
                <a href="vote.php?election_id=<?= $el['id'] ?>" class="btn btn-primary btn-sm">
                  <i class="fas fa-vote-yea"></i> Vote
                </a>
              <?php else: ?>
                <span class="badge badge-muted">Unverified</span>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Notifications -->
    <div class="dashboard-card">
      <div class="card-header">
        <h3><i class="fas fa-bell"></i> Notifications</h3>
        <a href="notifications.php" class="card-link">View All</a>
      </div>
      <div class="card-body">
        <?php if (empty($notifs)): ?>
          <div class="empty-state"><i class="fas fa-inbox"></i><p>No new notifications.</p></div>
        <?php else: ?>
          <?php foreach ($notifs as $n): ?>
          <div class="notif-item">
            <p><?= clean($n['message']) ?></p>
            <small><?= timeAgo($n['created_at']) ?></small>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Recent Voting Activity -->
    <div class="dashboard-card">
      <div class="card-header">
        <h3><i class="fas fa-history"></i> Recent Votes</h3>
        <a href="history.php" class="card-link">Full History</a>
      </div>
      <div class="card-body">
        <?php if (empty($pastVoted)): ?>
          <div class="empty-state"><i class="fas fa-ballot-check"></i><p>You haven't voted in any election yet.</p></div>
        <?php else: ?>
          <table class="data-table">
            <thead><tr><th>Election</th><th>Candidate</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($pastVoted as $v): ?>
              <tr>
                <td><?= clean($v['title']) ?></td>
                <td><?= clean($v['candidate_name']) ?></td>
                <td><?= formatDateTime($v['timestamp']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

    <!-- Quick Links -->
    <div class="dashboard-card">
      <div class="card-header"><h3><i class="fas fa-bolt"></i> Quick Actions</h3></div>
      <div class="card-body">
        <div class="quick-links">
          <a href="profile.php" class="quick-link"><i class="fas fa-user-circle"></i> My Profile</a>
          <a href="elections.php" class="quick-link"><i class="fas fa-poll"></i> All Elections</a>
          <a href="revote_request.php" class="quick-link"><i class="fas fa-redo"></i> Request Revote</a>
          <a href="revote_status.php" class="quick-link"><i class="fas fa-info-circle"></i> Revote Status</a>
          <a href="complaint.php" class="quick-link"><i class="fas fa-flag"></i> Report Issue</a>
          <a href="notifications.php" class="quick-link"><i class="fas fa-bell"></i> Notifications</a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
