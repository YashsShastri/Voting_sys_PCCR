<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('admin');
$pageTitle = 'Manage Students - ' . SITE_NAME;

$db = getDB();

// Block/Unblock
if (isset($_GET['block'])) {
    $db->prepare("UPDATE users SET is_blocked=1 WHERE id=?")->execute([(int)$_GET['block']]);
    logAction('STUDENT_BLOCKED', "User ID " . (int)$_GET['block'] . " blocked.");
    flash('success', 'User blocked.'); header('Location: students.php'); exit;
}
if (isset($_GET['unblock'])) {
    $db->prepare("UPDATE users SET is_blocked=0 WHERE id=?")->execute([(int)$_GET['unblock']]);
    logAction('STUDENT_UNBLOCKED', "User ID " . (int)$_GET['unblock'] . " unblocked.");
    flash('success', 'User unblocked.'); header('Location: students.php'); exit;
}
// Verify/Unverify
if (isset($_GET['verify'])) {
    $db->prepare("UPDATE students SET verified=1 WHERE id=?")->execute([(int)$_GET['verify']]);
    // Notify student
    $sUser = $db->prepare("SELECT user_id FROM students WHERE id=?");
    $sUser->execute([(int)$_GET['verify']]);
    $sUser = $sUser->fetchColumn();
    if ($sUser) {
        sendNotification($sUser, 'Your account has been verified! You can now participate in elections.');
    }
    logAction('STUDENT_VERIFIED', "Student ID " . (int)$_GET['verify'] . " verified.");
    flash('success', 'Student verified.'); header('Location: students.php'); exit;
}

// Search
$search = clean($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'all'; // all | verified | unverified | blocked
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where = "WHERE 1=1";
$params = [];
if ($search) { $where .= " AND (s.name LIKE ? OR s.roll_no LIKE ? OR u.email LIKE ?)"; $params = array_fill(0, 3, "%$search%"); }
if ($filter === 'verified')   $where .= " AND s.verified=1";
if ($filter === 'unverified') $where .= " AND s.verified=0";
if ($filter === 'blocked')    $where .= " AND u.is_blocked=1";

$total = $db->prepare("SELECT COUNT(*) FROM students s JOIN users u ON s.user_id=u.id $where");
$total->execute($params);
$total = $total->fetchColumn();
$pg   = paginate($total, $page, $perPage);

$students = $db->prepare("SELECT s.*, u.email, u.is_blocked, u.created_at FROM students s JOIN users u ON s.user_id=u.id $where ORDER BY s.id DESC LIMIT {$perPage} OFFSET {$pg['offset']}");
$students->execute($params);
$students = $students->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container">
  <div class="page-header">
    <h1><i class="fas fa-users"></i> Manage Students</h1>
    <a href="bulk_upload.php" class="btn btn-outline"><i class="fas fa-file-csv"></i> Bulk Upload</a>
  </div>

  <!-- Filters -->
  <div class="filter-bar">
    <form method="GET" class="filter-form">
      <input type="text" name="q" class="form-control" placeholder="Search name, roll no, email..." value="<?= clean($search) ?>">
      <select name="filter" class="form-control">
        <option value="all" <?= $filter==='all'?'selected':'' ?>>All Students</option>
        <option value="verified" <?= $filter==='verified'?'selected':'' ?>>Verified</option>
        <option value="unverified" <?= $filter==='unverified'?'selected':'' ?>>Unverified</option>
        <option value="blocked" <?= $filter==='blocked'?'selected':'' ?>>Blocked</option>
      </select>
      <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
    </form>
    <span class="text-muted"><?= $total ?> students found</span>
  </div>

  <div class="card">
    <div class="card-body">
      <table class="data-table">
        <thead>
          <tr><th>Name</th><th>Roll No</th><th>Email</th><th>Dept</th><th>Yr/Div</th><th>Verified</th><th>Blocked</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php if (empty($students)): ?>
          <tr><td colspan="8" class="text-center text-muted">No students found.</td></tr>
        <?php endif; ?>
        <?php foreach ($students as $s): ?>
          <tr class="<?= $s['is_blocked'] ? 'row-blocked' : '' ?>">
            <td><strong><?= clean($s['name']) ?></strong></td>
            <td><?= clean($s['roll_no']) ?></td>
            <td><?= clean($s['email']) ?></td>
            <td><?= clean($s['department']) ?></td>
            <td><?= $s['year'] ?>/<?= clean($s['division']) ?></td>
            <td>
              <?php if ($s['verified']): ?>
                <span class="badge badge-success">✓</span>
              <?php else: ?>
                <span class="badge badge-warning">Pending</span>
              <?php endif; ?>
            </td>
            <td><?= $s['is_blocked'] ? '<span class="badge badge-danger">Blocked</span>' : '<span class="badge badge-muted">No</span>' ?></td>
            <td class="action-btns">
              <?php if (!$s['verified']): ?>
                <a href="students.php?verify=<?= $s['id'] ?>" class="btn btn-success btn-xs" title="Verify"><i class="fas fa-check"></i></a>
              <?php endif; ?>
              <?php if ($s['is_blocked']): ?>
                <a href="students.php?unblock=<?= $s['user_id'] ?>" class="btn btn-outline btn-xs" title="Unblock"><i class="fas fa-unlock"></i></a>
              <?php else: ?>
                <a href="students.php?block=<?= $s['user_id'] ?>" class="btn btn-danger btn-xs" onclick="return confirm('Block this user?')" title="Block"><i class="fas fa-ban"></i></a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <!-- Pagination -->
      <?php if ($pg['total_pages'] > 1): ?>
      <div class="pagination">
        <?php for ($i=1; $i<=$pg['total_pages']; $i++): ?>
          <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&filter=<?= $filter ?>" class="page-btn <?= $i===$pg['page'] ? 'page-btn--active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
