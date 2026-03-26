<?php
require_once __DIR__ . '/../includes/session.php';
$pageTitle = 'Register - ' . SITE_NAME;

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'student/dashboard.php');
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name     = clean($_POST['name'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $roll_no  = strtoupper(trim($_POST['roll_no'] ?? ''));
    $division = clean($_POST['division'] ?? '');
    $year     = (int)($_POST['year'] ?? 1);
    $dept     = clean($_POST['department'] ?? '');
    $phone    = clean($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($name))    $errors[] = 'Full name is required.';
    if (!validatePccoerEmail($email)) $errors[] = 'Email must be a valid @pccoer.in address.';
    if (empty($roll_no)) $errors[] = 'Roll number is required.';
    if (empty($division)) $errors[] = 'Division is required.';
    if ($year < 1 || $year > 4) $errors[] = 'Invalid year selected.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';
    if (!preg_match('/[A-Z]/', $password)) $errors[] = 'Password must contain at least one uppercase letter.';
    if (!preg_match('/[0-9]/', $password)) $errors[] = 'Password must contain at least one digit.';

    if (empty($errors)) {
        $db = getDB();
        // Check uniqueness
        $chk = $db->prepare("SELECT id FROM users WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) $errors[] = 'This email is already registered.';

        $chkR = $db->prepare("SELECT id FROM students WHERE roll_no = ?");
        $chkR->execute([$roll_no]);
        if ($chkR->fetch()) $errors[] = 'This roll number is already registered.';
    }

    if (empty($errors)) {
        $db = getDB();
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        try {
            $db->beginTransaction();

            // Insert user
            $uStmt = $db->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'student')");
            $uStmt->execute([$email, $hash]);
            $userId = $db->lastInsertId();

            // Insert student profile
            $sStmt = $db->prepare("INSERT INTO students (user_id, name, roll_no, division, year, department, phone, verified) VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
            $sStmt->execute([$userId, $name, $roll_no, $division, $year, $dept, $phone]);

            $db->commit();
            logAction('REGISTER', "New student registered: $email | Roll: $roll_no", $userId);
            header('Location: ' . BASE_URL . 'auth/login.php?registered=1');
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="auth-container auth-container--wide">
  <div class="auth-card">
    <div class="auth-header">
      <div class="auth-logo"><i class="fas fa-user-plus"></i></div>
      <h1 class="auth-title">Create Account</h1>
      <p class="auth-subtitle">Register with your official PCCOER email</p>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-error">
        <strong>✗ Please fix the following errors:</strong>
        <ul><?php foreach ($errors as $e): ?><li><?= clean($e) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <form method="POST" class="auth-form" novalidate>
      <?= csrf_field() ?>
      <div class="form-grid-2">
        <div class="form-group">
          <label><i class="fas fa-user"></i> Full Name</label>
          <input type="text" name="name" class="form-control" placeholder="Your Full Name"
                 value="<?= clean($_POST['name'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label><i class="fas fa-id-badge"></i> Roll Number</label>
          <input type="text" name="roll_no" class="form-control" placeholder="e.g. PCCOER2021001"
                 value="<?= clean($_POST['roll_no'] ?? '') ?>" required>
        </div>
        <div class="form-group form-group--full">
          <label><i class="fas fa-envelope"></i> PCCOER Email</label>
          <input type="email" name="email" class="form-control" placeholder="name@pccoer.in"
                 value="<?= clean($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label><i class="fas fa-layer-group"></i> Division</label>
          <select name="division" class="form-control" required>
            <option value="">-- Select --</option>
            <?php foreach (['A','B','C','D','E','F'] as $d): ?>
            <option value="<?= $d ?>" <?= ($_POST['division'] ?? '') === $d ? 'selected' : '' ?>><?= $d ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label><i class="fas fa-graduation-cap"></i> Year</label>
          <select name="year" class="form-control" required>
            <?php for ($y = 1; $y <= 4; $y++): ?>
            <option value="<?= $y ?>" <?= (($_POST['year'] ?? 1) == $y) ? 'selected' : '' ?>>Year <?= $y ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="form-group">
          <label><i class="fas fa-building"></i> Department</label>
          <select name="department" class="form-control" required>
            <?php foreach (['Computer Engineering','Information Technology','Mechanical Engineering','Civil Engineering','Electronics & Telecom','AI & DS'] as $dept): ?>
            <option value="<?= $dept ?>" <?= (($_POST['department'] ?? '') === $dept) ? 'selected' : '' ?>><?= $dept ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label><i class="fas fa-phone"></i> Phone (optional)</label>
          <input type="tel" name="phone" class="form-control" placeholder="10-digit mobile"
                 value="<?= clean($_POST['phone'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label><i class="fas fa-lock"></i> Password</label>
          <div class="input-with-toggle">
            <input type="password" name="password" id="reg-password" class="form-control" placeholder="Min 8 chars, 1 uppercase, 1 digit" required>
            <button type="button" class="toggle-password" onclick="togglePassword('reg-password')">
              <i class="fas fa-eye" id="eye-reg-password"></i>
            </button>
          </div>
        </div>
        <div class="form-group">
          <label><i class="fas fa-lock"></i> Confirm Password</label>
          <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter password" required>
        </div>
      </div>

      <div class="password-strength" id="password-strength"></div>

      <div class="form-notice">
        <i class="fas fa-info-circle"></i>
        After registration, an admin must verify your account before you can vote.
      </div>

      <button type="submit" class="btn btn-primary btn-full btn-lg">
        <i class="fas fa-user-plus"></i> Create Account
      </button>
    </form>

    <div class="auth-footer">
      Already have an account? <a href="login.php">Sign in</a>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
