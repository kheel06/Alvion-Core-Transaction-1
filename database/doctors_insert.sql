-- =====================================================
-- Create doctors table (if it doesn't exist)
-- =====================================================
CREATE TABLE IF NOT EXISTS `doctors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prc_number` varchar(20) NOT NULL,
  `specialty` varchar(100) NOT NULL,
  `services` text DEFAULT NULL,
  `years_experience` int(11) DEFAULT NULL,
  `consultation_fee` decimal(10,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1,
  `description` text DEFAULT NULL,
  `education` text DEFAULT NULL,
  `certifications` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `prc_number` (`prc_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- Insert doctor data
-- =====================================================
INSERT INTO `doctors` (
    `prc_number`, 
    `specialty`, 
    `services`, 
    `years_experience`, 
    `consultation_fee`, 
    `status`, 
    `description`, 
    `education`, 
    `certifications`
) VALUES
(
    'PRC-123456', 
    'Cardiologist', 
    'International Cardiology, Preventive Heart Care', 
    12, 
    1500.00, 
    1, 
    'With over 12 years of experience in cardiovascular medicine, Dr. Reyes provides international cardiology and preventive heart care for Filipino patients.', 
    'University of the Philippines College of Medicine - Doctor of Medicine; Philippine Heart Center - Cardiology Fellowship', 
    'N.D., PFCG, NZCC, Diplomate Philippine College of Cardiology'
),
(
    'PRC-234567', 
    'Obstetrician & Gynecologist', 
    'Prenatal Care, High-risk Pregnancies, Gynecological Surgery', 
    8, 
    1200.00, 
    1, 
    'Providing comprehensive research health services including personal care, high-risk pregnancies, and gynecological surgery with compassionate support.', 
    'University of Santo Tomas Faculty of Medicine and Surgery - Doctor of Medicine; St. Lukes Medical Center - OB-GYN Residency', 
    'N.D., PFCGCP, Diplomate Philippine Board of Obstetrics and Gynecology'
),
(
    'PRC-345678', 
    'Pediatrician', 
    'Pediatric Immunology, Developmental Pediatrics', 
    15, 
    800.00, 
    1, 
    'Dr. Dela Cruz has dedicated his career to children''s health, with special interest in pediatric immunology and developmental pediatrics for Filipino families.', 
    'University of the East Ramon Magsaysay Memorial Medical Center - Doctor of Medicine; Philippine Children''s Medical Center - Pediatrics Fellowship', 
    'N.D., PPFS, Diplomate Philippine Pediatric Society'
),
(
    'PRC-456789', 
    'Neurologist', 
    'Neurodegenerative Diseases', 
    10, 
    1800.00, 
    1, 
    'Specializing in neurodegenerative diseases, Dr. Marquez leads our neurology department with expertise in neuroclinical care.', 
    'De La Salle Health Sciences Institute - Doctor of Medicine; Philippine General Hospital - Neurology Residency', 
    'N.D., FPNA, Diplomate Philippine Neurological Association'
),
(
    'PRC-567890', 
    'Internal Medicine', 
    'Preventive Medicine, Chronic Disease Management', 
    7, 
    900.00, 
    1, 
    'Board-certified internal specializing in preventive medicine, chronic disease management, and comprehensive primary care for adult patients.', 
    'Ateneo School of Medicine and Public Health - Doctor of Medicine; Makati Medical Center - Internal Medicine Residency', 
    'N.D., PFCG, Diplomate Philippine College of Physicians'
),
(
    'PRC-678901', 
    'Orthopedic Surgeon', 
    'Musculoskeletal Conditions, Sports Injuries, Joint Replacements', 
    11, 
    2000.00, 
    1, 
    'Expert in musculoskeletal conditions, from sports injuries to joint replacements, Dr. Navarro provides comprehensive orthopedic care for all ages.', 
    'Cebu Institute of Medicine - Doctor of Medicine; Philippine Orthopedic Center - Orthopedic Surgery Residency', 
    'N.D., PFCG, Diplomate Philippine Board of Orthopedic Surgery'
);

