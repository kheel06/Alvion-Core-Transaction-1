-- =====================================================
-- Hospital Core 1 System - Comprehensive Seed Data
-- Philippine Hospital Transaction Data
-- Run: mysql -u root -p hospital-core1-system < seed_admin_data.sql
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- =====================================================
-- 0. ADMIN USER (role_id 1) – for admin dashboard and all admin pages
-- Password: Hospital@2026 (same bcrypt hash as staff)
-- =====================================================
INSERT INTO `users` (`id`, `username`, `password`, `email`, `first_name`, `last_name`, `role_id`, `is_active`, `status`, `created_at`) VALUES
(1, 'admin', '$2y$10$8KzQHZxGkO1rUjKxLQ5Yku0VZ6TvMN3zp4.wB1qRHmK9vG5Xh2sFe', 'admin@alvionhealth.ph', 'System', 'Administrator', 1, 1, 'active', '2025-11-01 08:00:00')
ON DUPLICATE KEY UPDATE `first_name` = VALUES(`first_name`);

-- =====================================================
-- 1. USERS (Staff Accounts: Doctors, Nurses, Staff)
-- Password: Hospital@2026 => bcrypt hash
-- =====================================================
INSERT INTO `users` (`id`, `username`, `password`, `email`, `first_name`, `last_name`, `role_id`, `is_active`, `status`, `created_at`) VALUES
(3, 'dr.santos', '$2y$10$8KzQHZxGkO1rUjKxLQ5Yku0VZ6TvMN3zp4.wB1qRHmK9vG5Xh2sFe', 'dr.santos@alvionhealth.ph', 'Ricardo', 'Santos', 2, 1, 'active', '2025-11-01 08:00:00'),
(4, 'dr.reyes', '$2y$10$8KzQHZxGkO1rUjKxLQ5Yku0VZ6TvMN3zp4.wB1qRHmK9vG5Xh2sFe', 'dr.reyes@alvionhealth.ph', 'Maria', 'Reyes', 2, 1, 'active', '2025-11-01 08:00:00'),
(5, 'dr.cruz', '$2y$10$8KzQHZxGkO1rUjKxLQ5Yku0VZ6TvMN3zp4.wB1qRHmK9vG5Xh2sFe', 'dr.cruz@alvionhealth.ph', 'Juan', 'Cruz', 2, 1, 'active', '2025-11-05 08:00:00'),
(6, 'dr.garcia', '$2y$10$8KzQHZxGkO1rUjKxLQ5Yku0VZ6TvMN3zp4.wB1qRHmK9vG5Xh2sFe', 'dr.garcia@alvionhealth.ph', 'Angela', 'Garcia', 2, 1, 'active', '2025-11-10 08:00:00'),
(7, 'dr.delos_reyes', '$2y$10$8KzQHZxGkO1rUjKxLQ5Yku0VZ6TvMN3zp4.wB1qRHmK9vG5Xh2sFe', 'dr.delosreyes@alvionhealth.ph', 'Paolo', 'Delos Reyes', 2, 1, 'active', '2025-11-15 08:00:00'),
(8, 'nurse.mendoza', '$2y$10$8KzQHZxGkO1rUjKxLQ5Yku0VZ6TvMN3zp4.wB1qRHmK9vG5Xh2sFe', 'nurse.mendoza@alvionhealth.ph', 'Ana', 'Mendoza', 3, 1, 'active', '2025-11-01 08:00:00'),
(9, 'nurse.bautista', '$2y$10$8KzQHZxGkO1rUjKxLQ5Yku0VZ6TvMN3zp4.wB1qRHmK9vG5Xh2sFe', 'nurse.bautista@alvionhealth.ph', 'Rosa', 'Bautista', 3, 1, 'active', '2025-11-01 08:00:00'),
(10, 'nurse.villanueva', '$2y$10$8KzQHZxGkO1rUjKxLQ5Yku0VZ6TvMN3zp4.wB1qRHmK9vG5Xh2sFe', 'nurse.villanueva@alvionhealth.ph', 'Carmen', 'Villanueva', 3, 1, 'active', '2025-11-05 08:00:00'),
(11, 'rec.lopez', '$2y$10$8KzQHZxGkO1rUjKxLQ5Yku0VZ6TvMN3zp4.wB1qRHmK9vG5Xh2sFe', 'rec.lopez@alvionhealth.ph', 'Jennifer', 'Lopez', 5, 1, 'active', '2025-11-01 08:00:00'),
(12, 'rec.ramos', '$2y$10$8KzQHZxGkO1rUjKxLQ5Yku0VZ6TvMN3zp4.wB1qRHmK9vG5Xh2sFe', 'rec.ramos@alvionhealth.ph', 'Kristine', 'Ramos', 5, 1, 'active', '2025-11-10 08:00:00'),
(13, 'fin.dela_cruz', '$2y$10$8KzQHZxGkO1rUjKxLQ5Yku0VZ6TvMN3zp4.wB1qRHmK9vG5Xh2sFe', 'fin.delacruz@alvionhealth.ph', 'Mark', 'Dela Cruz', 6, 1, 'active', '2025-11-01 08:00:00'),
(14, 'fin.aquino', '$2y$10$8KzQHZxGkO1rUjKxLQ5Yku0VZ6TvMN3zp4.wB1qRHmK9vG5Xh2sFe', 'fin.aquino@alvionhealth.ph', 'Diane', 'Aquino', 6, 1, 'active', '2025-11-15 08:00:00'),
(15, 'staff.tan', '$2y$10$8KzQHZxGkO1rUjKxLQ5Yku0VZ6TvMN3zp4.wB1qRHmK9vG5Xh2sFe', 'staff.tan@alvionhealth.ph', 'Bryan', 'Tan', 3, 1, 'active', '2025-12-01 08:00:00'),
(16, 'staff.lim', '$2y$10$8KzQHZxGkO1rUjKxLQ5Yku0VZ6TvMN3zp4.wB1qRHmK9vG5Xh2sFe', 'staff.lim@alvionhealth.ph', 'Grace', 'Lim', 3, 1, 'active', '2025-12-01 08:00:00')
ON DUPLICATE KEY UPDATE `first_name` = VALUES(`first_name`);

-- =====================================================
-- 2. PATIENTS (Filipino names, PH addresses)
-- =====================================================
INSERT INTO `patients` (`id`, `hospital_id`, `first_name`, `last_name`, `middle_name`, `birth_date`, `gender`, `civil_status`, `nationality`, `house_no_street`, `barangay`, `city_code`, `province_code`, `region_code`, `zip_code`, `contact_number`, `email`, `philhealth_id`, `blood_type`, `known_allergies`, `pre_existing_conditions`, `occupation`, `status`, `created_by`, `created_at`) VALUES
(1, 'AHN-2025-0001', 'Jose', 'Rizal', 'Protacio', '1985-06-19', 'male', 'married', 'Filipino', '123 Mabini St', 'Barangay 1', 'MANILA', 'NCR_MNL', 'NCR', '1000', '09171234567', 'jose.rizal@email.com', '01-234567890-1', 'O+', NULL, 'Hypertension', 'Teacher', 'active', 2, '2025-11-20 09:00:00'),
(2, 'AHN-2025-0002', 'Maria', 'Clara', 'Santos', '1990-03-25', 'female', 'single', 'Filipino', '456 Ayala Ave', 'Barangay San Lorenzo', 'MAKATI', 'NCR_MAK', 'NCR', '1226', '09181234568', 'maria.clara@email.com', '01-234567890-2', 'A+', 'Penicillin', NULL, 'Accountant', 'active', 2, '2025-11-22 10:00:00'),
(3, 'AHN-2025-0003', 'Andres', 'Bonifacio', 'Magsaysay', '1978-11-30', 'male', 'married', 'Filipino', '789 Taft Ave', 'Barangay 12', 'MANILA', 'NCR_MNL', 'NCR', '1004', '09191234569', 'andres.b@email.com', '01-234567890-3', 'B+', NULL, 'Diabetes Type 2', 'Driver', 'active', 2, '2025-12-01 08:30:00'),
(4, 'AHN-2025-0004', 'Gabriela', 'Silang', 'Cariño', '1995-08-15', 'female', 'married', 'Filipino', '321 Session Rd', 'Barangay Burnham', 'BAGUIO', 'BEN', 'CAR', '2600', '09201234570', 'gab.silang@email.com', '01-234567890-4', 'AB+', 'Sulfa drugs', NULL, 'Nurse', 'active', 2, '2025-12-05 11:00:00'),
(5, 'AHN-2025-0005', 'Emilio', 'Aguinaldo', 'Luna', '1970-03-22', 'male', 'widowed', 'Filipino', '55 Rizal St', 'Barangay 5', 'LAOAG', 'ILN', 'REGION1', '2900', '09211234571', 'emilio.a@email.com', '01-234567890-5', 'O-', NULL, 'Asthma, COPD', 'Retired', 'active', 2, '2025-12-10 09:15:00'),
(6, 'AHN-2025-0006', 'Apolinario', 'Mabini', 'DelPilar', '1988-07-23', 'male', 'single', 'Filipino', '12 Quezon Blvd', 'Barangay Batasan', 'QC', 'NCR_QUE', 'NCR', '1126', '09221234572', 'apolinario.m@email.com', '01-234567890-6', 'A-', NULL, NULL, 'IT Professional', 'active', 2, '2025-12-12 14:00:00'),
(7, 'AHN-2025-0007', 'Teresa', 'Magbanua', 'Recto', '1992-12-01', 'female', 'married', 'Filipino', '88 EDSA', 'Barangay Sacred Heart', 'QC', 'NCR_QUE', 'NCR', '1100', '09231234573', 'teresa.m@email.com', '01-234567890-7', 'B-', 'Seafood', 'Gestational Diabetes', 'Engineer', 'active', 2, '2025-12-15 08:45:00'),
(8, 'AHN-2025-0008', 'Manuel', 'Quezon', 'Molina', '1965-08-19', 'male', 'married', 'Filipino', '100 Commonwealth Ave', 'Barangay Holy Spirit', 'QC', 'NCR_QUE', 'NCR', '1127', '09241234574', 'manuel.q@email.com', '01-234567890-8', 'AB-', NULL, 'Coronary Artery Disease', 'Businessman', 'active', 2, '2025-12-18 10:30:00'),
(9, 'AHN-2025-0009', 'Corazon', 'Aquino', 'Cojuangco', '1983-01-25', 'female', 'married', 'Filipino', '22 Roxas Blvd', 'Barangay Ermita', 'MANILA', 'NCR_MNL', 'NCR', '1000', '09251234575', 'corazon.a@email.com', '01-234567890-9', 'O+', 'Aspirin', NULL, 'Lawyer', 'active', 2, '2025-12-20 09:00:00'),
(10, 'AHN-2025-0010', 'Lapu', 'Lapu', 'Cebu', '2000-04-27', 'male', 'single', 'Filipino', '5 Osmeña Blvd', 'Barangay Lahug', 'MAKATI', 'NCR_MAK', 'NCR', '1200', '09261234576', 'lapu.l@email.com', '01-234567890-10', 'A+', NULL, NULL, 'Student', 'active', 2, '2026-01-02 08:00:00')
ON DUPLICATE KEY UPDATE `first_name` = VALUES(`first_name`);

INSERT INTO `patients` (`id`, `hospital_id`, `first_name`, `last_name`, `middle_name`, `birth_date`, `gender`, `civil_status`, `nationality`, `house_no_street`, `barangay`, `city_code`, `province_code`, `region_code`, `zip_code`, `contact_number`, `email`, `philhealth_id`, `blood_type`, `known_allergies`, `pre_existing_conditions`, `occupation`, `status`, `created_by`, `created_at`) VALUES
(11, 'AHN-2025-0011', 'Melchora', 'Aquino', 'Ramos', '1975-01-06', 'female', 'married', 'Filipino', '33 Katipunan Ave', 'Barangay Loyola Heights', 'QC', 'NCR_QUE', 'NCR', '1108', '09271234577', 'melchora.a@email.com', '01-234567891-1', 'B+', NULL, 'Thyroid disorder', 'Government employee', 'active', 2, '2026-01-05 10:00:00'),
(12, 'AHN-2025-0012', 'Diego', 'Silang', 'Pangasinan', '1998-12-16', 'male', 'single', 'Filipino', '77 Aurora Blvd', 'Barangay Doña Aurora', 'QC', 'NCR_QUE', 'NCR', '1113', '09281234578', 'diego.s@email.com', '01-234567891-2', 'O+', 'Ibuprofen', NULL, 'BPO Agent', 'active', 2, '2026-01-08 11:30:00'),
(13, 'AHN-2025-0013', 'Josefa', 'Llanes', 'Escoda', '1987-09-20', 'female', 'married', 'Filipino', '14 España Blvd', 'Barangay Sampaloc', 'MANILA', 'NCR_MNL', 'NCR', '1008', '09291234579', 'josefa.l@email.com', '01-234567891-3', 'A+', NULL, 'Migraine', 'Social Worker', 'active', 2, '2026-01-10 13:00:00'),
(14, 'AHN-2025-0014', 'Tandang', 'Sora', 'Lakandula', '1960-01-06', 'female', 'widowed', 'Filipino', '8 Magsaysay Ave', 'Barangay Sta. Mesa', 'MANILA', 'NCR_MNL', 'NCR', '1016', '09301234580', 'tandang.s@email.com', '01-234567891-4', 'AB+', 'NSAIDs', 'Osteoarthritis, Hypertension', 'Retired', 'active', 2, '2026-01-12 09:00:00'),
(15, 'AHN-2025-0015', 'Pedro', 'Paterno', 'Luna', '1993-05-12', 'male', 'single', 'Filipino', '60 Gil Puyat Ave', 'Barangay Palanan', 'MAKATI', 'NCR_MAK', 'NCR', '1235', '09311234581', 'pedro.p@email.com', '01-234567891-5', 'B+', NULL, NULL, 'Seaman', 'active', 2, '2026-01-15 08:15:00'),
(16, 'AHN-2025-0016', 'Leonor', 'Rivera', 'Kipping', '1991-04-11', 'female', 'single', 'Filipino', '45 Buendia Ave', 'Barangay Pio Del Pilar', 'MAKATI', 'NCR_MAK', 'NCR', '1230', '09321234582', 'leonor.r@email.com', '01-234567891-6', 'O-', NULL, 'Anemia', 'Bank Teller', 'active', 2, '2026-01-18 10:45:00'),
(17, 'AHN-2025-0017', 'Marcelo', 'Del Pilar', 'Gatmaitan', '1982-08-30', 'male', 'married', 'Filipino', '200 Recto Ave', 'Barangay Quiapo', 'MANILA', 'NCR_MNL', 'NCR', '1001', '09331234583', 'marcelo.d@email.com', '01-234567891-7', 'A+', NULL, 'GERD', 'Journalist', 'active', 2, '2026-01-20 14:30:00'),
(18, 'AHN-2025-0018', 'Gregoria', 'De Jesus', 'Nakpil', '1996-05-09', 'female', 'married', 'Filipino', '10 Shaw Blvd', 'Barangay Wack Wack', 'MAKATI', 'NCR_MAK', 'NCR', '1555', '09341234584', 'gregoria.j@email.com', '01-234567891-8', 'B-', 'Latex', NULL, 'Marketing Manager', 'active', 2, '2026-01-22 09:00:00'),
(19, 'AHN-2025-0019', 'Antonio', 'Luna', 'Novicio', '1979-10-29', 'male', 'married', 'Filipino', '150 Ortigas Ave', 'Barangay San Antonio', 'MAKATI', 'NCR_MAK', 'NCR', '1203', '09351234585', 'antonio.l@email.com', '01-234567891-9', 'O+', NULL, 'Gout, Kidney Stones', 'Architect', 'active', 2, '2026-01-25 11:00:00'),
(20, 'AHN-2025-0020', 'Trinidad', 'Tecson', 'Rizal', '2001-11-18', 'female', 'single', 'Filipino', '3 P. Burgos St', 'Barangay Poblacion', 'MAKATI', 'NCR_MAK', 'NCR', '1210', '09361234586', 'trinidad.t@email.com', '01-234567892-0', 'A-', NULL, NULL, 'College Student', 'active', 2, '2026-01-28 08:30:00'),
(21, 'AHN-2025-0021', 'Felipe', 'Agoncillo', 'Buencamino', '1974-09-26', 'male', 'married', 'Filipino', '88 Timog Ave', 'Barangay South Triangle', 'QC', 'NCR_QUE', 'NCR', '1103', '09371234587', 'felipe.a@email.com', '01-234567892-1', 'AB+', 'Codeine', 'Diabetes Type 2, Hypertension', 'Civil Servant', 'active', 2, '2026-01-30 10:00:00'),
(22, 'AHN-2025-0022', 'Catalina', 'De Castro', 'Magat', '1989-02-14', 'female', 'married', 'Filipino', '20 Congressional Ave', 'Barangay Bahay Toro', 'QC', 'NCR_QUE', 'NCR', '1106', '09381234588', 'catalina.c@email.com', '01-234567892-2', 'O+', NULL, NULL, 'OFW Spouse', 'active', 2, '2026-02-01 09:15:00'),
(23, 'AHN-2025-0023', 'Ramon', 'Magsaysay', 'Del Rosario', '1968-08-31', 'male', 'married', 'Filipino', '5 Visayas Ave', 'Barangay Vasra', 'QC', 'NCR_QUE', 'NCR', '1128', '09391234589', 'ramon.m@email.com', '01-234567892-3', 'B+', NULL, 'Chronic Kidney Disease', 'Retired Military', 'active', 2, '2026-02-03 08:00:00'),
(24, 'AHN-2025-0024', 'Aurora', 'Quezon', 'Aragon', '1994-07-19', 'female', 'single', 'Filipino', '30 Kalayaan Ave', 'Barangay Olympia', 'MAKATI', 'NCR_MAK', 'NCR', '1207', '09401234590', 'aurora.q@email.com', '01-234567892-4', 'A+', 'Peanuts', NULL, 'Call Center Agent', 'active', 2, '2026-02-05 10:30:00'),
(25, 'AHN-2025-0025', 'Sergio', 'Osmeña', 'Lim', '1972-09-09', 'male', 'separated', 'Filipino', '75 Macapagal Blvd', 'Barangay 76', 'MANILA', 'NCR_MNL', 'NCR', '1300', '09411234591', 'sergio.o@email.com', '01-234567892-5', 'O+', NULL, 'Peptic Ulcer', 'Businessman', 'active', 2, '2026-02-07 14:00:00'),
(26, 'AHN-2025-0026', 'Liliosa', 'Hilao', 'Enrile', '1999-04-03', 'female', 'single', 'Filipino', '40 Tomas Morato', 'Barangay Laging Handa', 'QC', 'NCR_QUE', 'NCR', '1103', '09421234592', 'liliosa.h@email.com', '01-234567892-6', 'B-', NULL, NULL, 'Nurse Intern', 'active', 2, '2026-02-08 09:00:00'),
(27, 'AHN-2025-0027', 'Diosdado', 'Macapagal', 'Pangan', '1963-09-28', 'male', 'married', 'Filipino', '18 Pioneer St', 'Barangay Highway Hills', 'MAKATI', 'NCR_MAK', 'NCR', '1550', '09431234593', 'diosdado.m@email.com', '01-234567892-7', 'AB-', 'Morphine', 'Heart Failure, COPD', 'Retired Judge', 'active', 2, '2026-02-09 11:30:00'),
(28, 'AHN-2025-0028', 'Panday', 'Pira', 'Moro', '2003-06-15', 'male', 'single', 'Filipino', '99 C. Palanca St', 'Barangay Quiapo', 'MANILA', 'NCR_MNL', 'NCR', '1001', '09441234594', 'panday.p@email.com', '01-234567892-8', 'O+', NULL, NULL, 'Student', 'active', 2, '2026-02-10 08:00:00'),
(29, 'AHN-2025-0029', 'Liwayway', 'Arceo', 'Gatbonton', '1986-03-11', 'female', 'married', 'Filipino', '25 Mindanao Ave', 'Barangay Tandang Sora', 'QC', 'NCR_QUE', 'NCR', '1116', '09451234595', 'liwayway.a@email.com', '01-234567892-9', 'A+', NULL, 'Bronchial Asthma', 'Teacher', 'active', 2, '2026-02-11 10:00:00'),
(30, 'AHN-2025-0030', 'Carlos', 'Garcia', 'Polistico', '1977-11-04', 'male', 'married', 'Filipino', '7 Scout Borromeo', 'Barangay South Triangle', 'QC', 'NCR_QUE', 'NCR', '1103', '09461234596', 'carlos.g@email.com', '01-234567893-0', 'B+', NULL, 'Benign Prostatic Hyperplasia', 'Taxi Driver', 'active', 2, '2026-02-12 09:30:00')
ON DUPLICATE KEY UPDATE `first_name` = VALUES(`first_name`);

-- =====================================================
-- 3. WARDS
-- =====================================================
INSERT INTO `wards` (`id`, `ward_code`, `ward_name`, `ward_type`, `capacity`, `current_occupancy`, `charge_per_day`, `status`) VALUES
(1, 'GEN-A', 'General Ward A', 'general', 10, 6, 800.00, 'available'),
(2, 'GEN-B', 'General Ward B', 'general', 10, 4, 800.00, 'available'),
(3, 'PVT-A', 'Private Ward A', 'private', 6, 4, 3500.00, 'available'),
(4, 'SP-A', 'Semi-Private Ward', 'semi_private', 8, 5, 2000.00, 'available'),
(5, 'ICU-1', 'Intensive Care Unit', 'icu', 6, 3, 8000.00, 'available'),
(6, 'ER-W', 'Emergency Ward', 'emergency', 8, 5, 1500.00, 'available'),
(7, 'MAT-1', 'Maternity Ward', 'maternity', 8, 3, 2500.00, 'available'),
(8, 'PED-1', 'Pediatric Ward', 'pediatric', 8, 2, 1800.00, 'available')
ON DUPLICATE KEY UPDATE `ward_name` = VALUES(`ward_name`);

-- =====================================================
-- 4. BEDS
-- =====================================================
INSERT INTO `beds` (`id`, `bed_number`, `ward_id`, `bed_type`, `status`, `current_patient_id`, `daily_rate`) VALUES
(1,'GEN-A-01',1,'regular','occupied',1,800.00),(2,'GEN-A-02',1,'regular','occupied',3,800.00),
(3,'GEN-A-03',1,'regular','available',NULL,800.00),(4,'GEN-A-04',1,'regular','occupied',5,800.00),
(5,'GEN-A-05',1,'regular','available',NULL,800.00),(6,'GEN-A-06',1,'regular','occupied',17,800.00),
(7,'GEN-A-07',1,'regular','maintenance',NULL,800.00),(8,'GEN-A-08',1,'regular','occupied',19,800.00),
(9,'GEN-A-09',1,'regular','available',NULL,800.00),(10,'GEN-A-10',1,'regular','occupied',25,800.00),
(11,'GEN-B-01',2,'regular','occupied',21,800.00),(12,'GEN-B-02',2,'regular','available',NULL,800.00),
(13,'GEN-B-03',2,'regular','occupied',23,800.00),(14,'GEN-B-04',2,'regular','available',NULL,800.00),
(15,'GEN-B-05',2,'regular','occupied',29,800.00),(16,'GEN-B-06',2,'regular','available',NULL,800.00),
(17,'GEN-B-07',2,'regular','occupied',30,800.00),(18,'GEN-B-08',2,'regular','available',NULL,800.00),
(19,'PVT-A-01',3,'regular','occupied',2,3500.00),(20,'PVT-A-02',3,'regular','occupied',9,3500.00),
(21,'PVT-A-03',3,'regular','available',NULL,3500.00),(22,'PVT-A-04',3,'regular','occupied',8,3500.00),
(23,'PVT-A-05',3,'regular','available',NULL,3500.00),(24,'PVT-A-06',3,'regular','occupied',14,3500.00),
(25,'SP-A-01',4,'regular','occupied',4,2000.00),(26,'SP-A-02',4,'regular','occupied',6,2000.00),
(27,'SP-A-03',4,'regular','available',NULL,2000.00),(28,'SP-A-04',4,'regular','occupied',11,2000.00),
(29,'SP-A-05',4,'regular','occupied',13,2000.00),(30,'SP-A-06',4,'regular','available',NULL,2000.00),
(31,'ICU-01',5,'icu','occupied',27,8000.00),(32,'ICU-02',5,'icu','occupied',22,8000.00),
(33,'ICU-03',5,'icu','available',NULL,8000.00),(34,'ICU-04',5,'icu','occupied',16,8000.00),
(35,'ER-01',6,'regular','occupied',10,1500.00),(36,'ER-02',6,'regular','occupied',12,1500.00),
(37,'ER-03',6,'regular','available',NULL,1500.00),(38,'ER-04',6,'regular','occupied',15,1500.00),
(39,'MAT-01',7,'maternity','occupied',7,2500.00),(40,'MAT-02',7,'maternity','occupied',18,2500.00),
(41,'MAT-03',7,'maternity','available',NULL,2500.00),(42,'MAT-04',7,'maternity','occupied',24,2500.00),
(43,'PED-01',8,'pediatric','occupied',20,1800.00),(44,'PED-02',8,'pediatric','occupied',28,1800.00),
(45,'PED-03',8,'pediatric','available',NULL,1800.00)
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
