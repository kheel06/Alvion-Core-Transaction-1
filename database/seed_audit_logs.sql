-- =====================================================
-- Audit Logs seed data for admin/system/audit_logs.php
-- Run after: seed_admin_data.sql (users 1-16 exist)
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM audit_logs;

INSERT INTO audit_logs (user_id, action, module, record_id, old_values, new_values, ip_address, user_agent, created_at) VALUES
(1, 'login', 'auth', NULL, NULL, '{"details":"Admin login from dashboard"}', '192.168.1.100', 'Mozilla/5.0 Windows NT 10.0', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(3, 'login', 'auth', NULL, NULL, '{"details":"Dr. Santos logged in"}', '192.168.1.101', 'Mozilla/5.0 Windows NT 10.0', DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(4, 'login', 'auth', NULL, NULL, '{"details":"Dr. Reyes logged in"}', '192.168.1.102', 'Mozilla/5.0 Windows NT 10.0', DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(1, 'create', 'patients', 30, NULL, '{"details":"Registered new patient Carlos Garcia"}', '192.168.1.100', 'Mozilla/5.0 Windows NT 10.0', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(3, 'update', 'appointments', 5, '{"status":"scheduled"}', '{"status":"completed","details":"Appointment marked as completed"}', '192.168.1.101', 'Mozilla/5.0 Windows NT 10.0', DATE_SUB(NOW(), INTERVAL 90 MINUTE)),
(8, 'login', 'auth', NULL, NULL, '{"details":"Nurse Mendoza logged in"}', '192.168.1.103', 'Mozilla/5.0 Windows NT 10.0', DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(11, 'login', 'auth', NULL, NULL, '{"details":"Receptionist Lopez logged in"}', '192.168.1.104', 'Mozilla/5.0 Windows NT 10.0', DATE_SUB(NOW(), INTERVAL 6 HOUR)),
(11, 'create', 'patient_queue', 9, NULL, '{"details":"Added Jose Rizal to OPD queue"}', '192.168.1.104', 'Mozilla/5.0 Windows NT 10.0', DATE_SUB(NOW(), INTERVAL 25 MINUTE)),
(13, 'login', 'auth', NULL, NULL, '{"details":"Finance staff Dela Cruz logged in"}', '192.168.1.105', 'Mozilla/5.0 Windows NT 10.0', DATE_SUB(NOW(), INTERVAL 7 HOUR)),
(13, 'create', 'billing', 14, NULL, '{"details":"Created billing record for Pedro Paterno"}', '192.168.1.105', 'Mozilla/5.0 Windows NT 10.0', DATE_SUB(NOW(), INTERVAL 30 MINUTE)),
(1, 'update', 'users', 15, '{"status":"inactive"}', '{"status":"active","details":"Activated staff account Bryan Tan"}', '192.168.1.100', 'Mozilla/5.0 Windows NT 10.0', DATE_SUB(NOW(), INTERVAL 45 MINUTE)),
(3, 'logout', 'auth', NULL, NULL, '{"details":"Dr. Santos logged out"}', '192.168.1.101', 'Mozilla/5.0 Windows NT 10.0', DATE_SUB(NOW(), INTERVAL 30 MINUTE)),
(4, 'update', 'prescriptions', 3, NULL, '{"details":"Updated prescription for Maria Clara"}', '192.168.1.102', 'Mozilla/5.0 Windows NT 10.0', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(5, 'login', 'auth', NULL, NULL, '{"details":"Dr. Cruz logged in"}', '192.168.1.106', 'Mozilla/5.0 Windows NT 10.0', DATE_SUB(NOW(), INTERVAL 8 HOUR)),
(5, 'create', 'teleconsultations', 1, NULL, '{"details":"Started teleconsultation with Andres Bonifacio"}', '192.168.1.106', 'Mozilla/5.0 Windows NT 10.0', DATE_SUB(NOW(), INTERVAL 6 HOUR)),
(8, 'update', 'patient_queue', 11, '{"status":"waiting"}', '{"status":"in_progress","details":"Called patient Corazon Aquino for consultation"}', '192.168.1.103', 'Mozilla/5.0 Windows NT 10.0', DATE_SUB(NOW(), INTERVAL 45 MINUTE)),
(1, 'update', 'settings', 1, NULL, '{"details":"Updated system settings - Hospital Information"}', '192.168.1.100', 'Mozilla/5.0 Windows NT 10.0', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1, 'login', 'auth', NULL, NULL, '{"details":"Admin login"}', '192.168.1.100', 'Mozilla/5.0 Windows NT 10.0', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(6, 'login', 'auth', NULL, NULL, '{"details":"Dr. Garcia logged in"}', '192.168.1.107', 'Mozilla/5.0 Windows NT 10.0', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(6, 'create', 'lab_orders', 8, NULL, '{"details":"Ordered CBC and Urinalysis for Emilio Aguinaldo"}', '192.168.1.107', 'Mozilla/5.0 Windows NT 10.0', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(9, 'login', 'auth', NULL, NULL, '{"details":"Nurse Bautista logged in"}', '192.168.1.108', 'Mozilla/5.0 Windows NT 10.0', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(9, 'update', 'admissions', 2, '{"status":"admitted"}', '{"status":"discharged","details":"Patient Gabriela Silang discharged"}', '192.168.1.108', 'Mozilla/5.0 Windows NT 10.0', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(11, 'create', 'patients', 28, NULL, '{"details":"Registered walk-in patient Panday Pira"}', '192.168.1.104', 'Mozilla/5.0 Windows NT 10.0', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(14, 'login', 'auth', NULL, NULL, '{"details":"Finance staff Aquino logged in"}', '192.168.1.109', 'Mozilla/5.0 Windows NT 10.0', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(14, 'update', 'billing', 4, '{"payment_status":"pending"}', '{"payment_status":"partial","details":"Processed partial payment for Emilio Aguinaldo"}', '192.168.1.109', 'Mozilla/5.0 Windows NT 10.0', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1, 'delete', 'appointments', 12, '{"status":"scheduled"}', '{"details":"Cancelled appointment - patient no-show"}', '192.168.1.100', 'Mozilla/5.0 Windows NT 10.0', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(7, 'login', 'auth', NULL, NULL, '{"details":"Dr. Delos Reyes logged in"}', '192.168.1.110', 'Mozilla/5.0 Windows NT 10.0', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(7, 'create', 'prescriptions', 7, NULL, '{"details":"Prescribed medications for Manuel Quezon"}', '192.168.1.110', 'Mozilla/5.0 Windows NT 10.0', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(1, 'logout', 'auth', NULL, NULL, '{"details":"Admin logged out"}', '192.168.1.100', 'Mozilla/5.0 Windows NT 10.0', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(10, 'login', 'auth', NULL, NULL, '{"details":"Nurse Villanueva logged in"}', '192.168.1.111', 'Mozilla/5.0 Windows NT 10.0', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(10, 'update', 'admissions', 5, NULL, '{"details":"Updated vitals for patient in ICU"}', '192.168.1.111', 'Mozilla/5.0 Windows NT 10.0', DATE_SUB(NOW(), INTERVAL 4 DAY));

SET FOREIGN_KEY_CHECKS = 1;
