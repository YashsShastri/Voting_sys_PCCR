<?php
/**
 * Database Configuration - PCCOER Voting System
 * Uses PDO with prepared statements (SQL injection prevention)
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'voting_db');
define('DB_USER', 'root');
define('DB_PASS', '');        // Default XAMPP MySQL password is empty
define('DB_CHARSET', 'utf8mb4');

define('BASE_URL', 'http://localhost/voting/');
define('SITE_NAME', 'PCCOER Vote');

// Session timeout in seconds (30 minutes)
define('SESSION_TIMEOUT', 1800);

/**
 * Get PDO database connection (singleton pattern)
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // Use real prepared statements
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Do not expose DB details to the user
            die('<div style="font-family:sans-serif;padding:20px;background:#fee;border:1px solid red;">
                <h3>Database Connection Error</h3>
                <p>Could not connect to the database. Please ensure XAMPP MySQL is running and the database <strong>voting_db</strong> has been imported.</p>
                <small>Error code: ' . $e->getCode() . '</small>
                </div>');
        }
    }
    return $pdo;
}
