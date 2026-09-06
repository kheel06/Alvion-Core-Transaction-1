-- =====================================================
-- Role-Based System Schema Updates
-- Based on ROLE_PROCESSES.md documentation
-- =====================================================

-- =====================================================
-- 1. Insurance Providers Table
-- =====================================================
CREATE TABLE IF NOT EXISTS `insurance_providers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `provider_name` varchar(100) NOT NULL,
  `provider_type` enum('HMO','Insurance','PhilHealth','Government') NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `coverage_details` text DEFAULT NULL,
  `reimbursement_rate` decimal(5,2) DEFAULT 0.00 COMMENT 'Percentage',
  `status` enum('active','inactive','pending_approval') DEFAULT 'pending_approval',
  `approved_by` int(11) DEFAULT NULL COMMENT 'Admin user_id',
  `approved_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `approved_by` (`approved_by`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 2. Billing Charges Table
-- =====================================================
CREATE TABLE IF NOT EXISTS `billing_charges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `billing_id` int(11) NOT NULL,
  `charge_type` enum('room','lab','doctor_fee','procedure','medication','service','other') NOT NULL,
  `charge_description` varchar(255) NOT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `unit_price` decimal(10,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `final_amount` decimal(10,2) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `billing_id` (`billing_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_billing_charges_billing` FOREIGN KEY (`billing_id`) REFERENCES `billing` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 3. Insurance Claims Table
-- =====================================================
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
  `processed_by` int(11) DEFAULT NULL COMMENT 'Finance staff user_id',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `claim_number` (`claim_number`),
  KEY `billing_id` (`billing_id`),
  KEY `insurance_provider_id` (`insurance_provider_id`),
  KEY `patient_id` (`patient_id`),
  KEY `processed_by` (`processed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 4. Payments Table
-- =====================================================
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_number` varchar(50) NOT NULL,
  `billing_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `payment_method` enum('cash','credit_card','debit_card','online','bank_transfer','check','insurance','hmo') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_date` datetime NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `processed_by` int(11) DEFAULT NULL COMMENT 'Finance staff user_id',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_number` (`payment_number`),
  KEY `billing_id` (`billing_id`),
  KEY `patient_id` (`patient_id`),
  KEY `processed_by` (`processed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 5. Queue Management Table
-- =====================================================
CREATE TABLE IF NOT EXISTS `queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `queue_number` varchar(20) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `queue_type` enum('outpatient','emergency','walkin') NOT NULL,
  `priority_level` enum('low','medium','high','emergency') DEFAULT 'medium',
  `status` enum('waiting','called','in_progress','completed','cancelled','no_show') DEFAULT 'waiting',
  `assigned_by` int(11) DEFAULT NULL COMMENT 'Staff/Receptionist user_id',
  `called_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `queue_number` (`queue_number`),
  KEY `patient_id` (`patient_id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `assigned_by` (`assigned_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 6. Vital Signs Table
-- =====================================================
CREATE TABLE IF NOT EXISTS `vital_signs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `triage_id` int(11) DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL COMMENT 'Staff user_id',
  `blood_pressure_systolic` int(11) DEFAULT NULL,
  `blood_pressure_diastolic` int(11) DEFAULT NULL,
  `temperature` decimal(4,2) DEFAULT NULL COMMENT 'Celsius',
  `pulse_rate` int(11) DEFAULT NULL COMMENT 'BPM',
  `respiratory_rate` int(11) DEFAULT NULL COMMENT 'per minute',
  `oxygen_saturation` decimal(5,2) DEFAULT NULL COMMENT 'Percentage',
  `pain_scale` int(11) DEFAULT NULL COMMENT '0-10',
  `weight` decimal(5,2) DEFAULT NULL COMMENT 'kg',
  `height` decimal(5,2) DEFAULT NULL COMMENT 'cm',
  `bmi` decimal(4,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recorded_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `triage_id` (`triage_id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `recorded_by` (`recorded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 7. Consent Forms Table
-- =====================================================
CREATE TABLE IF NOT EXISTS `consent_forms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `form_type` enum('medical_consent','treatment_consent','privacy_consent','telehealth_consent','surgery_consent') NOT NULL,
  `form_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`form_data`)),
  `signed_at` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `status` enum('pending','signed','expired') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 8. System Settings Table
-- =====================================================
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','integer','decimal','boolean','json') DEFAULT 'string',
  `category` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 9. Clinic Hours Table
-- =====================================================
CREATE TABLE IF NOT EXISTS `clinic_hours` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department` varchar(100) DEFAULT NULL COMMENT 'NULL for general clinic hours',
  `day_of_week` enum('monday','tuesday','wednesday','thursday','friday','saturday','sunday') NOT NULL,
  `open_time` time NOT NULL,
  `close_time` time NOT NULL,
  `is_closed` tinyint(1) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `department` (`department`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 10. Services Catalog Table
-- =====================================================
CREATE TABLE IF NOT EXISTS `services_catalog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service_code` varchar(50) NOT NULL,
  `service_name` varchar(255) NOT NULL,
  `service_category` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `service_code` (`service_code`),
  KEY `service_category` (`service_category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 11. Update audit_logs table to match new structure
-- =====================================================
ALTER TABLE `audit_logs` 
ADD COLUMN IF NOT EXISTS `resource` varchar(100) DEFAULT NULL AFTER `module`,
ADD COLUMN IF NOT EXISTS `status` enum('granted','denied') DEFAULT NULL AFTER `resource`;

-- =====================================================
-- 12. Add missing columns to appointments table
-- =====================================================
ALTER TABLE `appointments`
ADD COLUMN IF NOT EXISTS `clinic` VARCHAR(50) DEFAULT NULL AFTER `doctor_id`,
ADD COLUMN IF NOT EXISTS `department` VARCHAR(100) DEFAULT NULL AFTER `clinic`,
ADD COLUMN IF NOT EXISTS `booking_channel` ENUM('online','walkin','phone','reception') DEFAULT 'online' AFTER `is_walkin`,
ADD COLUMN IF NOT EXISTS `patient_email` VARCHAR(100) DEFAULT NULL AFTER `patient_id`,
ADD COLUMN IF NOT EXISTS `patient_contact` VARCHAR(20) DEFAULT NULL AFTER `patient_email`;

-- =====================================================
-- 13. Add missing columns to patients table for insurance
-- =====================================================
ALTER TABLE `patients`
ADD COLUMN IF NOT EXISTS `insurance_provider_id` int(11) DEFAULT NULL AFTER `philhealth_id`,
ADD COLUMN IF NOT EXISTS `insurance_member_number` varchar(50) DEFAULT NULL AFTER `insurance_provider_id`,
ADD COLUMN IF NOT EXISTS `insurance_expiry_date` date DEFAULT NULL AFTER `insurance_member_number`;

-- =====================================================
-- 14. Insert default system settings
-- =====================================================
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `category`, `description`) VALUES
('site_name', 'Alvion Health Network', 'string', 'general', 'Hospital name'),
('site_short_name', 'ALVION', 'string', 'general', 'Hospital short name'),
('default_consultation_fee', '500.00', 'decimal', 'billing', 'Default consultation fee'),
('currency', 'PHP', 'string', 'general', 'Currency code'),
('timezone', 'Asia/Manila', 'string', 'general', 'System timezone')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- =====================================================
-- 15. Insert default clinic hours (Monday-Friday, 8AM-5PM)
-- =====================================================
INSERT INTO `clinic_hours` (`day_of_week`, `open_time`, `close_time`, `is_closed`) VALUES
('monday', '08:00:00', '17:00:00', 0),
('tuesday', '08:00:00', '17:00:00', 0),
('wednesday', '08:00:00', '17:00:00', 0),
('thursday', '08:00:00', '17:00:00', 0),
('friday', '08:00:00', '17:00:00', 0),
('saturday', '08:00:00', '12:00:00', 0),
('sunday', '00:00:00', '00:00:00', 1)
ON DUPLICATE KEY UPDATE `open_time` = VALUES(`open_time`), `close_time` = VALUES(`close_time`);

-- =====================================================
-- END OF SCHEMA UPDATES
-- =====================================================

