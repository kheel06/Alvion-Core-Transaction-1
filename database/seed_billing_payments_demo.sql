-- =====================================================
-- Billing & Payments demo data for Payment Processing page
-- Run after: seed_admin_data.sql (patients 1-30, users exist)
-- Run: mysql -u root -p hospital-core1-system < seed_billing_payments_demo.sql
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;

-- Ensure patient_billing view exists (payments.php uses it or falls back to billing)
DROP VIEW IF EXISTS `patient_billing`;
CREATE VIEW `patient_billing` AS SELECT
  id, bill_number AS billing_number, patient_id, admission_id, appointment_id, teleconsultation_id,
  total_amount, paid_amount, balance_amount AS balance, payment_status AS status, bill_date AS billing_date, due_date,
  payment_method, insurance_provider, philhealth_benefits, created_by, created_at, updated_at
FROM `billing`;

-- Extra pending/partial bills for diverse patients on the Payment Processing page
SET @uid = (SELECT id FROM users LIMIT 1);
INSERT IGNORE INTO `billing` (`bill_number`, `patient_id`, `total_amount`, `paid_amount`, `balance_amount`, `payment_status`, `bill_date`, `due_date`, `payment_method`, `philhealth_benefits`, `created_by`, `created_at`)
VALUES
  ('BILL-2026-0014', 15, 4500.00, 0.00, 4500.00, 'pending', '2026-02-11', '2026-02-25', 'cash', 0.00, @uid, NOW()),
  ('BILL-2026-0015', 19, 12800.00, 5000.00, 7800.00, 'partial', '2026-02-10', '2026-02-24', 'philhealth', 3200.00, @uid, NOW()),
  ('BILL-2026-0016', 24, 1650.00, 0.00, 1650.00, 'pending', '2026-02-12', '2026-02-26', 'hmo', 0.00, @uid, NOW()),
  ('BILL-2026-0017', 29, 6200.00, 3100.00, 3100.00, 'partial', '2026-02-09', '2026-02-23', 'cash', 0.00, @uid, NOW());

-- Demo payments for partial bills
INSERT IGNORE INTO `payments` (`payment_number`, `billing_id`, `patient_id`, `payment_method`, `amount`, `payment_date`, `reference_number`, `status`, `processed_by`, `notes`)
SELECT 'PAY-20260210-0015', b.id, 19, 'philhealth', 5000.00, '2026-02-10 14:00:00', 'PH-2026-0015', 'completed', @uid, 'PhilHealth partial coverage'
FROM billing b WHERE b.bill_number = 'BILL-2026-0015' LIMIT 1;

INSERT IGNORE INTO `payments` (`payment_number`, `billing_id`, `patient_id`, `payment_method`, `amount`, `payment_date`, `reference_number`, `status`, `processed_by`, `notes`)
SELECT 'PAY-20260209-0017', b.id, 29, 'cash', 3100.00, '2026-02-09 11:30:00', 'CASH-REC-0017', 'completed', @uid, 'Cash down payment'
FROM billing b WHERE b.bill_number = 'BILL-2026-0017' LIMIT 1;

SET FOREIGN_KEY_CHECKS = 1;
