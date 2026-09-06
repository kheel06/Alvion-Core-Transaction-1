-- =====================================================
-- Migration: Ensure patients exist for er_triage.patient_id
-- =====================================================
-- Fixes ER Patient Statistics Report showing empty list while
-- summary counts exist. Run when er_triage has rows whose
-- patient_id is not in patients (e.g. after seed_reports_all.sql
-- with literal patient_id 1-7 and no matching patients).
-- Safe to run multiple times (only inserts missing ids).

SET FOREIGN_KEY_CHECKS = 0;

-- Insert minimal columns for placeholder patients.
-- Requires: user_id nullable or omit; patient_code if your table has it.
-- If your schema has user_id NOT NULL UNIQUE, alter column to allow NULL before running, or skip this script (ER report still shows cases via LEFT JOIN).
INSERT INTO `patients` (
    `id`,
    `hospital_id`,
    `first_name`,
    `last_name`,
    `birth_date`,
    `gender`,
    `created_at`
)
SELECT
    et.patient_id,
    CONCAT('ER-', et.patient_id),
    'Unknown',
    'Patient',
    '2000-01-01',
    'male',
    NOW()
FROM (
    SELECT DISTINCT patient_id FROM er_triage
) et
LEFT JOIN patients p ON et.patient_id = p.id
WHERE p.id IS NULL;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- Notes:
-- =====================================================
-- 1. Run after er_triage table and patients table exist.
-- 2. If your patients table has no 'other' in gender enum,
--    change to 'male' or 'female' or alter the enum first.
-- 3. After this, ER report detail list will show these as
--    "Unknown Patient" with hospital_id ER-1, ER-2, etc.
--    You can later update names in patients if needed.
