-- =====================================================
-- Teleconsultations Table
-- =====================================================
-- This table stores teleconsultation/telemedicine records
-- Used for remote consultations, telehealth services, and virtual appointments

CREATE TABLE IF NOT EXISTS `teleconsultations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `consultation_number` varchar(20) NOT NULL COMMENT 'Unique consultation number (e.g., TEL-2024-001)',
  `patient_id` int(11) NOT NULL COMMENT 'Reference to patients table',
  `doctor_id` int(11) NOT NULL COMMENT 'Reference to users table (doctor)',
  `consultation_date` datetime NOT NULL COMMENT 'Date and time of consultation',
  `status` enum('scheduled','ongoing','completed','cancelled','no_show') DEFAULT 'scheduled' COMMENT 'Consultation status',
  `consultation_notes` text DEFAULT NULL COMMENT 'Notes from the consultation',
  `diagnosis` text DEFAULT NULL COMMENT 'Diagnosis made during consultation',
  `prescription` text DEFAULT NULL COMMENT 'Prescription given',
  `follow_up_required` tinyint(1) DEFAULT 0 COMMENT 'Whether follow-up is required',
  `follow_up_date` date DEFAULT NULL COMMENT 'Scheduled follow-up date',
  `video_link` varchar(255) DEFAULT NULL COMMENT 'Video conference link (if applicable)',
  `consultation_fee` decimal(10,2) DEFAULT 0.00 COMMENT 'Fee for the consultation',
  `reason` text DEFAULT NULL COMMENT 'Reason for consultation',
  `symptoms` text DEFAULT NULL COMMENT 'Patient symptoms',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `consultation_number` (`consultation_number`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `consultation_date` (`consultation_date`),
  KEY `status` (`status`),
  CONSTRAINT `fk_teleconsultations_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_teleconsultations_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- Sample Data for Teleconsultations
-- =====================================================
-- Note: This assumes you have patients and users (doctors) in your database
-- Adjust patient_id and doctor_id values based on your actual data

-- Get sample IDs
SET @patient1_id = (SELECT id FROM patients LIMIT 1 OFFSET 0);
SET @patient2_id = (SELECT id FROM patients LIMIT 1 OFFSET 1);
SET @patient3_id = (SELECT id FROM patients LIMIT 1 OFFSET 2);
SET @patient4_id = (SELECT id FROM patients LIMIT 1 OFFSET 3);
SET @patient5_id = (SELECT id FROM patients LIMIT 1 OFFSET 4);

-- Get sample doctor ID
SET @doctor1_id = (SELECT id FROM users WHERE role_id IN (SELECT id FROM roles WHERE role_name = 'doctor' OR role_name = 'admin') LIMIT 1);

-- Insert sample teleconsultation records
INSERT INTO `teleconsultations` (
    `consultation_number`,
    `patient_id`,
    `doctor_id`,
    `consultation_date`,
    `status`,
    `consultation_notes`,
    `diagnosis`,
    `prescription`,
    `follow_up_required`,
    `follow_up_date`,
    `video_link`,
    `consultation_fee`,
    `reason`,
    `symptoms`
)
SELECT 
    CONCAT('TEL-', DATE_FORMAT(CURDATE(), '%Y%m%d'), '-', LPAD(ROW_NUMBER() OVER (ORDER BY p.id), 3, '0')) as consultation_number,
    p.id as patient_id,
    @doctor1_id as doctor_id,
    DATE_ADD(NOW(), INTERVAL FLOOR(RAND() * 30) DAY) + INTERVAL FLOOR(RAND() * 24) HOUR as consultation_date,
    ELT(1 + FLOOR(RAND() * 5), 'scheduled', 'ongoing', 'completed', 'cancelled', 'no_show') as status,
    CONCAT('Patient consultation for ', ELT(1 + FLOOR(RAND() * 5), 'routine checkup', 'follow-up', 'new complaint', 'medication review', 'symptom evaluation')) as consultation_notes,
    ELT(1 + FLOOR(RAND() * 10),
        'Hypertension',
        'Diabetes Mellitus Type 2',
        'Upper Respiratory Tract Infection',
        'Gastroenteritis',
        'Migraine',
        'Anxiety',
        'Insomnia',
        'Allergic Rhinitis',
        'Acid Reflux',
        'Muscle Strain'
    ) as diagnosis,
    CASE 
        WHEN RAND() < 0.7 THEN CONCAT('Prescribed: ', ELT(1 + FLOOR(RAND() * 5), 'Antibiotics', 'Pain relievers', 'Antihistamines', 'Antacids', 'Vitamins'))
        ELSE NULL
    END as prescription,
    CASE WHEN RAND() < 0.3 THEN 1 ELSE 0 END as follow_up_required,
    CASE 
        WHEN RAND() < 0.3 THEN DATE_ADD(CURDATE(), INTERVAL FLOOR(7 + RAND() * 14) DAY)
        ELSE NULL
    END as follow_up_date,
    CASE 
        WHEN RAND() < 0.5 THEN CONCAT('https://meet.example.com/room-', FLOOR(1000 + RAND() * 9000))
        ELSE NULL
    END as video_link,
    ROUND(500 + (RAND() * 1500), 2) as consultation_fee,
    ELT(1 + FLOOR(RAND() * 8),
        'Routine checkup',
        'Follow-up appointment',
        'New symptoms',
        'Medication review',
        'Second opinion',
        'Chronic condition management',
        'Preventive care',
        'Health consultation'
    ) as reason,
    ELT(1 + FLOOR(RAND() * 10),
        'Fever and cough',
        'Headache',
        'Stomach pain',
        'Difficulty sleeping',
        'Anxiety symptoms',
        'Allergy symptoms',
        'Joint pain',
        'Fatigue',
        'Dizziness',
        'Chest discomfort'
    ) as symptoms
FROM patients p
WHERE p.id IS NOT NULL
LIMIT 15;

-- =====================================================
-- Alternative: Manual Sample Data (if auto-generation doesn't work)
-- =====================================================
-- Uncomment and adjust these if the above SELECT doesn't work
/*
INSERT INTO `teleconsultations` (
    `consultation_number`,
    `patient_id`,
    `doctor_id`,
    `consultation_date`,
    `status`,
    `consultation_notes`,
    `diagnosis`,
    `prescription`,
    `consultation_fee`,
    `reason`,
    `symptoms`
) VALUES
('TEL-2024-001', 1, 1, NOW() + INTERVAL 2 DAY, 'scheduled', 'Routine checkup scheduled', NULL, NULL, 800.00, 'Routine checkup', 'None'),
('TEL-2024-002', 2, 1, NOW() + INTERVAL 5 DAY, 'scheduled', 'Follow-up appointment', 'Hypertension', 'Continue current medication', 800.00, 'Follow-up appointment', 'None'),
('TEL-2024-003', 3, 1, NOW() - INTERVAL 2 DAY, 'completed', 'Consultation completed successfully', 'Upper Respiratory Tract Infection', 'Antibiotics and rest', 1000.00, 'New symptoms', 'Fever and cough'),
('TEL-2024-004', 4, 1, NOW() - INTERVAL 1 DAY, 'completed', 'Patient showed improvement', 'Migraine', 'Pain relievers', 800.00, 'Follow-up appointment', 'Headache'),
('TEL-2024-005', 5, 1, NOW() + INTERVAL 3 DAY, 'scheduled', 'New consultation', NULL, NULL, 800.00, 'New symptoms', 'Stomach pain');
*/

-- =====================================================
-- Create indexes for better performance
-- =====================================================
-- Most indexes are already included in the CREATE TABLE statement
-- Additional composite indexes for common queries:
-- Optional indexes (skip if already exist)
-- CREATE INDEX idx_teleconsult_date_status ON teleconsultations (consultation_date, status);
-- CREATE INDEX idx_teleconsult_patient_date ON teleconsultations (patient_id, consultation_date);
-- CREATE INDEX idx_teleconsult_doctor_date ON teleconsultations (doctor_id, consultation_date);

-- =====================================================
-- Notes:
-- =====================================================
-- 1. This table requires the `patients` and `users` (doctors) tables to exist
-- 2. Consultation numbers should be unique and follow a pattern
-- 3. Status should be updated as consultation progresses
-- 4. Video links can be generated for video conferencing platforms
-- 5. Consultation fees can vary based on doctor or service type
-- 6. Run this file after creating the patients and users tables

