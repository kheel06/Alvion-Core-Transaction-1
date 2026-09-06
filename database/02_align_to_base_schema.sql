-- =====================================================
-- Align existing DB to base schema so seeds can run
-- Run after 01_migrate_admin_schema.sql (or with a fresh base DB).
-- Adds: ph_regions, ph_provinces, ph_cities; patients.hospital_id; appointments.appointment_number
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET @db = DATABASE();

-- =====================================================
-- 1. PH LOCATIONS (create tables if missing, then data)
-- =====================================================
CREATE TABLE IF NOT EXISTS `ph_regions` (
  `region_code` varchar(10) NOT NULL,
  `region_name` varchar(100) NOT NULL,
  `region_description` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`region_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `ph_provinces` (
  `province_code` varchar(10) NOT NULL,
  `province_name` varchar(100) NOT NULL,
  `region_code` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`province_code`),
  KEY `region_code` (`region_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `ph_cities` (
  `city_code` varchar(10) NOT NULL,
  `city_name` varchar(100) NOT NULL,
  `province_code` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`city_code`),
  KEY `province_code` (`province_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `ph_regions` (`region_code`, `region_name`, `region_description`) VALUES
('CAR', 'Cordillera Administrative Region', 'CAR'),
('NCR', 'National Capital Region', 'Metro Manila'),
('REGION1', 'Region I - Ilocos Region', 'Ilocos Region'),
('REGION2', 'Region II - Cagayan Valley', 'Cagayan Valley'),
('REGION3', 'Region III - Central Luzon', 'Central Luzon');

INSERT IGNORE INTO `ph_provinces` (`province_code`, `province_name`, `region_code`) VALUES
('NCR_MNL', 'Manila', 'NCR'),
('NCR_QUE', 'Quezon City', 'NCR'),
('NCR_MAK', 'Makati', 'NCR'),
('BEN', 'Benguet', 'CAR'),
('ILN', 'Ilocos Norte', 'REGION1');

INSERT IGNORE INTO `ph_cities` (`city_code`, `city_name`, `province_code`) VALUES
('MANILA', 'Manila', 'NCR_MNL'),
('QC', 'Quezon City', 'NCR_QUE'),
('MAKATI', 'Makati City', 'NCR_MAK'),
('BAGUIO', 'Baguio City', 'BEN'),
('LAOAG', 'Laoag City', 'ILN');

-- =====================================================
-- 2. PATIENTS: add hospital_id if missing
-- =====================================================
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'patients' AND COLUMN_NAME = 'hospital_id');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE patients ADD COLUMN hospital_id varchar(20) DEFAULT NULL AFTER id',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE patients SET hospital_id = CONCAT('AHN-', YEAR(CURDATE()), '-', LPAD(id, 4, '0'))
WHERE hospital_id IS NULL OR hospital_id = '';

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'patients' AND COLUMN_NAME = 'hospital_id');
SET @sql = IF(@col_exists > 0,
  'ALTER TABLE patients MODIFY hospital_id varchar(20) NOT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add unique key on hospital_id if not present (optional, base schema has it)
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'patients' AND INDEX_NAME = 'hospital_id');
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE patients ADD UNIQUE KEY hospital_id (hospital_id)',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 3. APPOINTMENTS: add appointment_number if missing
-- =====================================================
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'appointment_number');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE appointments ADD COLUMN appointment_number varchar(20) DEFAULT NULL AFTER id',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE appointments SET appointment_number = CONCAT('APT-', YEAR(appointment_date), '-', LPAD(id, 4, '0'))
WHERE appointment_number IS NULL OR appointment_number = '';

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'appointment_number');
SET @sql = IF(@col_exists > 0,
  'ALTER TABLE appointments MODIFY appointment_number varchar(20) NOT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'appointments' AND INDEX_NAME = 'appointment_number');
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE appointments ADD UNIQUE KEY appointment_number (appointment_number)',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 4. PATIENTS: add first_name, last_name if missing (for seeds)
-- =====================================================
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'patients' AND COLUMN_NAME = 'first_name');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE patients ADD COLUMN first_name varchar(100) DEFAULT NULL AFTER hospital_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'patients' AND COLUMN_NAME = 'last_name');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE patients ADD COLUMN last_name varchar(100) DEFAULT NULL AFTER first_name', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'patients' AND COLUMN_NAME = 'middle_name');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE patients ADD COLUMN middle_name varchar(100) DEFAULT NULL AFTER last_name', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'patients' AND COLUMN_NAME = 'created_at');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE patients ADD COLUMN created_at timestamp NOT NULL DEFAULT current_timestamp()', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'patients' AND COLUMN_NAME = 'created_by');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE patients ADD COLUMN created_by int(11) DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'patients' AND COLUMN_NAME = 'birth_date');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE patients ADD COLUMN birth_date date DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'patients' AND COLUMN_NAME = 'gender');
SET @sql = IF(@col_exists = 0, "ALTER TABLE patients ADD COLUMN gender enum('male','female','other') DEFAULT NULL", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'patients' AND COLUMN_NAME = 'occupation');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE patients ADD COLUMN occupation varchar(100) DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =====================================================
-- 5. APPOINTMENTS: add reason, symptoms, priority_level, is_walkin, created_by, created_at if missing
-- =====================================================
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'reason');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE appointments ADD COLUMN reason text DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'symptoms');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE appointments ADD COLUMN symptoms text DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'priority_level');
SET @sql = IF(@col_exists = 0, "ALTER TABLE appointments ADD COLUMN priority_level enum('low','medium','high','emergency') DEFAULT 'medium'", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'is_walkin');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE appointments ADD COLUMN is_walkin tinyint(1) DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'created_by');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE appointments ADD COLUMN created_by int(11) DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'created_at');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE appointments ADD COLUMN created_at timestamp NOT NULL DEFAULT current_timestamp()', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =====================================================
-- 6. PAYMENTS: add billing_id if missing (01_migrate uses it)
-- =====================================================
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'billing_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE payments ADD COLUMN billing_id int(11) DEFAULT NULL AFTER id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =====================================================
-- 7. DOCTORS: allow first_name/last_name to have default (for doctors_insert seed)
-- =====================================================
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'doctors' AND COLUMN_NAME = 'first_name');
SET @sql = IF(@col_exists > 0, 'ALTER TABLE doctors MODIFY COLUMN first_name varchar(100) DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'doctors' AND COLUMN_NAME = 'last_name');
SET @sql = IF(@col_exists > 0, 'ALTER TABLE doctors MODIFY COLUMN last_name varchar(100) DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =====================================================
-- 8. DOCTOR_SCHEDULES: add day_of_week if missing
-- =====================================================
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'doctor_schedules' AND COLUMN_NAME = 'day_of_week');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE doctor_schedules ADD COLUMN day_of_week tinyint(1) DEFAULT NULL COMMENT ''0=Sun,1=Mon,...,6=Sat''', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
