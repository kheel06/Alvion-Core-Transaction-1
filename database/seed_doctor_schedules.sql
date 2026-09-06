-- =====================================================
-- Doctor Schedules: table + seed for manage_schedules.php
-- Run after: doctors_insert.sql (doctors 1-6 exist).
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- =====================================================
-- 1. CREATE TABLE doctor_schedules (if not exists)
-- =====================================================
CREATE TABLE IF NOT EXISTS `doctor_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `doctor_id` int(11) NOT NULL COMMENT 'doctors.id',
  `day_of_week` tinyint(1) NOT NULL COMMENT '0=Sunday, 1=Monday, ..., 6=Saturday',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `slot_duration_minutes` int(11) DEFAULT 30,
  `max_daily_appointments` int(11) DEFAULT 20,
  `room_id` int(11) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_doctor_day` (`doctor_id`, `day_of_week`),
  KEY `doctor_id` (`doctor_id`),
  KEY `day_of_week` (`day_of_week`),
  CONSTRAINT `fk_doctor_schedules_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 2. SEED: 2-3 schedules per doctor (Mon–Fri, 8am–5pm style)
-- doctors.id 1–6 from doctors_insert.sql; created_by = 1 (admin)
-- =====================================================
INSERT INTO `doctor_schedules` (`doctor_id`, `day_of_week`, `start_time`, `end_time`, `slot_duration_minutes`, `max_daily_appointments`, `room_id`, `notes`, `created_by`, `created_at`) VALUES
(1, 1, '08:00:00', '12:00:00', 30, 8, NULL, 'Morning clinic', 1, NOW()),
(1, 3, '08:00:00', '17:00:00', 30, 16, NULL, 'Full day', 1, NOW()),
(2, 2, '09:00:00', '16:00:00', 30, 14, NULL, 'OB-GYN clinic', 1, NOW()),
(2, 4, '08:30:00', '12:30:00', 30, 8, NULL, 'Morning only', 1, NOW()),
(3, 1, '08:00:00', '17:00:00', 30, 18, NULL, 'Pediatrics', 1, NOW()),
(3, 4, '13:00:00', '17:00:00', 30, 8, NULL, 'Afternoon', 1, NOW()),
(4, 2, '08:00:00', '12:00:00', 30, 8, NULL, 'Neurology AM', 1, NOW()),
(4, 5, '08:00:00', '17:00:00', 30, 16, NULL, 'Full day', 1, NOW()),
(5, 1, '09:00:00', '17:00:00', 30, 16, NULL, 'Internal medicine', 1, NOW()),
(5, 3, '08:00:00', '12:00:00', 30, 8, NULL, 'Morning', 1, NOW()),
(6, 2, '08:00:00', '17:00:00', 30, 18, NULL, 'Orthopedics', 1, NOW()),
(6, 5, '09:00:00', '15:00:00', 30, 12, NULL, 'Half day', 1, NOW())
ON DUPLICATE KEY UPDATE `start_time` = VALUES(`start_time`), `end_time` = VALUES(`end_time`), `notes` = VALUES(`notes`);

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
