-- =====================================================
-- Hospital Core 1 System - Seed Data Part 3
-- Billing, Payments, Insurance, System Settings, Clinic Hours
-- Philippine hospital transaction data.
-- Run after: seed_admin_data.sql, seed_admin_data_part2.sql
-- Requires: 01_migrate_admin_schema.sql (for payments, insurance_providers, etc.)
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- =====================================================
-- 1. BILLING (base table: billing)
-- =====================================================
INSERT INTO `billing` (`id`,`bill_number`,`patient_id`,`admission_id`,`appointment_id`,`teleconsultation_id`,`total_amount`,`paid_amount`,`balance_amount`,`payment_status`,`bill_date`,`due_date`,`payment_method`,`insurance_provider`,`philhealth_benefits`,`created_by`,`created_at`) VALUES
(1,'BILL-2026-0001',1,NULL,1,NULL,2500.00,2500.00,0.00,'paid','2025-12-01',NULL,'cash',NULL,0.00,13,'2025-12-01 10:00:00'),
(2,'BILL-2026-0002',2,NULL,2,NULL,1800.00,1800.00,0.00,'paid','2025-12-03',NULL,'cash',NULL,0.00,13,'2025-12-03 11:00:00'),
(3,'BILL-2026-0003',3,NULL,3,NULL,1200.00,1200.00,0.00,'paid','2025-12-05',NULL,'philhealth','PhilHealth',600.00,13,'2025-12-05 15:00:00'),
(4,'BILL-2026-0004',5,NULL,5,NULL,3500.00,2000.00,1500.00,'partial','2025-12-10','2025-12-24','cash',NULL,0.00,13,'2025-12-10 12:00:00'),
(5,'BILL-2026-0005',8,NULL,8,NULL,5200.00,0.00,5200.00,'pending','2025-12-20','2026-01-05','insurance','Maxicare',NULL,13,'2025-12-20 15:00:00'),
(6,'BILL-2026-0006',11,NULL,11,NULL,800.00,800.00,0.00,'paid','2026-01-05',NULL,'cash',NULL,0.00,14,'2026-01-05 09:00:00'),
(7,'BILL-2026-0007',14,NULL,14,NULL,1500.00,1500.00,0.00,'paid','2026-01-12',NULL,'philhealth','PhilHealth',750.00,13,'2026-01-12 10:00:00'),
(8,'BILL-2026-0008',21,NULL,21,NULL,1200.00,0.00,1200.00,'pending','2026-02-03','2026-02-17','cash',NULL,0.00,14,'2026-02-03 10:00:00'),
(9,'BILL-2026-0009',22,NULL,22,NULL,1800.00,1800.00,0.00,'paid','2026-02-05',NULL,'hmo','Intellicare',NULL,13,'2026-02-05 11:30:00'),
(10,'BILL-2026-0010',27,NULL,27,NULL,2500.00,1000.00,1500.00,'partial','2026-02-10',NULL,'cash',NULL,0.00,14,'2026-02-10 15:00:00'),
(11,'BILL-2026-0011',1,1,NULL,NULL,45000.00,20000.00,25000.00,'partial','2026-02-01','2026-02-15','philhealth','PhilHealth',15000.00,13,'2026-02-01 09:00:00'),
(12,'BILL-2026-0012',2,2,NULL,NULL,85000.00,0.00,85000.00,'pending','2026-02-03','2026-02-17','insurance','Maxicare',NULL,14,'2026-02-03 11:00:00'),
(13,'BILL-2026-0013',7,7,NULL,NULL,32000.00,32000.00,0.00,'paid','2026-02-08',NULL,'cash',NULL,12000.00,13,'2026-02-08 10:00:00')
ON DUPLICATE KEY UPDATE `payment_status` = VALUES(`payment_status`), `paid_amount` = VALUES(`paid_amount`), `balance_amount` = VALUES(`balance_amount`);

-- =====================================================
-- 2. PAYMENTS (for schema with payments.billing_id only)
-- If your DB uses patient_bills + payments(patient_bill_id), skip:
--   mysql ... < seed_payments_billing_schema.sql
-- See seed_payments_billing_schema.sql when payments has billing_id.
-- =====================================================

-- =====================================================
-- 3. INSURANCE PROVIDERS (Philippine HMOs/PhilHealth)
-- Uses columns common to both schemas: provider_name, provider_type, contact_person, contact_number, email, address, status
-- =====================================================
INSERT INTO `insurance_providers` (`id`,`provider_name`,`provider_type`,`contact_person`,`contact_number`,`email`,`address`,`status`,`created_at`) VALUES
(1,'PhilHealth', 'PhilHealth', 'Regional Office NCR', '02-8641-9000', 'ncr@philhealth.gov.ph', 'Citystate Centre, 709 Shaw Blvd, Pasig City', 'active', NOW()),
(2,'Maxicare Healthcare Corp.', 'HMO', 'Account Manager NCR', '02-8870-6000', 'corporate@maxicare.com.ph', 'Maxicare Plaza, Makati City', 'active', NOW()),
(3,'Intellicare', 'HMO', 'Provider Relations', '02-8552-7000', 'providers@intellicare.com.ph', 'Ortigas Center, Pasig City', 'active', NOW()),
(4,'Medicare Plus (Pacific Cross)', 'Private', 'Claims Dept', '02-8818-7000', 'claims@pacificcross.com', 'Makati City', 'active', NOW()),
(5,'Caritas Health Shield', 'HMO', 'Member Services', '02-8842-5000', 'info@caritashealthshield.com', 'Quezon City', 'active', NOW()),
(6,'Etiqa (MAA General)', 'Private', 'Hospital Relations', '02-8810-3000', 'hospitals@etiqa.com.ph', 'Taguig City', 'active', NOW())
ON DUPLICATE KEY UPDATE `provider_name` = VALUES(`provider_name`), `email` = VALUES(`email`), `status` = VALUES(`status`);

-- =====================================================
-- 4. INSURANCE CLAIMS
-- =====================================================
INSERT INTO `insurance_claims` (`id`,`claim_number`,`billing_id`,`insurance_provider_id`,`patient_id`,`claim_type`,`claim_amount`,`approved_amount`,`status`,`submitted_at`,`processed_by`,`created_at`) VALUES
(1,'CLM-2026-0001',5,2,8,'Direct_Billing',5200.00,NULL,'submitted','2026-02-01 10:00:00',13,NOW()),
(2,'CLM-2026-0002',12,2,2,'Direct_Billing',85000.00,NULL,'pending',NULL,NULL,NOW()),
(3,'CLM-2026-0003',3,1,3,'Reimbursement',600.00,600.00,'paid','2025-12-06 09:00:00',13,NOW()),
(4,'CLM-2026-0004',7,1,14,'Reimbursement',750.00,750.00,'paid','2026-01-13 10:00:00',13,NOW()),
(5,'CLM-2026-0005',9,3,22,'HMO_LOA',1800.00,1800.00,'approved','2026-02-05 12:00:00',13,NOW()),
(6,'CLM-2026-0006',11,1,1,'Reimbursement',15000.00,NULL,'submitted','2026-02-02 09:30:00',13,NOW())
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- =====================================================
-- 5. SYSTEM SETTINGS (Alvion / Philippines)
-- =====================================================
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('site_name', 'Alvion Health Network', 'string', 'Hospital name'),
('site_short_name', 'ALVION', 'string', 'Hospital short name'),
('default_consultation_fee', '500.00', 'string', 'Default consultation fee (PHP)'),
('currency', 'PHP', 'string', 'Currency code'),
('timezone', 'Asia/Manila', 'string', 'System timezone'),
('hospital_address', 'Metro Manila, Philippines', 'string', 'Main address'),
('hospital_phone', '+63 2 8123 4567', 'string', 'Main contact'),
('philhealth_accredited', '1', 'boolean', 'PhilHealth accredited')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`), `description` = VALUES(`description`);

-- =====================================================
-- 6. CLINIC HOURS (Philippine hospital - Mon–Fri 8–5, Sat half day)
-- Run only if clinic_hours is empty for general (department IS NULL).
-- =====================================================
INSERT IGNORE INTO `clinic_hours` (`department`, `day_of_week`, `open_time`, `close_time`, `is_closed`) VALUES
(NULL, 'monday', '08:00:00', '17:00:00', 0),
(NULL, 'tuesday', '08:00:00', '17:00:00', 0),
(NULL, 'wednesday', '08:00:00', '17:00:00', 0),
(NULL, 'thursday', '08:00:00', '17:00:00', 0),
(NULL, 'friday', '08:00:00', '17:00:00', 0),
(NULL, 'saturday', '08:00:00', '12:00:00', 0),
(NULL, 'sunday', '00:00:00', '00:00:00', 1);

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
