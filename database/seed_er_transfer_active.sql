-- =====================================================
-- Seed active ER cases for "ER Transfer & Discharge" page
-- (status = waiting or in_progress so they show in Active ER Cases)
-- Run after: seed_admin_data.sql (patients + users), er_triage.sql (table)
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- Use first nurse or admin as triage_nurse_id
SET @nurse_id = (SELECT id FROM users WHERE role_id IN (SELECT id FROM roles WHERE role_name IN ('nurse', 'admin')) LIMIT 1);
SET @nurse_id = IFNULL(@nurse_id, 1);

-- Insert 5 active ER cases when no active cases exist (idempotent)
INSERT INTO `er_triage` (`patient_id`, `triage_nurse_id`, `chief_complaint`, `triage_level`, `vital_signs`, `initial_assessment`, `priority_score`, `status`, `created_at`)
SELECT * FROM (
  SELECT 1 AS p, @nurse_id AS n, 'Chest pain radiating to left arm' AS cc, 'emergency' AS lvl, '{"blood_pressure":"140/90","heart_rate":95,"respiratory_rate":18,"temperature":37.2,"oxygen_saturation":98,"pain_level":8}' AS vs, 'Patient presents with acute chest pain. ECG ordered.' AS ia, 2 AS ps, 'in_progress' AS st, NOW() AS ca
  UNION ALL SELECT 2, @nurse_id, 'Severe abdominal pain', 'urgent', '{"blood_pressure":"130/85","heart_rate":88,"respiratory_rate":16,"temperature":38.5,"oxygen_saturation":97,"pain_level":9}', 'Acute abdomen. Surgical consult requested.', 3, 'waiting', NOW()
  UNION ALL SELECT 3, @nurse_id, 'High fever and cough', 'semi_urgent', '{"blood_pressure":"120/80","heart_rate":85,"respiratory_rate":20,"temperature":39.2,"oxygen_saturation":96,"pain_level":4}', 'Fever and productive cough. Chest X-ray ordered.', 4, 'waiting', NOW()
  UNION ALL SELECT 4, @nurse_id, 'Head injury after fall', 'emergency', '{"blood_pressure":"118/76","heart_rate":92,"respiratory_rate":16,"temperature":36.8,"oxygen_saturation":97,"pain_level":7}', 'GCS 14. CT head ordered.', 2, 'in_progress', NOW()
  UNION ALL SELECT 5, @nurse_id, 'Minor cut on hand', 'non_urgent', '{"blood_pressure":"115/75","heart_rate":72,"respiratory_rate":14,"temperature":36.5,"oxygen_saturation":99,"pain_level":3}', 'Superficial laceration. Wound cleaning required.', 5, 'waiting', NOW()
) AS d
WHERE NOT EXISTS (SELECT 1 FROM er_triage WHERE status IN ('waiting', 'in_progress') LIMIT 1);

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
