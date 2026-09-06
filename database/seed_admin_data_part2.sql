-- =====================================================
-- Hospital Core 1 System - Seed Data Part 2
-- Appointments, Admissions, ER Triage, Teleconsultations
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- =====================================================
-- 5. APPOINTMENTS (varied statuses across 3 months)
-- =====================================================
INSERT INTO `appointments` (`id`,`appointment_number`,`patient_id`,`doctor_id`,`appointment_type`,`appointment_date`,`appointment_time`,`status`,`reason`,`symptoms`,`priority_level`,`is_walkin`,`created_by`,`created_at`) VALUES
(1,'APT-2025-0001',1,3,'consultation','2025-12-01','09:00:00','completed','Annual checkup','General wellness check','medium',0,11,'2025-11-28 10:00:00'),
(2,'APT-2025-0002',2,4,'consultation','2025-12-03','10:30:00','completed','Persistent headache','Headache, dizziness','medium',0,11,'2025-12-01 09:00:00'),
(3,'APT-2025-0003',3,3,'followup','2025-12-05','14:00:00','completed','Diabetes follow-up','Blood sugar monitoring','medium',0,11,'2025-12-02 08:30:00'),
(4,'APT-2025-0004',4,5,'checkup','2025-12-08','08:30:00','completed','Pre-employment checkup','None','low',0,12,'2025-12-05 10:00:00'),
(5,'APT-2025-0005',5,3,'consultation','2025-12-10','11:00:00','completed','Breathing difficulty','Shortness of breath, wheezing','high',0,11,'2025-12-07 14:00:00'),
(6,'APT-2025-0006',6,6,'consultation','2025-12-15','09:30:00','completed','Skin rash','Itching, redness on arms','low',0,11,'2025-12-12 09:00:00'),
(7,'APT-2025-0007',7,4,'consultation','2025-12-18','10:00:00','completed','Prenatal checkup','Routine pregnancy check','medium',0,12,'2025-12-15 11:00:00'),
(8,'APT-2025-0008',8,5,'consultation','2025-12-20','14:30:00','completed','Chest pain','Intermittent chest pain','high',0,11,'2025-12-17 08:00:00'),
(9,'APT-2025-0009',9,3,'followup','2025-12-22','09:00:00','completed','Post-surgery follow-up','Wound check','medium',0,11,'2025-12-19 10:30:00'),
(10,'APT-2025-0010',10,6,'emergency','2025-12-23','16:00:00','completed','Motorcycle accident','Laceration, bruising','emergency',1,12,'2025-12-23 16:00:00'),
(11,'APT-2026-0011',11,3,'consultation','2026-01-05','08:30:00','completed','Thyroid evaluation','Fatigue, weight gain','medium',0,11,'2026-01-02 09:00:00'),
(12,'APT-2026-0012',12,4,'consultation','2026-01-08','10:00:00','completed','Cough and cold','Persistent cough, runny nose','low',0,11,'2026-01-06 08:30:00'),
(13,'APT-2026-0013',13,5,'consultation','2026-01-10','11:30:00','completed','Severe migraine','Throbbing headache, nausea','high',0,12,'2026-01-08 10:00:00'),
(14,'APT-2026-0014',14,3,'followup','2026-01-12','09:00:00','completed','Arthritis management','Joint pain, stiffness','medium',0,11,'2026-01-10 09:00:00'),
(15,'APT-2026-0015',15,6,'checkup','2026-01-15','08:00:00','completed','Seaman medical exam','Comprehensive physical','low',0,12,'2026-01-12 14:00:00'),
(16,'APT-2026-0016',16,4,'consultation','2026-01-18','13:00:00','completed','Anemia workup','Fatigue, pallor','medium',0,11,'2026-01-15 08:00:00'),
(17,'APT-2026-0017',17,5,'consultation','2026-01-22','10:30:00','completed','GERD symptoms','Heartburn, acid reflux','low',0,11,'2026-01-20 09:30:00'),
(18,'APT-2026-0018',18,3,'consultation','2026-01-25','09:00:00','completed','Allergy assessment','Skin irritation, swelling','medium',0,12,'2026-01-22 10:00:00'),
(19,'APT-2026-0019',19,6,'followup','2026-01-28','14:00:00','completed','Kidney stone follow-up','Post-ESWL check','medium',0,11,'2026-01-25 11:00:00'),
(20,'APT-2026-0020',20,4,'consultation','2026-01-30','11:00:00','completed','Stomach pain','Abdominal cramps, nausea','medium',1,11,'2026-01-30 11:00:00'),
(21,'APT-2026-0021',21,3,'followup','2026-02-03','09:00:00','completed','Diabetes & BP check','Blood sugar, BP monitoring','medium',0,11,'2026-02-01 08:00:00'),
(22,'APT-2026-0022',22,5,'consultation','2026-02-05','10:30:00','completed','Back pain','Lower back pain, sciatica','medium',0,12,'2026-02-03 09:00:00'),
(23,'APT-2026-0023',23,6,'consultation','2026-02-06','08:30:00','completed','Kidney disease review','Routine CKD monitoring','high',0,11,'2026-02-04 10:00:00'),
(24,'APT-2026-0024',24,4,'consultation','2026-02-07','13:00:00','completed','UTI symptoms','Painful urination, frequency','medium',1,11,'2026-02-07 13:00:00'),
(25,'APT-2026-0025',25,3,'followup','2026-02-08','09:00:00','completed','Ulcer check-up','Stomach pain management','medium',0,12,'2026-02-06 08:00:00'),
(26,'APT-2026-0026',26,5,'consultation','2026-02-10','10:00:00','completed','Flu symptoms','Fever, body aches','low',1,11,'2026-02-10 10:00:00'),
(27,'APT-2026-0027',27,3,'consultation','2026-02-10','14:30:00','in_progress','Heart failure mgmt','Edema, SOB on exertion','high',0,11,'2026-02-08 09:00:00'),
(28,'APT-2026-0028',1,4,'followup','2026-02-11','09:00:00','in_progress','BP follow-up','Blood pressure monitoring','medium',0,12,'2026-02-09 10:00:00'),
(29,'APT-2026-0029',3,5,'followup','2026-02-11','10:30:00','confirmed','Diabetes recheck','HbA1c results review','medium',0,11,'2026-02-09 08:00:00'),
(30,'APT-2026-0030',5,6,'followup','2026-02-11','14:00:00','confirmed','Asthma follow-up','Inhaler adequacy check','medium',0,11,'2026-02-09 11:00:00'),
(31,'APT-2026-0031',7,4,'consultation','2026-02-12','08:30:00','scheduled','Prenatal visit','36-week checkup','medium',0,12,'2026-02-10 09:00:00'),
(32,'APT-2026-0032',9,3,'consultation','2026-02-12','10:00:00','scheduled','Knee pain','Joint pain, swelling','medium',0,11,'2026-02-10 10:30:00'),
(33,'APT-2026-0033',11,5,'followup','2026-02-12','11:30:00','scheduled','Thyroid recheck','TSH level review','medium',0,11,'2026-02-10 14:00:00'),
(34,'APT-2026-0034',13,6,'consultation','2026-02-12','14:00:00','scheduled','Migraine management','Medication adjustment','medium',0,12,'2026-02-11 08:00:00'),
(35,'APT-2026-0035',2,3,'followup','2026-02-13','09:00:00','scheduled','Headache follow-up','Check-up after medication','low',0,11,'2026-02-11 09:00:00'),
(36,'APT-2026-0036',4,4,'consultation','2026-02-14','10:00:00','scheduled','Annual physical','Routine check','low',0,12,'2026-02-11 10:00:00'),
(37,'APT-2026-0037',6,5,'consultation','2026-02-15','08:30:00','scheduled','Rash recurrence','Dermatology follow-up','low',0,11,'2026-02-12 08:00:00'),
(38,'APT-2026-0038',8,3,'followup','2026-02-16','09:00:00','scheduled','Cardiac review','ECG follow-up','high',0,11,'2026-02-12 09:00:00'),
(39,'APT-2026-0039',15,6,'consultation','2026-02-10','15:00:00','cancelled','General checkup','None','low',0,12,'2026-02-08 10:00:00'),
(40,'APT-2026-0040',20,4,'consultation','2026-02-09','11:00:00','no_show','Stomach follow-up','No show','medium',0,11,'2026-02-07 14:00:00')
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- =====================================================
-- 6. ADMISSIONS
-- =====================================================
INSERT INTO `admissions` (`id`,`admission_number`,`patient_id`,`admitting_doctor_id`,`bed_id`,`admission_date`,`admission_type`,`diagnosis`,`reason_for_admission`,`status`,`expected_discharge_date`,`actual_discharge_date`,`created_by`,`created_at`) VALUES
(1,'ADM-2026-0001',1,3,1,'2026-02-01 08:00:00','scheduled','Hypertensive Crisis','Blood pressure management','admitted','2026-02-15',NULL,3,'2026-02-01 08:00:00'),
(2,'ADM-2026-0002',2,4,19,'2026-02-03 10:00:00','scheduled','Cholecystitis','Gallbladder surgery preparation','admitted','2026-02-10',NULL,4,'2026-02-03 10:00:00'),
(3,'ADM-2026-0003',3,3,2,'2026-02-02 14:00:00','emergency','Diabetic Ketoacidosis','DKA management','admitted','2026-02-12',NULL,3,'2026-02-02 14:00:00'),
(4,'ADM-2026-0004',5,5,4,'2026-02-05 09:00:00','emergency','Acute Asthma Exacerbation','Severe bronchospasm','admitted','2026-02-09',NULL,5,'2026-02-05 09:00:00'),
(5,'ADM-2026-0005',8,3,22,'2026-02-06 11:00:00','emergency','Acute Coronary Syndrome','Chest pain workup','admitted','2026-02-16',NULL,3,'2026-02-06 11:00:00'),
(6,'ADM-2026-0006',27,3,31,'2026-02-07 06:00:00','emergency','Acute Decompensated Heart Failure','Severe dyspnea, edema','admitted','2026-02-21',NULL,3,'2026-02-07 06:00:00'),
(7,'ADM-2026-0007',7,4,39,'2026-02-08 08:00:00','scheduled','Normal Pregnancy at 36 weeks','Pre-delivery monitoring','admitted','2026-02-15',NULL,4,'2026-02-08 08:00:00'),
(8,'ADM-2026-0008',14,5,24,'2026-01-28 10:00:00','scheduled','Bilateral Knee Osteoarthritis','Total knee replacement','discharged','2026-02-05','2026-02-04 14:00:00',5,'2026-01-28 10:00:00'),
(9,'ADM-2026-0009',10,6,35,'2026-02-09 16:00:00','emergency','Multiple Lacerations','Motorcycle accident injuries','admitted','2026-02-14',NULL,6,'2026-02-09 16:00:00'),
(10,'ADM-2026-0010',23,3,13,'2026-02-10 07:00:00','emergency','Acute Kidney Injury on CKD','Elevated creatinine, oliguria','admitted','2026-02-20',NULL,3,'2026-02-10 07:00:00'),
(11,'ADM-2026-0011',16,4,34,'2025-12-20 09:00:00','scheduled','Severe Anemia','Blood transfusion','discharged','2025-12-25','2025-12-24 10:00:00',4,'2025-12-20 09:00:00'),
(12,'ADM-2026-0012',21,5,11,'2026-02-11 08:00:00','scheduled','Uncontrolled Diabetes Type 2','Insulin dose adjustment','admitted','2026-02-18',NULL,5,'2026-02-11 08:00:00')
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- =====================================================
-- 7. TELECONSULTATIONS
-- =====================================================
INSERT INTO `teleconsultations` (`id`,`consultation_number`,`patient_id`,`doctor_id`,`consultation_date`,`status`,`consultation_notes`,`diagnosis`,`prescription`,`follow_up_required`,`consultation_fee`,`created_at`) VALUES
(1,'TEL-2026-001',2,3,'2026-01-15 09:00:00','completed','BP stable. Continue current meds.','Hypertension Stage 1','Amlodipine 5mg OD',1,800.00,NOW()),
(2,'TEL-2026-002',6,4,'2026-01-18 10:30:00','completed','Mild dermatitis. Prescribed topical cream.','Contact Dermatitis','Hydrocortisone cream 1%',0,800.00,NOW()),
(3,'TEL-2026-003',9,5,'2026-01-20 14:00:00','completed','Post-op recovery going well.','Post-surgical healing','Paracetamol 500mg PRN',1,1000.00,NOW()),
(4,'TEL-2026-004',11,3,'2026-01-25 08:30:00','completed','TSH elevated. Increase Levothyroxine.','Hypothyroidism','Levothyroxine 100mcg OD',1,800.00,NOW()),
(5,'TEL-2026-005',15,6,'2026-01-28 11:00:00','completed','Fit for duty. All labs normal.','No significant findings','None',0,1500.00,NOW()),
(6,'TEL-2026-006',17,4,'2026-02-01 09:00:00','completed','GERD well controlled with PPI.','GERD','Omeprazole 20mg OD',0,800.00,NOW()),
(7,'TEL-2026-007',20,5,'2026-02-03 10:00:00','completed','Gastritis resolved. Diet counseling given.','Acute Gastritis','Antacid PRN',0,800.00,NOW()),
(8,'TEL-2026-008',22,3,'2026-02-05 13:30:00','completed','Lower back pain - recommend PT.','Lumbar Strain','Ibuprofen 400mg TID, PT referral',1,800.00,NOW()),
(9,'TEL-2026-009',24,6,'2026-02-07 09:00:00','completed','UTI confirmed. Started antibiotics.','Urinary Tract Infection','Ciprofloxacin 500mg BID x7d',1,800.00,NOW()),
(10,'TEL-2026-010',26,4,'2026-02-10 10:30:00','completed','Influenza A. Rest and hydration.','Influenza','Oseltamivir 75mg BID x5d',0,800.00,NOW()),
(11,'TEL-2026-011',29,3,'2026-02-11 08:00:00','ongoing','Discussing asthma action plan...','Bronchial Asthma',NULL,0,800.00,NOW()),
(12,'TEL-2026-012',4,5,'2026-02-12 10:00:00','scheduled',NULL,NULL,NULL,0,800.00,NOW()),
(13,'TEL-2026-013',12,6,'2026-02-13 09:00:00','scheduled',NULL,NULL,NULL,0,800.00,NOW()),
(14,'TEL-2026-014',18,3,'2026-02-14 14:00:00','scheduled',NULL,NULL,NULL,0,800.00,NOW()),
(15,'TEL-2026-015',25,4,'2026-02-09 11:00:00','cancelled','Patient requested cancellation',NULL,NULL,0,0.00,NOW())
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- =====================================================
-- 8. ER TRIAGE
-- =====================================================
INSERT INTO `er_triage` (`id`,`patient_id`,`triage_nurse_id`,`chief_complaint`,`triage_level`,`vital_signs`,`initial_assessment`,`priority_score`,`status`,`created_at`) VALUES
(1,10,8,'Motorcycle accident - multiple lacerations','emergency','{"bp":"140/90","hr":110,"temp":37.2,"rr":22,"spo2":96}','Multiple lacerations on extremities, no fractures suspected',2,'in_progress','2026-02-09 16:00:00'),
(2,27,9,'Severe difficulty breathing','resuscitation','{"bp":"180/110","hr":130,"temp":37.0,"rr":32,"spo2":85}','Acute pulmonary edema, bilateral crackles',1,'in_progress','2026-02-07 06:00:00'),
(3,3,8,'Altered mental status, high blood sugar','emergency','{"bp":"100/60","hr":120,"temp":37.8,"rr":28,"spo2":94}','Suspected DKA, Kussmaul breathing observed',2,'admitted','2026-02-02 14:00:00'),
(4,5,9,'Severe wheezing, unable to speak in full sentences','urgent','{"bp":"130/85","hr":105,"temp":36.8,"rr":30,"spo2":90}','Severe asthma attack, accessory muscle use',3,'admitted','2026-02-05 09:00:00'),
(5,8,8,'Crushing chest pain radiating to left arm','resuscitation','{"bp":"160/100","hr":95,"temp":36.7,"rr":20,"spo2":97}','Rule out acute MI, ECG changes noted',1,'admitted','2026-02-06 11:00:00'),
(6,23,9,'Decreased urine output, swollen legs','emergency','{"bp":"170/100","hr":90,"temp":36.9,"rr":24,"spo2":93}','AKI on CKD, fluid overload',2,'admitted','2026-02-10 07:00:00'),
(7,12,8,'High fever, severe cough for 5 days','urgent','{"bp":"120/80","hr":100,"temp":39.5,"rr":26,"spo2":94}','Suspected pneumonia, rhonchi bilateral',3,'in_progress','2026-02-12 08:00:00'),
(8,15,9,'Abdominal pain after food intake','semi_urgent','{"bp":"130/80","hr":88,"temp":37.1,"rr":18,"spo2":98}','Suspected acute gastritis',4,'waiting','2026-02-12 10:30:00'),
(9,28,8,'Minor cut on hand','non_urgent','{"bp":"120/75","hr":78,"temp":36.6,"rr":16,"spo2":99}','Superficial laceration, 2cm',5,'waiting','2026-02-12 11:00:00'),
(10,14,9,'Severe joint pain, unable to walk','urgent','{"bp":"145/90","hr":85,"temp":37.0,"rr":18,"spo2":97}','Acute gout flare, right knee',3,'discharged','2026-02-08 14:00:00'),
(11,19,8,'Flank pain, blood in urine','urgent','{"bp":"150/95","hr":92,"temp":37.3,"rr":20,"spo2":97}','Suspected renal colic',3,'discharged','2026-02-06 08:00:00'),
(12,16,9,'Dizziness, extreme fatigue','semi_urgent','{"bp":"100/65","hr":105,"temp":36.5,"rr":20,"spo2":96}','Suspected severe anemia',4,'discharged','2025-12-20 09:00:00')
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
