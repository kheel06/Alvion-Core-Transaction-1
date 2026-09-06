-- =====================================================
-- Clinic Rooms Table
-- =====================================================
-- This table stores clinic/consultation rooms information
-- Used for appointment scheduling and room assignment

CREATE TABLE IF NOT EXISTS `clinic_rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT 'Room name or number (e.g., Room 101, Consultation Room A)',
  `room_code` varchar(20) DEFAULT NULL COMMENT 'Short code for the room',
  `department` varchar(100) DEFAULT NULL COMMENT 'Department this room belongs to',
  `floor` varchar(10) DEFAULT NULL COMMENT 'Floor number or location',
  `building` varchar(50) DEFAULT NULL COMMENT 'Building name or wing',
  `capacity` int(11) DEFAULT 1 COMMENT 'Number of patients that can be seen simultaneously',
  `equipment` text DEFAULT NULL COMMENT 'Available equipment in the room',
  `description` text DEFAULT NULL COMMENT 'Additional room description',
  `status` enum('available','occupied','maintenance','closed') DEFAULT 'available',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Whether the room is active for scheduling',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `room_code` (`room_code`),
  KEY `department` (`department`),
  KEY `status` (`status`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- Sample Data for Clinic Rooms
-- =====================================================

INSERT IGNORE INTO `clinic_rooms` (`name`, `room_code`, `department`, `floor`, `building`, `capacity`, `equipment`, `description`, `status`, `is_active`) VALUES
-- Cardiology Department
('Cardiology Consultation Room 1', 'CARD-001', 'Cardiology', '3', 'Cardiac & Vascular Institute', 1, 'ECG Machine, Blood Pressure Monitor, Stethoscope', 'Main consultation room for cardiology patients', 'available', 1),
('Cardiology Consultation Room 2', 'CARD-002', 'Cardiology', '3', 'Cardiac & Vascular Institute', 1, 'ECG Machine, Blood Pressure Monitor, Stethoscope', 'Secondary consultation room for cardiology', 'available', 1),
('Cardiology Procedure Room', 'CARD-PROC', 'Cardiology', '3', 'Cardiac & Vascular Institute', 1, 'ECG Machine, Stress Test Equipment, Ultrasound', 'Procedure room for cardiac tests', 'available', 1),

-- Obstetrics & Gynecology Department
('OB-GYN Consultation Room 1', 'OBGYN-001', 'Obstetrics & Gynecology', '2', 'Women & Child Health Pavilion', 1, 'Ultrasound Machine, Examination Table, Fetal Monitor', 'Main consultation room for OB-GYN patients', 'available', 1),
('OB-GYN Consultation Room 2', 'OBGYN-002', 'Obstetrics & Gynecology', '2', 'Women & Child Health Pavilion', 1, 'Ultrasound Machine, Examination Table', 'Secondary consultation room for OB-GYN', 'available', 1),
('Prenatal Care Room', 'OBGYN-PREN', 'Obstetrics & Gynecology', '2', 'Women & Child Health Pavilion', 1, 'Fetal Monitor, Ultrasound Machine', 'Dedicated room for prenatal checkups', 'available', 1),

-- Pediatrics Department
('Pediatrics Consultation Room 1', 'PED-001', 'Pediatrics', '1', 'Women & Child Health Pavilion', 1, 'Pediatric Examination Table, Growth Chart, Toys', 'Main consultation room for pediatric patients', 'available', 1),
('Pediatrics Consultation Room 2', 'PED-002', 'Pediatrics', '1', 'Women & Child Health Pavilion', 1, 'Pediatric Examination Table, Growth Chart', 'Secondary consultation room for pediatrics', 'available', 1),
('Pediatric Play Area Consultation', 'PED-PLAY', 'Pediatrics', '1', 'Women & Child Health Pavilion', 1, 'Pediatric Examination Table, Play Area', 'Child-friendly consultation room', 'available', 1),

-- Neurology Department
('Neurology Consultation Room 1', 'NEURO-001', 'Neurology', '4', 'Neuroscience & Stroke Center', 1, 'Neurological Examination Tools, Reflex Hammer', 'Main consultation room for neurology patients', 'available', 1),
('Neurology Consultation Room 2', 'NEURO-002', 'Neurology', '4', 'Neuroscience & Stroke Center', 1, 'Neurological Examination Tools', 'Secondary consultation room for neurology', 'available', 1),
('Neurology Testing Room', 'NEURO-TEST', 'Neurology', '4', 'Neuroscience & Stroke Center', 1, 'EEG Machine, EMG Equipment', 'Room for neurological testing procedures', 'available', 1),

-- Internal Medicine Department
('Internal Medicine Room 1', 'IM-001', 'Internal Medicine', '2', 'Adult & Family Medicine', 1, 'Examination Table, Blood Pressure Monitor, Stethoscope', 'Main consultation room for internal medicine', 'available', 1),
('Internal Medicine Room 2', 'IM-002', 'Internal Medicine', '2', 'Adult & Family Medicine', 1, 'Examination Table, Blood Pressure Monitor', 'Secondary consultation room for internal medicine', 'available', 1),
('Family Medicine Room', 'IM-FAM', 'Internal Medicine', '2', 'Adult & Family Medicine', 1, 'Examination Table, Family Health Records', 'Dedicated room for family medicine consultations', 'available', 1),

-- Orthopedics Department
('Orthopedics Consultation Room 1', 'ORTHO-001', 'Orthopedics', '3', 'Orthopedics Department', 1, 'X-Ray Viewer, Examination Table, Orthopedic Tools', 'Main consultation room for orthopedic patients', 'available', 1),
('Orthopedics Consultation Room 2', 'ORTHO-002', 'Orthopedics', '3', 'Orthopedics Department', 1, 'X-Ray Viewer, Examination Table', 'Secondary consultation room for orthopedics', 'available', 1),
('Orthopedics Procedure Room', 'ORTHO-PROC', 'Orthopedics', '3', 'Orthopedics Department', 1, 'Casting Equipment, Minor Procedure Tools', 'Room for minor orthopedic procedures', 'available', 1),

-- General Consultation Rooms
('General Consultation Room 1', 'GEN-001', 'General Medicine', '1', 'Main Clinic', 1, 'Examination Table, Basic Medical Equipment', 'General purpose consultation room', 'available', 1),
('General Consultation Room 2', 'GEN-002', 'General Medicine', '1', 'Main Clinic', 1, 'Examination Table, Basic Medical Equipment', 'General purpose consultation room', 'available', 1),
('General Consultation Room 3', 'GEN-003', 'General Medicine', '1', 'Main Clinic', 1, 'Examination Table, Basic Medical Equipment', 'General purpose consultation room', 'available', 1),

-- Teleconsultation Rooms
('Teleconsultation Room 1', 'TELECON-001', 'Telehealth', '1', 'Main Clinic', 1, 'Video Conferencing Equipment, Computer, Webcam', 'Dedicated room for teleconsultation appointments', 'available', 1),
('Teleconsultation Room 2', 'TELECON-002', 'Telehealth', '1', 'Main Clinic', 1, 'Video Conferencing Equipment, Computer, Webcam', 'Secondary teleconsultation room', 'available', 1);

-- =====================================================
-- Update appointments table to add room_id if it doesn't exist
-- (MySQL < 8.0.12 may not support IF NOT EXISTS on ADD COLUMN; run 02_align or add manually if needed)
-- =====================================================
-- ALTER TABLE `appointments`
-- ADD COLUMN IF NOT EXISTS `room_id` int(11) DEFAULT NULL COMMENT 'Reference to clinic_rooms table' AFTER `doctor_id`;
-- ADD CONSTRAINT `fk_appointments_room` FOREIGN KEY (`room_id`) REFERENCES `clinic_rooms` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

