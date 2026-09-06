<?php
// Use absolute path for config
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/audit_log_helper.php';

// Check if user is logged in and log logout action to audit_logs
if (isset($_SESSION['user_id'])) {
    $user_name = isset($_SESSION['first_name']) ? $_SESSION['first_name'] . ' ' . (isset($_SESSION['last_name']) ? $_SESSION['last_name'] : '') : 'Unknown';
    error_log("User logout: " . $user_name . " (ID: " . $_SESSION['user_id'] . ")");

    $sessionUserMeta = [
        'employee_id' => $_SESSION['employee_id'] ?? null,
        'id' => $_SESSION['user_id'] ?? null,
        'first_name' => $_SESSION['first_name'] ?? '',
        'last_name' => $_SESSION['last_name'] ?? '',
        'login_context' => $_SESSION['login_context'] ?? 'standard',
        'source_table' => $_SESSION['source_table'] ?? null,
        'role_name' => $_SESSION['role_name'] ?? null
    ];

    if (!empty($sessionUserMeta['employee_id'])) {
        logDepartmentAccountAuditEvent(
            $db,
            $sessionUserMeta,
            'logout',
            sprintf('Employee %s logged out', $sessionUserMeta['employee_id'])
        );
    }
}

// Unset all session variables
$_SESSION = array();

// Delete the session cookie if it exists
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// Start a new session to store logout message (optional)
session_start();
$_SESSION['success'] = "You have been successfully logged out.";

// Redirect to login page
header("Location: " . BASE_URL . "/auth/login.php");
exit();
?>