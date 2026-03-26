<?php
/**
 * DSDBA Analytics Dashboard - PCCOER Voting System
 * Data Science & Database Analytics Module
 */
require_once __DIR__ . '/../includes/session.php';
requireRole('admin');
$pageTitle = 'DSDBA Analytics - ' . SITE_NAME;

$db = getDB();

// ─── DATA PREPROCESSING & AGGREGATION ────────────────────────────────────────

// 1. Overall turnout
$totalStudents  = (int)$db->query("SELECT COUNT(*) FROM students WHERE verified=1")->fetchColumn();
$totalVotes     = (int)$db->query("SELECT COUNT(*) FROM votes WHERE is_valid=1")->fetchColumn();
$globalTurnout  = $totalStudents > 0 ? round(($totalVotes/$totalStudents)*100, 1) : 0;

// 2. Elections with stats
$elections = $db->query("
    SELECT e.*,
        COUNT(DISTINCT v.id) as votes,
        COUNT(DISTINCT c.id) as candidates
    FROM elections e
    LEFT JOIN votes v ON v.election_id=e.id AND v.is_valid=1
    LEFT JOIN candidates c ON c.election_id=e.id
    GROUP BY e.id
    ORDER BY e.start_time ASC
")->fetchAll();

// 3. Division-wise participation (aggregation)
$divStats = $db->query("
    SELECT s.division,
           COUNT(DISTINCT s.id) as total,
           COUNT(DISTINCT v.id) as voted
    FROM students s
    LEFT JOIN votes v ON v.student_id=s.id AND v.is_valid=1
    WHERE s.verified=1
    GROUP BY s.division
    ORDER BY s.division
")->fetchAll();

// 4. Time-based voting trends (hourly for last 30 days)
$hourlyTrend = $db->query("
    SELECT HOUR(timestamp) as hour, COUNT(*) as votes
    FROM votes WHERE is_valid=1
    AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY HOUR(timestamp)
    ORDER BY hour
")->fetchAll();

// Fill missing hours with 0
$hourlyData = array_fill(0, 24, 0);
foreach ($hourlyTrend as $row) { $hourlyData[$row['hour']] = (int)$row['votes']; }

// 5. Peak hour detection
$peakHour = array_search(max($hourlyData), $hourlyData);
$peakCount = max($hourlyData);

// 6. Candidate-wise distribution (latest election)
$latestElection = $db->query("SELECT id, title FROM elections WHERE status='completed' ORDER BY end_time DESC LIMIT 1")->fetch();
$candidateDist  = [];
if ($latestElection) {
    $candidateDist = $db->prepare("
        SELECT c.name, COUNT(v.id) as votes
        FROM candidates c
        LEFT JOIN votes v ON v.candidate_id=c.id AND v.election_id=? AND v.is_valid=1
        WHERE c.election_id=?
        GROUP BY c.id ORDER BY votes DESC
    ");
    $candidateDist->execute([$latestElection['id'], $latestElection['id']]);
    $candidateDist = $candidateDist->fetchAll();
}

// 7. Anomaly detection: multiple votes from same IP in short window
$suspicious = $db->query("
    SELECT ip_address, COUNT(*) as cnt, 
           MIN(timestamp) as first_vote, 
           MAX(timestamp) as last_vote,
           GROUP_CONCAT(DISTINCT election_id) as elections
    FROM votes WHERE is_valid=1
    GROUP BY ip_address
    HAVING cnt > 2
    ORDER BY cnt DESC
    LIMIT 20
")->fetchAll();

// 8. Historical comparison (turnout per election)
$historicalTurnout = [];
foreach ($elections as $el) {
    $eligible = $totalStudents; // simplification; could be filtered by department
    $pct = $eligible > 0 ? round(($el['votes']/$eligible)*100, 1) : 0;
    $historicalTurnout[] = ['election' => $el['title'], 'pct' => $pct, 'votes' => $el['votes']];
}

// 9. Simple linear regression for turnout prediction (if ≥2 elections)
$predictedTurnout = null;
if (count($historicalTurnout) >= 2) {
    $n = count($historicalTurnout);
    $x = range(1, $n);
    $y = array_column($historicalTurnout, 'pct');
    $xMean = array_sum($x) / $n;
    $yMean = array_sum($y) / $n;
    $num = 0; $den = 0;
    for ($i = 0; $i < $n; $i++) {
        $num += ($x[$i] - $xMean) * ($y[$i] - $yMean);
        $den += ($x[$i] - $xMean) ** 2;
    }
    $slope = $den != 0 ? $num / $den : 0;
    $intercept = $yMean - $slope * $xMean;
    $predictedTurnout = round($intercept + $slope * ($n + 1), 1);
    $predictedTurnout = max(0, min(100, $predictedTurnout));
}

// 10. Participation clustering (simple: low/medium/high based on vote count)
$clusters = $db->query("
    SELECT
        CASE
            WHEN voted_count = 0 THEN 'Non-Voters'
            WHEN voted_count = 1 THEN 'Low Participation'
            WHEN voted_count BETWEEN 2 AND 3 THEN 'Medium Participation'
            ELSE 'High Participation'
        END as cluster,
        COUNT(*) as student_count
    FROM (
        SELECT s.id, COUNT(v.id) as voted_count
        FROM students s
        LEFT JOIN votes v ON v.student_id=s.id AND v.is_valid=1
        WHERE s.verified=1
        GROUP BY s.id
    ) t
    GROUP BY cluster
    ORDER BY student_count DESC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container">
  <div class="page-header">
    <h1><i class="fas fa-brain"></i> DSDBA Analytics Dashboard</h1>
    <p>Data Science & Database Analytics — Voting System Insights</p>
  </div>

  <!-- KPI Cards -->
  <div class="stats-grid stats-grid--5">
    <div class="stat-card stat-card--blue">
      <div class="stat-icon"><i class="fas fa-users"></i></div>
      <div class="stat-info"><div class="stat-value"><?= $totalStudents ?></div><div class="stat-label">Eligible Voters</div></div>
    </div>
    <div class="stat-card stat-card--green">
      <div class="stat-icon"><i class="fas fa-vote-yea"></i></div>
      <div class="stat-info"><div class="stat-value"><?= $totalVotes ?></div><div class="stat-label">Total Votes</div></div>
    </div>
    <div class="stat-card stat-card--purple">
      <div class="stat-icon"><i class="fas fa-chart-pie"></i></div>
      <div class="stat-info"><div class="stat-value"><?= $globalTurnout ?>%</div><div class="stat-label">Global Turnout</div></div>
    </div>
    <div class="stat-card stat-card--teal">
      <div class="stat-icon"><i class="fas fa-clock"></i></div>
      <div class="stat-info"><div class="stat-value"><?= $peakHour ?>:00</div><div class="stat-label">Peak Voting Hour</div></div>
    </div>
    <?php if ($predictedTurnout !== null): ?>
    <div class="stat-card stat-card--orange">
      <div class="stat-icon"><i class="fas fa-robot"></i></div>
      <div class="stat-info"><div class="stat-value"><?= $predictedTurnout ?>%</div><div class="stat-label">Predicted Next Turnout</div></div>
    </div>
    <?php endif; ?>
  </div>

  <div class="dashboard-grid dashboard-grid--3">
    <!-- Candidate-wise Distribution -->
    <?php if ($candidateDist): ?>
    <div class="card">
      <div class="card-header"><h3><i class="fas fa-chart-pie"></i> Candidate Distribution<br><small><?= clean($latestElection['title']) ?></small></h3></div>
      <div class="card-body"><canvas id="candPieChart" height="220"></canvas></div>
    </div>
    <?php endif; ?>

    <!-- Division-wise Participation -->
    <div class="card">
      <div class="card-header"><h3><i class="fas fa-layer-group"></i> Division-wise Participation</h3></div>
      <div class="card-body"><canvas id="divChart" height="220"></canvas></div>
    </div>

    <!-- Participation Clustering -->
    <div class="card">
      <div class="card-header"><h3><i class="fas fa-sitemap"></i> Voter Clustering</h3></div>
      <div class="card-body">
        <canvas id="clusterChart" height="220"></canvas>
        <div class="cluster-legend">
          <?php foreach ($clusters as $cl): ?>
            <div><?= clean($cl['cluster']) ?>: <strong><?= $cl['student_count'] ?></strong></div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Historical Turnout -->
    <div class="card card--full">
      <div class="card-header"><h3><i class="fas fa-chart-line"></i> Historical Turnout Comparison</h3></div>
      <div class="card-body"><canvas id="historicalChart" height="80"></canvas></div>
    </div>

    <!-- Hourly Voting Trend -->
    <div class="card card--full">
      <div class="card-header"><h3><i class="fas fa-clock"></i> Hourly Voting Trends (Last 30 Days)</h3></div>
      <div class="card-body">
        <canvas id="hourlyChart" height="80"></canvas>
        <p class="text-center mt-1"><i class="fas fa-fire"></i> Peak hour: <strong><?= $peakHour ?>:00 – <?= $peakHour + 1 ?>:00</strong> with <?= $peakCount ?> votes</p>
      </div>
    </div>

    <!-- Anomaly Detection -->
    <div class="card card--full">
      <div class="card-header">
        <h3><i class="fas fa-exclamation-triangle text-orange"></i> Anomaly Detection — Suspicious IPs</h3>
        <small class="text-muted">IPs that cast more than 2 votes (possible shared device / suspicious activity)</small>
      </div>
      <div class="card-body">
        <?php if (empty($suspicious)): ?>
          <div class="empty-state"><i class="fas fa-check-shield"></i><p>No suspicious patterns detected.</p></div>
        <?php else: ?>
        <table class="data-table">
          <thead><tr><th>IP Address</th><th>Total Votes</th><th>Elections</th><th>First Vote</th><th>Last Vote</th></tr></thead>
          <tbody>
          <?php foreach ($suspicious as $s): ?>
            <tr>
              <td><strong class="text-orange"><?= clean($s['ip_address']) ?></strong></td>
              <td><span class="badge badge-danger"><?= $s['cnt'] ?></span></td>
              <td>IDs: <?= clean($s['elections']) ?></td>
              <td><?= formatDateTime($s['first_vote']) ?></td>
              <td><?= formatDateTime($s['last_vote']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($predictedTurnout !== null): ?>
    <!-- Prediction -->
    <div class="card card--full">
      <div class="card-header"><h3><i class="fas fa-robot"></i> Turnout Prediction (Linear Regression)</h3></div>
      <div class="card-body">
        <p>Based on historical election data, the predicted turnout for the <strong>next election</strong> is:</p>
        <div class="prediction-display">
          <span class="prediction-value"><?= $predictedTurnout ?>%</span>
          <span class="prediction-label">Estimated Voter Turnout</span>
        </div>
        <div class="progress-bar-wrap">
          <div class="progress-bar-fill progress-bar-fill--predict" style="width:<?= $predictedTurnout ?>%"></div>
        </div>
        <small class="text-muted">* Simple linear regression on historical turnout percentages. Accuracy improves with more elections.</small>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const chartColors = ['#6366f1','#8b5cf6','#06b6d4','#10b981','#f59e0b','#ef4444','#ec4899'];

<?php if ($candidateDist): ?>
new Chart(document.getElementById('candPieChart'), {
  type:'doughnut',
  data:{
    labels:[<?= implode(',', array_map(fn($c)=>'"'.addslashes($c['name']).'"', $candidateDist)) ?>],
    datasets:[{ data:[<?= implode(',', array_column($candidateDist, 'votes')) ?>], backgroundColor:chartColors }]
  },
  options:{responsive:true,plugins:{legend:{position:'bottom'}}}
});
<?php endif; ?>

new Chart(document.getElementById('divChart'), {
  type:'bar',
  data:{
    labels:[<?= implode(',', array_map(fn($d)=>'"'.$d['division'].'"', $divStats)) ?>],
    datasets:[
      {label:'Total',data:[<?= implode(',', array_column($divStats,'total')) ?>],backgroundColor:'#e0e7ff',borderRadius:6},
      {label:'Voted',data:[<?= implode(',', array_column($divStats,'voted')) ?>],backgroundColor:'#6366f1',borderRadius:6}
    ]
  },
  options:{responsive:true,scales:{y:{beginAtZero:true}}}
});

new Chart(document.getElementById('clusterChart'), {
  type:'pie',
  data:{
    labels:[<?= implode(',', array_map(fn($c)=>'"'.addslashes($c['cluster']).'"', $clusters)) ?>],
    datasets:[{
      data:[<?= implode(',', array_column($clusters,'student_count')) ?>],
      backgroundColor:chartColors
    }]
  },
  options:{responsive:true,plugins:{legend:{position:'bottom'}}}
});

new Chart(document.getElementById('historicalChart'), {
  type:'line',
  data:{
    labels:[<?= implode(',', array_map(fn($h)=>'"'.addslashes($h['election']).'"', $historicalTurnout)) ?>],
    datasets:[{
      label:'Turnout %',
      data:[<?= implode(',', array_column($historicalTurnout,'pct')) ?>],
      backgroundColor:'rgba(99,102,241,0.15)',
      borderColor:'#6366f1',
      fill:true,tension:0.4,pointRadius:5
    }]
  },
  options:{responsive:true,scales:{y:{beginAtZero:true,max:100,ticks:{callback:v=>v+'%'}}}}
});

new Chart(document.getElementById('hourlyChart'), {
  type:'bar',
  data:{
    labels:['12am','1am','2am','3am','4am','5am','6am','7am','8am','9am','10am','11am','12pm','1pm','2pm','3pm','4pm','5pm','6pm','7pm','8pm','9pm','10pm','11pm'],
    datasets:[{
      label:'Votes',
      data:[<?= implode(',', $hourlyData) ?>],
      backgroundColor: [<?= implode(',', array_map(fn($h) => $h===array_search(max($hourlyData),array_values($hourlyData)) ? '"#ef4444"' : '"#6366f1"', $hourlyData)) ?>],
      borderRadius:6
    }]
  },
  options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
