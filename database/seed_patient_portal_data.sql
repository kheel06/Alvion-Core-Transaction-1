-- =====================================================
-- Patient portal real data: EHR + e_prescriptions for
-- patient_id 1 (Jose Rizal, linked to user id 2 via created_by).
-- Run after: 02_align, seed_admin_data (1,2,3), ehr.sql, 03_ehr_reference_patients.sql
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- Optional: set patient user (id 2) password to Hospital@2026 for testing
UPDATE `users` SET `password` = '$2y$10$8KzQHZxGkO1rUjKxLQ5Yku0VZ6TvMN3zp4.wB1qRHmK9vG5Xh2sFe' WHERE `id` = 2 AND `role_id` = 4 LIMIT 1;

-- =====================================================
-- 1. E-PRESCRIPTIONS TABLE (if not exists)
-- patient_id = patients.id so patient portal can show data
-- =====================================================
CREATE TABLE IF NOT EXISTS `e_prescriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prescription_number` varchar(50) NOT NULL,
  `patient_id` int(11) NOT NULL COMMENT 'patients.id',
  `doctor_id` int(11) NOT NULL COMMENT 'users.id',
  `consultation_id` int(11) DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `prescription_date` date NOT NULL,
  `medications` json DEFAULT NULL COMMENT 'Array of med name, dosage, etc.',
  `instructions` text DEFAULT NULL,
  `status` enum('pending','filled','partially_filled','cancelled') DEFAULT 'pending',
  `filled_at` datetime DEFAULT NULL,
  `filled_by` int(11) DEFAULT NULL COMMENT 'users.id (pharmacist)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `prescription_number` (`prescription_number`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `status` (`status`),
  CONSTRAINT `fk_e_prescriptions_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_e_prescriptions_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 2. EHR DIAGNOSES (patient_id = 1, doctors 3–7)
-- =====================================================
INSERT IGNORE INTO `ehr_diagnoses` (`patient_id`, `diagnosed_by`, `diagnosis_date`, `diagnosis_type`, `icd10_code`, `diagnosis_description`, `status`, `notes`, `created_at`) VALUES
(1, 3, '2024-06-15', 'primary', 'I10', 'Essential (primary) hypertension', 'active', 'BP monitoring. Lifestyle and medication.', NOW()),
(1, 3, '2024-09-20', 'secondary', 'E66.9', 'Obesity, unspecified', 'active', 'Weight management advised.', NOW()),
(1, 4, '2025-01-10', 'primary', 'J00', 'Acute nasopharyngitis (common cold)', 'resolved', 'Symptomatic treatment.', NOW());

-- =====================================================
-- 3. EHR MEDICATIONS (patient_id = 1)
-- =====================================================
INSERT IGNORE INTO `ehr_medications` (`patient_id`, `prescribed_by`, `prescription_date`, `medication_name`, `dosage`, `frequency`, `route`, `start_date`, `end_date`, `status`, `instructions`, `created_at`) VALUES
(1, 3, '2024-06-20', 'Amlodipine', '5 mg', 'Once daily', 'oral', '2024-06-20', NULL, 'active', 'Take in the morning.', NOW()),
(1, 3, '2024-06-20', 'Losartan', '50 mg', 'Once daily', 'oral', '2024-06-20', NULL, 'active', 'Take with or without food.', NOW()),
(1, 4, '2025-01-10', 'Paracetamol', '500 mg', 'Every 6 hours as needed', 'oral', '2025-01-10', '2025-01-17', 'completed', 'For fever and pain.', NOW());

-- =====================================================
-- 4. EHR LAB RESULTS (patient_id = 1)
-- =====================================================
INSERT IGNORE INTO `ehr_lab_results` (`patient_id`, `ordered_by`, `order_date`, `test_name`, `test_type`, `result_date`, `result_value`, `reference_range`, `unit`, `status`, `abnormal_flag`, `notes`, `created_at`) VALUES
(1, 3, '2024-06-18 08:00:00', 'Fasting Blood Sugar', 'blood', '2024-06-19 10:00:00', '98', '70-100', 'mg/dL', 'completed', 'normal', NULL, NOW()),
(1, 3, '2024-06-18 08:00:00', 'Serum Creatinine', 'blood', '2024-06-19 10:00:00', '0.9', '0.7-1.3', 'mg/dL', 'completed', 'normal', NULL, NOW()),
(1, 3, '2024-06-18 08:00:00', 'Lipid Panel - Total Cholesterol', 'blood', '2024-06-19 10:00:00', '195', '<200', 'mg/dL', 'completed', 'normal', NULL, NOW()),
(1, 4, '2025-01-12 09:00:00', 'CBC - Hemoglobin', 'blood', '2025-01-13 14:00:00', '14.2', '13.5-17.5', 'g/dL', 'completed', 'normal', NULL, NOW());

-- =====================================================
-- 5. EHR PROCEDURES (patient_id = 1)
-- =====================================================
INSERT IGNORE INTO `ehr_procedures` (`patient_id`, `performed_by`, `procedure_date`, `procedure_name`, `procedure_type`, `description`, `outcome`, `follow_up_required`, `created_at`) VALUES
(1, 3, '2024-07-01 09:00:00', 'ECG', 'diagnostic', '12-lead ECG for hypertension baseline.', 'Normal sinus rhythm.', 0, NOW()),
(1, 3, '2024-09-25 10:30:00', 'Physical examination - annual', 'preventive', 'Routine annual physical.', 'Within normal limits.', 1, NOW());

-- =====================================================
-- 6. EHR IMMUNIZATIONS (patient_id = 1)
-- =====================================================
INSERT IGNORE INTO `ehr_immunizations` (`patient_id`, `administered_by`, `vaccine_name`, `administration_date`, `dose_number`, `route`, `created_at`) VALUES
(1, 8, 'Influenza (Flu)', '2024-10-15', 1, 'injection', NOW()),
(1, 8, 'COVID-19 (Bivalent)', '2024-11-01', 1, 'injection', NOW());

-- =====================================================
-- 7. E-PRESCRIPTIONS (patient_id = 1, doctor_id 3/4)
-- =====================================================
INSERT INTO `e_prescriptions` (`prescription_number`, `patient_id`, `doctor_id`, `consultation_id`, `appointment_id`, `prescription_date`, `medications`, `instructions`, `status`, `created_at`) VALUES
('RX20251201001', 1, 3, NULL, 1, '2025-12-01', '[{"name":"Amlodipine","dosage":"5mg","frequency":"OD"}]', 'Take in the morning. Monitor BP.', 'filled', NOW()),
('RX20260211002', 1, 4, NULL, 28, '2026-02-11', '[{"name":"Losartan","dosage":"50mg","frequency":"OD"}]', 'Continue as directed. Follow-up in 4 weeks.', 'pending', NOW()),
('RX20260212003', 1, 1, NULL, NULL, CURDATE(), '[{"name":"Paracetamol","dosage":"500mg","frequency":"2x daily","duration":"7 days","quantity":"14 tablets","instructions":"Take with food"}]', 'For fever or pain. Do not exceed 8 tablets in 24 hours.', 'pending', NOW())
ON DUPLICATE KEY UPDATE `instructions` = VALUES(`instructions`);

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
