-- =====================================================
-- Seed: Upcoming appointments for Reschedule & Cancel page
-- =====================================================
-- Inserts appointments with status scheduled, confirmed, in_progress
-- and date today or tomorrow so they appear on Reschedule & Cancel.
-- Run after: seed_admin_data (patients + users with role doctor), migrate_appointments_reschedule.sql
-- Safe to re-run (INSERT IGNORE / unique appointment_number).

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- Resolve first available patient and doctor (works with any id range)
SET @p1 = (SELECT id FROM patients ORDER BY id ASC LIMIT 1);
SET @p2 = (SELECT id FROM patients ORDER BY id ASC LIMIT 1 OFFSET 1);
SET @p3 = (SELECT id FROM patients ORDER BY id ASC LIMIT 1 OFFSET 2);
SET @doc1 = (SELECT u.id FROM users u INNER JOIN roles r ON u.role_id = r.id WHERE r.role_name = 'doctor' ORDER BY u.id LIMIT 1);
SET @doc2 = (SELECT u.id FROM users u INNER JOIN roles r ON u.role_id = r.id WHERE r.role_name = 'doctor' ORDER BY u.id LIMIT 1 OFFSET 1);
SET @created = (SELECT id FROM users LIMIT 1);

-- Use single patient/doctor if that's all we have
SET @doc2 = IFNULL(@doc2, @doc1);
SET @p2 = IFNULL(@p2, @p1);
SET @p3 = IFNULL(@p3, @p1);

-- Insert upcoming appointments (idempotent: skip if appointment_number already exists)
INSERT IGNORE INTO `appointments` (`appointment_number`, `patient_id`, `doctor_id`, `appointment_type`, `appointment_date`, `appointment_time`, `status`, `reason`, `symptoms`, `priority_level`, `is_walkin`, `created_by`, `created_at`)
SELECT 'APT-RS-TODAY-1', @p1, @doc1, 'consultation', CURDATE(), '09:00:00', 'scheduled', 'Follow-up visit', 'None', 'medium', 0, @created, NOW() FROM DUAL WHERE @p1 IS NOT NULL AND @doc1 IS NOT NULL AND NOT EXISTS (SELECT 1 FROM appointments WHERE appointment_number = 'APT-RS-TODAY-1');

INSERT IGNORE INTO `appointments` (`appointment_number`, `patient_id`, `doctor_id`, `appointment_type`, `appointment_date`, `appointment_time`, `status`, `reason`, `symptoms`, `priority_level`, `is_walkin`, `created_by`, `created_at`)
SELECT 'APT-RS-TODAY-2', @p2, @doc2, 'checkup', CURDATE(), '10:30:00', 'confirmed', 'Annual check-up', NULL, 'low', 0, @created, NOW() FROM DUAL WHERE @p1 IS NOT NULL AND @doc1 IS NOT NULL AND NOT EXISTS (SELECT 1 FROM appointments WHERE appointment_number = 'APT-RS-TODAY-2');

INSERT IGNORE INTO `appointments` (`appointment_number`, `patient_id`, `doctor_id`, `appointment_type`, `appointment_date`, `appointment_time`, `status`, `reason`, `symptoms`, `priority_level`, `is_walkin`, `created_by`, `created_at`)
SELECT 'APT-RS-TODAY-3', @p3, @doc1, 'consultation', CURDATE(), '14:00:00', 'in_progress', 'Blood pressure review', 'Stable', 'medium', 0, @created, NOW() FROM DUAL WHERE @p1 IS NOT NULL AND @doc1 IS NOT NULL AND NOT EXISTS (SELECT 1 FROM appointments WHERE appointment_number = 'APT-RS-TODAY-3');

INSERT IGNORE INTO `appointments` (`appointment_number`, `patient_id`, `doctor_id`, `appointment_type`, `appointment_date`, `appointment_time`, `status`, `reason`, `symptoms`, `priority_level`, `is_walkin`, `created_by`, `created_at`)
SELECT 'APT-RS-TOMORROW-1', @p1, @doc2, 'followup', CURDATE() + INTERVAL 1 DAY, '08:00:00', 'scheduled', 'Diabetes follow-up', 'Stable', 'medium', 0, @created, NOW() FROM DUAL WHERE @p1 IS NOT NULL AND @doc1 IS NOT NULL AND NOT EXISTS (SELECT 1 FROM appointments WHERE appointment_number = 'APT-RS-TOMORROW-1');

INSERT IGNORE INTO `appointments` (`appointment_number`, `patient_id`, `doctor_id`, `appointment_type`, `appointment_date`, `appointment_time`, `status`, `reason`, `symptoms`, `priority_level`, `is_walkin`, `created_by`, `created_at`)
SELECT 'APT-RS-TOMORROW-2', @p2, @doc1, 'consultation', CURDATE() + INTERVAL 1 DAY, '11:00:00', 'confirmed', 'New patient consult', NULL, 'low', 0, @created, NOW() FROM DUAL WHERE @p1 IS NOT NULL AND @doc1 IS NOT NULL AND NOT EXISTS (SELECT 1 FROM appointments WHERE appointment_number = 'APT-RS-TOMORROW-2');

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
