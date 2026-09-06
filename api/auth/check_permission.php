<?php
/**
 * Permission Check API
 * Checks if a user has a specific permission
 * 
 * Usage: POST with JSON body
 * {
 *   "permission": "permission_name",
 *   "resource": "optional_resource_id"
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
    $permission = $input['permission'] ?? null;
    $resource = $input['resource'] ?? null;
    
    if (!$permission) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Permission parameter required']);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    $role_id = $_SESSION['role_id'] ?? null;
    $role_name = $_SESSION['role_name'] ?? null;
    
    // Check permissions from database if role_permissions table exists
    $has_permission = false;
    
    try {
        // Try to get permission from role_permissions table
        $perm_query = "SELECT rp.* FROM role_permissions rp
                      INNER JOIN roles r ON rp.role_id = r.id
                      WHERE r.id = :role_id AND rp.permission_name = :permission";
        $perm_stmt = $db->prepare($perm_query);
        $perm_stmt->bindParam(':role_id', $role_id, PDO::PARAM_INT);
        $perm_stmt->bindParam(':permission', $permission);
        $perm_stmt->execute();
        $perm_data = $perm_stmt->fetch();
        
        if ($perm_data) {
            $has_permission = true;
        }
    } catch (PDOException $e) {
        // If role_permissions table doesn't exist, use hardcoded mapping
        error_log("role_permissions table not found, using hardcoded mapping: " . $e->getMessage());
        
        // Hardcoded permission mapping by role
        $role_permissions = [
            'admin' => ['*'], // All permissions
            'receptionist' => ['register_patient', 'view_patient', 'schedule_appointment', 'view_queue'],
            'appointment_coordinator' => ['schedule_appointment', 'manage_schedules', 'reschedule_appointment', 'cancel_appointment'],
            'doctor' => ['view_patient', 'view_ehr', 'prescribe', 'consult', 'triage', 'admit_patient', 'discharge_patient'],
            'nurse' => ['view_patient', 'triage', 'monitor_patient', 'transfer_patient', 'view_bed_status'],
            'pharmacist' => ['view_prescription', 'process_prescription', 'view_ehr'],
            'lab_technician' => ['view_lab_order', 'process_lab_order', 'view_ehr'],
            'billing_staff' => ['view_patient', 'process_billing', 'view_insurance', 'process_payment'],
            'housekeeping' => ['view_bed_status', 'update_bed_cleaning'],
            'patient' => ['view_profile', 'view_appointments', 'book_appointment', 'view_teleconsult']
        ];
        
        $user_perms = $role_permissions[$role_name] ?? [];
        $has_permission = in_array('*', $user_perms) || in_array($permission, $user_perms);
    }
    
    // Log permission check
    try {
        $log_query = "INSERT INTO audit_logs (user_id, action, resource, status, ip_address, user_agent) 
                     VALUES (:user_id, :action, :resource, :status, :ip_address, :user_agent)";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $log_stmt->bindValue(':action', 'check_permission');
        $log_stmt->bindValue(':resource', $permission . ($resource ? ':' . $resource : ''));
        $log_stmt->bindValue(':status', $has_permission ? 'granted' : 'denied');
        $log_stmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'] ?? null);
        $log_stmt->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? null);
        $log_stmt->execute();
    } catch (PDOException $e) {
        error_log("Audit log error: " . $e->getMessage());
    }
    
    if ($has_permission) {
        echo json_encode([
            'success' => true,
            'permission' => $permission,
            'granted' => true,
            'role' => $role_name
        ]);
    } else {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'permission' => $permission,
            'granted' => false,
            'message' => 'Permission denied',
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








