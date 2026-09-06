-- =====================================================
-- Permissions Table Data Insert
-- Hospital Core System Permissions
-- =====================================================

-- User Management Permissions
INSERT INTO `permissions` (`permission_name`, `permission_description`, `module`) VALUES
('users.create', 'Create new user accounts', 'User Management'),
('users.edit', 'Edit existing user accounts', 'User Management'),
('users.delete', 'Delete user accounts', 'User Management'),
('users.view_all', 'View all user accounts', 'User Management'),
('users.lock_unlock', 'Lock or unlock user accounts', 'User Management'),
('users.reset_password', 'Reset user passwords', 'User Management');

-- Role Management Permissions
INSERT INTO `permissions` (`permission_name`, `permission_description`, `module`) VALUES
('roles.create', 'Create new roles', 'Role Management'),
('roles.edit', 'Edit existing roles', 'Role Management'),
('roles.delete', 'Delete roles', 'Role Management'),
('roles.view_all', 'View all roles', 'Role Management'),
('roles.assign_permissions', 'Assign or update permissions for roles', 'Role Management'),
('roles.assign_to_users', 'Assign roles to users', 'Role Management');

-- System Settings Permissions
INSERT INTO `permissions` (`permission_name`, `permission_description`, `module`) VALUES
('system.settings', 'Configure system settings', 'System Settings'),
('system.clinic_hours', 'Manage clinic operating hours', 'System Settings'),
('system.rooms', 'Manage clinic rooms', 'System Settings'),
('system.services', 'Manage services catalog', 'System Settings'),
('system.pricing', 'Configure service pricing', 'System Settings'),
('system.departments', 'Manage departments', 'System Settings'),
('system.backup', 'Create and manage backups', 'System Settings'),
('system.restore', 'Restore from backups', 'System Settings');

-- Audit & Logs Permissions
INSERT INTO `permissions` (`permission_name`, `permission_description`, `module`) VALUES
('audit.view_all', 'View all audit logs', 'Audit Logs'),
('audit.export', 'Export audit logs', 'Audit Logs'),
('audit.monitor', 'Monitor system activity', 'Audit Logs'),
('audit.filter', 'Filter audit logs by user, date, action', 'Audit Logs');

-- Patient Management Permissions
INSERT INTO `permissions` (`permission_name`, `permission_description`, `module`) VALUES
('patients.create', 'Register new patients', 'Patient Management'),
('patients.edit', 'Edit patient information', 'Patient Management'),
('patients.view', 'View patient records', 'Patient Management'),
('patients.view_all', 'View all patient records', 'Patient Management'),
('patients.delete', 'Delete patient records', 'Patient Management'),
('patients.medical_history', 'View patient medical history', 'Patient Management'),
('patients.insurance', 'Manage patient insurance information', 'Patient Management');

-- Appointment Management Permissions
INSERT INTO `permissions` (`permission_name`, `permission_description`, `module`) VALUES
('appointments.create', 'Schedule new appointments', 'Appointments'),
('appointments.edit', 'Edit appointments', 'Appointments'),
('appointments.cancel', 'Cancel appointments', 'Appointments'),
('appointments.reschedule', 'Reschedule appointments', 'Appointments'),
('appointments.view', 'View appointments', 'Appointments'),
('appointments.view_all', 'View all appointments', 'Appointments'),
('appointments.walkin', 'Create walk-in appointments', 'Appointments'),
('appointments.manage_schedules', 'Manage doctor schedules', 'Appointments');

-- Billing & Payments Permissions
INSERT INTO `permissions` (`permission_name`, `permission_description`, `module`) VALUES
('billing.create', 'Create bills', 'Billing'),
('billing.edit', 'Edit bills', 'Billing'),
('billing.view', 'View bills', 'Billing'),
('billing.view_all', 'View all bills', 'Billing'),
('billing.process_payment', 'Process payments', 'Billing'),
('billing.refund', 'Process refunds', 'Billing'),
('billing.insurance_claims', 'Manage insurance claims', 'Billing'),
('billing.approve_claims', 'Approve insurance claims', 'Billing'),
('billing.view_reports', 'View billing reports', 'Billing');

-- Emergency Room & Triage Permissions
INSERT INTO `permissions` (`permission_name`, `permission_description`, `module`) VALUES
('er.triage', 'Perform triage assessment', 'Emergency Room'),
('er.view_dashboard', 'View ER dashboard', 'Emergency Room'),
('er.transfer', 'Transfer patients', 'Emergency Room'),
('er.discharge', 'Discharge patients from ER', 'Emergency Room'),
('er.view_all', 'View all ER records', 'Emergency Room'),
('er.vital_signs', 'Record vital signs', 'Emergency Room');

-- Inpatient Management Permissions
INSERT INTO `permissions` (`permission_name`, `permission_description`, `module`) VALUES
('inpatient.admit', 'Admit patients', 'Inpatient'),
('inpatient.discharge', 'Discharge patients', 'Inpatient'),
('inpatient.transfer', 'Transfer patients between beds', 'Inpatient'),
('inpatient.bed_management', 'Manage bed assignments', 'Inpatient'),
('inpatient.view_all', 'View all inpatient records', 'Inpatient'),
('inpatient.view_assignments', 'View bed assignments', 'Inpatient');

-- Telehealth Permissions
INSERT INTO `permissions` (`permission_name`, `permission_description`, `module`) VALUES
('telehealth.consult', 'Conduct telehealth consultations', 'Telehealth'),
('telehealth.view', 'View telehealth consultations', 'Telehealth'),
('telehealth.prescriptions', 'Create e-prescriptions', 'Telehealth'),
('telehealth.lab_orders', 'Create e-lab orders', 'Telehealth'),
('telehealth.followup', 'Schedule follow-up appointments', 'Telehealth'),
('telehealth.process_prescriptions', 'Process prescriptions', 'Telehealth'),
('telehealth.process_labs', 'Process lab orders', 'Telehealth');

-- Reports Permissions
INSERT INTO `permissions` (`permission_name`, `permission_description`, `module`) VALUES
('reports.view_all', 'View all reports', 'Reports'),
('reports.patients', 'Generate patient reports', 'Reports'),
('reports.appointments', 'Generate appointment reports', 'Reports'),
('reports.billing', 'Generate billing reports', 'Reports'),
('reports.er', 'Generate ER reports', 'Reports'),
('reports.beds', 'Generate bed occupancy reports', 'Reports'),
('reports.users', 'Generate user activity reports', 'Reports'),
('reports.system', 'Generate system reports', 'Reports'),
('reports.export', 'Export reports to Excel/PDF', 'Reports');

-- Insurance Management Permissions
INSERT INTO `permissions` (`permission_name`, `permission_description`, `module`) VALUES
('insurance.create', 'Add insurance providers', 'Insurance'),
('insurance.edit', 'Edit insurance providers', 'Insurance'),
('insurance.approve', 'Approve insurance providers', 'Insurance'),
('insurance.view_all', 'View all insurance providers', 'Insurance'),
('insurance.claims', 'Manage insurance claims', 'Insurance'),
('insurance.setup', 'Setup insurance information', 'Insurance');

-- General Permissions
INSERT INTO `permissions` (`permission_name`, `permission_description`, `module`) VALUES
('dashboard.view', 'View dashboard', 'General'),
('profile.edit', 'Edit own profile', 'General'),
('profile.view', 'View own profile', 'General'),
('notifications.view', 'View notifications', 'General'),
('search.patients', 'Search patients', 'General'),
('search.users', 'Search users', 'General');

