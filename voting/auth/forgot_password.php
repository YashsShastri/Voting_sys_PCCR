<?php
require_once __DIR__ . '/../includes/session.php';
$pageTitle = 'Forgot Password - ' . SITE_NAME;

$step    = 'request'; // request | sent
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (!validatePccoerEmail($email)) {
        $errors[] = 'Please enter a valid @pccoer.in email.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate a secure reset token
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
            $upd = $db->prepare("UPDATE students SET reset_token = ?, reset_expires = ? WHERE user_id = ?");
            $upd->execute([$token, $expires, $user['id']]);
            logAction('PASSWORD_RESET_REQUEST', "Reset requested for: $email", $user['id']);

            // In a real system: send email with the reset link
            // For demo: we display the link directly
            $resetLink = BASE_URL . "auth/reset_password.php?token=$token";
            flash('info', 'A reset link has been generated. In production this is emailed. Demo link: <a href="' . $resetLink . '">' . $resetLink . '</a>');
        } else {
            // Don't reveal if email exists (security)
            flash('info', 'If this email exists, a reset link has been sent.');
        }
        header('Location: forgot_password.php?sent=1');
        exit;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="auth-container">
  <div class="auth-card">
    <div class="auth-header">
      <div class="auth-logo"><i class="fas fa-key"></i></div>
      <h1 class="auth-title">Forgot Password</h1>
      <p class="auth-subtitle">Enter your PCCOER email to reset your password</p>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-error"><span>✗</span><?= implode('<br>', array_map('clean', $errors)) ?></div>
    <?php endif; ?>

    <form method="POST" class="auth-form">
      <?= csrf_field() ?>
      <div class="form-group">
        <label><i class="fas fa-envelope"></i> Email Address</label>
        <input type="email" name="email" class="form-control" placeholder="name@pccoer.in" required autofocus>
      </div>
      <button type="submit" class="btn btn-primary btn-full">
        <i class="fas fa-paper-plane"></i> Send Reset Link
      </button>
    </form>
    <div class="auth-footer"><a href="login.php">Back to Login</a></div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
