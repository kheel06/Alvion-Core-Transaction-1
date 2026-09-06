-- =====================================================
-- Patient Queue: seed data for modules/general/patient_queue.php
-- (Waiting Queue + In Consultation for OPD/ER/Clinic).
-- Run after: general_modules.sql (creates patient_queue),
--            seed_admin_data.sql (patients 1–30, doctors 3–7 in users).
-- patient_id = patients.id (real patients from patients table).
-- doctor_id = users.id (doctors: 3=Santos, 4=Reyes, 5=Cruz, 6=Garcia, 7=Delos Reyes).
-- created_by = 11 (receptionist Jennifer Lopez).
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- Clear existing queue data
DELETE FROM `patient_queue`;

-- OPD: 2 waiting, 2 in consultation (different patients each)
INSERT INTO `patient_queue` (`patient_id`, `appointment_id`, `doctor_id`, `clinic_type`, `queue_number`, `status`, `priority`, `check_in_time`, `called_at`, `created_by`, `created_at`) VALUES
(1, NULL, 4, 'OPD', 1, 'waiting', 'normal', DATE_SUB(NOW(), INTERVAL 25 MINUTE), NULL, 11, NOW()),
(3, NULL, 5, 'OPD', 2, 'waiting', 'urgent', DATE_SUB(NOW(), INTERVAL 15 MINUTE), NULL, 11, NOW()),
(9, NULL, 3, 'OPD', 3, 'in_progress', 'normal', DATE_SUB(NOW(), INTERVAL 45 MINUTE), DATE_SUB(NOW(), INTERVAL 30 MINUTE), 11, NOW()),
(6, NULL, 7, 'OPD', 4, 'in_progress', 'urgent', DATE_SUB(NOW(), INTERVAL 35 MINUTE), DATE_SUB(NOW(), INTERVAL 20 MINUTE), 11, NOW());

-- ER: 1 waiting, 1 in consultation (different patients)
INSERT INTO `patient_queue` (`patient_id`, `appointment_id`, `doctor_id`, `clinic_type`, `queue_number`, `status`, `priority`, `check_in_time`, `called_at`, `created_by`, `created_at`) VALUES
(5, NULL, 6, 'ER', 1, 'waiting', 'emergency', DATE_SUB(NOW(), INTERVAL 10 MINUTE), NULL, 11, NOW()),
(8, NULL, 3, 'ER', 2, 'in_progress', 'urgent', DATE_SUB(NOW(), INTERVAL 20 MINUTE), DATE_SUB(NOW(), INTERVAL 12 MINUTE), 11, NOW());

-- Clinic: 1 waiting, 1 in consultation (different patients)
INSERT INTO `patient_queue` (`patient_id`, `appointment_id`, `doctor_id`, `clinic_type`, `queue_number`, `status`, `priority`, `check_in_time`, `called_at`, `created_by`, `created_at`) VALUES
(12, NULL, 4, 'Clinic', 1, 'waiting', 'normal', DATE_SUB(NOW(), INTERVAL 5 MINUTE), NULL, 11, NOW()),
(7, NULL, 5, 'Clinic', 2, 'in_progress', 'normal', DATE_SUB(NOW(), INTERVAL 30 MINUTE), DATE_SUB(NOW(), INTERVAL 18 MINUTE), 11, NOW());

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
