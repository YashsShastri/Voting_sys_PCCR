<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('admin');
$pageTitle = 'Database Backup - ' . SITE_NAME;

$db = getDB();

if (isset($_POST['backup'])) {
    verify_csrf();
    // Simple backup: dump all tables as SQL INSERTs
    $tables = ['users','students','elections','candidates','votes','revote_requests','notifications','logs','complaints','user_sessions'];
    $sql = "-- PCCOER Voting System Backup\n-- Generated: " . date('Y-m-d H:i:s') . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        $rows = $db->query("SELECT * FROM `$table`")->fetchAll();
        if (empty($rows)) continue;
        $cols  = implode('`,`', array_keys($rows[0]));
        $sql  .= "-- Table: $table\n";
        foreach ($rows as $row) {
            $vals = implode(',', array_map(fn($v) => $v === null ? 'NULL' : $db->quote((string)$v), $row));
            $sql .= "INSERT INTO `$table` (`$cols`) VALUES ($vals);\n";
        }
        $sql .= "\n";
    }
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

    logAction('DB_BACKUP', 'Manual database backup generated.');
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="voting_backup_' . date('Y-m-d_His') . '.sql"');
    echo $sql;
    exit;
}

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container">
  <div class="page-header"><h1><i class="fas fa-database"></i> Database Backup</h1></div>
  <div class="card">
    <div class="card-header"><h3>Manual Backup</h3></div>
    <div class="card-body">
      <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        This will export all data as an SQL file. Store it securely. This backup can be re-imported via phpMyAdmin.
      </div>
      <form method="POST">
        <?= csrf_field() ?>
        <button type="submit" name="backup" class="btn btn-primary btn-lg">
          <i class="fas fa-download"></i> Download Database Backup
        </button>
      </form>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
