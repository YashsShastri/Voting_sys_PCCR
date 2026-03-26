<?php
/**
 * Shared Functions - PCCOER Voting System
 * Includes security helpers, logging, CSRF, and notifications.
 */

require_once __DIR__ . '/../config/db.php';

// ─── CSRF Protection ────────────────────────────────────────────────────────

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(): void {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        http_response_code(403);
        die('CSRF validation failed. Please go back and try again.');
    }
}

// ─── Session / Auth ──────────────────────────────────────────────────────────

function requireLogin(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit;
    }
    // Session timeout check
    if (isset($_SESSION['last_active']) && (time() - $_SESSION['last_active']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . 'auth/login.php?timeout=1');
        exit;
    }
    $_SESSION['last_active'] = time();
}

function requireRole(string $role): void {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        header('Location: ' . BASE_URL . ($role === 'admin' ? 'student/dashboard.php' : 'admin/dashboard.php'));
        exit;
    }
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

// ─── Audit Logging ───────────────────────────────────────────────────────────

function logAction(string $action, string $details = '', ?int $userId = null): void {
    $db = getDB();
    $uid = $userId ?? ($_SESSION['user_id'] ?? null);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $db->prepare("INSERT INTO logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$uid, $action, $details, $ip]);
}

// ─── Notifications ───────────────────────────────────────────────────────────

function sendNotification(int $userId, string $message): void {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $stmt->execute([$userId, $message]);
}

function getUnreadCount(int $userId): int {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

// ─── Election Status Auto-Sync ───────────────────────────────────────────────

function syncElectionStatuses(): void {
    $db = getDB();
    $now = date('Y-m-d H:i:s');
    // Activate elections that should be live
    $db->exec("UPDATE elections SET status='active' WHERE status='upcoming' AND start_time <= '$now' AND end_time > '$now'");
    // Complete elections that have ended
    $db->exec("UPDATE elections SET status='completed' WHERE status='active' AND end_time <= '$now'");
}

// ─── Input Sanitization ──────────────────────────────────────────────────────

function clean(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function validateEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePccoerEmail(string $email): bool {
    return validateEmail($email) && str_ends_with(strtolower($email), '@pccoer.in');
}

// ─── Flash Messages ──────────────────────────────────────────────────────────

function flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function showFlash(): string {
    $f = getFlash();
    if (!$f) return '';
    $icons = ['success' => '✓', 'error' => '✗', 'info' => 'ℹ', 'warning' => '⚠'];
    $icon = $icons[$f['type']] ?? 'ℹ';
    return '<div class="alert alert-' . $f['type'] . '"><span class="alert-icon">' . $icon . '</span>' . clean($f['message']) . '</div>';
}

// ─── Pagination Helper ───────────────────────────────────────────────────────

function paginate(int $total, int $page, int $perPage = 15): array {
    $totalPages = max(1, ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    return ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => $totalPages, 'offset' => $offset];
}

// ─── Time Formatting ─────────────────────────────────────────────────────────

function formatDateTime(string $dt): string {
    return date('d M Y, h:i A', strtotime($dt));
}

function timeAgo(string $dt): string {
    $now = time();
    $diff = $now - strtotime($dt);
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    return floor($diff/86400) . 'd ago';
}
