-- =====================================================
-- Insurance Setup seed: Providers, Policies, Coverage Rules
-- For: modules/general/insurance_setup.php (all 3 tabs)
-- Prerequisite: Run general_modules.sql first (creates insurance_providers,
--   insurance_policies, coverage_rules). Then run this seed.
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------
-- 1. Insurance Providers (only if table is empty)
-- Uses general_modules schema: provider_code, contact_number, email
-- -----------------------------------------------------
INSERT INTO `insurance_providers` (
  `provider_name`, `provider_type`, `provider_code`, `contact_person`, `contact_number`, `email`, `address`, `status`
)
SELECT * FROM (
  SELECT 'PhilHealth' AS pn, 'PhilHealth' AS pt, 'PHIC' AS pc, 'Regional Office NCR' AS cp, '02-8641-9000' AS cn, 'ncr@philhealth.gov.ph' AS em, 'Citystate Centre, 709 Shaw Blvd, Pasig City' AS ad, 'active' AS st
  UNION ALL SELECT 'Maxicare Healthcare Corp.', 'HMO', 'MAXICARE', 'Account Manager NCR', '02-8870-6000', 'corporate@maxicare.com.ph', 'Maxicare Plaza, Makati City', 'active'
  UNION ALL SELECT 'Intellicare', 'HMO', 'INTELLICARE', 'Provider Relations', '02-8552-7000', 'providers@intellicare.com.ph', 'Ortigas Center, Pasig City', 'active'
  UNION ALL SELECT 'Medicard Philippines', 'HMO', 'MEDICARD', 'Member Services', '02-8810-2000', 'info@medicard.ph', 'Makati City', 'active'
  UNION ALL SELECT 'Pacific Cross', 'Private', 'PACIFIC-CROSS', 'Claims Dept', '02-8818-7000', 'claims@pacificcross.com', 'Makati City', 'active'
  UNION ALL SELECT 'Caritas Health Shield', 'HMO', 'CARITAS', 'Member Services', '02-8842-5000', 'info@caritashealthshield.com', 'Quezon City', 'active'
) AS t
WHERE (SELECT COUNT(*) FROM insurance_providers) = 0;

-- -----------------------------------------------------
-- 2. Insurance Policies (per provider)
-- -----------------------------------------------------
INSERT IGNORE INTO `insurance_policies` (
  `id`, `provider_id`, `policy_name`, `policy_number`, `coverage_type`, `coverage_percentage`, `max_coverage_amount`, `deductible`, `valid_from`, `valid_until`, `status`
) VALUES
(1, 1, 'PhilHealth Standard', 'PHIC-STD-2026', 'Partial', 80.00, 100000.00, 0.00, '2026-01-01', '2026-12-31', 'active'),
(2, 1, 'PhilHealth Sponsored / Indigent', 'PHIC-SPO-2026', 'Partial', 100.00, NULL, 0.00, '2026-01-01', '2026-12-31', 'active'),
(3, 2, 'Maxicare Platinum', 'MAX-PLAT-001', 'Full', 100.00, 500000.00, 0.00, '2026-01-01', '2026-12-31', 'active'),
(4, 2, 'Maxicare Bronze', 'MAX-BRONZE-001', 'Partial', 70.00, 100000.00, 5000.00, '2026-01-01', '2026-12-31', 'active'),
(5, 3, 'Intellicare Inpatient', 'INT-IN-001', 'Partial', 85.00, 250000.00, 2000.00, '2026-01-01', '2026-12-31', 'active'),
(6, 3, 'Intellicare Outpatient', 'INT-OUT-001', 'Partial', 80.00, 50000.00, 1000.00, '2026-01-01', '2026-12-31', 'active'),
(7, 4, 'Medicard Executive', 'MED-EXEC-001', 'Full', 100.00, 300000.00, 0.00, '2026-01-01', '2026-12-31', 'active'),
(8, 5, 'Pacific Cross Emergency', 'PC-EMER-001', 'Emergency Only', 90.00, 200000.00, 5000.00, '2026-01-01', '2026-12-31', 'active'),
(9, 6, 'Caritas Basic', 'CAR-BASIC-001', 'Partial', 75.00, 150000.00, 3000.00, '2026-01-01', '2026-12-31', 'active');

-- -----------------------------------------------------
-- 3. Coverage Rules (per policy / service type)
-- -----------------------------------------------------
INSERT IGNORE INTO `coverage_rules` (
  `id`, `policy_id`, `service_type`, `coverage_percentage`, `max_amount`, `requires_pre_approval`, `notes`
) VALUES
(1, 1, 'Consultation', 80.00, 500.00, 0, 'Outpatient consultation'),
(2, 1, 'Laboratory', 80.00, 5000.00, 0, 'Routine labs'),
(3, 1, 'Confinement', 80.00, 100000.00, 1, 'Inpatient; pre-approval required'),
(4, 2, 'Consultation', 100.00, NULL, 0, 'Sponsored program'),
(5, 2, 'Confinement', 100.00, NULL, 1, NULL),
(6, 3, 'Consultation', 100.00, 1500.00, 0, NULL),
(7, 3, 'Laboratory', 100.00, 15000.00, 0, NULL),
(8, 3, 'Confinement', 100.00, 500000.00, 1, 'LOA required'),
(9, 3, 'Emergency', 100.00, 100000.00, 0, NULL),
(10, 4, 'Consultation', 70.00, 800.00, 0, NULL),
(11, 4, 'Laboratory', 70.00, 10000.00, 0, NULL),
(12, 4, 'Confinement', 70.00, 100000.00, 1, NULL),
(13, 5, 'Confinement', 85.00, 250000.00, 1, 'Inpatient only'),
(14, 6, 'Consultation', 80.00, 500.00, 0, NULL),
(15, 6, 'Laboratory', 80.00, 50000.00, 0, NULL),
(16, 7, 'Consultation', 100.00, 2000.00, 0, NULL),
(17, 7, 'Laboratory', 100.00, 30000.00, 0, NULL),
(18, 7, 'Confinement', 100.00, 300000.00, 1, NULL),
(19, 8, 'Emergency', 90.00, 200000.00, 0, 'ER only'),
(20, 9, 'Consultation', 75.00, 600.00, 0, NULL),
(21, 9, 'Confinement', 75.00, 150000.00, 1, NULL);

SET FOREIGN_KEY_CHECKS = 1;
