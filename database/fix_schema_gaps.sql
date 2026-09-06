-- =====================================================
-- Fix schema gaps between base schema and migration files
-- Run AFTER 01_migrate_admin_schema.sql, BEFORE seeds
-- Adds missing columns that some modules expect
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- 1. departments table (wards FK references it)
-- =====================================================
CREATE TABLE IF NOT EXISTS `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_name` varchar(100) NOT NULL,
  `department_code` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `head_doctor_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `department_name` (`department_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `departments` (`id`, `department_name`, `department_code`, `description`, `status`) VALUES
(1, 'General Medicine', 'GEN', 'General medical ward', 'active'),
(2, 'Surgery', 'SURG', 'Surgical ward', 'active'),
(3, 'Obstetrics & Gynecology', 'OBGYN', 'Maternity and gynecology', 'active'),
(4, 'Pediatrics', 'PED', 'Children''s ward', 'active'),
(5, 'Emergency', 'ER', 'Emergency department', 'active'),
(6, 'ICU', 'ICU', 'Intensive care unit', 'active'),
(7, 'Cardiology', 'CARD', 'Heart and cardiovascular', 'active'),
(8, 'Orthopedics', 'ORTHO', 'Bone and joint care', 'active');

-- =====================================================
-- 2. doctor_schedules table (02_align references it,
--    seed_doctor_schedules.sql seeds it)
-- =====================================================
CREATE TABLE IF NOT EXISTS `doctor_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `doctor_id` int(11) NOT NULL COMMENT 'doctors.id',
  `day_of_week` tinyint(1) NOT NULL COMMENT '0=Sunday, 1=Monday, ..., 6=Saturday',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `slot_duration_minutes` int(11) DEFAULT 30,
  `max_daily_appointments` int(11) DEFAULT 20,
  `room_id` int(11) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `day_of_week` (`day_of_week`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 3. Drop and recreate teleconsultations with ALL columns
--    (base schema created it without reason/symptoms)
-- =====================================================
DROP TABLE IF EXISTS `teleconsultations`;
CREATE TABLE `teleconsultations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `consultation_number` varchar(20) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `consultation_date` datetime NOT NULL,
  `status` enum('scheduled','ongoing','completed','cancelled','no_show') DEFAULT 'scheduled',
  `consultation_notes` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `prescription` text DEFAULT NULL,
  `follow_up_required` tinyint(1) DEFAULT 0,
  `follow_up_date` date DEFAULT NULL,
  `video_link` varchar(255) DEFAULT NULL,
  `consultation_fee` decimal(10,2) DEFAULT 0.00,
  `reason` text DEFAULT NULL,
  `symptoms` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `consultation_number` (`consultation_number`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `consultation_date` (`consultation_date`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 4. Drop and recreate insurance_providers with ALL columns
--    (01_migrate creates it with contact_email/contact_phone,
--     general_modules needs provider_code/contact_number/email)
-- =====================================================
DROP TABLE IF EXISTS `insurance_claims`;
DROP TABLE IF EXISTS `insurance_providers`;
CREATE TABLE `insurance_providers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `provider_name` varchar(100) NOT NULL,
  `provider_type` enum('HMO','Insurance','PhilHealth','Government','Private') NOT NULL,
  `provider_code` varchar(50) DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `coverage_details` text DEFAULT NULL,
  `reimbursement_rate` decimal(5,2) DEFAULT 0.00,
  `status` enum('active','inactive','pending_approval') DEFAULT 'active',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `provider_code` (`provider_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Recreate insurance_claims (depends on insurance_providers)
CREATE TABLE IF NOT EXISTS `insurance_claims` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `claim_number` varchar(50) NOT NULL,
  `billing_id` int(11) NOT NULL,
  `insurance_provider_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `claim_type` enum('HMO_LOA','Reimbursement','Direct_Billing') NOT NULL,
  `claim_amount` decimal(12,2) NOT NULL,
  `approved_amount` decimal(12,2) DEFAULT NULL,
  `status` enum('pending','submitted','approved','rejected','paid') DEFAULT 'pending',
  `submitted_at` datetime DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `claim_number` (`claim_number`),
  KEY `billing_id` (`billing_id`),
  KEY `insurance_provider_id` (`insurance_provider_id`),
  KEY `patient_id` (`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 5. Drop and recreate wards with ALL columns
--    (base schema created without floor/building/description)
-- =====================================================
DROP TABLE IF EXISTS `beds`;
DROP TABLE IF EXISTS `wards`;
CREATE TABLE `wards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ward_code` varchar(10) NOT NULL,
  `ward_name` varchar(100) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `ward_type` enum('general','private','semi_private','icu','emergency','maternity','pediatric') NOT NULL,
  `capacity` int(11) NOT NULL,
  `current_occupancy` int(11) DEFAULT 0,
  `charge_per_day` decimal(10,2) DEFAULT 0.00,
  `status` enum('available','full','maintenance','closed') DEFAULT 'available',
  `floor` varchar(10) DEFAULT NULL,
  `building` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ward_code` (`ward_code`),
  KEY `department_id` (`department_id`),
  KEY `ward_type` (`ward_type`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Recreate beds table (depends on wards)
CREATE TABLE `beds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bed_number` varchar(20) NOT NULL,
  `ward_id` int(11) NOT NULL,
  `bed_type` enum('regular','icu','maternity','pediatric','isolation') DEFAULT 'regular',
  `status` enum('available','occupied','maintenance','reserved') DEFAULT 'available',
  `current_patient_id` int(11) DEFAULT NULL,
  `daily_rate` decimal(10,2) DEFAULT 0.00,
  `features` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `bed_number` (`bed_number`),
  KEY `ward_id` (`ward_id`),
  KEY `current_patient_id` (`current_patient_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 6. Fix billing view (needs teleconsultations to exist)
-- =====================================================
DROP VIEW IF EXISTS `patient_billing`;
CREATE VIEW `patient_billing` AS SELECT
  id, bill_number AS billing_number, patient_id, admission_id, appointment_id, teleconsultation_id,
  total_amount, paid_amount, balance_amount AS balance, payment_status AS status, bill_date AS billing_date, due_date,
  payment_method, insurance_provider, philhealth_benefits, created_by, created_at, updated_at
FROM `billing`;

-- 7. Fix patient_queue FK: patient_id should reference patients(id) not users(id)
-- so queue entries can use real patient IDs from the patients table
ALTER TABLE `patient_queue` DROP FOREIGN KEY `patient_queue_ibfk_1`;
ALTER TABLE `patient_queue` ADD CONSTRAINT `patient_queue_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;
