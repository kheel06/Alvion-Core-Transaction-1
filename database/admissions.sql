-- =====================================================
-- Admissions Table
-- =====================================================
-- This table stores patient admission records
-- Used for inpatient management, bed tracking, and discharge management

CREATE TABLE IF NOT EXISTS `admissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admission_number` varchar(20) NOT NULL COMMENT 'Unique admission number (e.g., ADM-2024-001)',
  `patient_id` int(11) NOT NULL COMMENT 'Reference to patients table',
  `admitting_doctor_id` int(11) DEFAULT NULL COMMENT 'Reference to users table (doctor)',
  `bed_id` int(11) DEFAULT NULL COMMENT 'Reference to beds table',
  `admission_date` datetime NOT NULL COMMENT 'Date and time of admission',
  `admission_type` enum('emergency','scheduled','transfer') NOT NULL COMMENT 'Type of admission',
  `diagnosis` text DEFAULT NULL COMMENT 'Primary diagnosis',
  `reason_for_admission` text DEFAULT NULL COMMENT 'Reason for admission',
  `status` enum('admitted','discharged','transferred') DEFAULT 'admitted' COMMENT 'Current admission status',
  `expected_discharge_date` date DEFAULT NULL COMMENT 'Expected discharge date',
  `actual_discharge_date` datetime DEFAULT NULL COMMENT 'Actual discharge date and time',
  `discharge_summary` text DEFAULT NULL COMMENT 'Discharge summary notes',
  `created_by` int(11) DEFAULT NULL COMMENT 'User who created the admission',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `admission_number` (`admission_number`),
  KEY `patient_id` (`patient_id`),
  KEY `admitting_doctor_id` (`admitting_doctor_id`),
  KEY `bed_id` (`bed_id`),
  KEY `created_by` (`created_by`),
  KEY `admission_date` (`admission_date`),
  KEY `status` (`status`),
  KEY `admission_type` (`admission_type`),
  CONSTRAINT `fk_admissions_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_admissions_doctor` FOREIGN KEY (`admitting_doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_admissions_bed` FOREIGN KEY (`bed_id`) REFERENCES `beds` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_admissions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- Sample Data for Admissions
-- =====================================================
-- Note: This assumes you have patients, users (doctors), and beds in your database
-- Adjust patient_id, admitting_doctor_id, and bed_id values based on your actual data

-- Get sample IDs (using subquery to get existing records)
SET @patient1_id = (SELECT id FROM patients LIMIT 1 OFFSET 0);
SET @patient2_id = (SELECT id FROM patients LIMIT 1 OFFSET 1);
SET @patient3_id = (SELECT id FROM patients LIMIT 1 OFFSET 2);
SET @patient4_id = (SELECT id FROM patients LIMIT 1 OFFSET 3);
SET @patient5_id = (SELECT id FROM patients LIMIT 1 OFFSET 4);

-- Get sample doctor ID
SET @doctor1_id = (SELECT id FROM users WHERE role_id IN (SELECT id FROM roles WHERE role_name = 'doctor' OR role_name = 'admin') LIMIT 1);

-- Get sample bed IDs (available beds)
SET @bed1_id = (SELECT id FROM beds WHERE status = 'available' LIMIT 1 OFFSET 0);
SET @bed2_id = (SELECT id FROM beds WHERE status = 'available' LIMIT 1 OFFSET 1);
SET @bed3_id = (SELECT id FROM beds WHERE status = 'available' LIMIT 1 OFFSET 2);

-- Get sample user ID for created_by
SET @user1_id = (SELECT id FROM users LIMIT 1);

-- Insert sample admission records
-- Only insert if we have at least one patient and bed
INSERT INTO `admissions` (
    `admission_number`,
    `patient_id`,
    `admitting_doctor_id`,
    `bed_id`,
    `admission_date`,
    `admission_type`,
    `diagnosis`,
    `reason_for_admission`,
    `status`,
    `expected_discharge_date`,
    `actual_discharge_date`,
    `discharge_summary`,
    `created_by`
)
SELECT 
    CONCAT('ADM-', DATE_FORMAT(CURDATE(), '%Y%m%d'), '-', LPAD(ROW_NUMBER() OVER (ORDER BY p.id), 3, '0')) as admission_number,
    p.id as patient_id,
    @doctor1_id as admitting_doctor_id,
    (SELECT id FROM beds WHERE status = 'available' ORDER BY RAND() LIMIT 1) as bed_id,
    DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 10) DAY) as admission_date,
    ELT(1 + FLOOR(RAND() * 3), 'emergency', 'scheduled', 'transfer') as admission_type,
    ELT(1 + FLOOR(RAND() * 10), 
        'Pneumonia', 
        'Hypertension', 
        'Diabetes Mellitus Type 2', 
        'Acute Appendicitis',
        'Fracture - Right Arm',
        'Acute Myocardial Infarction',
        'Stroke',
        'Gastroenteritis',
        'Urinary Tract Infection',
        'Bronchitis'
    ) as diagnosis,
    CONCAT('Patient admitted for ', ELT(1 + FLOOR(RAND() * 5), 'observation', 'surgery', 'treatment', 'monitoring', 'recovery')) as reason_for_admission,
    CASE 
        WHEN RAND() < 0.3 THEN 'discharged'
        WHEN RAND() < 0.5 THEN 'transferred'
        ELSE 'admitted'
    END as status,
    DATE_ADD(DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 10) DAY), INTERVAL FLOOR(3 + RAND() * 7) DAY) as expected_discharge_date,
    CASE 
        WHEN RAND() < 0.3 THEN DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 5) DAY)
        ELSE NULL
    END as actual_discharge_date,
    CASE 
        WHEN RAND() < 0.3 THEN CONCAT('Patient discharged in stable condition. Follow-up recommended in ', FLOOR(1 + RAND() * 4), ' weeks.')
        ELSE NULL
    END as discharge_summary,
    @user1_id as created_by
FROM patients p
WHERE p.id IS NOT NULL
  AND EXISTS (SELECT 1 FROM beds WHERE status = 'available' LIMIT 1)
LIMIT 15;

-- Update bed status for admitted patients
UPDATE beds b
INNER JOIN admissions a ON b.id = a.bed_id
SET b.status = 'occupied',
    b.current_patient_id = a.patient_id
WHERE a.status = 'admitted';

-- =====================================================
-- Alternative: Manual Sample Data (if auto-generation doesn't work)
-- =====================================================
-- Uncomment and adjust these if the above SELECT doesn't work
/*
INSERT INTO `admissions` (
    `admission_number`,
    `patient_id`,
    `admitting_doctor_id`,
    `bed_id`,
    `admission_date`,
    `admission_type`,
    `diagnosis`,
    `reason_for_admission`,
    `status`,
    `expected_discharge_date`,
    `created_by`
) VALUES
('ADM-2024-001', 1, 1, 1, NOW() - INTERVAL 5 DAY, 'emergency', 'Pneumonia', 'Patient admitted for observation and treatment', 'admitted', CURDATE() + INTERVAL 3 DAY, 1),
('ADM-2024-002', 2, 1, 2, NOW() - INTERVAL 3 DAY, 'scheduled', 'Hypertension', 'Scheduled admission for monitoring', 'admitted', CURDATE() + INTERVAL 5 DAY, 1),
('ADM-2024-003', 3, 1, 3, NOW() - INTERVAL 10 DAY, 'emergency', 'Acute Appendicitis', 'Emergency surgery required', 'discharged', CURDATE() - INTERVAL 2 DAY, 1),
('ADM-2024-004', 4, 1, 4, NOW() - INTERVAL 2 DAY, 'transfer', 'Diabetes Mellitus Type 2', 'Transferred from ER for further treatment', 'admitted', CURDATE() + INTERVAL 4 DAY, 1),
('ADM-2024-005', 5, 1, 5, NOW() - INTERVAL 7 DAY, 'scheduled', 'Fracture - Right Arm', 'Scheduled surgery and recovery', 'admitted', CURDATE() + INTERVAL 2 DAY, 1);
*/

-- =====================================================
-- Create indexes for better performance
-- =====================================================
-- Most indexes are already included in the CREATE TABLE statement
-- Additional composite indexes for common queries:
-- CREATE INDEX (skip if already exist — MySQL does not support IF NOT EXISTS for indexes)
-- CREATE INDEX `idx_admissions_date_status` ON `admissions` (`admission_date`, `status`);
-- CREATE INDEX `idx_admissions_patient_status` ON `admissions` (`patient_id`, `status`);
-- CREATE INDEX `idx_admissions_bed_status` ON `admissions` (`bed_id`, `status`);

-- =====================================================
-- Notes:
-- =====================================================
-- 1. This table requires the `patients` table to exist
-- 2. Foreign keys to `users` (doctors) and `beds` are optional
-- 3. Admission numbers should be unique and follow a pattern
-- 4. Status should be updated when patients are discharged or transferred
-- 5. Bed status should be updated when admission is created/discharged
-- 6. Run this file after creating the patients, users, and beds tables

