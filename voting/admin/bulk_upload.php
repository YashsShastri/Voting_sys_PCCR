<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('admin');
$pageTitle = 'Bulk Upload Students - ' . SITE_NAME;

$db = getDB();
$results = [];
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'No valid CSV file uploaded.';
    } else {
        $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') { $errors[] = 'Only CSV files are accepted.'; }
        else {
            $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
            $header = fgetcsv($handle); // Skip header row
            $row = 0;
            while (($data = fgetcsv($handle)) !== false) {
                $row++;
                if (count($data) < 6) { $errors[] = "Row $row: Insufficient columns."; continue; }
                [$name, $email, $roll_no, $division, $year, $dept] = array_map('trim', $data);
                $email = strtolower($email);

                if (!validatePccoerEmail($email)) { $errors[] = "Row $row: Invalid email $email."; continue; }

                // Check for duplicates
                $ex = $db->prepare("SELECT id FROM users WHERE email=?");
                $ex->execute([$email]);
                if ($ex->fetch()) { $errors[] = "Row $row: Email $email already exists. Skipped."; continue; }

                $exR = $db->prepare("SELECT id FROM students WHERE roll_no=?");
                $exR->execute([$roll_no]);
                if ($exR->fetch()) { $errors[] = "Row $row: Roll No $roll_no already exists. Skipped."; continue; }

                try {
                    $db->beginTransaction();
                    $pass = password_hash('Student@1234', PASSWORD_BCRYPT, ['cost'=>12]); // default password
                    $db->prepare("INSERT INTO users (email,password,role) VALUES (?,?,'student')")->execute([$email, $pass]);
                    $uid = $db->lastInsertId();
                    $db->prepare("INSERT INTO students (user_id,name,roll_no,division,year,department,verified) VALUES (?,?,?,?,?,?,1)")->execute([$uid,$name,$roll_no,$division,(int)$year,$dept]);
                    $db->commit();
                    $results[] = "Row $row: ✅ $name ($email) imported successfully. Default password: Student@1234";
                } catch (Exception $e) {
                    $db->rollBack(); $errors[] = "Row $row: DB error — " . $e->getMessage();
                }
            }
            fclose($handle);
            logAction('BULK_UPLOAD', count($results) . " students imported.");
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container">
  <div class="page-header">
    <h1><i class="fas fa-file-csv"></i> Bulk Upload Students</h1>
  </div>

  <div class="card mb-3">
    <div class="card-header"><h3>CSV Format</h3></div>
    <div class="card-body">
      <p>Upload a CSV file with the following columns (no header required, but recommended):</p>
      <table class="data-table data-table--compact">
        <thead><tr><th>Col 1: Name</th><th>Col 2: Email</th><th>Col 3: Roll No</th><th>Col 4: Division</th><th>Col 5: Year</th><th>Col 6: Department</th></tr></thead>
        <tbody><tr><td>Rahul Sharma</td><td>rahul.sharma@pccoer.in</td><td>PCCOER2021001</td><td>A</td><td>3</td><td>Computer Engineering</td></tr></tbody>
      </table>
      <p class="text-muted mt-1"><i class="fas fa-info-circle"></i> Default password for imported students: <strong>Student@1234</strong> (advise them to change it on first login)</p>
      <a href="sample_upload.csv" class="btn btn-outline btn-sm"><i class="fas fa-download"></i> Download Sample CSV</a>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h3>Upload File</h3></div>
    <div class="card-body">
      <form method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="form-group">
          <label>Select CSV File</label>
          <input type="file" name="csv_file" class="form-control" accept=".csv" required>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Upload &amp; Import</button>
      </form>
    </div>
  </div>

  <?php if ($results || $errors): ?>
  <div class="card mt-3">
    <div class="card-header"><h3>Import Results</h3></div>
    <div class="card-body">
      <?php foreach ($results as $r): ?>
        <div class="alert alert-success"><?= clean($r) ?></div>
      <?php endforeach; ?>
      <?php foreach ($errors as $e): ?>
        <div class="alert alert-error"><?= clean($e) ?></div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
