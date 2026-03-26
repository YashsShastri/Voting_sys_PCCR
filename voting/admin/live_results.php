<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('admin');
$pageTitle = 'Live Results - ' . SITE_NAME;

$db  = getDB();
$eid = (int)($_GET['election_id'] ?? 0);

$elections = $db->query("SELECT * FROM elections ORDER BY start_time DESC")->fetchAll();
$selectedElection = null;
$candidates = [];
$totalVotes = 0;
$totalEligible = $db->query("SELECT COUNT(*) FROM students WHERE verified=1")->fetchColumn();

if ($eid) {
    $sel = $db->prepare("SELECT * FROM elections WHERE id=?");
    $sel->execute([$eid]);
    $selectedElection = $sel->fetch();

    $cands = $db->prepare("SELECT c.*, COUNT(v.id) as votes FROM candidates c LEFT JOIN votes v ON v.candidate_id=c.id AND v.election_id=? AND v.is_valid=1 WHERE c.election_id=? GROUP BY c.id ORDER BY votes DESC");
    $cands->execute([$eid, $eid]);
    $candidates = $cands->fetchAll();
    $totalVotes = array_sum(array_column($candidates, 'votes'));
}

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container">
  <div class="page-header">
    <h1><i class="fas fa-chart-bar"></i> Live Results</h1>
  </div>

  <form method="GET" class="filter-form mb-3">
    <select name="election_id" class="form-control" onchange="this.form.submit()">
      <option value="">-- Select Election --</option>
      <?php foreach ($elections as $e): ?>
        <option value="<?= $e['id'] ?>" <?= $e['id']==$eid ? 'selected':'' ?>><?= clean($e['title']) ?> [<?= ucfirst($e['status']) ?>]</option>
      <?php endforeach; ?>
    </select>
  </form>

  <?php if ($selectedElection): ?>
  <div class="page-header">
    <h2><?= clean($selectedElection['title']) ?></h2>
    <span class="status-badge status-<?= $selectedElection['status'] ?>"><?= ucfirst($selectedElection['status']) ?></span>
    <p>Total Votes: <strong><?= $totalVotes ?></strong> / <?= $totalEligible ?> eligible
       (<?= $totalEligible > 0 ? round(($totalVotes/$totalEligible)*100) : 0 ?>% turnout)</p>
  </div>

  <!-- Results Bars -->
  <div class="card mb-3">
    <div class="card-header"><h3>Candidate Standings</h3>
      <a href="../admin/export.php?election_id=<?= $eid ?>" class="btn btn-outline btn-sm"><i class="fas fa-download"></i> Export</a>
    </div>
    <div class="card-body">
      <?php foreach ($candidates as $idx => $c): ?>
        <?php $pct = $totalVotes > 0 ? round(($c['votes']/$totalVotes)*100) : 0; ?>
        <div class="result-row">
          <div class="result-rank"><?= $idx+1 ?></div>
          <div class="result-name">
            <?= clean($c['name']) ?>
            <?php if ($idx===0 && $totalVotes > 0): ?><span class="winner-badge"><i class="fas fa-crown"></i></span><?php endif; ?>
          </div>
          <div class="result-bar-wrap">
            <div class="result-bar" style="width:<?= $pct ?>%"></div>
          </div>
          <div class="result-stats"><?= $c['votes'] ?> votes (<?= $pct ?>%)</div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Charts -->
  <div class="dashboard-grid">
    <div class="card">
      <div class="card-header"><h3>Vote Distribution (Pie)</h3></div>
      <div class="card-body"><canvas id="pieChart" height="200"></canvas></div>
    </div>
    <div class="card">
      <div class="card-header"><h3>Vote Count (Bar)</h3></div>
      <div class="card-body"><canvas id="barChart" height="200"></canvas></div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
  const labels = [<?= implode(',', array_map(fn($c) => '"' . addslashes($c['name']) . '"', $candidates)) ?>];
  const data   = [<?= implode(',', array_column($candidates, 'votes')) ?>];
  const colors = ['#6366f1','#8b5cf6','#06b6d4','#10b981','#f59e0b','#ef4444'];

  new Chart(document.getElementById('pieChart').getContext('2d'), {
    type: 'doughnut',
    data: { labels, datasets: [{ data, backgroundColor: colors, hoverOffset: 10 }] },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
  });
  new Chart(document.getElementById('barChart').getContext('2d'), {
    type: 'bar',
    data: { labels, datasets: [{ label: 'Votes', data, backgroundColor: colors, borderRadius: 8 }] },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
  });

  <?php if ($selectedElection['status'] === 'active'): ?>
  // Auto-refresh every 30 seconds for live elections
  setTimeout(() => location.reload(), 30000);
  <?php endif; ?>
  </script>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
