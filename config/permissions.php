<?php
/**
 * Enhanced Role-Based Permissions System
 * Implements all role processes as defined in ROLE_PROCESSES.md
 */

/**
 * Get all permissions for a role
 */
function getRolePermissions($role_name) {
    $permissions = [
        'admin' => [
            // User Management
            'users.create', 'users.edit', 'users.delete', 'users.view_all',
            'roles.manage', 'roles.assign',
            // System Configuration
            'system.settings', 'system.clinic_hours', 'system.rooms', 'system.services',
            'system.pricing', 'system.departments',
            // Insurance
            'insurance.create', 'insurance.edit', 'insurance.approve', 'insurance.view_all',
            // Audit & Logs
            'audit.view_all', 'audit.export', 'audit.monitor',
            // Reports
            'reports.all', 'reports.patients', 'reports.appointments', 'reports.billing',
            'reports.er', 'reports.beds', 'reports.users', 'reports.system',
            // Maintenance
            'maintenance.backup', 'maintenance.restore', 'maintenance.monitor',
            // Data Access
            'patients.view_all', 'appointments.view_all', 'billing.view_all',
            'triage.view_all', 'admissions.view_all', 'prescriptions.view_all',
            'labs.view_all'
        ],
        
        'doctor' => [
            // Patient Care
            'patients.view_assigned', 'patients.view_history',
            'appointments.view_own', 'appointments.view_schedule',
            'consultations.create', 'consultations.update',
            'telehealth.create', 'telehealth.conduct',
            // Medical Records
            'ehr.view', 'ehr.update', 'ehr.add_notes',
            'diagnosis.create', 'diagnosis.update',
            // Prescriptions
            'prescriptions.create', 'prescriptions.view_own',
            // Lab Orders
            'labs.request', 'labs.view_results',
            // Inpatient
            'admissions.approve', 'admissions.request', 'admissions.view_assigned',
            'discharge.create', 'discharge.update',
            'transfers.request', 'transfers.approve',
            // Triage
            'triage.view_assigned'
        ],
        
        'staff' => [
            // Patient Registration
            'patients.register', 'patients.edit_demographics',
            'patients.view_basic',
            // Triage
            'triage.create', 'triage.update', 'triage.view',
            // Vital Signs
            'vitals.create', 'vitals.update',
            // Queue
            'queue.assign', 'queue.manage', 'queue.view',
            // Bed Management
            'beds.assign', 'beds.update_status', 'beds.view',
            // Monitoring
            'monitoring.log', 'monitoring.view'
        ],
        
        'patient' => [
            // Account
            'profile.view_own', 'profile.edit_own',
            // Appointments
            'appointments.view_own', 'appointments.book', 'appointments.cancel_own',
            // Medical Records
            'records.view_own', 'prescriptions.view_own', 'labs.view_own',
            'ehr.view_own',
            // Telehealth
            'telehealth.view_own', 'telehealth.schedule',
            // Billing
            'billing.view_own', 'billing.pay_online',
            // Consent
            'consent.submit'
        ],
        
        'receptionist' => [
            // Patient Registration
            'patients.register', 'patients.edit', 'patients.view',
            // Insurance
            'insurance.verify', 'insurance.view',
            // Appointments
            'appointments.create', 'appointments.edit', 'appointments.cancel',
            'appointments.view_all', 'appointments.confirm',
            // Queue
            'queue.assign', 'queue.manage', 'queue.view_all',
            // Walk-ins
            'walkins.register', 'walkins.route',
            // Billing (Estimate)
            'billing.estimate', 'billing.view'
        ],
        
        'finance staff' => [
            // Billing
            'billing.create', 'billing.edit', 'billing.view_all',
            'billing.generate_soa', 'billing.adjust',
            // Charges
            'charges.add', 'charges.edit', 'charges.view_all',
            // Insurance
            'insurance.process_claims', 'insurance.process_hmo',
            'insurance.process_reimbursement', 'insurance.view_all',
            // Payments
            'payments.process', 'payments.view_all', 'payments.reconcile',
            'payments.refund',
            // Reports
            'reports.billing', 'reports.financial', 'reports.revenue'
        ]
    ];
    
    return $permissions[$role_name] ?? [];
}

/**
 * Check if user has permission
 */
function hasPermission($permission, $role_name = null) {
    if ($role_name === null) {
        $role_name = $_SESSION['role_name'] ?? $_SESSION['user_role'] ?? null;
    }
    
    if (!$role_name) {
        return false;
    }
    
    // Admin has all permissions
    if ($role_name === 'admin') {
        return true;
    }
    
    $role_permissions = getRolePermissions($role_name);
    return in_array($permission, $role_permissions);
}

/**
 * Require permission or redirect
 */
function requirePermission($permission, $redirect_url = '../index.php') {
    if (!hasPermission($permission)) {
        $_SESSION['error'] = "You don't have permission to perform this action.";
        header("Location: " . $redirect_url);
        exit;
    }
}

/**
 * Check data access based on role
 */
function canAccessData($data_type, $data_id = null, $user_id = null) {
    $role_name = $_SESSION['role_name'] ?? $_SESSION['user_role'] ?? null;
    $current_user_id = $user_id ?? $_SESSION['user_id'] ?? null;
    
    if (!$role_name || !$current_user_id) {
        return false;
    }
    
    // Admin can access all
    if ($role_name === 'admin') {
        return true;
    }
    
    // Role-specific access rules
    switch ($data_type) {
        case 'patient':
            if ($role_name === 'doctor') {
                // Doctor can access assigned patients
                return canDoctorAccessPatient($data_id, $current_user_id);
            } elseif ($role_name === 'patient') {
                // Patient can only access own record
                return $data_id == $current_user_id;
            } elseif (in_array($role_name, ['staff', 'receptionist', 'finance staff'])) {
                // Staff, receptionist, finance can access for their functions
                return true;
            }
            break;
            
        case 'appointment':
            if ($role_name === 'doctor') {
                // Doctor can access own appointments
                return canDoctorAccessAppointment($data_id, $current_user_id);
            } elseif ($role_name === 'patient') {
                // Patient can access own appointments
                return canPatientAccessAppointment($data_id, $current_user_id);
            } elseif (in_array($role_name, ['receptionist', 'staff'])) {
                // Receptionist and staff can access for management
                return true;
            }
            break;
            
        case 'billing':
            if ($role_name === 'patient') {
                // Patient can only access own bills
                return canPatientAccessBilling($data_id, $current_user_id);
            } elseif ($role_name === 'finance staff') {
                // Finance can access all
                return true;
            }
            break;
            
        case 'prescription':
        case 'lab':
            if ($role_name === 'patient') {
                // Patient can access own records
                return canPatientAccessRecord($data_type, $data_id, $current_user_id);
            } elseif ($role_name === 'doctor') {
                // Doctor can access own prescriptions/labs
                return canDoctorAccessRecord($data_type, $data_id, $current_user_id);
            } elseif (in_array($role_name, ['staff', 'receptionist'])) {
                // Staff can view for coordination
                return true;
            }
            break;
    }
    
    return false;
}

/**
 * Helper: Check if doctor can access patient
 */
function canDoctorAccessPatient($patient_id, $doctor_id) {
    global $db;
    try {
        // Check if patient has appointment with doctor
        $query = "SELECT COUNT(*) FROM appointments 
                  WHERE patient_id = :patient_id AND doctor_id = :doctor_id
                  AND status != 'cancelled'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':patient_id', $patient_id);
        $stmt->bindParam(':doctor_id', $doctor_id);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Helper: Check if doctor can access appointment
 */
function canDoctorAccessAppointment($appointment_id, $doctor_id) {
    global $db;
    try {
        $query = "SELECT doctor_id FROM appointments WHERE id = :appointment_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':appointment_id', $appointment_id);
        $stmt->execute();
        $appointment = $stmt->fetch();
        return $appointment && $appointment['doctor_id'] == $doctor_id;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Helper: Check if patient can access appointment
 */
function canPatientAccessAppointment($appointment_id, $patient_id) {
    global $db;
    try {
        $query = "SELECT patient_id FROM appointments WHERE id = :appointment_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':appointment_id', $appointment_id);
        $stmt->execute();
        $appointment = $stmt->fetch();
        return $appointment && $appointment['patient_id'] == $patient_id;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Helper: Check if patient can access billing
 */
function canPatientAccessBilling($billing_id, $patient_id) {
    global $db;
    try {
        $query = "SELECT patient_id FROM billing WHERE id = :billing_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':billing_id', $billing_id);
        $stmt->execute();
        $billing = $stmt->fetch();
        return $billing && $billing['patient_id'] == $patient_id;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Helper: Check if patient can access record (prescription/lab)
 */
function canPatientAccessRecord($record_type, $record_id, $patient_id) {
    global $db;
    try {
        $table = ($record_type === 'prescription') ? 'e_prescriptions' : 'e_lab_orders';
        $query = "SELECT patient_id FROM {$table} WHERE id = :record_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':record_id', $record_id);
        $stmt->execute();
        $record = $stmt->fetch();
        return $record && $record['patient_id'] == $patient_id;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Helper: Check if doctor can access record
 */
function canDoctorAccessRecord($record_type, $record_id, $doctor_id) {
    global $db;
    try {
        $table = ($record_type === 'prescription') ? 'e_prescriptions' : 'e_lab_orders';
        $query = "SELECT doctor_id FROM {$table} WHERE id = :record_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':record_id', $record_id);
        $stmt->execute();
        $record = $stmt->fetch();
        return $record && $record['doctor_id'] == $doctor_id;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Log action for audit trail
 */
function logAction($action, $module, $record_id = null, $old_values = null, $new_values = null) {
    global $db;
    
    try {
        $user_id = $_SESSION['user_id'] ?? null;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $query = "INSERT INTO audit_logs 
                  (user_id, action, module, record_id, old_values, new_values, ip_address, user_agent)
                  VALUES (:user_id, :action, :module, :record_id, :old_values, :new_values, :ip_address, :user_agent)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':module', $module);
        $stmt->bindParam(':record_id', $record_id);
        $stmt->bindValue(':old_values', $old_values ? json_encode($old_values) : null);
        $stmt->bindValue(':new_values', $new_values ? json_encode($new_values) : null);
        $stmt->bindParam(':ip_address', $ip_address);
        $stmt->bindParam(':user_agent', $user_agent);
        $stmt->execute();
    } catch (PDOException $e) {
        error_log("Audit log error: " . $e->getMessage());
    }
}

/**
 * Get role display name
 */
function getRoleDisplayName($role_name) {
    $display_names = [
        'admin' => 'Administrator',
        'doctor' => 'Doctor',
        'staff' => 'Staff',
        'patient' => 'Patient',
        'receptionist' => 'Receptionist',
        'finance staff' => 'Finance Staff'
    ];
    
    return $display_names[$role_name] ?? ucfirst($role_name);
}

?>

