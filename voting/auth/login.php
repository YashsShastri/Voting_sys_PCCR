<?php
require_once __DIR__ . '/../includes/session.php';
$pageTitle = 'Login - ' . SITE_NAME;

// Redirect already-logged-in users
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'student/dashboard.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id, email, password, role, is_blocked FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['is_blocked']) {
                $error = 'Your account has been blocked. Contact admin.';
                logAction('LOGIN_BLOCKED', "Blocked user attempted login: $email");
            } else {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email']   = $user['email'];
                $_SESSION['role']    = $user['role'];
                $_SESSION['last_active'] = time();

                // If student, load student id
                if ($user['role'] === 'student') {
                    $s = $db->prepare("SELECT id, name, verified FROM students WHERE user_id = ?");
                    $s->execute([$user['id']]);
                    $student = $s->fetch();
                    if ($student) {
                        $_SESSION['student_id'] = $student['id'];
                        $_SESSION['student_name'] = $student['name'];
                        $_SESSION['verified'] = $student['verified'];
                    }
                }

                logAction('LOGIN', "User logged in: $email");
                flash('success', 'Welcome back!');
                header('Location: ' . BASE_URL . ($user['role'] === 'admin' ? 'admin/dashboard.php' : 'student/dashboard.php'));
                exit;
            }
        } else {
            $error = 'Invalid email or password.';
            logAction('LOGIN_FAIL', "Failed login attempt for: $email", null);
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="auth-container">
  <div class="auth-card">
    <div class="auth-header">
      <div class="auth-logo"><i class="fas fa-vote-yea"></i></div>
      <h1 class="auth-title">Welcome Back</h1>
      <p class="auth-subtitle">Sign in to your PCCOER Voting account</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error"><span class="alert-icon">✗</span><?= clean($error) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['timeout'])): ?>
      <div class="alert alert-warning"><span class="alert-icon">⚠</span>Session expired. Please log in again.</div>
    <?php endif; ?>
    <?php if (isset($_GET['registered'])): ?>
      <div class="alert alert-success"><span class="alert-icon">✓</span>Registration successful! Please wait for admin verification before logging in.</div>
    <?php endif; ?>

    <form method="POST" class="auth-form" novalidate>
      <?= csrf_field() ?>
      <div class="form-group">
        <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
        <input type="email" id="email" name="email" class="form-control"
               placeholder="your.name@pccoer.in" value="<?= clean($_POST['email'] ?? '') ?>" required autofocus>
      </div>
      <div class="form-group">
        <label for="password"><i class="fas fa-lock"></i> Password</label>
        <div class="input-with-toggle">
          <input type="password" id="password" name="password" class="form-control"
                 placeholder="Enter your password" required>
          <button type="button" class="toggle-password" onclick="togglePassword('password')">
            <i class="fas fa-eye" id="eye-password"></i>
          </button>
        </div>
      </div>
      <div class="form-row-between">
        <label class="checkbox-label">
          <input type="checkbox" name="remember"> Remember me
        </label>
        <a href="forgot_password.php" class="link-muted">Forgot password?</a>
      </div>
      <button type="submit" class="btn btn-primary btn-full btn-lg">
        <i class="fas fa-sign-in-alt"></i> Sign In
      </button>
    </form>

    <div class="auth-footer">
      Don't have an account? <a href="register.php">Register here</a>
    </div>

    <!-- Demo credentials hint for development -->
    <div class="demo-creds">
      <small><strong>Admin Demo:</strong> admin@pccoer.in / Admin@1234</small>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
