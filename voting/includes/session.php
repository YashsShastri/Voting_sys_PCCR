<?php
/**
 * Session Initialization - must be included at the TOP of every PHP file
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,    // Set true in production (HTTPS)
        'httponly' => true,     // Prevent JS access to session cookie
        'samesite' => 'Strict',
    ]);
    session_start();
}

require_once __DIR__ . '/../includes/functions.php';

// Auto-sync election statuses on every page load
if (function_exists('syncElectionStatuses')) {
    syncElectionStatuses();
}
