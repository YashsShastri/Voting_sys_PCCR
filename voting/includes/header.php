<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?? SITE_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="<?= isset($_SESSION['role']) ? 'role-' . $_SESSION['role'] : '' ?>">

<nav class="navbar">
  <div class="nav-brand">
    <i class="fas fa-vote-yea nav-icon"></i>
    <span><?= SITE_NAME ?></span>
    <span class="nav-subtitle">PCCOER</span>
  </div>
  <div class="nav-links">
  <?php if (isLoggedIn()): ?>
    <?php $unread = getUnreadCount($_SESSION['user_id']); ?>
    <?php if ($_SESSION['role'] === 'admin'): ?>
      <a href="<?= BASE_URL ?>admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
      <a href="<?= BASE_URL ?>admin/elections.php"><i class="fas fa-poll"></i> Elections</a>
      <a href="<?= BASE_URL ?>admin/students.php"><i class="fas fa-users"></i> Students</a>
      <a href="<?= BASE_URL ?>admin/analytics.php"><i class="fas fa-chart-bar"></i> Analytics</a>
      <a href="<?= BASE_URL ?>admin/audit_logs.php"><i class="fas fa-shield-alt"></i> Logs</a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>student/dashboard.php"><i class="fas fa-home"></i> Home</a>
      <a href="<?= BASE_URL ?>student/elections.php"><i class="fas fa-poll"></i> Elections</a>
      <a href="<?= BASE_URL ?>student/history.php"><i class="fas fa-history"></i> My Votes</a>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>student/notifications.php" class="notif-link">
      <i class="fas fa-bell"></i>
      <?php if ($unread > 0): ?><span class="badge"><?= $unread ?></span><?php endif; ?>
    </a>
    <div class="nav-dropdown">
      <button class="nav-user-btn">
        <i class="fas fa-user-circle"></i>
        <?= clean($_SESSION['email'] ?? '') ?>
        <i class="fas fa-chevron-down"></i>
      </button>
      <div class="nav-dropdown-menu">
        <?php if ($_SESSION['role'] === 'student'): ?>
        <a href="<?= BASE_URL ?>student/profile.php"><i class="fas fa-id-card"></i> Profile</a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </div>
    </div>
  <?php else: ?>
    <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-outline">Login</a>
    <a href="<?= BASE_URL ?>auth/register.php" class="btn btn-primary">Register</a>
  <?php endif; ?>
  </div>
  <button class="hamburger" id="hamburger" aria-label="Toggle menu">
    <span></span><span></span><span></span>
  </button>
</nav>

<main class="main-content">
<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?>">
  <span class="alert-icon"><?php
    echo ['success'=>'✓','error'=>'✗','warning'=>'⚠','info'=>'ℹ'][$flash['type']] ?? 'ℹ';
  ?></span>
  <?= clean($flash['message']) ?>
</div>
<?php endif; ?>
