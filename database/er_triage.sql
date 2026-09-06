-- =====================================================
-- ER Triage Table
-- =====================================================
-- This table stores Emergency Room triage records
-- Used for ER patient management, priority tracking, and statistics

CREATE TABLE IF NOT EXISTS `er_triage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL COMMENT 'Reference to patients table',
  `triage_nurse_id` int(11) DEFAULT NULL COMMENT 'Reference to users table (nurse who performed triage)',
  `chief_complaint` text NOT NULL COMMENT 'Patient chief complaint',
  `triage_level` enum('resuscitation','emergency','urgent','semi_urgent','non_urgent') NOT NULL COMMENT 'Triage priority level',
  `vital_signs` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON object containing vital signs',
  `initial_assessment` text DEFAULT NULL COMMENT 'Initial assessment notes',
  `priority_score` int(11) DEFAULT NULL COMMENT 'Numeric priority score (1=highest, 5=lowest)',
  `status` enum('waiting','in_progress','admitted','discharged','transferred') DEFAULT 'waiting' COMMENT 'Current status',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `triage_nurse_id` (`triage_nurse_id`),
  KEY `triage_level` (`triage_level`),
  KEY `status` (`status`),
  KEY `priority_score` (`priority_score`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_er_triage_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_er_triage_nurse` FOREIGN KEY (`triage_nurse_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- Sample Data for ER Triage
-- =====================================================
-- Note: This assumes you have patients and users (nurses) in your database
-- Adjust patient_id and triage_nurse_id values based on your actual data

-- Get sample IDs
SET @patient1_id = (SELECT id FROM patients LIMIT 1 OFFSET 0);
SET @patient2_id = (SELECT id FROM patients LIMIT 1 OFFSET 1);
SET @patient3_id = (SELECT id FROM patients LIMIT 1 OFFSET 2);
SET @patient4_id = (SELECT id FROM patients LIMIT 1 OFFSET 3);
SET @patient5_id = (SELECT id FROM patients LIMIT 1 OFFSET 4);

-- Get sample nurse ID
SET @nurse1_id = (SELECT id FROM users WHERE role_id IN (SELECT id FROM roles WHERE role_name = 'nurse' OR role_name = 'admin') LIMIT 1);

-- Get sample user ID for created_by
SET @user1_id = (SELECT id FROM users LIMIT 1);

-- Insert sample ER triage records
INSERT INTO `er_triage` (
    `patient_id`,
    `triage_nurse_id`,
    `chief_complaint`,
    `triage_level`,
    `vital_signs`,
    `initial_assessment`,
    `priority_score`,
    `status`,
    `created_at`
)
SELECT 
    p.id as patient_id,
    @nurse1_id as triage_nurse_id,
    ELT(1 + FLOOR(RAND() * 15),
        'Chest pain',
        'Shortness of breath',
        'Severe abdominal pain',
        'Head injury',
        'High fever',
        'Severe headache',
        'Unconscious',
        'Difficulty breathing',
        'Severe trauma',
        'Seizure',
        'Severe allergic reaction',
        'Burns',
        'Poisoning',
        'Cardiac arrest',
        'Stroke symptoms'
    ) as chief_complaint,
    ELT(1 + FLOOR(RAND() * 5), 'resuscitation', 'emergency', 'urgent', 'semi_urgent', 'non_urgent') as triage_level,
    JSON_OBJECT(
        'blood_pressure', CONCAT(FLOOR(90 + RAND() * 40), '/', FLOOR(60 + RAND() * 30)),
        'heart_rate', FLOOR(60 + RAND() * 60),
        'respiratory_rate', FLOOR(12 + RAND() * 20),
        'temperature', ROUND(36.5 + RAND() * 2.5, 1),
        'oxygen_saturation', FLOOR(85 + RAND() * 15),
        'pain_level', FLOOR(1 + RAND() * 10)
    ) as vital_signs,
    CONCAT('Patient presents with ', ELT(1 + FLOOR(RAND() * 5), 'acute', 'severe', 'moderate', 'mild', 'chronic'), ' symptoms. Initial assessment completed.') as initial_assessment,
    CASE 
        WHEN RAND() < 0.1 THEN 1  -- resuscitation
        WHEN RAND() < 0.3 THEN 2  -- emergency
        WHEN RAND() < 0.6 THEN 3  -- urgent
        WHEN RAND() < 0.85 THEN 4 -- semi_urgent
        ELSE 5                     -- non_urgent
    END as priority_score,
    ELT(1 + FLOOR(RAND() * 5), 'waiting', 'in_progress', 'admitted', 'discharged', 'transferred') as status,
    DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 30) DAY) + INTERVAL FLOOR(RAND() * 24) HOUR as created_at
FROM patients p
WHERE p.id IS NOT NULL
LIMIT 20;

-- =====================================================
-- Alternative: Manual Sample Data (if auto-generation doesn't work)
-- =====================================================
-- Uncomment and adjust these if the above SELECT doesn't work
/*
INSERT INTO `er_triage` (
    `patient_id`,
    `triage_nurse_id`,
    `chief_complaint`,
    `triage_level`,
    `vital_signs`,
    `initial_assessment`,
    `priority_score`,
    `status`
) VALUES
(1, 1, 'Chest pain radiating to left arm', 'emergency', 
 '{"blood_pressure":"140/90","heart_rate":95,"respiratory_rate":18,"temperature":37.2,"oxygen_saturation":98,"pain_level":8}',
 'Patient presents with acute chest pain. ECG ordered. Cardiac enzymes pending.', 2, 'in_progress'),
(2, 1, 'Severe abdominal pain', 'urgent',
 '{"blood_pressure":"130/85","heart_rate":88,"respiratory_rate":16,"temperature":38.5,"oxygen_saturation":97,"pain_level":9}',
 'Acute abdomen. Guarding and rebound tenderness present. Surgical consult requested.', 3, 'waiting'),
(3, 1, 'Unconscious after fall', 'resuscitation',
 '{"blood_pressure":"90/60","heart_rate":110,"respiratory_rate":22,"temperature":36.8,"oxygen_saturation":92,"pain_level":0}',
 'Patient unresponsive. GCS 8. CT head ordered. Neurosurgery consult.', 1, 'in_progress'),
(4, 1, 'High fever and cough', 'semi_urgent',
 '{"blood_pressure":"120/80","heart_rate":85,"respiratory_rate":20,"temperature":39.2,"oxygen_saturation":96,"pain_level":4}',
 'Fever and productive cough. Chest X-ray ordered. Likely pneumonia.', 4, 'waiting'),
(5, 1, 'Minor cut on hand', 'non_urgent',
 '{"blood_pressure":"115/75","heart_rate":72,"respiratory_rate":14,"temperature":36.5,"oxygen_saturation":99,"pain_level":3}',
 'Superficial laceration. Wound cleaning and suturing required.', 5, 'waiting');
*/

-- =====================================================
-- Create indexes for better performance
-- =====================================================
-- Most indexes are already included in the CREATE TABLE statement
-- Additional composite indexes for common queries:
-- Optional indexes (skip if already exist)
-- CREATE INDEX idx_er_triage_status_level ON er_triage (status, triage_level);
-- CREATE INDEX idx_er_triage_date_status ON er_triage (created_at, status);
-- CREATE INDEX idx_er_triage_priority ON er_triage (priority_score, status);

-- =====================================================
-- Notes:
-- =====================================================
-- 1. This table requires the `patients` table to exist
-- 2. Foreign key to `users` (nurses) is optional (triage_nurse_id can be NULL)
-- 3. Vital signs are stored as JSON for flexibility
-- 4. Priority score: 1=resuscitation, 2=emergency, 3=urgent, 4=semi_urgent, 5=non_urgent
-- 5. Status should be updated as patient progresses through ER
-- 6. Run this file after creating the patients and users tables

