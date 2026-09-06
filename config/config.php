<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Site Configuration
define('SITE_NAME', 'Alvion');
define('SITE_SHORT_NAME', 'ALVION');
define('SITE_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/hospital-core1');

// Theme Colors
define('PRIMARY_COLOR', 'teal');
define('SECONDARY_COLOR', 'sky');
define('ACCENT_COLOR', 'emerald');
define('NEUTRAL_COLOR', 'slate');

// Database Configuration
require_once 'database.php';
$database = new Database();
$db = $database->getConnection();

// Include helper functions
require_once __DIR__ . '/../includes/functions.php';

// Include permissions system
require_once __DIR__ . '/permissions.php';

// Include role-based helpers
require_once __DIR__ . '/../includes/role_helpers.php';

// Check if user is logged in for protected pages
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit();
    }
}

// Check user role - supports both role_id (array of integers) and role_name (array of strings)
function checkRole($allowed_roles) {
    $user_role_id = $_SESSION['role_id'] ?? null;
    $user_role_name = $_SESSION['role_name'] ?? $_SESSION['user_role'] ?? null;
    
    // Check if allowed_roles contains integers (role_ids) or strings (role_names)
    $is_role_id_check = !empty($allowed_roles) && is_numeric($allowed_roles[0]);
    
    if ($is_role_id_check) {
        // Check using role_id
        if (!$user_role_id || !in_array($user_role_id, $allowed_roles)) {
            $_SESSION['error'] = "You don't have permission to access this page.";
            header("Location: ../index.php");
            exit();
        }
    } else {
        // Check using role_name (backward compatibility)
        if (!$user_role_name) {
            $_SESSION['error'] = "You don't have permission to access this page.";
            header("Location: ../index.php");
            exit();
        }

        $normalized_allowed = array_map(static function ($role) {
            return is_string($role) ? strtolower($role) : $role;
        }, $allowed_roles);

        $normalized_user_role = is_string($user_role_name) ? strtolower($user_role_name) : $user_role_name;

        if (!in_array($normalized_user_role, $normalized_allowed, true)) {
            $_SESSION['error'] = "You don't have permission to access this page.";
            header("Location: ../index.php");
            exit();
        }
    }
}
?>