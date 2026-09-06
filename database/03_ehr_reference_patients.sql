-- =====================================================
-- EHR tables: point patient_id to patients(id) so patient
-- portal (Medical History, Lab Results) can use patients.id.
-- Run after ehr.sql. Safe to run if tables do not exist yet.
-- =====================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ehr_vital_signs
ALTER TABLE `ehr_vital_signs` DROP FOREIGN KEY `fk_ehr_vitals_patient`;
ALTER TABLE `ehr_vital_signs` ADD CONSTRAINT `fk_ehr_vitals_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- ehr_diagnoses
ALTER TABLE `ehr_diagnoses` DROP FOREIGN KEY `fk_ehr_diagnoses_patient`;
ALTER TABLE `ehr_diagnoses` ADD CONSTRAINT `fk_ehr_diagnoses_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- ehr_medications
ALTER TABLE `ehr_medications` DROP FOREIGN KEY `fk_ehr_medications_patient`;
ALTER TABLE `ehr_medications` ADD CONSTRAINT `fk_ehr_medications_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- ehr_lab_results
ALTER TABLE `ehr_lab_results` DROP FOREIGN KEY `fk_ehr_lab_patient`;
ALTER TABLE `ehr_lab_results` ADD CONSTRAINT `fk_ehr_lab_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- ehr_procedures
ALTER TABLE `ehr_procedures` DROP FOREIGN KEY `fk_ehr_procedures_patient`;
ALTER TABLE `ehr_procedures` ADD CONSTRAINT `fk_ehr_procedures_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- ehr_allergies
ALTER TABLE `ehr_allergies` DROP FOREIGN KEY `fk_ehr_allergies_patient`;
ALTER TABLE `ehr_allergies` ADD CONSTRAINT `fk_ehr_allergies_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- ehr_immunizations
ALTER TABLE `ehr_immunizations` DROP FOREIGN KEY `fk_ehr_immunizations_patient`;
ALTER TABLE `ehr_immunizations` ADD CONSTRAINT `fk_ehr_immunizations_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- ehr_clinical_notes
ALTER TABLE `ehr_clinical_notes` DROP FOREIGN KEY `fk_ehr_notes_patient`;
ALTER TABLE `ehr_clinical_notes` ADD CONSTRAINT `fk_ehr_notes_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;
