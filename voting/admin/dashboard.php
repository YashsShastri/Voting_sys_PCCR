<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('admin');
$pageTitle = 'Admin Dashboard - ' . SITE_NAME;

$db = getDB();

// Summary stats
$stats = [
    'students'  => $db->query("SELECT COUNT(*) FROM students")->fetchColumn(),
    'verified'  => $db->query("SELECT COUNT(*) FROM students WHERE verified=1")->fetchColumn(),
    'elections' => $db->query("SELECT COUNT(*) FROM elections")->fetchColumn(),
    'active'    => $db->query("SELECT COUNT(*) FROM elections WHERE status='active'")->fetchColumn(),
    'votes'     => $db->query("SELECT COUNT(*) FROM votes WHERE is_valid=1")->fetchColumn(),
    'revotes'   => $db->query("SELECT COUNT(*) FROM revote_requests WHERE status='pending'")->fetchColumn(),
    'complaints'=> $db->query("SELECT COUNT(*) FROM complaints WHERE status='open'")->fetchColumn(),
];

// Active elections with live vote counts
$activeElections = $db->query("
    SELECT e.*, COUNT(v.id) as vote_count,
           (SELECT COUNT(*) FROM students WHERE verified=1) as total_eligible
    FROM elections e
    LEFT JOIN votes v ON v.election_id=e.id AND v.is_valid=1
    WHERE e.status='active'
    GROUP BY e.id
")->fetchAll();

// Recent audit logs
$recentLogs = $db->query("
    SELECT l.*, u.email FROM logs l
    LEFT JOIN users u ON l.user_id=u.id
    ORDER BY l.timestamp DESC LIMIT 10
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container">
  <div class="page-header">
    <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
    <p>PCCOER Voting System — Control Panel</p>
  </div>

  <!-- Stats Grid -->
  <div class="stats-grid stats-grid--6">
    <div class="stat-card stat-card--blue">
      <div class="stat-icon"><i class="fas fa-users"></i></div>
      <div class="stat-info"><div class="stat-value"><?= $stats['students'] ?></div><div class="stat-label">Total Students</div></div>
    </div>
    <div class="stat-card stat-card--green">
      <div class="stat-icon"><i class="fas fa-user-check"></i></div>
      <div class="stat-info"><div class="stat-value"><?= $stats['verified'] ?></div><div class="stat-label">Verified</div></div>
    </div>
    <div class="stat-card stat-card--purple">
      <div class="stat-icon"><i class="fas fa-poll"></i></div>
      <div class="stat-info"><div class="stat-value"><?= $stats['elections'] ?></div><div class="stat-label">Elections</div></div>
    </div>
    <div class="stat-card stat-card--teal">
      <div class="stat-icon"><i class="fas fa-circle text-green"></i></div>
      <div class="stat-info"><div class="stat-value"><?= $stats['active'] ?></div><div class="stat-label">Active Now</div></div>
    </div>
    <div class="stat-card stat-card--orange">
      <div class="stat-icon"><i class="fas fa-vote-yea"></i></div>
      <div class="stat-info"><div class="stat-value"><?= $stats['votes'] ?></div><div class="stat-label">Votes Cast</div></div>
    </div>
    <div class="stat-card stat-card--red">
      <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
      <div class="stat-info"><div class="stat-value"><?= $stats['revotes'] + $stats['complaints'] ?></div><div class="stat-label">Pending Actions</div></div>
    </div>
  </div>

  <!-- Quick Actions -->
  <div class="card mb-3">
    <div class="card-header"><h3><i class="fas fa-bolt"></i> Quick Actions</h3></div>
    <div class="card-body">
      <div class="quick-links">
        <a href="elections.php?create=1" class="quick-link quick-link--primary"><i class="fas fa-plus-circle"></i> Create Election</a>
        <a href="students.php" class="quick-link"><i class="fas fa-user-plus"></i> Manage Students</a>
        <a href="revote_requests.php" class="quick-link <?= $stats['revotes']>0 ? 'quick-link--alert':'' ?>">
          <i class="fas fa-redo"></i> Revote Requests <span class="badge badge-danger"><?= $stats['revotes'] ?></span>
        </a>
        <a href="live_results.php" class="quick-link"><i class="fas fa-chart-bar"></i> Live Results</a>
        <a href="analytics.php" class="quick-link"><i class="fas fa-brain"></i> DSDBA Analytics</a>
        <a href="audit_logs.php" class="quick-link"><i class="fas fa-shield-alt"></i> Audit Logs</a>
        <a href="bulk_upload.php" class="quick-link"><i class="fas fa-file-csv"></i> Bulk Upload</a>
        <a href="backup.php" class="quick-link"><i class="fas fa-database"></i> Backup DB</a>
        <a href="notifications.php" class="quick-link"><i class="fas fa-bell"></i> Send Notification</a>
        <a href="complaints.php" class="quick-link <?= $stats['complaints']>0 ? 'quick-link--alert':'' ?>">
          <i class="fas fa-flag"></i> Complaints <span class="badge badge-warning"><?= $stats['complaints'] ?></span>
        </a>
      </div>
    </div>
  </div>

  <div class="dashboard-grid">
    <!-- Live Elections -->
    <div class="dashboard-card">
      <div class="card-header"><h3><i class="fas fa-circle text-green blink"></i> Live Elections</h3></div>
      <div class="card-body">
        <?php if (empty($activeElections)): ?>
          <div class="empty-state"><i class="fas fa-calendar-times"></i><p>No active elections.</p></div>
        <?php else: ?>
          <?php foreach ($activeElections as $el): ?>
          <?php $pct = $el['total_eligible']>0 ? round(($el['vote_count']/$el['total_eligible'])*100) : 0; ?>
          <div class="live-election">
            <div class="live-election__header">
              <strong><?= clean($el['title']) ?></strong>
              <span><?= $el['vote_count'] ?> / <?= $el['total_eligible'] ?> votes</span>
            </div>
            <div class="progress-bar-wrap">
              <div class="progress-bar-fill progress-bar-fill--animated" style="width:<?= $pct ?>%"></div>
            </div>
            <div class="live-election__footer">
              <small><?= $pct ?>% turnout &bull; Ends: <?= formatDateTime($el['end_time']) ?></small>
              <div>
                <a href="live_results.php?election_id=<?= $el['id'] ?>" class="btn btn-outline btn-xs">Results</a>
                <a href="elections.php?stop=<?= $el['id'] ?>" class="btn btn-danger btn-xs" onclick="return confirm('Stop this election?')">Stop</a>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Recent Audit Logs -->
    <div class="dashboard-card">
      <div class="card-header"><h3><i class="fas fa-shield-alt"></i> Recent Activity</h3></div>
      <div class="card-body">
        <div class="log-list">
          <?php foreach ($recentLogs as $log): ?>
          <div class="log-item">
            <span class="log-action"><?= clean($log['action']) ?></span>
            <span class="log-user"><?= clean($log['email'] ?? 'System') ?></span>
            <span class="log-time"><?= timeAgo($log['timestamp']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <a href="audit_logs.php" class="btn btn-outline btn-sm mt-1">View All Logs</a>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
