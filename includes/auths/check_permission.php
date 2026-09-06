<?php
/**
 * Permission Check Helper Function
 * Use this in PHP pages to check permissions
 */

function hasPermission($permission, $resource = null) {
    global $db;
    
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $role_id = $_SESSION['role_id'] ?? null;
    $role_name = $_SESSION['role_name'] ?? null;
    
    // Check permissions from database if role_permissions table exists
    try {
        $perm_query = "SELECT rp.* FROM role_permissions rp
                      INNER JOIN roles r ON rp.role_id = r.id
                      WHERE r.id = :role_id AND rp.permission_name = :permission";
        $perm_stmt = $db->prepare($perm_query);
        $perm_stmt->bindParam(':role_id', $role_id, PDO::PARAM_INT);
        $perm_stmt->bindParam(':permission', $permission);
        $perm_stmt->execute();
        $perm_data = $perm_stmt->fetch();
        
        if ($perm_data) {
            return true;
        }
    } catch (PDOException $e) {
        // Fallback to hardcoded mapping
    }
    
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
    return in_array('*', $user_perms) || in_array($permission, $user_perms);
}

function requirePermission($permission, $resource = null) {
    if (!hasPermission($permission, $resource)) {
        $_SESSION['error'] = "You don't have permission to perform this action.";
        header("Location: ../../index.php");
        exit;
    }
}
?>








