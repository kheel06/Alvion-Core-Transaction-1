-- =====================================================
-- Admin Dashboard: seed data for "last 7 days" metrics
-- and charts (appointments, revenue, new patients, ER).
-- Run after: seed_admin_data.sql, seed_admin_data_part2.sql,
--            seed_admin_data_part3.sql, seed_payments_billing_schema.sql
-- Uses CURDATE() so data always falls in the last 7 days.
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- =====================================================
-- 1. APPOINTMENTS in last 7 days (for "Appointments (7 days)" and chart)
-- Uses existing patients 1-7 and doctors 3-5. One per day.
-- =====================================================
INSERT INTO `appointments` (`appointment_number`, `patient_id`, `doctor_id`, `appointment_type`, `appointment_date`, `appointment_time`, `status`, `reason`, `symptoms`, `priority_level`, `is_walkin`, `created_by`, `created_at`)
SELECT 'APT-D7-006', 1, 3, 'consultation', DATE_SUB(CURDATE(), INTERVAL 6 DAY), '09:00:00', 'completed', 'BP check', 'Routine', 'medium', 0, 11, NOW()
WHERE NOT EXISTS (SELECT 1 FROM appointments WHERE appointment_number = 'APT-D7-006');
INSERT INTO `appointments` (`appointment_number`, `patient_id`, `doctor_id`, `appointment_type`, `appointment_date`, `appointment_time`, `status`, `reason`, `symptoms`, `priority_level`, `is_walkin`, `created_by`, `created_at`)
SELECT 'APT-D7-005', 2, 4, 'consultation', DATE_SUB(CURDATE(), INTERVAL 5 DAY), '10:00:00', 'completed', 'Headache follow-up', 'None', 'low', 0, 11, NOW()
WHERE NOT EXISTS (SELECT 1 FROM appointments WHERE appointment_number = 'APT-D7-005');
INSERT INTO `appointments` (`appointment_number`, `patient_id`, `doctor_id`, `appointment_type`, `appointment_date`, `appointment_time`, `status`, `reason`, `symptoms`, `priority_level`, `is_walkin`, `created_by`, `created_at`)
SELECT 'APT-D7-004', 3, 5, 'followup', DATE_SUB(CURDATE(), INTERVAL 4 DAY), '11:00:00', 'completed', 'Diabetes recheck', 'Stable', 'medium', 0, 12, NOW()
WHERE NOT EXISTS (SELECT 1 FROM appointments WHERE appointment_number = 'APT-D7-004');
INSERT INTO `appointments` (`appointment_number`, `patient_id`, `doctor_id`, `appointment_type`, `appointment_date`, `appointment_time`, `status`, `reason`, `symptoms`, `priority_level`, `is_walkin`, `created_by`, `created_at`)
SELECT 'APT-D7-003', 4, 3, 'checkup', DATE_SUB(CURDATE(), INTERVAL 3 DAY), '08:30:00', 'completed', 'Annual physical', 'None', 'low', 0, 11, NOW()
WHERE NOT EXISTS (SELECT 1 FROM appointments WHERE appointment_number = 'APT-D7-003');
INSERT INTO `appointments` (`appointment_number`, `patient_id`, `doctor_id`, `appointment_type`, `appointment_date`, `appointment_time`, `status`, `reason`, `symptoms`, `priority_level`, `is_walkin`, `created_by`, `created_at`)
SELECT 'APT-D7-002', 5, 4, 'consultation', DATE_SUB(CURDATE(), INTERVAL 2 DAY), '14:00:00', 'completed', 'Asthma review', 'Wheezing improved', 'medium', 0, 12, NOW()
WHERE NOT EXISTS (SELECT 1 FROM appointments WHERE appointment_number = 'APT-D7-002');
INSERT INTO `appointments` (`appointment_number`, `patient_id`, `doctor_id`, `appointment_type`, `appointment_date`, `appointment_time`, `status`, `reason`, `symptoms`, `priority_level`, `is_walkin`, `created_by`, `created_at`)
SELECT 'APT-D7-001', 6, 5, 'consultation', DATE_SUB(CURDATE(), INTERVAL 1 DAY), '10:30:00', 'completed', 'Skin rash', 'Rash on arms', 'low', 0, 11, NOW()
WHERE NOT EXISTS (SELECT 1 FROM appointments WHERE appointment_number = 'APT-D7-001');
INSERT INTO `appointments` (`appointment_number`, `patient_id`, `doctor_id`, `appointment_type`, `appointment_date`, `appointment_time`, `status`, `reason`, `symptoms`, `priority_level`, `is_walkin`, `created_by`, `created_at`)
SELECT 'APT-D7-000', 7, 3, 'consultation', CURDATE(), '09:00:00', 'in_progress', 'Prenatal check', 'Routine', 'medium', 0, 12, NOW()
WHERE NOT EXISTS (SELECT 1 FROM appointments WHERE appointment_number = 'APT-D7-000');

-- Schedule page: today's appointments so "Upcoming" and "Today's Summary" (Scheduled/Confirmed/Completed) show data
INSERT INTO `appointments` (`appointment_number`, `patient_id`, `doctor_id`, `appointment_type`, `appointment_date`, `appointment_time`, `status`, `reason`, `symptoms`, `priority_level`, `is_walkin`, `created_by`, `created_at`)
SELECT 'APT-TODAY-SCH', 1, 4, 'consultation', CURDATE(), '10:00:00', 'scheduled', 'Follow-up', 'None', 'medium', 0, 11, NOW()
WHERE NOT EXISTS (SELECT 1 FROM appointments WHERE appointment_number = 'APT-TODAY-SCH');
INSERT INTO `appointments` (`appointment_number`, `patient_id`, `doctor_id`, `appointment_type`, `appointment_date`, `appointment_time`, `status`, `reason`, `symptoms`, `priority_level`, `is_walkin`, `created_by`, `created_at`)
SELECT 'APT-TODAY-CON', 2, 5, 'consultation', CURDATE(), '11:30:00', 'confirmed', 'Check-up', 'None', 'low', 0, 11, NOW()
WHERE NOT EXISTS (SELECT 1 FROM appointments WHERE appointment_number = 'APT-TODAY-CON');
INSERT INTO `appointments` (`appointment_number`, `patient_id`, `doctor_id`, `appointment_type`, `appointment_date`, `appointment_time`, `status`, `reason`, `symptoms`, `priority_level`, `is_walkin`, `created_by`, `created_at`)
SELECT 'APT-TODAY-DON', 3, 3, 'followup', CURDATE(), '08:00:00', 'completed', 'Diabetes review', 'Stable', 'medium', 0, 11, NOW()
WHERE NOT EXISTS (SELECT 1 FROM appointments WHERE appointment_number = 'APT-TODAY-DON');

-- Walk-in page: 2 walk-ins for today (is_walkin = 1)
INSERT INTO `appointments` (`appointment_number`, `patient_id`, `doctor_id`, `appointment_type`, `appointment_date`, `appointment_time`, `status`, `reason`, `symptoms`, `priority_level`, `is_walkin`, `created_by`, `created_at`)
SELECT 'APT-WALK-001', 4, 5, 'consultation', CURDATE(), '09:30:00', 'scheduled', 'Walk-in fever', 'Fever, cough', 'medium', 1, 11, NOW()
WHERE NOT EXISTS (SELECT 1 FROM appointments WHERE appointment_number = 'APT-WALK-001');
INSERT INTO `appointments` (`appointment_number`, `patient_id`, `doctor_id`, `appointment_type`, `appointment_date`, `appointment_time`, `status`, `reason`, `symptoms`, `priority_level`, `is_walkin`, `created_by`, `created_at`)
SELECT 'APT-WALK-002', 5, 6, 'consultation', CURDATE(), '14:00:00', 'in_progress', 'Walk-in minor injury', 'Cut on hand', 'low', 1, 11, NOW()
WHERE NOT EXISTS (SELECT 1 FROM appointments WHERE appointment_number = 'APT-WALK-002');

-- =====================================================
-- 2. NEW PATIENTS in last 7 days (update created_at for demo)
-- Backdate existing patients 5,6,7 so "New patients (7 days)" shows data (works with only 7 patients)
-- =====================================================
UPDATE `patients` SET `created_at` = DATE_SUB(CURDATE(), INTERVAL 6 DAY) + INTERVAL 10 HOUR WHERE `id` = 5 LIMIT 1;
UPDATE `patients` SET `created_at` = DATE_SUB(CURDATE(), INTERVAL 5 DAY) + INTERVAL 9 HOUR WHERE `id` = 6 LIMIT 1;
UPDATE `patients` SET `created_at` = DATE_SUB(CURDATE(), INTERVAL 4 DAY) + INTERVAL 14 HOUR WHERE `id` = 7 LIMIT 1;
UPDATE `patients` SET `created_at` = DATE_SUB(CURDATE(), INTERVAL 3 DAY) + INTERVAL 11 HOUR WHERE `id` = 3 LIMIT 1;
UPDATE `patients` SET `created_at` = DATE_SUB(CURDATE(), INTERVAL 2 DAY) + INTERVAL 8 HOUR WHERE `id` = 4 LIMIT 1;
UPDATE `patients` SET `created_at` = DATE_SUB(CURDATE(), INTERVAL 1 DAY) + INTERVAL 15 HOUR WHERE `id` = 2 LIMIT 1;

-- =====================================================
-- 3. PAYMENTS in last 7 days (for "Revenue (7 days)" and chart)
-- Use existing billing rows; if none, insert 3 minimal billing rows then payments
-- =====================================================
SET @b1 = (SELECT id FROM billing LIMIT 1);
SET @b2 = (SELECT id FROM billing ORDER BY id LIMIT 1 OFFSET 1);
SET @b3 = (SELECT id FROM billing ORDER BY id LIMIT 1 OFFSET 2);
SET @uid = (SELECT id FROM users LIMIT 1);

-- If no billing rows, insert 3 so dashboard revenue can show
INSERT IGNORE INTO `billing` (`bill_number`, `patient_id`, `total_amount`, `paid_amount`, `balance_amount`, `payment_status`, `bill_date`, `payment_method`, `created_by`, `created_at`)
SELECT 'BILL-D7-01', 1, 5000.00, 0, 5000.00, 'pending', CURDATE(), 'cash', @uid, NOW()
UNION ALL SELECT 'BILL-D7-02', 2, 3000.00, 0, 3000.00, 'pending', CURDATE(), 'cash', @uid, NOW()
UNION ALL SELECT 'BILL-D7-03', 3, 2000.00, 0, 2000.00, 'pending', CURDATE(), 'cash', @uid, NOW()
FROM DUAL WHERE (SELECT COUNT(*) FROM billing) = 0;

SET @b1 = (SELECT id FROM billing LIMIT 1);
SET @b2 = (SELECT id FROM billing ORDER BY id LIMIT 1 OFFSET 1);
SET @b3 = (SELECT id FROM billing ORDER BY id LIMIT 1 OFFSET 2);

INSERT INTO `payments` (`payment_number`, `billing_id`, `patient_id`, `payment_method`, `amount`, `payment_date`, `reference_number`, `status`, `processed_by`, `notes`)
SELECT 'PAY-D7-001', @b1, 1, 'cash', 1500.00, DATE_SUB(CURDATE(), INTERVAL 6 DAY) + INTERVAL 10 HOUR, 'D7-001', 'completed', @uid, 'Dashboard demo'
WHERE @b1 IS NOT NULL AND NOT EXISTS (SELECT 1 FROM payments WHERE payment_number = 'PAY-D7-001');
INSERT INTO `payments` (`payment_number`, `billing_id`, `patient_id`, `payment_method`, `amount`, `payment_date`, `reference_number`, `status`, `processed_by`, `notes`)
SELECT 'PAY-D7-002', @b2, 2, 'cash', 2200.00, DATE_SUB(CURDATE(), INTERVAL 5 DAY) + INTERVAL 11 HOUR, 'D7-002', 'completed', @uid, 'Dashboard demo'
WHERE @b2 IS NOT NULL AND NOT EXISTS (SELECT 1 FROM payments WHERE payment_number = 'PAY-D7-002');
INSERT INTO `payments` (`payment_number`, `billing_id`, `patient_id`, `payment_method`, `amount`, `payment_date`, `reference_number`, `status`, `processed_by`, `notes`)
SELECT 'PAY-D7-003', @b3, 3, 'philhealth', 1800.00, DATE_SUB(CURDATE(), INTERVAL 4 DAY) + INTERVAL 9 HOUR, 'D7-003', 'completed', @uid, 'Dashboard demo'
WHERE @b3 IS NOT NULL AND NOT EXISTS (SELECT 1 FROM payments WHERE payment_number = 'PAY-D7-003');
INSERT INTO `payments` (`payment_number`, `billing_id`, `patient_id`, `payment_method`, `amount`, `payment_date`, `reference_number`, `status`, `processed_by`, `notes`)
SELECT 'PAY-D7-004', @b1, 1, 'cash', 3000.00, DATE_SUB(CURDATE(), INTERVAL 2 DAY) + INTERVAL 14 HOUR, 'D7-004', 'completed', @uid, 'Dashboard demo'
WHERE @b1 IS NOT NULL AND NOT EXISTS (SELECT 1 FROM payments WHERE payment_number = 'PAY-D7-004');
INSERT INTO `payments` (`payment_number`, `billing_id`, `patient_id`, `payment_method`, `amount`, `payment_date`, `reference_number`, `status`, `processed_by`, `notes`)
SELECT 'PAY-D7-005', @b2, 2, 'hmo', 2500.00, CURDATE() + INTERVAL 9 HOUR, 'D7-005', 'completed', @uid, 'Dashboard demo'
WHERE @b2 IS NOT NULL AND NOT EXISTS (SELECT 1 FROM payments WHERE payment_number = 'PAY-D7-005');

-- =====================================================
-- 4. ER TRIAGE in last 7 days (for "Patient registrations & ER cases" chart)
-- Uses patients 1-5 and first user as nurse so it works with minimal data
-- =====================================================
SET @nurse_id = (SELECT id FROM users LIMIT 1);

INSERT INTO `er_triage` (`patient_id`, `triage_nurse_id`, `chief_complaint`, `triage_level`, `vital_signs`, `initial_assessment`, `priority_score`, `status`, `created_at`)
SELECT 1, @nurse_id, 'Minor injury', 'non_urgent', '{"bp":"120/80","hr":75,"temp":36.8,"rr":16,"spo2":98}', 'Small cut', 5, 'discharged', DATE_SUB(CURDATE(), INTERVAL 5 DAY) + INTERVAL 13 HOUR
WHERE NOT EXISTS (SELECT 1 FROM er_triage WHERE patient_id = 1 AND DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 5 DAY) LIMIT 1);
INSERT INTO `er_triage` (`patient_id`, `triage_nurse_id`, `chief_complaint`, `triage_level`, `vital_signs`, `initial_assessment`, `priority_score`, `status`, `created_at`)
SELECT 2, @nurse_id, 'Fever and cough', 'urgent', '{"bp":"118/78","hr":95,"temp":38.5,"rr":22,"spo2":96}', 'URTI', 3, 'discharged', DATE_SUB(CURDATE(), INTERVAL 2 DAY) + INTERVAL 10 HOUR
WHERE NOT EXISTS (SELECT 1 FROM er_triage WHERE patient_id = 2 AND DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 2 DAY) LIMIT 1);
INSERT INTO `er_triage` (`patient_id`, `triage_nurse_id`, `chief_complaint`, `triage_level`, `vital_signs`, `initial_assessment`, `priority_score`, `status`, `created_at`)
SELECT 3, @nurse_id, 'Abdominal pain', 'semi_urgent', '{"bp":"125/82","hr":88,"temp":37.0,"rr":18,"spo2":98}', 'Mild gastritis', 4, 'waiting', CURDATE() + INTERVAL 8 HOUR
WHERE NOT EXISTS (SELECT 1 FROM er_triage WHERE patient_id = 3 AND DATE(created_at) = CURDATE() LIMIT 1);
INSERT INTO `er_triage` (`patient_id`, `triage_nurse_id`, `chief_complaint`, `triage_level`, `vital_signs`, `initial_assessment`, `priority_score`, `status`, `created_at`)
SELECT 4, @nurse_id, 'Chest pain', 'emergency', '{"bp":"140/90","hr":95}', 'ECG ordered', 2, 'discharged', DATE_SUB(CURDATE(), INTERVAL 3 DAY) + INTERVAL 9 HOUR
WHERE NOT EXISTS (SELECT 1 FROM er_triage WHERE patient_id = 4 AND DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 3 DAY) LIMIT 1);
INSERT INTO `er_triage` (`patient_id`, `triage_nurse_id`, `chief_complaint`, `triage_level`, `vital_signs`, `initial_assessment`, `priority_score`, `status`, `created_at`)
SELECT 5, @nurse_id, 'Headache', 'non_urgent', '{"bp":"115/75","hr":72}', 'Pain relief', 5, 'discharged', DATE_SUB(CURDATE(), INTERVAL 1 DAY) + INTERVAL 14 HOUR
WHERE NOT EXISTS (SELECT 1 FROM er_triage WHERE patient_id = 5 AND DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) LIMIT 1);

-- =====================================================
-- 5. "TODAY" METRICS (so dashboard cards show real numbers)
-- =====================================================

-- At least one patient registered "today" (+N today)
SET @today_patients = (SELECT COUNT(*) FROM patients WHERE DATE(created_at) = CURDATE());
UPDATE `patients` SET `created_at` = CONCAT(CURDATE(), ' 08:00:00')
WHERE @today_patients = 0
ORDER BY id LIMIT 1;

-- Extra payment today (revenue today & payments count)
INSERT INTO `payments` (`payment_number`, `billing_id`, `patient_id`, `payment_method`, `amount`, `payment_date`, `reference_number`, `status`, `processed_by`, `notes`)
SELECT 'PAY-TODAY-01', @b1, 1, 'cash', 1200.00, CURDATE() + INTERVAL 10 HOUR, 'TODAY-01', 'completed', @uid, 'Dashboard today'
WHERE @b1 IS NOT NULL AND NOT EXISTS (SELECT 1 FROM payments WHERE payment_number = 'PAY-TODAY-01');

-- ER: one in_progress today (so "In Progress" > 0)
INSERT INTO `er_triage` (`patient_id`, `triage_nurse_id`, `chief_complaint`, `triage_level`, `vital_signs`, `initial_assessment`, `priority_score`, `status`, `created_at`)
SELECT 4, @nurse_id, 'Ankle sprain', 'non_urgent', '{"bp":"118/76","hr":78}', 'X-ray ordered', 5, 'in_progress', CURDATE() + INTERVAL 9 HOUR
WHERE NOT EXISTS (SELECT 1 FROM er_triage WHERE patient_id = 4 AND status = 'in_progress' AND DATE(created_at) = CURDATE() LIMIT 1);

-- =====================================================
-- 6. TELECONSULTATIONS TODAY (only if table exists)
-- =====================================================
INSERT INTO `teleconsultations` (`consultation_number`, `patient_id`, `doctor_id`, `consultation_date`, `status`, `consultation_notes`, `consultation_fee`, `reason`)
SELECT 'TEL-TODAY-1', 1, 3, CONCAT(CURDATE(), ' 09:30:00'), 'completed', 'BP check via video', 500.00, 'Follow-up'
FROM DUAL
WHERE (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'teleconsultations') = 1
AND NOT EXISTS (SELECT 1 FROM teleconsultations WHERE consultation_number = 'TEL-TODAY-1');
INSERT INTO `teleconsultations` (`consultation_number`, `patient_id`, `doctor_id`, `consultation_date`, `status`, `consultation_notes`, `consultation_fee`, `reason`)
SELECT 'TEL-TODAY-2', 2, 4, CONCAT(CURDATE(), ' 11:00:00'), 'ongoing', 'Skin consult', 500.00, 'Rash'
FROM DUAL
WHERE (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'teleconsultations') = 1
AND NOT EXISTS (SELECT 1 FROM teleconsultations WHERE consultation_number = 'TEL-TODAY-2');

-- =====================================================
-- 7. PENDING PRESCRIPTIONS (e_prescriptions; only if table exists)
-- =====================================================
INSERT INTO `e_prescriptions` (`prescription_number`, `patient_id`, `doctor_id`, `prescription_date`, `medications`, `instructions`, `status`)
SELECT 'RX-TODAY-1', 1, 3, CURDATE(), '[{"name":"Amlodipine","dosage":"5mg","frequency":"Once daily"}]', 'Take in AM', 'pending'
FROM DUAL
WHERE (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'e_prescriptions') = 1
AND NOT EXISTS (SELECT 1 FROM e_prescriptions WHERE prescription_number = 'RX-TODAY-1');
INSERT INTO `e_prescriptions` (`prescription_number`, `patient_id`, `doctor_id`, `prescription_date`, `medications`, `instructions`, `status`)
SELECT 'RX-TODAY-2', 2, 4, CURDATE(), '[{"name":"Paracetamol","dosage":"500mg","frequency":"TID"}]', 'Take with food', 'pending'
FROM DUAL
WHERE (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'e_prescriptions') = 1
AND NOT EXISTS (SELECT 1 FROM e_prescriptions WHERE prescription_number = 'RX-TODAY-2');

-- =====================================================
-- 8. PENDING LAB ORDERS (e_lab_orders – create table if missing)
-- =====================================================
CREATE TABLE IF NOT EXISTS `e_lab_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_number` varchar(50) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `consultation_id` int(11) DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `order_date` date NOT NULL,
  `lab_tests` json DEFAULT NULL,
  `priority` enum('routine','urgent','stat') DEFAULT 'routine',
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `results` text DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `completed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `e_lab_orders` (`order_number`, `patient_id`, `doctor_id`, `order_date`, `lab_tests`, `priority`, `status`)
SELECT 'LAB-TODAY-1', 1, 3, CURDATE(), '["CBC","FBS"]', 'routine', 'pending'
WHERE EXISTS (SELECT 1 FROM patients WHERE id = 1) AND NOT EXISTS (SELECT 1 FROM e_lab_orders WHERE order_number = 'LAB-TODAY-1');
INSERT IGNORE INTO `e_lab_orders` (`order_number`, `patient_id`, `doctor_id`, `order_date`, `lab_tests`, `priority`, `status`)
SELECT 'LAB-TODAY-2', 3, 4, CURDATE(), '["Urinalysis","Creatinine"]', 'urgent', 'pending'
WHERE EXISTS (SELECT 1 FROM patients WHERE id = 3) AND NOT EXISTS (SELECT 1 FROM e_lab_orders WHERE order_number = 'LAB-TODAY-2');

-- =====================================================
-- 9. OCCUPIED BEDS (when none are occupied yet)
-- =====================================================
SET @occ = (SELECT COUNT(*) FROM beds WHERE status = 'occupied');
UPDATE `beds` SET `status` = 'occupied', `current_patient_id` = (SELECT id FROM patients LIMIT 1)
WHERE `status` = 'available' AND @occ = 0
ORDER BY id LIMIT 10;

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
