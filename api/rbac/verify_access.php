<?php
/**
 * RBAC Access Verification API
 * Verifies if a user has access to a specific system/module
 * 
 * Usage: POST with JSON body
 * {
 *   "system": "SPRS|ASS|TOCS|EERTS|IBMS",
 *   "action": "read|write|delete|manage"
 * }
 */

header('Content-Type: application/json');
require_once '../../config/config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $system = $input['system'] ?? null;
    $action = $input['action'] ?? 'read';
    
    if (!$system) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'System parameter required']);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    $role_id = $_SESSION['role_id'] ?? null;
    $role_name = $_SESSION['role_name'] ?? null;
    
    // System access mapping based on role
    $system_access = [
        'SPRS' => ['admin', 'receptionist', 'billing_staff'],
        'ASS' => ['admin', 'receptionist', 'appointment_coordinator', 'doctor', 'billing_staff'],
        'TOCS' => ['admin', 'doctor', 'nurse', 'pharmacist', 'lab_technician', 'billing_staff', 'patient'],
        'EERTS' => ['admin', 'receptionist', 'doctor', 'nurse'],
        'IBMS' => ['admin', 'nurse', 'doctor', 'housekeeping', 'billing_staff']
    ];
    
    // Action permissions mapping
    $action_permissions = [
        'read' => ['admin', 'receptionist', 'appointment_coordinator', 'doctor', 'nurse', 'pharmacist', 'lab_technician', 'billing_staff', 'housekeeping', 'patient'],
        'write' => ['admin', 'receptionist', 'appointment_coordinator', 'doctor', 'nurse', 'billing_staff'],
        'delete' => ['admin', 'doctor'],
        'manage' => ['admin', 'appointment_coordinator']
    ];
    
    // Check if role has access to system
    $has_system_access = false;
    if (isset($system_access[$system])) {
        $has_system_access = in_array($role_name, $system_access[$system]);
    }
    
    // Check if role has permission for action
    $has_action_permission = false;
    if (isset($action_permissions[$action])) {
        $has_action_permission = in_array($role_name, $action_permissions[$action]);
    }
    
    $has_access = $has_system_access && $has_action_permission;
    
    // Log access attempt
    try {
        $log_query = "INSERT INTO audit_logs (user_id, action, resource, status, ip_address, user_agent) 
                     VALUES (:user_id, :action, :resource, :status, :ip_address, :user_agent)";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $log_stmt->bindParam(':action', $action);
        $log_stmt->bindValue(':resource', $system);
        $log_stmt->bindValue(':status', $has_access ? 'granted' : 'denied');
        $log_stmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'] ?? null);
        $log_stmt->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? null);
        $log_stmt->execute();
    } catch (PDOException $e) {
        // Log silently if audit_logs table doesn't exist
        error_log("Audit log error: " . $e->getMessage());
    }
    
    if ($has_access) {
        echo json_encode([
            'success' => true,
            'access' => true,
            'system' => $system,
            'action' => $action,
            'role' => $role_name
        ]);
    } else {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'access' => false,
            'message' => 'Access denied',
            'system' => $system,
            'action' => $action,
            'role' => $role_name
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>








