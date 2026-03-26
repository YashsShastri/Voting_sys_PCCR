<?php
require_once __DIR__ . '/../includes/session.php';
$pageTitle = 'Reset Password - ' . SITE_NAME;

$token  = trim($_GET['token'] ?? '');
$errors = [];
$valid  = false;
$student = null;

if (empty($token)) {
    flash('error', 'Invalid reset link.');
    header('Location: forgot_password.php');
    exit;
}

$db   = getDB();
$stmt = $db->prepare("SELECT s.id, s.user_id, s.reset_token, s.reset_expires FROM students s WHERE s.reset_token = ?");
$stmt->execute([$token]);
$student = $stmt->fetch();

if ($student && strtotime($student['reset_expires']) > time()) {
    $valid = true;
} else {
    $errors[] = 'This reset link is invalid or has expired.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    verify_csrf();
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8)       $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)       $errors[] = 'Passwords do not match.';
    if (!preg_match('/[A-Z]/', $password)) $errors[] = 'Password must contain at least one uppercase letter.';
    if (!preg_match('/[0-9]/', $password)) $errors[] = 'Password must contain at least one digit.';

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $student['user_id']]);
        $db->prepare("UPDATE students SET reset_token = NULL, reset_expires = NULL WHERE id = ?")->execute([$student['id']]);
        logAction('PASSWORD_RESET', 'Password reset completed.', $student['user_id']);
        flash('success', 'Password reset successfully! You can now log in.');
        header('Location: login.php');
        exit;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="auth-container">
  <div class="auth-card">
    <div class="auth-header">
      <div class="auth-logo"><i class="fas fa-unlock-alt"></i></div>
      <h1 class="auth-title">Reset Password</h1>
    </div>
    <?php if ($errors): ?>
      <div class="alert alert-error"><ul><?php foreach ($errors as $e): ?><li><?= clean($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <?php if ($valid): ?>
    <form method="POST" class="auth-form">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= clean($token) ?>">
      <div class="form-group">
        <label><i class="fas fa-lock"></i> New Password</label>
        <input type="password" name="password" class="form-control" placeholder="Min 8 chars" required>
      </div>
      <div class="form-group">
        <label><i class="fas fa-lock"></i> Confirm Password</label>
        <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter password" required>
      </div>
      <button type="submit" class="btn btn-primary btn-full">
        <i class="fas fa-save"></i> Reset Password
      </button>
    </form>
    <?php endif; ?>
    <div class="auth-footer"><a href="login.php">Back to Login</a></div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
