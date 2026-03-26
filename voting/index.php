<?php
/**
 * PCCOER Voting System - Entry Point
 * Redirects to the appropriate dashboard or login based on auth state.
 */
require_once __DIR__ . '/includes/session.php';

if (isLoggedIn()) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: ' . BASE_URL . 'admin/dashboard.php');
    } else {
        header('Location: ' . BASE_URL . 'student/dashboard.php');
    }
} else {
    header('Location: ' . BASE_URL . 'auth/login.php');
}
exit;
