# Role Quick Reference Guide

Quick lookup guide for role permissions and access levels.

---

## 🔑 Access Matrix

| Feature | Admin | Doctor | Staff | Patient | Receptionist | Finance |
|---------|:-----:|:------:|:-----:|:-------:|:-----------:|:-------:|
| **Patient Records** |
| View All Patients | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| View Own Patients | ✅ | ✅ | ❌ | ✅ | ✅ | ❌ |
| Edit Patient Demographics | ✅ | ❌ | ✅ | Own Only | ✅ | ❌ |
| View Medical History | ✅ | ✅ | View | Own Only | View | ❌ |
| Edit Medical Records | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Appointments** |
| View All Appointments | ✅ | ❌ | ❌ | ❌ | ✅ | View |
| View Own Appointments | ✅ | ✅ | Queue | ✅ | ✅ | View |
| Create Appointments | ✅ | ❌ | ❌ | ✅ | ✅ | ❌ |
| Reschedule/Cancel | ✅ | Own | ❌ | Own | ✅ | ❌ |
| **Billing** |
| View All Bills | ✅ | View | ❌ | Own | Estimate | ✅ |
| Create Bills | ✅ | ❌ | ❌ | ❌ | Estimate | ✅ |
| Process Payments | ✅ | ❌ | ❌ | Own | ❌ | ✅ |
| Insurance Claims | ✅ | ❌ | ❌ | View | Verify | ✅ |
| **Prescriptions** |
| View All | ✅ | ✅ | View | Own | View | ❌ |
| Create Prescriptions | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Lab Results** |
| View All | ✅ | ✅ | View | Own | View | ❌ |
| Request Labs | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Triage** |
| View All | ✅ | Assigned | ✅ | ❌ | View | ❌ |
| Perform Triage | ❌ | ❌ | ✅ | ❌ | Basic | ❌ |
| **Inpatient** |
| View All Admissions | ✅ | Assigned | ✅ | Own | View | ❌ |
| Approve Admission | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Bed Management | ✅ | Request | ✅ | ❌ | ❌ | ❌ |
| Discharge | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **System** |
| User Management | ✅ | ❌ | ❌ | Own | ❌ | ❌ |
| System Settings | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Audit Logs | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Reports | ✅ | Own | ❌ | Own | View | ✅ |

---

## 📋 Common Tasks by Role

### 👨‍💼 ADMIN
```
✓ Manage users and roles
✓ Configure system settings
✓ View all reports
✓ Manage insurance providers
✓ View audit logs
✓ System maintenance
```

### 👨‍⚕️ DOCTOR
```
✓ View patient schedule
✓ Perform consultations
✓ Issue prescriptions
✓ Request lab tests
✓ Update patient records
✓ Approve admissions
✓ Discharge patients
```

### 👩‍⚕️ STAFF
```
✓ Register walk-in patients
✓ Perform triage
✓ Record vital signs
✓ Manage queue
✓ Assign beds
✓ Update bed status
```

### 👤 PATIENT
```
✓ Book appointments
✓ View medical records
✓ View prescriptions
✓ View lab results
✓ Pay bills online
✓ Update profile
```

### 🏥 RECEPTIONIST
```
✓ Register patients
✓ Verify insurance
✓ Manage appointments
✓ Assign queue numbers
✓ Process walk-ins
✓ Coordinate patient flow
```

### 💰 FINANCE
```
✓ Generate bills
✓ Process payments
✓ Handle insurance claims
✓ Create financial reports
✓ Reconcile accounts
✓ Manage charges
```

---

## 🔐 Permission Levels

### Level 1: No Access ❌
- Cannot view or modify

### Level 2: View Only 👁️
- Can view but cannot modify

### Level 3: Own Records Only 👤
- Can view and modify own records only

### Level 4: Assigned Records 📋
- Can view and modify assigned records

### Level 5: Department Access 🏢
- Can view and modify department records

### Level 6: Full Access ✅
- Can view and modify all records

---

## 🚦 Workflow Quick Reference

### Patient Registration Flow
```
Patient → Receptionist/Staff → System → Doctor
```

### Appointment Booking Flow
```
Patient → Receptionist → Doctor Schedule → Confirmation
```

### Consultation Flow
```
Patient → Triage (Staff) → Queue → Doctor → Prescription/Lab
```

### Billing Flow
```
Services → Finance → Bill Generation → Payment → Receipt
```

### Admission Flow
```
Doctor Request → Staff Bed Assignment → Admission → Discharge
```

---

## 📞 Role-Specific Modules

### ADMIN Modules
- `/admin/users/` - User management
- `/admin/system/` - System settings
- `/admin/roles/` - Role management
- `/reports/` - All reports

### DOCTOR Modules
- `/dashboards/doctor_dashboard.php` - Dashboard
- `/modules/telehealth/` - Telehealth
- `/modules/telehealth/e_prescriptions.php` - Prescriptions
- `/modules/telehealth/e_labs.php` - Lab orders

### STAFF Modules
- `/modules/registration/` - Patient registration
- `/modules/er_triage/` - Triage
- `/modules/inpatient/bed_management.php` - Bed management

### PATIENT Modules
- `/dashboards/patient_dashboard.php` - Dashboard
- `/modules/appointments/` - Appointments
- `/modules/profile/` - Profile

### RECEPTIONIST Modules
- `/dashboards/receptionist_dashboard.php` - Dashboard
- `/modules/registration/` - Registration
- `/modules/appointments/` - Appointments
- `/modules/registration/queue.php` - Queue

### FINANCE Modules
- `/dashboards/billing_dashboard.php` - Dashboard
- `/modules/billing/` - Billing
- `/modules/billing/payments.php` - Payments
- `/modules/billing/insurance.php` - Insurance

---

## ⚠️ Important Notes

1. **Data Privacy**: All medical records are protected by privacy regulations
2. **Audit Trail**: All data modifications are logged
3. **Role Hierarchy**: Admin > Doctor > Staff/Receptionist/Finance > Patient
4. **Emergency Access**: Staff can access emergency records with proper authorization
5. **Read-Only Access**: Some roles have read-only access to sensitive data

---

## 🔄 Role Switching

- Users can only have ONE primary role
- Role switching requires admin approval
- Temporary role assignment possible for emergencies
- All role changes are logged in audit trail

---

**Quick Reference Version:** 1.0  
**Last Updated:** 2025

