<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('student');
$pageTitle = 'My Profile - ' . SITE_NAME;

$db  = getDB();
$sid = $_SESSION['student_id'];
$uid = $_SESSION['user_id'];

$student = $db->prepare("SELECT s.*, u.email FROM students s JOIN users u ON s.user_id=u.id WHERE s.id=?");
$student->execute([$sid]);
$student = $student->fetch();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (isset($_POST['update_profile'])) {
        $phone = clean($_POST['phone'] ?? '');
        $dept  = clean($_POST['department'] ?? '');
        if ($phone && !preg_match('/^[0-9]{10}$/', $phone)) $errors[] = 'Phone must be 10 digits.';
        if (empty($errors)) {
            $db->prepare("UPDATE students SET phone=?, department=? WHERE id=?")->execute([$phone, $dept, $sid]);
            logAction('PROFILE_UPDATE', 'Student updated profile.');
            flash('success', 'Profile updated successfully.');
            header('Location: profile.php'); exit;
        }
    } elseif (isset($_POST['change_password'])) {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $cnf = $_POST['confirm_new'] ?? '';
        $user = $db->prepare("SELECT password FROM users WHERE id=?")->execute([$uid]) ? $db->query("SELECT password FROM users WHERE id=$uid")->fetch() : null;
        $userRow = $db->prepare("SELECT password FROM users WHERE id=?");
        $userRow->execute([$uid]);
        $userRow = $userRow->fetch();
        if (!password_verify($old, $userRow['password'])) $errors[] = 'Current password is incorrect.';
        if (strlen($new) < 8) $errors[] = 'New password must be at least 8 characters.';
        if ($new !== $cnf) $errors[] = 'Passwords do not match.';
        if (empty($errors)) {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost'=>12]);
            $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $uid]);
            logAction('PASSWORD_CHANGE', 'Student changed password.');
            flash('success', 'Password changed successfully.');
            header('Location: profile.php'); exit;
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container">
  <div class="page-header">
    <h1><i class="fas fa-user-circle"></i> My Profile</h1>
  </div>
  <?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $e): ?><li><?= clean($e) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <div class="profile-grid">
    <!-- Profile Info Card -->
    <div class="card">
      <div class="card-header"><h3><i class="fas fa-id-card"></i> Student Information</h3></div>
      <div class="card-body">
        <div class="profile-avatar-section">
          <div class="profile-avatar"><i class="fas fa-user-graduate"></i></div>
          <div>
            <h2><?= clean($student['name']) ?></h2>
            <p class="text-muted"><?= clean($student['email']) ?></p>
            <span class="badge <?= $student['verified'] ? 'badge-success' : 'badge-warning' ?>">
              <?= $student['verified'] ? 'Verified' : 'Pending Verification' ?>
            </span>
          </div>
        </div>
        <table class="profile-table">
          <tr><td><strong>Roll No</strong></td><td><?= clean($student['roll_no']) ?></td></tr>
          <tr><td><strong>Division</strong></td><td><?= clean($student['division']) ?></td></tr>
          <tr><td><strong>Year</strong></td><td>Year <?= (int)$student['year'] ?></td></tr>
          <tr><td><strong>Department</strong></td><td><?= clean($student['department']) ?></td></tr>
          <tr><td><strong>Phone</strong></td><td><?= clean($student['phone'] ?? 'Not set') ?></td></tr>
        </table>
      </div>
    </div>

    <!-- Edit Profile -->
    <div class="card">
      <div class="card-header"><h3><i class="fas fa-edit"></i> Edit Profile</h3></div>
      <div class="card-body">
        <form method="POST">
          <?= csrf_field() ?>
          <div class="form-group">
            <label>Department</label>
            <select name="department" class="form-control">
              <?php foreach (['Computer Engineering','Information Technology','Mechanical Engineering','Civil Engineering','Electronics & Telecom','AI & DS'] as $d): ?>
              <option value="<?= $d ?>" <?= $student['department']===$d ? 'selected':'' ?>><?= $d ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Phone Number</label>
            <input type="tel" name="phone" class="form-control" placeholder="10 digit number" value="<?= clean($student['phone'] ?? '') ?>">
          </div>
          <button type="submit" name="update_profile" class="btn btn-primary">
            <i class="fas fa-save"></i> Save Changes
          </button>
        </form>
      </div>
    </div>

    <!-- Change Password -->
    <div class="card">
      <div class="card-header"><h3><i class="fas fa-lock"></i> Change Password</h3></div>
      <div class="card-body">
        <form method="POST">
          <?= csrf_field() ?>
          <div class="form-group">
            <label>Current Password</label>
            <input type="password" name="old_password" class="form-control" required>
          </div>
          <div class="form-group">
            <label>New Password</label>
            <input type="password" name="new_password" class="form-control" placeholder="Min 8 chars" required>
          </div>
          <div class="form-group">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_new" class="form-control" required>
          </div>
          <button type="submit" name="change_password" class="btn btn-secondary">
            <i class="fas fa-key"></i> Update Password
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
