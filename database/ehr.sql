-- =====================================================
-- Electronic Health Records (EHR) Tables
-- =====================================================
-- Comprehensive EHR system for storing patient health records

-- =====================================================
-- EHR Vital Signs Table
-- =====================================================
CREATE TABLE IF NOT EXISTS `ehr_vital_signs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL COMMENT 'Reference to users table (patient)',
  `recorded_by` int(11) DEFAULT NULL COMMENT 'Reference to users table (doctor/nurse)',
  `recorded_at` datetime NOT NULL COMMENT 'Date and time vital signs were recorded',
  `temperature` decimal(4,1) DEFAULT NULL COMMENT 'Body temperature in Celsius',
  `blood_pressure_systolic` int(11) DEFAULT NULL COMMENT 'Systolic BP (mmHg)',
  `blood_pressure_diastolic` int(11) DEFAULT NULL COMMENT 'Diastolic BP (mmHg)',
  `heart_rate` int(11) DEFAULT NULL COMMENT 'Heart rate (bpm)',
  `respiratory_rate` int(11) DEFAULT NULL COMMENT 'Respiratory rate (per minute)',
  `oxygen_saturation` decimal(4,1) DEFAULT NULL COMMENT 'SpO2 percentage',
  `weight` decimal(5,2) DEFAULT NULL COMMENT 'Weight in kg',
  `height` decimal(5,2) DEFAULT NULL COMMENT 'Height in cm',
  `bmi` decimal(4,1) DEFAULT NULL COMMENT 'Body Mass Index (calculated)',
  `pain_level` int(11) DEFAULT NULL COMMENT 'Pain level (0-10 scale)',
  `notes` text DEFAULT NULL COMMENT 'Additional notes',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `recorded_by` (`recorded_by`),
  KEY `recorded_at` (`recorded_at`),
  CONSTRAINT `fk_ehr_vitals_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_ehr_vitals_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- EHR Diagnoses Table
-- =====================================================
CREATE TABLE IF NOT EXISTS `ehr_diagnoses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL COMMENT 'Reference to users table (patient)',
  `diagnosed_by` int(11) DEFAULT NULL COMMENT 'Reference to users table (doctor)',
  `diagnosis_date` date NOT NULL COMMENT 'Date of diagnosis',
  `diagnosis_type` enum('primary','secondary','differential','rule_out') DEFAULT 'primary' COMMENT 'Type of diagnosis',
  `icd10_code` varchar(20) DEFAULT NULL COMMENT 'ICD-10 code',
  `diagnosis_description` text NOT NULL COMMENT 'Diagnosis description',
  `status` enum('active','resolved','chronic','inactive') DEFAULT 'active' COMMENT 'Diagnosis status',
  `notes` text DEFAULT NULL COMMENT 'Additional notes',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `diagnosed_by` (`diagnosed_by`),
  KEY `diagnosis_date` (`diagnosis_date`),
  KEY `status` (`status`),
  CONSTRAINT `fk_ehr_diagnoses_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_ehr_diagnoses_doctor` FOREIGN KEY (`diagnosed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- EHR Medications Table
-- =====================================================
CREATE TABLE IF NOT EXISTS `ehr_medications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL COMMENT 'Reference to users table (patient)',
  `prescribed_by` int(11) DEFAULT NULL COMMENT 'Reference to users table (doctor)',
  `prescription_date` date NOT NULL COMMENT 'Date medication was prescribed',
  `medication_name` varchar(255) NOT NULL COMMENT 'Name of medication',
  `dosage` varchar(100) DEFAULT NULL COMMENT 'Dosage information',
  `frequency` varchar(100) DEFAULT NULL COMMENT 'Frequency (e.g., twice daily)',
  `route` enum('oral','injection','topical','inhalation','other') DEFAULT 'oral' COMMENT 'Route of administration',
  `start_date` date DEFAULT NULL COMMENT 'Start date',
  `end_date` date DEFAULT NULL COMMENT 'End date (if applicable)',
  `status` enum('active','completed','discontinued','on_hold') DEFAULT 'active' COMMENT 'Medication status',
  `instructions` text DEFAULT NULL COMMENT 'Special instructions',
  `notes` text DEFAULT NULL COMMENT 'Additional notes',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `prescribed_by` (`prescribed_by`),
  KEY `prescription_date` (`prescription_date`),
  KEY `status` (`status`),
  CONSTRAINT `fk_ehr_medications_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_ehr_medications_doctor` FOREIGN KEY (`prescribed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- EHR Lab Results Table
-- =====================================================
CREATE TABLE IF NOT EXISTS `ehr_lab_results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL COMMENT 'Reference to users table (patient)',
  `ordered_by` int(11) DEFAULT NULL COMMENT 'Reference to users table (doctor)',
  `processed_by` int(11) DEFAULT NULL COMMENT 'Reference to users table (lab technician)',
  `order_date` datetime NOT NULL COMMENT 'Date lab was ordered',
  `test_name` varchar(255) NOT NULL COMMENT 'Name of lab test',
  `test_type` enum('blood','urine','stool','imaging','other') DEFAULT 'blood' COMMENT 'Type of test',
  `result_date` datetime DEFAULT NULL COMMENT 'Date results were available',
  `result_value` text DEFAULT NULL COMMENT 'Test result value',
  `reference_range` varchar(100) DEFAULT NULL COMMENT 'Normal reference range',
  `unit` varchar(50) DEFAULT NULL COMMENT 'Unit of measurement',
  `status` enum('ordered','in_progress','completed','cancelled') DEFAULT 'ordered' COMMENT 'Test status',
  `abnormal_flag` enum('normal','high','low','critical') DEFAULT 'normal' COMMENT 'Abnormal flag',
  `notes` text DEFAULT NULL COMMENT 'Additional notes',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `ordered_by` (`ordered_by`),
  KEY `processed_by` (`processed_by`),
  KEY `order_date` (`order_date`),
  KEY `status` (`status`),
  CONSTRAINT `fk_ehr_lab_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_ehr_lab_ordered_by` FOREIGN KEY (`ordered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ehr_lab_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- EHR Procedures Table
-- =====================================================
CREATE TABLE IF NOT EXISTS `ehr_procedures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL COMMENT 'Reference to users table (patient)',
  `performed_by` int(11) DEFAULT NULL COMMENT 'Reference to users table (doctor)',
  `procedure_date` datetime NOT NULL COMMENT 'Date procedure was performed',
  `procedure_name` varchar(255) NOT NULL COMMENT 'Name of procedure',
  `procedure_type` enum('surgical','diagnostic','therapeutic','preventive','other') DEFAULT 'diagnostic' COMMENT 'Type of procedure',
  `description` text DEFAULT NULL COMMENT 'Procedure description',
  `anesthesia_type` varchar(100) DEFAULT NULL COMMENT 'Type of anesthesia used',
  `complications` text DEFAULT NULL COMMENT 'Any complications',
  `outcome` text DEFAULT NULL COMMENT 'Procedure outcome',
  `follow_up_required` tinyint(1) DEFAULT 0 COMMENT 'Whether follow-up is required',
  `follow_up_date` date DEFAULT NULL COMMENT 'Follow-up date',
  `notes` text DEFAULT NULL COMMENT 'Additional notes',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `performed_by` (`performed_by`),
  KEY `procedure_date` (`procedure_date`),
  CONSTRAINT `fk_ehr_procedures_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_ehr_procedures_doctor` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- EHR Allergies Table
-- =====================================================
CREATE TABLE IF NOT EXISTS `ehr_allergies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL COMMENT 'Reference to users table (patient)',
  `allergen` varchar(255) NOT NULL COMMENT 'Substance causing allergy',
  `allergy_type` enum('medication','food','environmental','other') DEFAULT 'medication' COMMENT 'Type of allergy',
  `severity` enum('mild','moderate','severe','life_threatening') DEFAULT 'moderate' COMMENT 'Severity of allergy',
  `reaction` text DEFAULT NULL COMMENT 'Reaction description',
  `first_observed` date DEFAULT NULL COMMENT 'Date allergy was first observed',
  `status` enum('active','resolved','unknown') DEFAULT 'active' COMMENT 'Allergy status',
  `notes` text DEFAULT NULL COMMENT 'Additional notes',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `allergy_type` (`allergy_type`),
  KEY `severity` (`severity`),
  CONSTRAINT `fk_ehr_allergies_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- EHR Immunizations Table
-- =====================================================
CREATE TABLE IF NOT EXISTS `ehr_immunizations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL COMMENT 'Reference to users table (patient)',
  `administered_by` int(11) DEFAULT NULL COMMENT 'Reference to users table (doctor/nurse)',
  `vaccine_name` varchar(255) NOT NULL COMMENT 'Name of vaccine',
  `administration_date` date NOT NULL COMMENT 'Date vaccine was administered',
  `dose_number` int(11) DEFAULT 1 COMMENT 'Dose number (1, 2, booster, etc.)',
  `lot_number` varchar(100) DEFAULT NULL COMMENT 'Vaccine lot number',
  `manufacturer` varchar(255) DEFAULT NULL COMMENT 'Vaccine manufacturer',
  `administration_site` varchar(100) DEFAULT NULL COMMENT 'Site of administration',
  `route` enum('injection','oral','nasal','other') DEFAULT 'injection' COMMENT 'Route of administration',
  `next_dose_date` date DEFAULT NULL COMMENT 'Date for next dose (if applicable)',
  `notes` text DEFAULT NULL COMMENT 'Additional notes',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `administered_by` (`administered_by`),
  KEY `administration_date` (`administration_date`),
  CONSTRAINT `fk_ehr_immunizations_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_ehr_immunizations_provider` FOREIGN KEY (`administered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- EHR Clinical Notes Table
-- =====================================================
CREATE TABLE IF NOT EXISTS `ehr_clinical_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL COMMENT 'Reference to users table (patient)',
  `note_author` int(11) DEFAULT NULL COMMENT 'Reference to users table (doctor/nurse)',
  `note_date` datetime NOT NULL COMMENT 'Date and time of note',
  `note_type` enum('progress','consultation','discharge','admission','procedure','other') DEFAULT 'progress' COMMENT 'Type of clinical note',
  `chief_complaint` text DEFAULT NULL COMMENT 'Chief complaint',
  `subjective` text DEFAULT NULL COMMENT 'Subjective information (patient reported)',
  `objective` text DEFAULT NULL COMMENT 'Objective findings (examination)',
  `assessment` text DEFAULT NULL COMMENT 'Clinical assessment',
  `plan` text DEFAULT NULL COMMENT 'Treatment plan',
  `notes` text DEFAULT NULL COMMENT 'Additional notes',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `note_author` (`note_author`),
  KEY `note_date` (`note_date`),
  KEY `note_type` (`note_type`),
  CONSTRAINT `fk_ehr_notes_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_ehr_notes_author` FOREIGN KEY (`note_author`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

