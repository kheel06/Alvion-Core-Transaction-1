-- =====================================================
-- Migration: Patient Categories & Doctor Enhancements
-- Run this migration to add patient types and doctor status
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- =====================================================
-- 1. Add patient_type to patients table (Outpatient/Inpatient)
-- =====================================================
-- Add patient_type column if it doesn't exist
SET @dbname = DATABASE();
SET @tablename = 'patients';
SET @columnname = 'patient_type';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `patient_type` ENUM(\'outpatient\', \'inpatient\') DEFAULT \'outpatient\' AFTER `status`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add admission_status column if it doesn't exist
SET @columnname = 'admission_status';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `admission_status` ENUM(\'active\', \'admitted\', \'discharged\') DEFAULT \'active\' AFTER `patient_type`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- =====================================================
-- 2. Create patient_medical_history table for tracking
-- pre-existing conditions, treatments, and medications
-- =====================================================
CREATE TABLE IF NOT EXISTS `patient_medical_history` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `patient_id` INT(11) NOT NULL,
    `condition_name` VARCHAR(255) NOT NULL,
    `condition_type` ENUM('pre_existing', 'current', 'past') DEFAULT 'pre_existing',
    `diagnosis_date` DATE DEFAULT NULL,
    `treatment` TEXT DEFAULT NULL,
    `medications` TEXT DEFAULT NULL,
    `treating_doctor` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `status` ENUM('active', 'resolved', 'ongoing') DEFAULT 'active',
    `created_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `patient_id` (`patient_id`),
    KEY `condition_type` (`condition_type`),
    KEY `status` (`status`),
    CONSTRAINT `fk_medical_history_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 3. Add doctor specialty categories and status
-- =====================================================
SET @tablename = 'doctors';

-- Add specialty_category
SET @columnname = 'specialty_category';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `specialty_category` ENUM(\'surgeon\', \'cardiologist\', \'pediatrician\', \'neurologist\', \'orthopedic\', \'obgyn\', \'internal_medicine\', \'dermatologist\', \'oncologist\', \'psychiatrist\', \'radiologist\', \'anesthesiologist\', \'general_practitioner\', \'other\') DEFAULT \'general_practitioner\' AFTER `specialty`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add doctor_type
SET @columnname = 'doctor_type';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `doctor_type` ENUM(\'attending\', \'resident\', \'fellow\', \'consultant\') DEFAULT \'attending\' AFTER `specialty_category`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add availability_status
SET @columnname = 'availability_status';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `availability_status` ENUM(\'available\', \'on_call\', \'busy\', \'off_duty\', \'on_leave\') DEFAULT \'available\' AFTER `doctor_type`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add first_name
SET @columnname = 'first_name';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `first_name` VARCHAR(100) DEFAULT NULL AFTER `prc_number`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add last_name
SET @columnname = 'last_name';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `last_name` VARCHAR(100) DEFAULT NULL AFTER `first_name`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add email
SET @columnname = 'email';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `email` VARCHAR(255) DEFAULT NULL AFTER `last_name`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add phone
SET @columnname = 'phone';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `phone` VARCHAR(20) DEFAULT NULL AFTER `email`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add profile_image
SET @columnname = 'profile_image';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `profile_image` VARCHAR(255) DEFAULT NULL AFTER `phone`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- =====================================================
-- 4. Create doctor_availability table for fixed schedules
-- Example: Dr. Samuel - M,W,F 9-12, 1-5
-- =====================================================
CREATE TABLE IF NOT EXISTS `doctor_availability` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `doctor_id` INT(11) NOT NULL,
    `day_of_week` TINYINT(1) NOT NULL COMMENT '0=Sunday, 1=Monday, ..., 6=Saturday',
    `shift_type` ENUM('morning', 'afternoon', 'evening', 'full_day') DEFAULT 'full_day',
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `is_available` TINYINT(1) DEFAULT 1,
    `notes` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_doctor_day_shift` (`doctor_id`, `day_of_week`, `shift_type`),
    KEY `doctor_id` (`doctor_id`),
    KEY `day_of_week` (`day_of_week`),
    CONSTRAINT `fk_availability_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 5. Seed doctor availability data (example schedules)
-- =====================================================
INSERT INTO `doctor_availability` (`doctor_id`, `day_of_week`, `shift_type`, `start_time`, `end_time`, `is_available`, `notes`) VALUES
-- Dr. 1 (Cardiologist) - M, W, F
(1, 1, 'morning', '09:00:00', '12:00:00', 1, 'Morning consultations'),
(1, 1, 'afternoon', '13:00:00', '17:00:00', 1, 'Afternoon consultations'),
(1, 3, 'morning', '09:00:00', '12:00:00', 1, 'Morning consultations'),
(1, 3, 'afternoon', '13:00:00', '17:00:00', 1, 'Afternoon consultations'),
(1, 5, 'morning', '09:00:00', '12:00:00', 1, 'Morning consultations'),
(1, 5, 'afternoon', '13:00:00', '17:00:00', 1, 'Afternoon consultations'),
-- Dr. 2 (OB-GYN) - T, Th
(2, 2, 'full_day', '08:00:00', '17:00:00', 1, 'Full day clinic'),
(2, 4, 'full_day', '08:00:00', '17:00:00', 1, 'Full day clinic'),
-- Dr. 3 (Pediatrician) - M, T, W, Th, F
(3, 1, 'morning', '08:00:00', '12:00:00', 1, 'Pediatric clinic'),
(3, 2, 'morning', '08:00:00', '12:00:00', 1, 'Pediatric clinic'),
(3, 3, 'morning', '08:00:00', '12:00:00', 1, 'Pediatric clinic'),
(3, 4, 'morning', '08:00:00', '12:00:00', 1, 'Pediatric clinic'),
(3, 5, 'morning', '08:00:00', '12:00:00', 1, 'Pediatric clinic'),
-- Dr. 4 (Neurologist) - T, F
(4, 2, 'full_day', '09:00:00', '16:00:00', 1, 'Neurology consultations'),
(4, 5, 'full_day', '09:00:00', '16:00:00', 1, 'Neurology consultations'),
-- Dr. 5 (Internal Medicine) - M, W, Th
(5, 1, 'full_day', '08:00:00', '17:00:00', 1, 'Internal medicine'),
(5, 3, 'afternoon', '13:00:00', '17:00:00', 1, 'Afternoon only'),
(5, 4, 'full_day', '08:00:00', '17:00:00', 1, 'Internal medicine'),
-- Dr. 6 (Orthopedic Surgeon) - T, Th, Sat
(6, 2, 'morning', '08:00:00', '12:00:00', 1, 'Surgery day'),
(6, 4, 'morning', '08:00:00', '12:00:00', 1, 'Surgery day'),
(6, 6, 'morning', '09:00:00', '13:00:00', 1, 'Saturday clinic')
ON DUPLICATE KEY UPDATE `start_time` = VALUES(`start_time`), `end_time` = VALUES(`end_time`);

-- =====================================================
-- 6. Update existing doctors with specialty categories
-- =====================================================
UPDATE `doctors` SET `specialty_category` = 'cardiologist', `doctor_type` = 'attending' WHERE `specialty` LIKE '%Cardiolog%';
UPDATE `doctors` SET `specialty_category` = 'obgyn', `doctor_type` = 'attending' WHERE `specialty` LIKE '%Obstetrician%' OR `specialty` LIKE '%Gynecolog%';
UPDATE `doctors` SET `specialty_category` = 'pediatrician', `doctor_type` = 'attending' WHERE `specialty` LIKE '%Pediatric%';
UPDATE `doctors` SET `specialty_category` = 'neurologist', `doctor_type` = 'attending' WHERE `specialty` LIKE '%Neurolog%';
UPDATE `doctors` SET `specialty_category` = 'internal_medicine', `doctor_type` = 'attending' WHERE `specialty` LIKE '%Internal%';
UPDATE `doctors` SET `specialty_category` = 'orthopedic', `doctor_type` = 'surgeon' WHERE `specialty` LIKE '%Orthopedic%';
UPDATE `doctors` SET `specialty_category` = 'surgeon', `doctor_type` = 'surgeon' WHERE `specialty` LIKE '%Surgeon%' AND `specialty` NOT LIKE '%Orthopedic%';

-- =====================================================
-- 7. Add doctor names from PRC mapping
-- =====================================================
UPDATE `doctors` SET `first_name` = 'Antonio', `last_name` = 'Reyes', `email` = 'dr.reyes@alvion.com' WHERE `prc_number` = 'PRC-123456';
UPDATE `doctors` SET `first_name` = 'Maria Isabel', `last_name` = 'Santos', `email` = 'dr.santos@alvion.com' WHERE `prc_number` = 'PRC-234567';
UPDATE `doctors` SET `first_name` = 'Rafael', `last_name` = 'Dela Cruz', `email` = 'dr.delacruz@alvion.com' WHERE `prc_number` = 'PRC-345678';
UPDATE `doctors` SET `first_name` = 'Liza', `last_name` = 'Marquez', `email` = 'dr.marquez@alvion.com' WHERE `prc_number` = 'PRC-456789';
UPDATE `doctors` SET `first_name` = 'Jerome', `last_name` = 'Bautista', `email` = 'dr.bautista@alvion.com' WHERE `prc_number` = 'PRC-567890';
UPDATE `doctors` SET `first_name` = 'Aileen', `last_name` = 'Navarro', `email` = 'dr.navarro@alvion.com' WHERE `prc_number` = 'PRC-678901';

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
