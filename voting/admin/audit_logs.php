<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('admin');
$pageTitle = 'Audit Logs - ' . SITE_NAME;

$db = getDB();
$search = clean($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;

$where = "WHERE 1=1";
$params = [];
if ($search) { $where .= " AND (l.action LIKE ? OR u.email LIKE ? OR l.details LIKE ?)"; $params = array_fill(0, 3, "%$search%"); }

$total = $db->prepare("SELECT COUNT(*) FROM logs l LEFT JOIN users u ON l.user_id=u.id $where");
$total->execute($params);
$total = $total->fetchColumn();
$pg = paginate($total, $page, $perPage);

$logs = $db->prepare("SELECT l.*, u.email FROM logs l LEFT JOIN users u ON l.user_id=u.id $where ORDER BY l.timestamp DESC LIMIT {$perPage} OFFSET {$pg['offset']}");
$logs->execute($params);
$logs = $logs->fetchAll();

// Action color map
$actionColors = [
    'LOGIN' => 'green', 'LOGOUT' => 'blue', 'REGISTER' => 'purple',
    'VOTE_CAST' => 'teal', 'REVOTE_APPROVED' => 'orange',
    'LOGIN_FAIL' => 'red', 'LOGIN_BLOCKED' => 'red',
    'STUDENT_BLOCKED' => 'red', 'ELECTION_DELETED' => 'red',
];

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container">
  <div class="page-header">
    <h1><i class="fas fa-shield-alt"></i> Audit Logs</h1>
    <p>All <?= $total ?> system events recorded</p>
  </div>

  <div class="filter-bar">
    <form method="GET" class="filter-form">
      <input type="text" name="q" class="form-control" placeholder="Search action or user..." value="<?= clean($search) ?>">
      <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
    </form>
    <a href="export.php?type=logs" class="btn btn-outline"><i class="fas fa-download"></i> Export CSV</a>
  </div>

  <div class="card">
    <div class="card-body">
      <table class="data-table data-table--compact">
        <thead><tr><th>#</th><th>Action</th><th>User</th><th>Details</th><th>IP</th><th>Time</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
          <?php $color = $actionColors[$log['action']] ?? 'muted'; ?>
          <tr>
            <td class="text-muted"><?= $log['id'] ?></td>
            <td><span class="badge badge-<?= $color ?>"><?= clean($log['action']) ?></span></td>
            <td><?= clean($log['email'] ?? 'System') ?></td>
            <td class="log-details"><?= clean($log['details'] ?? '—') ?></td>
            <td class="text-muted"><?= clean($log['ip_address'] ?? '—') ?></td>
            <td class="text-muted"><?= formatDateTime($log['timestamp']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <!-- Pagination -->
      <?php if ($pg['total_pages'] > 1): ?>
      <div class="pagination">
        <?php for ($i=1; $i<=$pg['total_pages']; $i++): ?>
          <a href="?page=<?=$i?>&q=<?= urlencode($search) ?>" class="page-btn <?= $i===$pg['page']?'page-btn--active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
