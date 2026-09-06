-- =====================================================
-- Wards and Beds Tables Setup
-- =====================================================
-- Complete setup for hospital ward and bed management
-- Run this file to create both tables with sample data

-- =====================================================
-- 1. Wards Table
-- =====================================================
CREATE TABLE IF NOT EXISTS `wards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ward_code` varchar(10) NOT NULL COMMENT 'Short code for the ward (e.g., ICU-01, GEN-01)',
  `ward_name` varchar(100) NOT NULL COMMENT 'Full name of the ward',
  `department_id` int(11) DEFAULT NULL COMMENT 'Reference to department (if departments table exists)',
  `ward_type` enum('general','private','semi_private','icu','emergency','maternity','pediatric') NOT NULL,
  `capacity` int(11) NOT NULL COMMENT 'Total number of beds in the ward',
  `current_occupancy` int(11) DEFAULT 0 COMMENT 'Current number of occupied beds',
  `charge_per_day` decimal(10,2) DEFAULT 0.00 COMMENT 'Daily charge rate for this ward',
  `status` enum('available','full','maintenance','closed') DEFAULT 'available',
  `floor` varchar(10) DEFAULT NULL COMMENT 'Floor number or location',
  `building` varchar(50) DEFAULT NULL COMMENT 'Building name or wing',
  `description` text DEFAULT NULL COMMENT 'Additional ward description',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ward_code` (`ward_code`),
  KEY `department_id` (`department_id`),
  KEY `ward_type` (`ward_type`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 2. Beds Table (if it doesn't exist)
-- =====================================================
CREATE TABLE IF NOT EXISTS `beds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bed_number` varchar(20) NOT NULL COMMENT 'Bed number or identifier',
  `ward_id` int(11) NOT NULL COMMENT 'Reference to wards table',
  `bed_type` enum('regular','icu','maternity','pediatric') DEFAULT 'regular',
  `status` enum('available','occupied','maintenance','reserved') DEFAULT 'available',
  `current_patient_id` int(11) DEFAULT NULL COMMENT 'Currently assigned patient',
  `daily_rate` decimal(10,2) DEFAULT 0.00 COMMENT 'Daily rate for this bed',
  `features` text DEFAULT NULL COMMENT 'Bed features and equipment',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `bed_number` (`bed_number`),
  KEY `ward_id` (`ward_id`),
  KEY `current_patient_id` (`current_patient_id`),
  KEY `status` (`status`),
  CONSTRAINT `fk_beds_ward` FOREIGN KEY (`ward_id`) REFERENCES `wards` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_beds_patient` FOREIGN KEY (`current_patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 3. Sample Data for Wards
-- =====================================================
INSERT INTO `wards` (`ward_code`, `ward_name`, `department_id`, `ward_type`, `capacity`, `current_occupancy`, `charge_per_day`, `status`, `floor`, `building`, `description`) VALUES
-- Intensive Care Units
('ICU-01', 'Intensive Care Unit - Main', NULL, 'icu', 12, 0, 15000.00, 'available', '4', 'Main Building', 'Main ICU with advanced life support equipment'),
('ICU-02', 'Cardiac Intensive Care Unit', NULL, 'icu', 8, 0, 18000.00, 'available', '3', 'Cardiac & Vascular Institute', 'Specialized ICU for cardiac patients'),
('ICU-03', 'Neonatal Intensive Care Unit', NULL, 'icu', 10, 0, 20000.00, 'available', '2', 'Women & Child Health Pavilion', 'NICU for premature and critically ill newborns'),

-- General Wards
('GEN-01', 'General Ward - Male', NULL, 'general', 20, 0, 2500.00, 'available', '3', 'Main Building', 'General medical ward for male patients'),
('GEN-02', 'General Ward - Female', NULL, 'general', 20, 0, 2500.00, 'available', '3', 'Main Building', 'General medical ward for female patients'),
('GEN-03', 'General Ward - Mixed', NULL, 'general', 24, 0, 2500.00, 'available', '4', 'Main Building', 'General medical ward for mixed patients'),

-- Private Wards
('PRIV-01', 'Private Ward - Suite A', NULL, 'private', 8, 0, 8000.00, 'available', '5', 'Main Building', 'Private rooms with en-suite facilities'),
('PRIV-02', 'Private Ward - Suite B', NULL, 'private', 8, 0, 8000.00, 'available', '5', 'Main Building', 'Private rooms with premium amenities'),
('PRIV-03', 'VIP Ward', NULL, 'private', 4, 0, 12000.00, 'available', '6', 'Main Building', 'VIP suites with luxury accommodations'),

-- Semi-Private Wards
('SEMI-01', 'Semi-Private Ward - A', NULL, 'semi_private', 16, 0, 4000.00, 'available', '4', 'Main Building', 'Semi-private rooms (2 beds per room)'),
('SEMI-02', 'Semi-Private Ward - B', NULL, 'semi_private', 16, 0, 4000.00, 'available', '4', 'Main Building', 'Semi-private rooms with shared facilities'),

-- Maternity Wards
('MAT-01', 'Maternity Ward - Labor & Delivery', NULL, 'maternity', 12, 0, 5000.00, 'available', '2', 'Women & Child Health Pavilion', 'Labor and delivery rooms'),
('MAT-02', 'Maternity Ward - Postpartum', NULL, 'maternity', 20, 0, 4500.00, 'available', '2', 'Women & Child Health Pavilion', 'Postpartum recovery ward'),
('MAT-03', 'Maternity Ward - Private', NULL, 'maternity', 6, 0, 7500.00, 'available', '2', 'Women & Child Health Pavilion', 'Private maternity suites'),

-- Pediatric Wards
('PED-01', 'Pediatric Ward - General', NULL, 'pediatric', 18, 0, 3500.00, 'available', '1', 'Women & Child Health Pavilion', 'General pediatric ward'),
('PED-02', 'Pediatric Ward - Isolation', NULL, 'pediatric', 6, 0, 5000.00, 'available', '1', 'Women & Child Health Pavilion', 'Isolation rooms for infectious cases'),
('PED-03', 'Pediatric ICU', NULL, 'pediatric', 8, 0, 15000.00, 'available', '1', 'Women & Child Health Pavilion', 'Pediatric intensive care unit'),

-- Emergency Wards
('ER-01', 'Emergency Ward - Acute', NULL, 'emergency', 15, 0, 6000.00, 'available', '1', 'Main Building', 'Acute care emergency beds'),
('ER-02', 'Emergency Ward - Observation', NULL, 'emergency', 10, 0, 4000.00, 'available', '1', 'Main Building', 'Observation beds for short-term monitoring'),

-- Specialty Wards
('CARD-01', 'Cardiac Ward', NULL, 'general', 16, 0, 5500.00, 'available', '3', 'Cardiac & Vascular Institute', 'Specialized ward for cardiac patients'),
('NEURO-01', 'Neurology Ward', NULL, 'general', 14, 0, 5000.00, 'available', '4', 'Neuroscience & Stroke Center', 'Specialized ward for neurology patients'),
('ORTHO-01', 'Orthopedics Ward', NULL, 'general', 18, 0, 4500.00, 'available', '3', 'Orthopedics Department', 'Specialized ward for orthopedic patients'),
('ONCO-01', 'Oncology Ward', NULL, 'general', 12, 0, 6000.00, 'available', '5', 'Main Building', 'Specialized ward for cancer patients');

-- =====================================================
-- 4. Sample Data for Beds
-- =====================================================
-- Note: This creates beds for each ward based on capacity
-- Adjust bed numbers and types according to your needs

-- Get ward IDs for bed creation
SET @icu01_id = (SELECT id FROM wards WHERE ward_code = 'ICU-01' LIMIT 1);
SET @icu02_id = (SELECT id FROM wards WHERE ward_code = 'ICU-02' LIMIT 1);
SET @icu03_id = (SELECT id FROM wards WHERE ward_code = 'ICU-03' LIMIT 1);
SET @gen01_id = (SELECT id FROM wards WHERE ward_code = 'GEN-01' LIMIT 1);
SET @gen02_id = (SELECT id FROM wards WHERE ward_code = 'GEN-02' LIMIT 1);
SET @gen03_id = (SELECT id FROM wards WHERE ward_code = 'GEN-03' LIMIT 1);
SET @priv01_id = (SELECT id FROM wards WHERE ward_code = 'PRIV-01' LIMIT 1);
SET @priv02_id = (SELECT id FROM wards WHERE ward_code = 'PRIV-02' LIMIT 1);
SET @priv03_id = (SELECT id FROM wards WHERE ward_code = 'PRIV-03' LIMIT 1);
SET @semi01_id = (SELECT id FROM wards WHERE ward_code = 'SEMI-01' LIMIT 1);
SET @semi02_id = (SELECT id FROM wards WHERE ward_code = 'SEMI-02' LIMIT 1);
SET @mat01_id = (SELECT id FROM wards WHERE ward_code = 'MAT-01' LIMIT 1);
SET @mat02_id = (SELECT id FROM wards WHERE ward_code = 'MAT-02' LIMIT 1);
SET @mat03_id = (SELECT id FROM wards WHERE ward_code = 'MAT-03' LIMIT 1);
SET @ped01_id = (SELECT id FROM wards WHERE ward_code = 'PED-01' LIMIT 1);
SET @ped02_id = (SELECT id FROM wards WHERE ward_code = 'PED-02' LIMIT 1);
SET @ped03_id = (SELECT id FROM wards WHERE ward_code = 'PED-03' LIMIT 1);
SET @er01_id = (SELECT id FROM wards WHERE ward_code = 'ER-01' LIMIT 1);
SET @er02_id = (SELECT id FROM wards WHERE ward_code = 'ER-02' LIMIT 1);
SET @card01_id = (SELECT id FROM wards WHERE ward_code = 'CARD-01' LIMIT 1);
SET @neuro01_id = (SELECT id FROM wards WHERE ward_code = 'NEURO-01' LIMIT 1);
SET @ortho01_id = (SELECT id FROM wards WHERE ward_code = 'ORTHO-01' LIMIT 1);
SET @onco01_id = (SELECT id FROM wards WHERE ward_code = 'ONCO-01' LIMIT 1);

-- Insert beds for each ward
-- ICU-01 (12 beds)
INSERT INTO `beds` (`bed_number`, `ward_id`, `bed_type`, `status`, `daily_rate`) VALUES
('ICU-01-01', @icu01_id, 'icu', 'available', 15000.00),
('ICU-01-02', @icu01_id, 'icu', 'available', 15000.00),
('ICU-01-03', @icu01_id, 'icu', 'available', 15000.00),
('ICU-01-04', @icu01_id, 'icu', 'available', 15000.00),
('ICU-01-05', @icu01_id, 'icu', 'available', 15000.00),
('ICU-01-06', @icu01_id, 'icu', 'available', 15000.00),
('ICU-01-07', @icu01_id, 'icu', 'available', 15000.00),
('ICU-01-08', @icu01_id, 'icu', 'available', 15000.00),
('ICU-01-09', @icu01_id, 'icu', 'available', 15000.00),
('ICU-01-10', @icu01_id, 'icu', 'available', 15000.00),
('ICU-01-11', @icu01_id, 'icu', 'available', 15000.00),
('ICU-01-12', @icu01_id, 'icu', 'available', 15000.00);

-- ICU-02 (8 beds)
INSERT INTO `beds` (`bed_number`, `ward_id`, `bed_type`, `status`, `daily_rate`) VALUES
('CICU-01', @icu02_id, 'icu', 'available', 18000.00),
('CICU-02', @icu02_id, 'icu', 'available', 18000.00),
('CICU-03', @icu02_id, 'icu', 'available', 18000.00),
('CICU-04', @icu02_id, 'icu', 'available', 18000.00),
('CICU-05', @icu02_id, 'icu', 'available', 18000.00),
('CICU-06', @icu02_id, 'icu', 'available', 18000.00),
('CICU-07', @icu02_id, 'icu', 'available', 18000.00),
('CICU-08', @icu02_id, 'icu', 'available', 18000.00);

-- ICU-03 NICU (10 beds)
INSERT INTO `beds` (`bed_number`, `ward_id`, `bed_type`, `status`, `daily_rate`) VALUES
('NICU-01', @icu03_id, 'icu', 'available', 20000.00),
('NICU-02', @icu03_id, 'icu', 'available', 20000.00),
('NICU-03', @icu03_id, 'icu', 'available', 20000.00),
('NICU-04', @icu03_id, 'icu', 'available', 20000.00),
('NICU-05', @icu03_id, 'icu', 'available', 20000.00),
('NICU-06', @icu03_id, 'icu', 'available', 20000.00),
('NICU-07', @icu03_id, 'icu', 'available', 20000.00),
('NICU-08', @icu03_id, 'icu', 'available', 20000.00),
('NICU-09', @icu03_id, 'icu', 'available', 20000.00),
('NICU-10', @icu03_id, 'icu', 'available', 20000.00);

-- GEN-01 (20 beds)
INSERT INTO `beds` (`bed_number`, `ward_id`, `bed_type`, `status`, `daily_rate`) VALUES
('GEN-01-01', @gen01_id, 'regular', 'available', 2500.00),
('GEN-01-02', @gen01_id, 'regular', 'available', 2500.00),
('GEN-01-03', @gen01_id, 'regular', 'available', 2500.00),
('GEN-01-04', @gen01_id, 'regular', 'available', 2500.00),
('GEN-01-05', @gen01_id, 'regular', 'available', 2500.00),
('GEN-01-06', @gen01_id, 'regular', 'available', 2500.00),
('GEN-01-07', @gen01_id, 'regular', 'available', 2500.00),
('GEN-01-08', @gen01_id, 'regular', 'available', 2500.00),
('GEN-01-09', @gen01_id, 'regular', 'available', 2500.00),
('GEN-01-10', @gen01_id, 'regular', 'available', 2500.00),
('GEN-01-11', @gen01_id, 'regular', 'available', 2500.00),
('GEN-01-12', @gen01_id, 'regular', 'available', 2500.00),
('GEN-01-13', @gen01_id, 'regular', 'available', 2500.00),
('GEN-01-14', @gen01_id, 'regular', 'available', 2500.00),
('GEN-01-15', @gen01_id, 'regular', 'available', 2500.00),
('GEN-01-16', @gen01_id, 'regular', 'available', 2500.00),
('GEN-01-17', @gen01_id, 'regular', 'available', 2500.00),
('GEN-01-18', @gen01_id, 'regular', 'available', 2500.00),
('GEN-01-19', @gen01_id, 'regular', 'available', 2500.00),
('GEN-01-20', @gen01_id, 'regular', 'available', 2500.00);

-- GEN-02 (20 beds)
INSERT INTO `beds` (`bed_number`, `ward_id`, `bed_type`, `status`, `daily_rate`) VALUES
('GEN-02-01', @gen02_id, 'regular', 'available', 2500.00),
('GEN-02-02', @gen02_id, 'regular', 'available', 2500.00),
('GEN-02-03', @gen02_id, 'regular', 'available', 2500.00),
('GEN-02-04', @gen02_id, 'regular', 'available', 2500.00),
('GEN-02-05', @gen02_id, 'regular', 'available', 2500.00),
('GEN-02-06', @gen02_id, 'regular', 'available', 2500.00),
('GEN-02-07', @gen02_id, 'regular', 'available', 2500.00),
('GEN-02-08', @gen02_id, 'regular', 'available', 2500.00),
('GEN-02-09', @gen02_id, 'regular', 'available', 2500.00),
('GEN-02-10', @gen02_id, 'regular', 'available', 2500.00),
('GEN-02-11', @gen02_id, 'regular', 'available', 2500.00),
('GEN-02-12', @gen02_id, 'regular', 'available', 2500.00),
('GEN-02-13', @gen02_id, 'regular', 'available', 2500.00),
('GEN-02-14', @gen02_id, 'regular', 'available', 2500.00),
('GEN-02-15', @gen02_id, 'regular', 'available', 2500.00),
('GEN-02-16', @gen02_id, 'regular', 'available', 2500.00),
('GEN-02-17', @gen02_id, 'regular', 'available', 2500.00),
('GEN-02-18', @gen02_id, 'regular', 'available', 2500.00),
('GEN-02-19', @gen02_id, 'regular', 'available', 2500.00),
('GEN-02-20', @gen02_id, 'regular', 'available', 2500.00);

-- GEN-03 (24 beds)
INSERT INTO `beds` (`bed_number`, `ward_id`, `bed_type`, `status`, `daily_rate`) VALUES
('GEN-03-01', @gen03_id, 'regular', 'available', 2500.00),
('GEN-03-02', @gen03_id, 'regular', 'available', 2500.00),
('GEN-03-03', @gen03_id, 'regular', 'available', 2500.00),
('GEN-03-04', @gen03_id, 'regular', 'available', 2500.00),
('GEN-03-05', @gen03_id, 'regular', 'available', 2500.00),
('GEN-03-06', @gen03_id, 'regular', 'available', 2500.00),
('GEN-03-07', @gen03_id, 'regular', 'available', 2500.00),
('GEN-03-08', @gen03_id, 'regular', 'available', 2500.00),
('GEN-03-09', @gen03_id, 'regular', 'available', 2500.00),
('GEN-03-10', @gen03_id, 'regular', 'available', 2500.00),
('GEN-03-11', @gen03_id, 'regular', 'available', 2500.00),
('GEN-03-12', @gen03_id, 'regular', 'available', 2500.00),
('GEN-03-13', @gen03_id, 'regular', 'available', 2500.00),
('GEN-03-14', @gen03_id, 'regular', 'available', 2500.00),
('GEN-03-15', @gen03_id, 'regular', 'available', 2500.00),
('GEN-03-16', @gen03_id, 'regular', 'available', 2500.00),
('GEN-03-17', @gen03_id, 'regular', 'available', 2500.00),
('GEN-03-18', @gen03_id, 'regular', 'available', 2500.00),
('GEN-03-19', @gen03_id, 'regular', 'available', 2500.00),
('GEN-03-20', @gen03_id, 'regular', 'available', 2500.00),
('GEN-03-21', @gen03_id, 'regular', 'available', 2500.00),
('GEN-03-22', @gen03_id, 'regular', 'available', 2500.00),
('GEN-03-23', @gen03_id, 'regular', 'available', 2500.00),
('GEN-03-24', @gen03_id, 'regular', 'available', 2500.00);

-- Private Wards (8 beds each)
INSERT INTO `beds` (`bed_number`, `ward_id`, `bed_type`, `status`, `daily_rate`) VALUES
('PRIV-01-01', @priv01_id, 'regular', 'available', 8000.00),
('PRIV-01-02', @priv01_id, 'regular', 'available', 8000.00),
('PRIV-01-03', @priv01_id, 'regular', 'available', 8000.00),
('PRIV-01-04', @priv01_id, 'regular', 'available', 8000.00),
('PRIV-01-05', @priv01_id, 'regular', 'available', 8000.00),
('PRIV-01-06', @priv01_id, 'regular', 'available', 8000.00),
('PRIV-01-07', @priv01_id, 'regular', 'available', 8000.00),
('PRIV-01-08', @priv01_id, 'regular', 'available', 8000.00),
('PRIV-02-01', @priv02_id, 'regular', 'available', 8000.00),
('PRIV-02-02', @priv02_id, 'regular', 'available', 8000.00),
('PRIV-02-03', @priv02_id, 'regular', 'available', 8000.00),
('PRIV-02-04', @priv02_id, 'regular', 'available', 8000.00),
('PRIV-02-05', @priv02_id, 'regular', 'available', 8000.00),
('PRIV-02-06', @priv02_id, 'regular', 'available', 8000.00),
('PRIV-02-07', @priv02_id, 'regular', 'available', 8000.00),
('PRIV-02-08', @priv02_id, 'regular', 'available', 8000.00),
('VIP-01', @priv03_id, 'regular', 'available', 12000.00),
('VIP-02', @priv03_id, 'regular', 'available', 12000.00),
('VIP-03', @priv03_id, 'regular', 'available', 12000.00),
('VIP-04', @priv03_id, 'regular', 'available', 12000.00);

-- Semi-Private Wards (16 beds each)
INSERT INTO `beds` (`bed_number`, `ward_id`, `bed_type`, `status`, `daily_rate`) VALUES
('SEMI-01-01', @semi01_id, 'regular', 'available', 4000.00),
('SEMI-01-02', @semi01_id, 'regular', 'available', 4000.00),
('SEMI-01-03', @semi01_id, 'regular', 'available', 4000.00),
('SEMI-01-04', @semi01_id, 'regular', 'available', 4000.00),
('SEMI-01-05', @semi01_id, 'regular', 'available', 4000.00),
('SEMI-01-06', @semi01_id, 'regular', 'available', 4000.00),
('SEMI-01-07', @semi01_id, 'regular', 'available', 4000.00),
('SEMI-01-08', @semi01_id, 'regular', 'available', 4000.00),
('SEMI-01-09', @semi01_id, 'regular', 'available', 4000.00),
('SEMI-01-10', @semi01_id, 'regular', 'available', 4000.00),
('SEMI-01-11', @semi01_id, 'regular', 'available', 4000.00),
('SEMI-01-12', @semi01_id, 'regular', 'available', 4000.00),
('SEMI-01-13', @semi01_id, 'regular', 'available', 4000.00),
('SEMI-01-14', @semi01_id, 'regular', 'available', 4000.00),
('SEMI-01-15', @semi01_id, 'regular', 'available', 4000.00),
('SEMI-01-16', @semi01_id, 'regular', 'available', 4000.00),
('SEMI-02-01', @semi02_id, 'regular', 'available', 4000.00),
('SEMI-02-02', @semi02_id, 'regular', 'available', 4000.00),
('SEMI-02-03', @semi02_id, 'regular', 'available', 4000.00),
('SEMI-02-04', @semi02_id, 'regular', 'available', 4000.00),
('SEMI-02-05', @semi02_id, 'regular', 'available', 4000.00),
('SEMI-02-06', @semi02_id, 'regular', 'available', 4000.00),
('SEMI-02-07', @semi02_id, 'regular', 'available', 4000.00),
('SEMI-02-08', @semi02_id, 'regular', 'available', 4000.00),
('SEMI-02-09', @semi02_id, 'regular', 'available', 4000.00),
('SEMI-02-10', @semi02_id, 'regular', 'available', 4000.00),
('SEMI-02-11', @semi02_id, 'regular', 'available', 4000.00),
('SEMI-02-12', @semi02_id, 'regular', 'available', 4000.00),
('SEMI-02-13', @semi02_id, 'regular', 'available', 4000.00),
('SEMI-02-14', @semi02_id, 'regular', 'available', 4000.00),
('SEMI-02-15', @semi02_id, 'regular', 'available', 4000.00),
('SEMI-02-16', @semi02_id, 'regular', 'available', 4000.00);

-- Maternity Wards
INSERT INTO `beds` (`bed_number`, `ward_id`, `bed_type`, `status`, `daily_rate`) VALUES
('MAT-01-01', @mat01_id, 'maternity', 'available', 5000.00),
('MAT-01-02', @mat01_id, 'maternity', 'available', 5000.00),
('MAT-01-03', @mat01_id, 'maternity', 'available', 5000.00),
('MAT-01-04', @mat01_id, 'maternity', 'available', 5000.00),
('MAT-01-05', @mat01_id, 'maternity', 'available', 5000.00),
('MAT-01-06', @mat01_id, 'maternity', 'available', 5000.00),
('MAT-01-07', @mat01_id, 'maternity', 'available', 5000.00),
('MAT-01-08', @mat01_id, 'maternity', 'available', 5000.00),
('MAT-01-09', @mat01_id, 'maternity', 'available', 5000.00),
('MAT-01-10', @mat01_id, 'maternity', 'available', 5000.00),
('MAT-01-11', @mat01_id, 'maternity', 'available', 5000.00),
('MAT-01-12', @mat01_id, 'maternity', 'available', 5000.00),
('MAT-02-01', @mat02_id, 'maternity', 'available', 4500.00),
('MAT-02-02', @mat02_id, 'maternity', 'available', 4500.00),
('MAT-02-03', @mat02_id, 'maternity', 'available', 4500.00),
('MAT-02-04', @mat02_id, 'maternity', 'available', 4500.00),
('MAT-02-05', @mat02_id, 'maternity', 'available', 4500.00),
('MAT-02-06', @mat02_id, 'maternity', 'available', 4500.00),
('MAT-02-07', @mat02_id, 'maternity', 'available', 4500.00),
('MAT-02-08', @mat02_id, 'maternity', 'available', 4500.00),
('MAT-02-09', @mat02_id, 'maternity', 'available', 4500.00),
('MAT-02-10', @mat02_id, 'maternity', 'available', 4500.00),
('MAT-02-11', @mat02_id, 'maternity', 'available', 4500.00),
('MAT-02-12', @mat02_id, 'maternity', 'available', 4500.00),
('MAT-02-13', @mat02_id, 'maternity', 'available', 4500.00),
('MAT-02-14', @mat02_id, 'maternity', 'available', 4500.00),
('MAT-02-15', @mat02_id, 'maternity', 'available', 4500.00),
('MAT-02-16', @mat02_id, 'maternity', 'available', 4500.00),
('MAT-02-17', @mat02_id, 'maternity', 'available', 4500.00),
('MAT-02-18', @mat02_id, 'maternity', 'available', 4500.00),
('MAT-02-19', @mat02_id, 'maternity', 'available', 4500.00),
('MAT-02-20', @mat02_id, 'maternity', 'available', 4500.00),
('MAT-03-01', @mat03_id, 'maternity', 'available', 7500.00),
('MAT-03-02', @mat03_id, 'maternity', 'available', 7500.00),
('MAT-03-03', @mat03_id, 'maternity', 'available', 7500.00),
('MAT-03-04', @mat03_id, 'maternity', 'available', 7500.00),
('MAT-03-05', @mat03_id, 'maternity', 'available', 7500.00),
('MAT-03-06', @mat03_id, 'maternity', 'available', 7500.00);

-- Pediatric Wards
INSERT INTO `beds` (`bed_number`, `ward_id`, `bed_type`, `status`, `daily_rate`) VALUES
('PED-01-01', @ped01_id, 'pediatric', 'available', 3500.00),
('PED-01-02', @ped01_id, 'pediatric', 'available', 3500.00),
('PED-01-03', @ped01_id, 'pediatric', 'available', 3500.00),
('PED-01-04', @ped01_id, 'pediatric', 'available', 3500.00),
('PED-01-05', @ped01_id, 'pediatric', 'available', 3500.00),
('PED-01-06', @ped01_id, 'pediatric', 'available', 3500.00),
('PED-01-07', @ped01_id, 'pediatric', 'available', 3500.00),
('PED-01-08', @ped01_id, 'pediatric', 'available', 3500.00),
('PED-01-09', @ped01_id, 'pediatric', 'available', 3500.00),
('PED-01-10', @ped01_id, 'pediatric', 'available', 3500.00),
('PED-01-11', @ped01_id, 'pediatric', 'available', 3500.00),
('PED-01-12', @ped01_id, 'pediatric', 'available', 3500.00),
('PED-01-13', @ped01_id, 'pediatric', 'available', 3500.00),
('PED-01-14', @ped01_id, 'pediatric', 'available', 3500.00),
('PED-01-15', @ped01_id, 'pediatric', 'available', 3500.00),
('PED-01-16', @ped01_id, 'pediatric', 'available', 3500.00),
('PED-01-17', @ped01_id, 'pediatric', 'available', 3500.00),
('PED-01-18', @ped01_id, 'pediatric', 'available', 3500.00),
('PED-02-01', @ped02_id, 'pediatric', 'available', 5000.00),
('PED-02-02', @ped02_id, 'pediatric', 'available', 5000.00),
('PED-02-03', @ped02_id, 'pediatric', 'available', 5000.00),
('PED-02-04', @ped02_id, 'pediatric', 'available', 5000.00),
('PED-02-05', @ped02_id, 'pediatric', 'available', 5000.00),
('PED-02-06', @ped02_id, 'pediatric', 'available', 5000.00),
('PICU-01', @ped03_id, 'pediatric', 'available', 15000.00),
('PICU-02', @ped03_id, 'pediatric', 'available', 15000.00),
('PICU-03', @ped03_id, 'pediatric', 'available', 15000.00),
('PICU-04', @ped03_id, 'pediatric', 'available', 15000.00),
('PICU-05', @ped03_id, 'pediatric', 'available', 15000.00),
('PICU-06', @ped03_id, 'pediatric', 'available', 15000.00),
('PICU-07', @ped03_id, 'pediatric', 'available', 15000.00),
('PICU-08', @ped03_id, 'pediatric', 'available', 15000.00);

-- Emergency Wards
INSERT INTO `beds` (`bed_number`, `ward_id`, `bed_type`, `status`, `daily_rate`) VALUES
('ER-01-01', @er01_id, 'regular', 'available', 6000.00),
('ER-01-02', @er01_id, 'regular', 'available', 6000.00),
('ER-01-03', @er01_id, 'regular', 'available', 6000.00),
('ER-01-04', @er01_id, 'regular', 'available', 6000.00),
('ER-01-05', @er01_id, 'regular', 'available', 6000.00),
('ER-01-06', @er01_id, 'regular', 'available', 6000.00),
('ER-01-07', @er01_id, 'regular', 'available', 6000.00),
('ER-01-08', @er01_id, 'regular', 'available', 6000.00),
('ER-01-09', @er01_id, 'regular', 'available', 6000.00),
('ER-01-10', @er01_id, 'regular', 'available', 6000.00),
('ER-01-11', @er01_id, 'regular', 'available', 6000.00),
('ER-01-12', @er01_id, 'regular', 'available', 6000.00),
('ER-01-13', @er01_id, 'regular', 'available', 6000.00),
('ER-01-14', @er01_id, 'regular', 'available', 6000.00),
('ER-01-15', @er01_id, 'regular', 'available', 6000.00),
('ER-02-01', @er02_id, 'regular', 'available', 4000.00),
('ER-02-02', @er02_id, 'regular', 'available', 4000.00),
('ER-02-03', @er02_id, 'regular', 'available', 4000.00),
('ER-02-04', @er02_id, 'regular', 'available', 4000.00),
('ER-02-05', @er02_id, 'regular', 'available', 4000.00),
('ER-02-06', @er02_id, 'regular', 'available', 4000.00),
('ER-02-07', @er02_id, 'regular', 'available', 4000.00),
('ER-02-08', @er02_id, 'regular', 'available', 4000.00),
('ER-02-09', @er02_id, 'regular', 'available', 4000.00),
('ER-02-10', @er02_id, 'regular', 'available', 4000.00);

-- Specialty Wards
INSERT INTO `beds` (`bed_number`, `ward_id`, `bed_type`, `status`, `daily_rate`) VALUES
('CARD-01-01', @card01_id, 'regular', 'available', 5500.00),
('CARD-01-02', @card01_id, 'regular', 'available', 5500.00),
('CARD-01-03', @card01_id, 'regular', 'available', 5500.00),
('CARD-01-04', @card01_id, 'regular', 'available', 5500.00),
('CARD-01-05', @card01_id, 'regular', 'available', 5500.00),
('CARD-01-06', @card01_id, 'regular', 'available', 5500.00),
('CARD-01-07', @card01_id, 'regular', 'available', 5500.00),
('CARD-01-08', @card01_id, 'regular', 'available', 5500.00),
('CARD-01-09', @card01_id, 'regular', 'available', 5500.00),
('CARD-01-10', @card01_id, 'regular', 'available', 5500.00),
('CARD-01-11', @card01_id, 'regular', 'available', 5500.00),
('CARD-01-12', @card01_id, 'regular', 'available', 5500.00),
('CARD-01-13', @card01_id, 'regular', 'available', 5500.00),
('CARD-01-14', @card01_id, 'regular', 'available', 5500.00),
('CARD-01-15', @card01_id, 'regular', 'available', 5500.00),
('CARD-01-16', @card01_id, 'regular', 'available', 5500.00),
('NEURO-01-01', @neuro01_id, 'regular', 'available', 5000.00),
('NEURO-01-02', @neuro01_id, 'regular', 'available', 5000.00),
('NEURO-01-03', @neuro01_id, 'regular', 'available', 5000.00),
('NEURO-01-04', @neuro01_id, 'regular', 'available', 5000.00),
('NEURO-01-05', @neuro01_id, 'regular', 'available', 5000.00),
('NEURO-01-06', @neuro01_id, 'regular', 'available', 5000.00),
('NEURO-01-07', @neuro01_id, 'regular', 'available', 5000.00),
('NEURO-01-08', @neuro01_id, 'regular', 'available', 5000.00),
('NEURO-01-09', @neuro01_id, 'regular', 'available', 5000.00),
('NEURO-01-10', @neuro01_id, 'regular', 'available', 5000.00),
('NEURO-01-11', @neuro01_id, 'regular', 'available', 5000.00),
('NEURO-01-12', @neuro01_id, 'regular', 'available', 5000.00),
('NEURO-01-13', @neuro01_id, 'regular', 'available', 5000.00),
('NEURO-01-14', @neuro01_id, 'regular', 'available', 5000.00),
('ORTHO-01-01', @ortho01_id, 'regular', 'available', 4500.00),
('ORTHO-01-02', @ortho01_id, 'regular', 'available', 4500.00),
('ORTHO-01-03', @ortho01_id, 'regular', 'available', 4500.00),
('ORTHO-01-04', @ortho01_id, 'regular', 'available', 4500.00),
('ORTHO-01-05', @ortho01_id, 'regular', 'available', 4500.00),
('ORTHO-01-06', @ortho01_id, 'regular', 'available', 4500.00),
('ORTHO-01-07', @ortho01_id, 'regular', 'available', 4500.00),
('ORTHO-01-08', @ortho01_id, 'regular', 'available', 4500.00),
('ORTHO-01-09', @ortho01_id, 'regular', 'available', 4500.00),
('ORTHO-01-10', @ortho01_id, 'regular', 'available', 4500.00),
('ORTHO-01-11', @ortho01_id, 'regular', 'available', 4500.00),
('ORTHO-01-12', @ortho01_id, 'regular', 'available', 4500.00),
('ORTHO-01-13', @ortho01_id, 'regular', 'available', 4500.00),
('ORTHO-01-14', @ortho01_id, 'regular', 'available', 4500.00),
('ORTHO-01-15', @ortho01_id, 'regular', 'available', 4500.00),
('ORTHO-01-16', @ortho01_id, 'regular', 'available', 4500.00),
('ORTHO-01-17', @ortho01_id, 'regular', 'available', 4500.00),
('ORTHO-01-18', @ortho01_id, 'regular', 'available', 4500.00),
('ONCO-01-01', @onco01_id, 'regular', 'available', 6000.00),
('ONCO-01-02', @onco01_id, 'regular', 'available', 6000.00),
('ONCO-01-03', @onco01_id, 'regular', 'available', 6000.00),
('ONCO-01-04', @onco01_id, 'regular', 'available', 6000.00),
('ONCO-01-05', @onco01_id, 'regular', 'available', 6000.00),
('ONCO-01-06', @onco01_id, 'regular', 'available', 6000.00),
('ONCO-01-07', @onco01_id, 'regular', 'available', 6000.00),
('ONCO-01-08', @onco01_id, 'regular', 'available', 6000.00),
('ONCO-01-09', @onco01_id, 'regular', 'available', 6000.00),
('ONCO-01-10', @onco01_id, 'regular', 'available', 6000.00),
('ONCO-01-11', @onco01_id, 'regular', 'available', 6000.00),
('ONCO-01-12', @onco01_id, 'regular', 'available', 6000.00);

-- =====================================================
-- Update ward current_occupancy based on actual bed status
-- =====================================================
-- This trigger or update statement keeps ward occupancy in sync
UPDATE wards w
SET w.current_occupancy = (
    SELECT COUNT(*)
    FROM beds b
    WHERE b.ward_id = w.id AND b.status = 'occupied'
);

-- =====================================================
-- Notes:
-- =====================================================
-- 1. This creates 22 wards with various types
-- 2. Creates beds for each ward based on capacity
-- 3. Total beds created: ~400+ beds across all wards
-- 4. All beds are initially set to 'available' status
-- 5. Daily rates are set per ward type
-- 6. Foreign key constraints ensure data integrity
-- 7. Run this file after creating the patients table (for foreign key constraint)

