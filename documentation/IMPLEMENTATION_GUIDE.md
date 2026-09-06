# Role-Based System Implementation Guide

This guide explains how to implement the role-based processes in the Hospital Core System.

## 📋 Prerequisites

1. **Database Setup**
   - Run `database/hospital-core1-system.sql` (if not already done)
   - Run `database/role_based_schema_updates.sql` to add missing tables
   - Run `database/doctors_insert.sql` to add doctor data

2. **System Files**
   - `config/permissions.php` - Enhanced permissions system
   - `includes/role_helpers.php` - Role-based data access helpers
   - `config/config.php` - Updated to include permissions

## 🚀 Implementation Steps

### Step 1: Database Setup

```sql
-- Run these SQL files in order:
1. database/hospital-core1-system.sql (main schema)
2. database/role_based_schema_updates.sql (role-based tables)
3. database/doctors_insert.sql (doctor data)
```

### Step 2: Update Existing Modules

All modules should now use the permission system:

```php
<?php
require_once '../../config/config.php';
requireAuth();

// Check role access
checkRole(['doctor']); // or ['admin', 'doctor'] for multiple roles

// Check specific permission
requirePermission('prescriptions.create');

// Use role-based data access
$patients = getPatientsByRole(['search' => $search_term]);
$appointments = getAppointmentsByRole(['date' => date('Y-m-d')]);

// Log actions
logAction('prescription_created', 'prescriptions', $prescription_id, null, $prescription_data);
?>
```

### Step 3: Module Implementation Checklist

#### Admin Modules
- [x] User Management (`admin/users/`)
- [x] System Settings (`admin/system/`)
- [x] Role Management (`admin/roles/`)
- [ ] Insurance Provider Management (NEW)
- [ ] Clinic Hours Configuration (NEW)
- [ ] Services Catalog Management (NEW)
- [x] Audit Logs (`admin/system/audit_logs.php`)

#### Doctor Modules
- [x] Dashboard (`dashboards/doctor_dashboard.php`)
- [x] Consultations (`modules/telehealth/teleconsult.php`)
- [x] E-Prescriptions (`modules/telehealth/e_prescriptions.php`)
- [x] E-Labs (`modules/telehealth/e_labs.php`)
- [ ] Schedule View (UPDATE)
- [ ] Patient Queue (UPDATE)
- [ ] Inpatient Management (UPDATE)

#### Staff Modules
- [x] Patient Registration (`modules/registration/register.php`)
- [x] Triage (`modules/er_triage/triage.php`)
- [ ] Vital Signs (NEW)
- [ ] Queue Management (`modules/registration/queue.php` - UPDATE)
- [x] Bed Management (`modules/inpatient/bed_management.php`)

#### Patient Modules
- [x] Dashboard (`dashboards/patient_dashboard.php`)
- [x] Appointment Booking (`modules/appointments/`)
- [ ] Consent Forms (NEW)
- [ ] Online Payment (UPDATE)
- [x] Medical Records View (`modules/profile/`)

#### Receptionist Modules
- [x] Patient Registration (`modules/registration/`)
- [x] Appointment Management (`modules/appointments/`)
- [x] Queue Management (`modules/registration/queue.php`)
- [ ] Insurance Verification (UPDATE)
- [ ] Walk-in Processing (UPDATE)

#### Finance Modules
- [x] Billing (`modules/billing/`)
- [x] Payments (`modules/billing/payments.php`)
- [ ] Insurance Claims (NEW)
- [ ] Charges Management (NEW)
- [ ] Financial Reports (UPDATE)

## 🔧 Key Functions to Use

### Permission Checking

```php
// Check if user has permission
if (hasPermission('prescriptions.create')) {
    // Allow action
}

// Require permission (redirects if not)
requirePermission('prescriptions.create');

// Check data access
if (canAccessData('patient', $patient_id)) {
    // Show patient data
}
```

### Role-Based Data Access

```php
// Get patients based on role
$patients = getPatientsByRole(['search' => 'John', 'status' => 'active']);

// Get appointments based on role
$appointments = getAppointmentsByRole(['date' => '2025-01-15', 'status' => 'scheduled']);

// Get billing based on role
$billing = getBillingByRole(['status' => 'pending']);

// Get doctor schedule
$schedule = getDoctorSchedule($doctor_id, '2025-01-15');

// Get queue
$queue = getQueue(['doctor_id' => $doctor_id, 'department' => 'Cardiology']);
```

### Audit Logging

```php
// Log any action
logAction('patient_created', 'patients', $patient_id, null, $patient_data);
logAction('appointment_updated', 'appointments', $appointment_id, $old_data, $new_data);
logAction('prescription_created', 'prescriptions', $prescription_id, null, $prescription_data);
```

## 📝 Module Update Examples

### Example 1: Doctor Prescription Module

```php
<?php
require_once '../../config/config.php';
requireAuth();
checkRole(['doctor']);
requirePermission('prescriptions.create');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate data
    $patient_id = $_POST['patient_id'];
    
    // Check if doctor can access this patient
    if (!canAccessData('patient', $patient_id)) {
        $_SESSION['error'] = "You don't have access to this patient.";
        header("Location: e_prescriptions.php");
        exit;
    }
    
    // Create prescription
    $prescription_data = [
        'patient_id' => $patient_id,
        'doctor_id' => $_SESSION['user_id'],
        'medications' => $_POST['medications'],
        // ... other fields
    ];
    
    // Insert into database
    // ...
    
    // Log action
    logAction('prescription_created', 'prescriptions', $prescription_id, null, $prescription_data);
    
    $_SESSION['success'] = "Prescription created successfully.";
    header("Location: e_prescriptions.php");
    exit;
}
?>
```

### Example 2: Staff Queue Management

```php
<?php
require_once '../../config/config.php';
requireAuth();
checkRole(['staff', 'receptionist']);
requirePermission('queue.assign');

// Get queue
$queue = getQueue(['department' => $_GET['department'] ?? null]);

// Assign queue number
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_queue'])) {
    $patient_id = $_POST['patient_id'];
    $doctor_id = $_POST['doctor_id'];
    $queue_number = generateQueueNumber($_POST['department']);
    
    // Insert into queue table
    // ...
    
    logAction('queue_assigned', 'queue', $queue_id, null, [
        'queue_number' => $queue_number,
        'patient_id' => $patient_id
    ]);
}
?>
```

### Example 3: Finance Billing Module

```php
<?php
require_once '../../config/config.php';
requireAuth();
checkRole(['finance staff']);
requirePermission('billing.create');

// Get billing records
$billing = getBillingByRole(['status' => $_GET['status'] ?? 'pending']);

// Create bill
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bill_data = [
        'patient_id' => $_POST['patient_id'],
        'charges' => $_POST['charges'],
        // ...
    ];
    
    // Create billing record
    // ...
    
    logAction('billing_created', 'billing', $billing_id, null, $bill_data);
}
?>
```

## 🔒 Security Best Practices

1. **Always check authentication**
   ```php
   requireAuth();
   ```

2. **Always check role**
   ```php
   checkRole(['doctor']); // Specific role
   checkRole(['admin', 'doctor']); // Multiple roles
   ```

3. **Always check permissions**
   ```php
   requirePermission('prescriptions.create');
   ```

4. **Always check data access**
   ```php
   if (!canAccessData('patient', $patient_id)) {
       // Deny access
   }
   ```

5. **Always log actions**
   ```php
   logAction('action_name', 'module', $record_id, $old_data, $new_data);
   ```

## 📊 Database Tables Added

1. `insurance_providers` - Insurance provider management
2. `billing_charges` - Individual billing charges
3. `insurance_claims` - Insurance claim processing
4. `payments` - Payment records
5. `queue` - Queue management
6. `vital_signs` - Vital signs recording
7. `consent_forms` - Patient consent forms
8. `system_settings` - System configuration
9. `clinic_hours` - Clinic operating hours
10. `services_catalog` - Services and pricing

## 🧪 Testing Checklist

For each role, test:

- [ ] Login and authentication
- [ ] Dashboard access
- [ ] Data viewing (role-appropriate)
- [ ] Data creation (with permissions)
- [ ] Data modification (with permissions)
- [ ] Data access restrictions (cannot access unauthorized data)
- [ ] Audit logging (actions are logged)
- [ ] Error handling (proper error messages)

## 📚 Additional Resources

- `documentation/ROLE_PROCESSES.md` - Complete role documentation
- `documentation/ROLE_IMPLEMENTATION_CHECKLIST.md` - Implementation checklist
- `documentation/ROLE_QUICK_REFERENCE.md` - Quick reference guide

## 🆘 Troubleshooting

### Permission Denied Errors
- Check if user has correct role
- Verify permission exists in `config/permissions.php`
- Check if `requirePermission()` is called correctly

### Data Access Issues
- Verify `canAccessData()` is called
- Check role-based filtering in helper functions
- Ensure user_id is set in session

### Audit Log Not Working
- Check if `audit_logs` table exists
- Verify database connection
- Check error logs for PDO exceptions

---

**Last Updated:** 2025  
**Version:** 1.0

