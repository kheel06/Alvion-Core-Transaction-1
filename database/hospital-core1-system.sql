-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 17, 2025 at 06:31 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hospital-core1-system`
--

-- --------------------------------------------------------

--
-- Table structure for table `admissions`
--

CREATE TABLE `admissions` (
  `id` int(11) NOT NULL,
  `admission_number` varchar(20) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `admitting_doctor_id` int(11) DEFAULT NULL,
  `bed_id` int(11) DEFAULT NULL,
  `admission_date` datetime NOT NULL,
  `admission_type` enum('emergency','scheduled','transfer') NOT NULL,
  `diagnosis` text DEFAULT NULL,
  `reason_for_admission` text DEFAULT NULL,
  `status` enum('admitted','discharged','transferred') DEFAULT 'admitted',
  `expected_discharge_date` date DEFAULT NULL,
  `actual_discharge_date` datetime DEFAULT NULL,
  `discharge_summary` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `appointment_number` varchar(20) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `appointment_type` enum('consultation','followup','checkup','emergency','teleconsult') NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `status` enum('scheduled','confirmed','in_progress','completed','cancelled','no_show') DEFAULT 'scheduled',
  `reason` text DEFAULT NULL,
  `symptoms` text DEFAULT NULL,
  `priority_level` enum('low','medium','high','emergency') DEFAULT 'medium',
  `is_walkin` tinyint(1) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `module` varchar(50) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `beds`
--

CREATE TABLE `beds` (
  `id` int(11) NOT NULL,
  `bed_number` varchar(20) NOT NULL,
  `ward_id` int(11) NOT NULL,
  `bed_type` enum('regular','icu','maternity','pediatric') DEFAULT 'regular',
  `status` enum('available','occupied','maintenance','reserved') DEFAULT 'available',
  `current_patient_id` int(11) DEFAULT NULL,
  `daily_rate` decimal(10,2) DEFAULT 0.00,
  `features` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `billing`
--

CREATE TABLE `billing` (
  `id` int(11) NOT NULL,
  `bill_number` varchar(20) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `admission_id` int(11) DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `teleconsultation_id` int(11) DEFAULT NULL,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `paid_amount` decimal(12,2) DEFAULT 0.00,
  `balance_amount` decimal(12,2) DEFAULT 0.00,
  `payment_status` enum('pending','partial','paid','overdue') DEFAULT 'pending',
  `bill_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `payment_method` enum('cash','credit_card','debit_card','insurance','hmo','philhealth') DEFAULT 'cash',
  `insurance_provider` varchar(100) DEFAULT NULL,
  `philhealth_benefits` decimal(10,2) DEFAULT 0.00,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `department_accounts`
--

CREATE TABLE `department_accounts` (
  `employee_id` varchar(11) NOT NULL,
  `employee_fname` varchar(100) NOT NULL,
  `employee_lname` varchar(100) NOT NULL,
  `employee_email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `department_accounts`
--

INSERT INTO `department_accounts` (`employee_id`, `employee_fname`, `employee_lname`, `employee_email`, `password`, `role_name`, `profile_picture`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
('C1-2025-01', 'Michael', 'Petras', 'petrasmichael06@gmail.com', 'C1202501#AV06', 'admin', 'assets/uploads/profile_pictures/employee_profile_C1-2025-01_1763395425.gif', 1, '2025-11-17 17:30:18', '2025-11-17 13:34:35', '2025-11-17 17:30:18'),
('C1-2025-02', 'admin', 'blabla', 'jdoe.doctor@admin.alvion.com', 'C1202502#AV06', 'doctor', NULL, 1, NULL, '2025-11-17 13:34:35', '2025-11-17 13:34:35'),
('C1-2025-03', 'admin', 'blabla', 'jdoe.staff@admin.alvion.com', 'C1202503#AV06', 'staff', NULL, 1, NULL, '2025-11-17 13:34:35', '2025-11-17 13:34:35'),
('C1-2025-05', 'admin', 'blabla', 'jdoe.receptionist@admin.alvion.com', 'C1202505#AV06', 'receptionist', NULL, 1, NULL, '2025-11-17 13:34:35', '2025-11-17 13:34:35'),
('C1-2025-06', 'admin', 'blabla', 'jdoe.finance@admin.alvion.com', 'C1202506#AV06', 'finance staff', NULL, 1, NULL, '2025-11-17 13:34:35', '2025-11-17 13:34:35');

-- --------------------------------------------------------

--
-- Table structure for table `er_triage`
--

CREATE TABLE `er_triage` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `triage_nurse_id` int(11) DEFAULT NULL,
  `chief_complaint` text NOT NULL,
  `triage_level` enum('resuscitation','emergency','urgent','semi_urgent','non_urgent') NOT NULL,
  `vital_signs` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`vital_signs`)),
  `initial_assessment` text DEFAULT NULL,
  `priority_score` int(11) DEFAULT NULL,
  `status` enum('waiting','in_progress','admitted','discharged','transferred') DEFAULT 'waiting',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `otp_codes`
--

CREATE TABLE `otp_codes` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(10) NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `department_account_id` int(10) UNSIGNED DEFAULT NULL,
  `source_table` enum('users','department_accounts') NOT NULL,
  `destination` varchar(255) NOT NULL,
  `purpose` varchar(50) DEFAULT 'login',
  `expires_at` datetime NOT NULL,
  `consumed_at` datetime DEFAULT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `otp_codes`
--

INSERT INTO `otp_codes` (`id`, `code`, `user_id`, `department_account_id`, `source_table`, `destination`, `purpose`, `expires_at`, `consumed_at`, `attempts`, `created_at`, `updated_at`) VALUES
(1, '697555', 2, NULL, 'users', 'petrasmichael06@gmail.com', 'login', '2025-11-17 18:36:26', NULL, 0, '2025-11-17 17:26:26', NULL),
(2, '140349', NULL, 0, 'department_accounts', 'petrasmichael06@gmail.com', 'login', '2025-11-17 18:39:48', NULL, 0, '2025-11-17 17:29:48', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `hospital_id` varchar(20) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `suffix` varchar(10) DEFAULT NULL,
  `birth_date` date NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `civil_status` enum('single','married','widowed','separated','divorced') DEFAULT NULL,
  `nationality` varchar(50) DEFAULT 'Filipino',
  `birth_place` varchar(100) DEFAULT NULL,
  `house_no_street` varchar(255) DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `city_code` varchar(10) DEFAULT NULL,
  `province_code` varchar(10) DEFAULT NULL,
  `region_code` varchar(10) DEFAULT NULL,
  `zip_code` varchar(10) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_number` varchar(20) DEFAULT NULL,
  `emergency_contact_relationship` varchar(50) DEFAULT NULL,
  `philhealth_id` varchar(20) DEFAULT NULL,
  `sss_id` varchar(20) DEFAULT NULL,
  `gsis_id` varchar(20) DEFAULT NULL,
  `tin` varchar(20) DEFAULT NULL,
  `passport_number` varchar(20) DEFAULT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
  `known_allergies` text DEFAULT NULL,
  `pre_existing_conditions` text DEFAULT NULL,
  `current_medications` text DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `employer_name` varchar(100) DEFAULT NULL,
  `employer_address` text DEFAULT NULL,
  `employer_contact` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive','deceased') DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `permission_name` varchar(100) NOT NULL,
  `permission_description` text DEFAULT NULL,
  `module` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ph_cities`
--

CREATE TABLE `ph_cities` (
  `city_code` varchar(10) NOT NULL,
  `city_name` varchar(100) NOT NULL,
  `province_code` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ph_cities`
--

INSERT INTO `ph_cities` (`city_code`, `city_name`, `province_code`) VALUES
('BAGUIO', 'Baguio', 'BEN'),
('LAOAG', 'Laoag', 'ILN'),
('MAKATI', 'Makati', 'NCR_MAK'),
('MANILA', 'Manila', 'NCR_MNL'),
('QC', 'Quezon City', 'NCR_QUE');

-- --------------------------------------------------------

--
-- Table structure for table `ph_provinces`
--

CREATE TABLE `ph_provinces` (
  `province_code` varchar(10) NOT NULL,
  `province_name` varchar(100) NOT NULL,
  `region_code` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ph_provinces`
--

INSERT INTO `ph_provinces` (`province_code`, `province_name`, `region_code`) VALUES
('BEN', 'Benguet', 'CAR'),
('ILN', 'Ilocos Norte', 'REGION1'),
('NCR_MAK', 'Makati', 'NCR'),
('NCR_MNL', 'Manila', 'NCR'),
('NCR_QUE', 'Quezon City', 'NCR');

-- --------------------------------------------------------

--
-- Table structure for table `ph_regions`
--

CREATE TABLE `ph_regions` (
  `region_code` varchar(10) NOT NULL,
  `region_name` varchar(100) NOT NULL,
  `region_description` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ph_regions`
--

INSERT INTO `ph_regions` (`region_code`, `region_name`, `region_description`) VALUES
('CAR', 'Cordillera Administrative Region', 'CAR'),
('NCR', 'National Capital Region', 'Metro Manila'),
('REGION1', 'Region I', 'Ilocos Region'),
('REGION2', 'Region II', 'Cagayan Valley'),
('REGION3', 'Region III', 'Central Luzon');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `role_description` text DEFAULT NULL,
  `is_system_role` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `role_description`, `is_system_role`, `created_at`) VALUES
(1, 'admin', 'System Administrator with full access', 1, '2025-11-11 17:58:35'),
(2, 'doctor', 'Medical doctor with patient care privileges', 1, '2025-11-11 17:58:35'),
(3, 'staff', 'Hospital staff with limited access', 1, '2025-11-11 17:58:35'),
(4, 'patient', 'Patient with personal access', 1, '2025-11-11 17:58:35'),
(5, 'receptionist', 'Registration, scheduling, insurance check', 1, '2025-11-12 01:42:23'),
(6, 'finance staff', 'Insurance, payments, billing reports', 1, '2025-11-12 01:50:28');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teleconsultations`
--

CREATE TABLE `teleconsultations` (
  `id` int(11) NOT NULL,
  `consultation_number` varchar(20) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `consultation_date` datetime NOT NULL,
  `status` enum('scheduled','ongoing','completed','cancelled','no_show') DEFAULT 'scheduled',
  `consultation_notes` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `prescription` text DEFAULT NULL,
  `follow_up_required` tinyint(1) DEFAULT 0,
  `follow_up_date` date DEFAULT NULL,
  `video_link` varchar(255) DEFAULT NULL,
  `consultation_fee` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `role_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `status` enum('active','inactive') DEFAULT 'active',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `first_name`, `last_name`, `profile_picture`, `role_id`, `is_active`, `status`, `last_login`, `created_at`, `updated_at`) VALUES
(2, 'khel', '$2y$10$5LLVo/iZYR5T5drjqlNAJusKMmbeS/Q7rx56utckl.4RuTjdLoxOG', 'petrasmichael06@gmail.com', 'Michael', 'Petras', 'assets/uploads/profile_pictures/profile_2_1763392557.gif', 4, 1, 'active', '2025-11-17 17:27:08', '2025-11-10 19:01:54', '2025-11-17 17:27:08');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wards`
--

CREATE TABLE `wards` (
  `id` int(11) NOT NULL,
  `ward_code` varchar(10) NOT NULL,
  `ward_name` varchar(100) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `ward_type` enum('general','private','semi_private','icu','emergency','maternity','pediatric') NOT NULL,
  `capacity` int(11) NOT NULL,
  `current_occupancy` int(11) DEFAULT 0,
  `charge_per_day` decimal(10,2) DEFAULT 0.00,
  `status` enum('available','full','maintenance','closed') DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admissions`
--
ALTER TABLE `admissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `admission_number` (`admission_number`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `admitting_doctor_id` (`admitting_doctor_id`),
  ADD KEY `bed_id` (`bed_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `appointment_number` (`appointment_number`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `beds`
--
ALTER TABLE `beds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ward_id` (`ward_id`),
  ADD KEY `current_patient_id` (`current_patient_id`);

--
-- Indexes for table `billing`
--
ALTER TABLE `billing`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bill_number` (`bill_number`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `admission_id` (`admission_id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `teleconsultation_id` (`teleconsultation_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `department_accounts`
--
ALTER TABLE `department_accounts`
  ADD PRIMARY KEY (`employee_id`),
  ADD UNIQUE KEY `employee_email` (`employee_email`),
  ADD KEY `fk_role_name` (`role_name`);

--
-- Indexes for table `er_triage`
--
ALTER TABLE `er_triage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `triage_nurse_id` (`triage_nurse_id`);

--
-- Indexes for table `otp_codes`
--
ALTER TABLE `otp_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_source_user` (`source_table`,`user_id`),
  ADD KEY `idx_source_department` (`source_table`,`department_account_id`),
  ADD KEY `idx_code_destination` (`code`,`destination`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `hospital_id` (`hospital_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `city_code` (`city_code`),
  ADD KEY `province_code` (`province_code`),
  ADD KEY `region_code` (`region_code`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permission_name` (`permission_name`);

--
-- Indexes for table `ph_cities`
--
ALTER TABLE `ph_cities`
  ADD PRIMARY KEY (`city_code`),
  ADD KEY `province_code` (`province_code`);

--
-- Indexes for table `ph_provinces`
--
ALTER TABLE `ph_provinces`
  ADD PRIMARY KEY (`province_code`),
  ADD KEY `region_code` (`region_code`);

--
-- Indexes for table `ph_regions`
--
ALTER TABLE `ph_regions`
  ADD PRIMARY KEY (`region_code`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_role_permission` (`role_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `teleconsultations`
--
ALTER TABLE `teleconsultations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `consultation_number` (`consultation_number`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `fk_users_role_id` (`role_id`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `wards`
--
ALTER TABLE `wards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ward_code` (`ward_code`),
  ADD KEY `department_id` (`department_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admissions`
--
ALTER TABLE `admissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `beds`
--
ALTER TABLE `beds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `billing`
--
ALTER TABLE `billing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `er_triage`
--
ALTER TABLE `er_triage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `otp_codes`
--
ALTER TABLE `otp_codes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `teleconsultations`
--
ALTER TABLE `teleconsultations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wards`
--
ALTER TABLE `wards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admissions`
--
ALTER TABLE `admissions`
  ADD CONSTRAINT `admissions_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `admissions_ibfk_2` FOREIGN KEY (`admitting_doctor_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `admissions_ibfk_3` FOREIGN KEY (`bed_id`) REFERENCES `beds` (`id`),
  ADD CONSTRAINT `admissions_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `beds`
--
ALTER TABLE `beds`
  ADD CONSTRAINT `beds_ibfk_1` FOREIGN KEY (`ward_id`) REFERENCES `wards` (`id`),
  ADD CONSTRAINT `beds_ibfk_2` FOREIGN KEY (`current_patient_id`) REFERENCES `patients` (`id`);

--
-- Constraints for table `billing`
--
ALTER TABLE `billing`
  ADD CONSTRAINT `billing_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `billing_ibfk_2` FOREIGN KEY (`admission_id`) REFERENCES `admissions` (`id`),
  ADD CONSTRAINT `billing_ibfk_3` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`),
  ADD CONSTRAINT `billing_ibfk_4` FOREIGN KEY (`teleconsultation_id`) REFERENCES `teleconsultations` (`id`),
  ADD CONSTRAINT `billing_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `department_accounts`
--
ALTER TABLE `department_accounts`
  ADD CONSTRAINT `fk_role_name` FOREIGN KEY (`role_name`) REFERENCES `roles` (`role_name`) ON UPDATE CASCADE;

--
-- Constraints for table `er_triage`
--
ALTER TABLE `er_triage`
  ADD CONSTRAINT `er_triage_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `er_triage_ibfk_2` FOREIGN KEY (`triage_nurse_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `patients_ibfk_2` FOREIGN KEY (`city_code`) REFERENCES `ph_cities` (`city_code`),
  ADD CONSTRAINT `patients_ibfk_3` FOREIGN KEY (`province_code`) REFERENCES `ph_provinces` (`province_code`),
  ADD CONSTRAINT `patients_ibfk_4` FOREIGN KEY (`region_code`) REFERENCES `ph_regions` (`region_code`);

--
-- Constraints for table `ph_cities`
--
ALTER TABLE `ph_cities`
  ADD CONSTRAINT `ph_cities_ibfk_1` FOREIGN KEY (`province_code`) REFERENCES `ph_provinces` (`province_code`);

--
-- Constraints for table `ph_provinces`
--
ALTER TABLE `ph_provinces`
  ADD CONSTRAINT `ph_provinces_ibfk_1` FOREIGN KEY (`region_code`) REFERENCES `ph_regions` (`region_code`);

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teleconsultations`
--
ALTER TABLE `teleconsultations`
  ADD CONSTRAINT `teleconsultations_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `teleconsultations_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wards`
--
ALTER TABLE `wards`
  ADD CONSTRAINT `wards_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
