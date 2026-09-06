-- =====================================================
-- Philippines Locations - Regions, Provinces, Cities
-- For patient/registration address dropdowns
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- =====================================================
-- PH REGIONS (expanded)
-- =====================================================
INSERT INTO `ph_regions` (`region_code`, `region_name`, `region_description`) VALUES
('CAR', 'Cordillera Administrative Region', 'CAR'),
('NCR', 'National Capital Region', 'Metro Manila'),
('REGION1', 'Region I - Ilocos Region', 'Ilocos Region'),
('REGION2', 'Region II - Cagayan Valley', 'Cagayan Valley'),
('REGION3', 'Region III - Central Luzon', 'Central Luzon'),
('REGION4A', 'Region IV-A - CALABARZON', 'Calabarzon'),
('REGION4B', 'Region IV-B - MIMAROPA', 'Mimaropa'),
('REGION5', 'Region V - Bicol Region', 'Bicol'),
('REGION6', 'Region VI - Western Visayas', 'Western Visayas'),
('REGION7', 'Region VII - Central Visayas', 'Central Visayas'),
('REGION8', 'Region VIII - Eastern Visayas', 'Eastern Visayas'),
('REGION9', 'Region IX - Zamboanga Peninsula', 'Zamboanga Peninsula'),
('REGION10', 'Region X - Northern Mindanao', 'Northern Mindanao'),
('REGION11', 'Region XI - Davao Region', 'Davao Region'),
('REGION12', 'Region XII - SOCCSKSARGEN', 'Soccsksargen'),
('REGION13', 'Region XIII - Caraga', 'Caraga'),
('BARMM', 'Bangsamoro Autonomous Region', 'BARMM')
ON DUPLICATE KEY UPDATE `region_name` = VALUES(`region_name`);

-- =====================================================
-- PH PROVINCES (NCR and common provinces)
-- =====================================================
INSERT INTO `ph_provinces` (`province_code`, `province_name`, `region_code`) VALUES
('NCR_MNL', 'Manila', 'NCR'),
('NCR_QUE', 'Quezon City', 'NCR'),
('NCR_MAK', 'Makati', 'NCR'),
('NCR_MAN', 'Mandaluyong', 'NCR'),
('NCR_PAS', 'Pasig', 'NCR'),
('NCR_PASAY', 'Pasay', 'NCR'),
('NCR_TAG', 'Taguig', 'NCR'),
('NCR_CAL', 'Caloocan', 'NCR'),
('NCR_MAR', 'Marikina', 'NCR'),
('NCR_LAS', 'Las Piñas', 'NCR'),
('NCR_MUN', 'Muntinlupa', 'NCR'),
('BEN', 'Benguet', 'CAR'),
('ILN', 'Ilocos Norte', 'REGION1'),
('ILS', 'Ilocos Sur', 'REGION1'),
('LUN', 'La Union', 'REGION1'),
('PAN', 'Pangasinan', 'REGION1'),
('BAT', 'Bataan', 'REGION3'),
('BUL', 'Bulacan', 'REGION3'),
('NUE', 'Nueva Ecija', 'REGION3'),
('PAM', 'Pampanga', 'REGION3'),
('TAR', 'Tarlac', 'REGION3'),
('ZAM', 'Zambales', 'REGION3'),
('CAV', 'Cavite', 'REGION4A'),
('LAG', 'Laguna', 'REGION4A'),
('BATANGAS', 'Batangas', 'REGION4A'),
('RIZ', 'Rizal', 'REGION4A'),
('QUE', 'Quezon', 'REGION4A'),
('CEB', 'Cebu', 'REGION7'),
('DAV', 'Davao del Sur', 'REGION11'),
('DAVOR', 'Davao Oriental', 'REGION11')
ON DUPLICATE KEY UPDATE `province_name` = VALUES(`province_name`);

-- =====================================================
-- PH CITIES (NCR and key cities)
-- =====================================================
INSERT INTO `ph_cities` (`city_code`, `city_name`, `province_code`) VALUES
('MANILA', 'Manila', 'NCR_MNL'),
('QC', 'Quezon City', 'NCR_QUE'),
('MAKATI', 'Makati City', 'NCR_MAK'),
('MANDALUYONG', 'Mandaluyong City', 'NCR_MAN'),
('PASIG', 'Pasig City', 'NCR_PAS'),
('PASAY', 'Pasay City', 'NCR_PASAY'),
('TAGUIG', 'Taguig City', 'NCR_TAG'),
('CALOOCAN', 'Caloocan City', 'NCR_CAL'),
('MARIKINA', 'Marikina City', 'NCR_MAR'),
('LASPINAS', 'Las Piñas City', 'NCR_LAS'),
('MUNTINLUPA', 'Muntinlupa City', 'NCR_MUN'),
('BAGUIO', 'Baguio City', 'BEN'),
('LAOAG', 'Laoag City', 'ILN'),
('VIGAN', 'Vigan City', 'ILS'),
('SANFERNANDO', 'San Fernando (La Union)', 'LUN'),
('DAGUPAN', 'Dagupan City', 'PAN'),
('OLONGAPO', 'Olongapo City', 'ZAM'),
('ANGELES', 'Angeles City', 'PAM'),
('BACOLOR', 'Bacolor', 'PAM'),
('MALOLOS', 'Malolos City', 'BUL'),
('MEYCAUAYAN', 'Meycauayan City', 'BUL'),
('CABANATUAN', 'Cabanatuan City', 'NUE'),
('TARLAC', 'Tarlac City', 'TAR'),
('BACOOR', 'Bacoor', 'CAV'),
('IMUS', 'Imus City', 'CAV'),
('DASMARINAS', 'Dasmariñas City', 'CAV'),
('BIÑAN', 'Biñan City', 'LAG'),
('CALAMBA', 'Calamba City', 'LAG'),
('SANPABLO', 'San Pablo City', 'LAG'),
('LIPA', 'Lipa City', 'BATANGAS'),
('BATANGAS', 'Batangas City', 'BATANGAS'),
('ANTIPOLO', 'Antipolo City', 'RIZ'),
('TAYTAY', 'Taytay', 'RIZ'),
('LUCENA', 'Lucena City', 'QUE'),
('CEBU', 'Cebu City', 'CEB'),
('DAVAO', 'Davao City', 'DAV')
ON DUPLICATE KEY UPDATE `city_name` = VALUES(`city_name`);
