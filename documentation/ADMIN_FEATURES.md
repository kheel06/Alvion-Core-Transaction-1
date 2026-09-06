# Admin Features Documentation
## Full System Access - Admin Role Only

This document outlines all admin-specific features and capabilities for each module in the Hospital Core System.

---

## 📊 Dashboard

**Admin Capabilities:**
- View total patients count
- Monitor ongoing appointments (scheduled, confirmed, in-progress)
- Track bed occupancy in real-time
- Monitor ER activity (cases today, waiting patients)
- View comprehensive financial summary:
  - Total revenue
  - Revenue today
  - Revenue this month
  - Pending payments
  - Unpaid bills
  - Pending insurance claims
- System health monitoring:
  - Database status
  - Active users today
  - Log entries today
  - Storage information
- View recent user activity logs

---

## 📁 GENERAL

### 1. Patient Registration
**Admin Can:**
- ✅ Register new patients
- ✅ Update patient information (full access)
- ✅ Verify patient identity & insurance
- ✅ View registration logs (audit trail)
- ✅ Export patient registration data

### 2. View Patients
**Admin Can:**
- ✅ Search for any patient in the system
- ✅ Open any patient profile
- ✅ View complete EHR summary
- ✅ View medical history (read-only for clinical safety)
- ✅ Export patient data (Excel/PDF)
- ✅ Access all patient records regardless of department

### 3. Insurance Setup
**Admin Can:**
- ✅ Add new insurance providers (PhilHealth, HMOs, private insurers)
- ✅ Update insurance policies and coverage rules
- ✅ Set coverage rules and reimbursement rates
- ✅ Manage insurance provider agreements
- ✅ Approve insurance provider setup
- ✅ Configure insurance claim workflows

### 4. Consent Forms
**Admin Can:**
- ✅ Upload/update digital consent templates
- ✅ Track all signed consent forms
- ✅ View consent form history per patient
- ✅ Manage consent form versions
- ✅ Export consent form reports

### 5. Patient Queue
**Admin Can:**
- ✅ View real-time queue in OPD/clinics
- ✅ **Reassign patients to different doctors** (admin-only feature)
- ✅ Override queue priorities
- ✅ View queue statistics
- ✅ Export queue reports

---

## 📅 APPOINTMENTS

### Schedule Appointment
**Admin Can:**
- ✅ Book appointments for any patient
- ✅ Schedule appointments with any doctor
- ✅ Override scheduling conflicts (with approval)
- ✅ Set appointment priorities
- ✅ View all appointment schedules

### Walk-in Patients
**Admin Can:**
- ✅ Add walk-in patients into the system
- ✅ Assign walk-ins to available doctors
- ✅ Create immediate appointments
- ✅ Manage walk-in queue

### Reschedule & Cancel
**Admin Can:**
- ✅ Manage all cancellations
- ✅ Assign new appointment times
- ✅ Override cancellation policies
- ✅ View cancellation history
- ✅ Process refunds if applicable

### Doctor Schedules
**Admin Can:**
- ✅ Set clinic hours for all doctors
- ✅ Assign doctors to departments
- ✅ Block unavailable dates
- ✅ Manage doctor availability
- ✅ Override schedule conflicts
- ✅ View all doctor schedules

---

## 🩺 TELEHEALTH

### Teleconsultation
**Admin Can:**
- ✅ Monitor all telehealth queues
- ✅ Assign doctors to teleconsultations
- ✅ Open video session logs
- ✅ View all teleconsultation records
- ✅ Export teleconsultation reports

### E-Prescriptions
**Admin Can:**
- ✅ View all prescriptions issued
- ✅ Access prescription history
- ✅ Export prescription reports

### Process Prescriptions
**Admin Can:**
- ✅ Approve/flag prescriptions
- ✅ Override prescription processing
- ✅ View prescription processing logs
- ✅ Manage prescription workflows

### E-Lab Orders
**Admin Can:**
- ✅ View all lab orders
- ✅ Access lab order history
- ✅ Export lab order reports

### Process Lab Orders
**Admin Can:**
- ✅ Assign tests to lab staff
- ✅ Override lab order priorities
- ✅ View lab processing logs
- ✅ Manage lab workflows

### Follow-up Scheduling
**Admin Can:**
- ✅ Manage doctor follow-up calendars
- ✅ Schedule follow-ups for any patient
- ✅ View all follow-up appointments
- ✅ Export follow-up reports

---

## 🚑 EMERGENCY ROOM

### Triage Assessment
**Admin Can:**
- ✅ View all past triage records
- ✅ Monitor severity levels
- ✅ Access triage statistics
- ✅ Export triage reports
- ✅ Override triage assignments

### ER Dashboard
**Admin Can:**
- ✅ Live view of all patients in ER
- ✅ View bed & doctor assignments
- ✅ Monitor ER capacity
- ✅ Access real-time ER statistics

### Transfer/Discharge
**Admin Can:**
- ✅ Approve all transfers
- ✅ View ER discharge history
- ✅ Override transfer restrictions
- ✅ Export transfer/discharge reports

---

## 🏥 INPATIENT

### Bed Management
**Admin Can:**
- ✅ View real-time bed status (all beds)
- ✅ Mark rooms as maintenance/cleaning
- ✅ Override bed assignments
- ✅ Manage bed availability
- ✅ Export bed occupancy reports

### Admissions
**Admin Can:**
- ✅ Approve or create admission requests
- ✅ Override admission restrictions
- ✅ View all admission history
- ✅ Export admission reports

### Bed Transfers
**Admin Can:**
- ✅ Manage movement between wards
- ✅ Approve all transfers
- ✅ Override transfer restrictions
- ✅ View transfer history

### Discharge
**Admin Can:**
- ✅ Approve all discharges
- ✅ View discharge summary logs
- ✅ Override discharge restrictions
- ✅ Export discharge reports

---

## 💳 BILLING

### Payment Processing
**Admin Can:**
- ✅ View all bills
- ✅ **Correct billing errors** (admin-only)
- ✅ Approve refunds
- ✅ Override payment restrictions
- ✅ View payment history
- ✅ Export payment reports

### Insurance Management
**Admin Can:**
- ✅ Manage all insurance claims
- ✅ View PhilHealth/HMO utilization
- ✅ Approve insurance claims
- ✅ Override claim restrictions
- ✅ Export insurance reports

---

## 📊 REPORTS

**Admin Can:**
- ✅ Generate all reports:
  - Appointments by date/doctor
  - Billing reports
  - Bed occupancy reports
  - ER patient statistics
  - Patient registration reports
  - Telehealth reports
  - Lab reports
  - Prescription reports
- ✅ **Export all data to Excel/PDF** (admin-only feature)
- ✅ Schedule automated reports
- ✅ Custom report generation

---

## ⚙️ ACCOUNT

### User Management
**Admin Can:**
- ✅ Create user accounts (all roles)
- ✅ Lock/unlock accounts
- ✅ Reset passwords
- ✅ Edit user profiles
- ✅ Deactivate/reactivate users
- ✅ View all user accounts

### Role Management
**Admin Can:**
- ✅ Assign or update permissions
- ✅ Create custom roles
- ✅ Manage role hierarchies
- ✅ View all role assignments

### System Settings
**Admin Can:**
- ✅ Configure all hospital settings
- ✅ Manage system backups
- ✅ Configure clinic hours
- ✅ Manage service catalogs
- ✅ Set system-wide policies

### Audit Logs
**Admin Can:**
- ✅ Track all actions by users
- ✅ View complete audit trail
- ✅ Export audit logs
- ✅ Filter logs by user, date, action
- ✅ Monitor system activity

---

## 🔐 Security & Access Control

**Admin Privileges:**
- Full read access to all data
- Full write/modify access to:
  - User accounts
  - System settings
  - Billing corrections
  - Appointment reassignments
  - Bed management
  - Insurance setup
- Read-only access to:
  - Clinical medical records (for compliance)
  - Prescription details (view only)
  - Lab results (view only)

**Admin Restrictions:**
- Cannot delete audit logs
- Cannot modify clinical notes without proper authorization
- Cannot access patient data without legitimate purpose (logged)

---

## 📝 Notes

1. All admin actions are logged in the audit trail
2. Admin can override most restrictions but should follow hospital policies
3. Clinical data access is read-only to maintain medical integrity
4. All exports and reports are logged for compliance
5. Admin should use override capabilities responsibly

---

**Last Updated:** 2025
**Version:** 1.0

