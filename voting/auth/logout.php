<?php
require_once __DIR__ . '/../includes/session.php';

if (isLoggedIn()) {
    logAction('LOGOUT', 'User logged out: ' . ($_SESSION['email'] ?? 'unknown'));
}
session_unset();
session_destroy();

// Clear cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

header('Location: ' . BASE_URL . 'auth/login.php');
exit;
