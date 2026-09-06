-- =====================================================
-- Seed real data for all report pages
-- Run after: seed_admin_data.sql, er_triage.sql, billing table, wards_and_beds (for bed report)
-- =====================================================
-- Reports covered: Appointments (seed_appointments_report.sql), Billing, ER, Bed (needs wards_and_beds + admissions)
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- =====================================================
-- 1. BILLING REPORT – current month so default date range shows data
-- Uses patients 1–5, created_by 1 (admin)
-- =====================================================
SET @uid = (SELECT id FROM users LIMIT 1);

INSERT IGNORE INTO `billing` (`bill_number`, `patient_id`, `total_amount`, `paid_amount`, `balance_amount`, `payment_status`, `bill_date`, `due_date`, `payment_method`, `created_by`, `created_at`) VALUES
(CONCAT('RPT-BILL-', DATE_FORMAT(CURDATE(), '%Y%m'), '-01'), 1, 2500.00, 2500.00, 0.00, 'paid', DATE_SUB(CURDATE(), INTERVAL 5 DAY), NULL, 'cash', @uid, NOW()),
(CONCAT('RPT-BILL-', DATE_FORMAT(CURDATE(), '%Y%m'), '-02'), 2, 1800.00, 1800.00, 0.00, 'paid', DATE_SUB(CURDATE(), INTERVAL 4 DAY), NULL, 'cash', @uid, NOW()),
(CONCAT('RPT-BILL-', DATE_FORMAT(CURDATE(), '%Y%m'), '-03'), 3, 3200.00, 1500.00, 1700.00, 'partial', DATE_SUB(CURDATE(), INTERVAL 3 DAY), CURDATE() + INTERVAL 14 DAY, 'philhealth', @uid, NOW()),
(CONCAT('RPT-BILL-', DATE_FORMAT(CURDATE(), '%Y%m'), '-04'), 4, 950.00, 0.00, 950.00, 'pending', DATE_SUB(CURDATE(), INTERVAL 2 DAY), CURDATE() + INTERVAL 7 DAY, 'cash', @uid, NOW()),
(CONCAT('RPT-BILL-', DATE_FORMAT(CURDATE(), '%Y%m'), '-05'), 5, 4100.00, 4100.00, 0.00, 'paid', CURDATE(), NULL, 'hmo', @uid, NOW());

-- =====================================================
-- 2. ER REPORT – er_triage rows in last 30 days (mixed statuses)
-- =====================================================
SET @nurse_id = (SELECT id FROM users WHERE role_id IN (SELECT id FROM roles WHERE role_name IN ('nurse', 'admin')) LIMIT 1);
SET @nurse_id = IFNULL(@nurse_id, 1);

INSERT INTO `er_triage` (`patient_id`, `triage_nurse_id`, `chief_complaint`, `triage_level`, `vital_signs`, `initial_assessment`, `priority_score`, `status`, `created_at`) VALUES
(1, @nurse_id, 'Chest pain', 'emergency', '{"bp":"140/90","hr":95}', 'ECG ordered', 2, 'discharged', DATE_SUB(CURDATE(), INTERVAL 28 DAY) + INTERVAL 10 HOUR),
(2, @nurse_id, 'Abdominal pain', 'urgent', '{"bp":"130/85","hr":88}', 'Surgical consult', 3, 'discharged', DATE_SUB(CURDATE(), INTERVAL 21 DAY) + INTERVAL 14 HOUR),
(3, @nurse_id, 'Fever and cough', 'semi_urgent', '{"bp":"120/80","hr":85}', 'Chest X-ray', 4, 'discharged', DATE_SUB(CURDATE(), INTERVAL 14 DAY) + INTERVAL 9 HOUR),
(4, @nurse_id, 'Head injury', 'emergency', '{"bp":"118/76","hr":92}', 'CT head', 2, 'admitted', DATE_SUB(CURDATE(), INTERVAL 10 DAY) + INTERVAL 11 HOUR),
(5, @nurse_id, 'Minor laceration', 'non_urgent', '{"bp":"115/75","hr":72}', 'Suturing', 5, 'discharged', DATE_SUB(CURDATE(), INTERVAL 7 DAY) + INTERVAL 15 HOUR),
(6, @nurse_id, 'Shortness of breath', 'urgent', '{"bp":"135/88","hr":102}', 'Nebulization', 3, 'transferred', DATE_SUB(CURDATE(), INTERVAL 5 DAY) + INTERVAL 8 HOUR),
(7, @nurse_id, 'Allergic reaction', 'emergency', '{"bp":"100/65","hr":110}', 'Epinephrine given', 2, 'discharged', DATE_SUB(CURDATE(), INTERVAL 3 DAY) + INTERVAL 16 HOUR),
(1, @nurse_id, 'Follow-up wound check', 'non_urgent', '{"bp":"120/78","hr":75}', 'Healing well', 5, 'discharged', DATE_SUB(CURDATE(), INTERVAL 1 DAY) + INTERVAL 10 HOUR);

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- NOTES:
-- - Appointments Report: run seed_appointments_report.sql for Jan–Feb 2026 data.
-- - Bed Occupancy Report: run wards_and_beds_setup.sql then admissions.sql
--   so wards, beds, and admissions exist; report will show occupancy and trend.
-- =====================================================
