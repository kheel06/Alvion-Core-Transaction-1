-- =====================================================
-- Seed appointments for Appointments Report page
-- Spread across Jan–Feb 2026 so report (default -30 days or custom range) shows real data
-- Run after: seed_admin_data.sql (patients 1+, doctors 3+)
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- Doctors 3,4,5,6 from seed_admin_data; patients 1–7; created_by 11 (idempotent: skip if numbers exist)
INSERT IGNORE INTO `appointments` (`appointment_number`, `patient_id`, `doctor_id`, `appointment_type`, `appointment_date`, `appointment_time`, `status`, `reason`, `symptoms`, `priority_level`, `is_walkin`, `created_by`, `created_at`) VALUES
('RPT-2026-001', 1, 3, 'consultation', '2026-01-13', '09:00:00', 'completed', 'BP check', 'Routine', 'medium', 0, 11, NOW()),
('RPT-2026-002', 2, 4, 'consultation', '2026-01-14', '10:00:00', 'completed', 'Follow-up', 'None', 'low', 0, 11, NOW()),
('RPT-2026-003', 3, 5, 'followup', '2026-01-15', '11:00:00', 'completed', 'Diabetes recheck', 'Stable', 'medium', 0, 11, NOW()),
('RPT-2026-004', 4, 3, 'checkup', '2026-01-16', '08:30:00', 'cancelled', 'Annual physical', 'Rescheduled', 'low', 0, 11, NOW()),
('RPT-2026-005', 5, 4, 'consultation', '2026-01-17', '14:00:00', 'no_show', 'Asthma review', NULL, 'medium', 0, 11, NOW()),
('RPT-2026-006', 6, 5, 'consultation', '2026-01-20', '09:30:00', 'completed', 'Skin rash', 'Rash on arms', 'low', 1, 11, NOW()),
('RPT-2026-007', 7, 3, 'consultation', '2026-01-21', '10:30:00', 'completed', 'Fever', 'Fever, cough', 'medium', 1, 11, NOW()),
('RPT-2026-008', 1, 4, 'consultation', '2026-01-22', '11:00:00', 'completed', 'Hypertension follow-up', 'Stable', 'medium', 0, 11, NOW()),
('RPT-2026-009', 2, 5, 'consultation', '2026-01-23', '15:00:00', 'cancelled', 'Check-up', 'Patient requested', 'low', 0, 11, NOW()),
('RPT-2026-010', 3, 3, 'followup', '2026-01-27', '09:00:00', 'completed', 'Diabetes review', 'Stable', 'medium', 0, 11, NOW()),
('RPT-2026-011', 4, 4, 'consultation', '2026-01-28', '10:00:00', 'no_show', 'General check', NULL, 'low', 0, 11, NOW()),
('RPT-2026-012', 5, 5, 'consultation', '2026-01-29', '14:30:00', 'completed', 'COPD review', 'Stable', 'medium', 1, 11, NOW()),
('RPT-2026-013', 6, 3, 'consultation', '2026-02-03', '08:00:00', 'completed', 'Back pain', 'Lower back', 'medium', 0, 11, NOW()),
('RPT-2026-014', 7, 4, 'consultation', '2026-02-05', '11:00:00', 'completed', 'Prenatal check', 'Routine', 'medium', 0, 11, NOW()),
('RPT-2026-015', 1, 5, 'consultation', '2026-02-08', '10:00:00', 'scheduled', 'Follow-up', 'None', 'low', 0, 11, NOW()),
('RPT-2026-016', 2, 3, 'consultation', '2026-02-10', '09:30:00', 'completed', 'Headache', 'Migraine', 'medium', 1, 11, NOW()),
('RPT-2026-017', 3, 4, 'followup', '2026-02-11', '13:00:00', 'completed', 'Diabetes', 'Stable', 'medium', 0, 11, NOW()),
('RPT-2026-018', 4, 5, 'consultation', '2026-02-12', '09:00:00', 'completed', 'Minor injury', 'Cut on hand', 'low', 1, 11, NOW());

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
