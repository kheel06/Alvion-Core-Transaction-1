-- =====================================================
-- SQL Queries for Public Appointment Booking (landing.php)
-- =====================================================
-- This file contains all SQL queries needed to handle
-- appointment bookings from the public landing page

-- =====================================================
-- 1. ALTER TABLE to add missing fields
-- =====================================================
-- Run these ALTER statements if the fields don't exist in your appointments table
-- Note: Remove "IF NOT EXISTS" if your MySQL version doesn't support it
-- In that case, check if columns exist before running

-- Check if columns exist first (for MySQL < 8.0 or MariaDB)
-- SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_SCHEMA = 'hospital-core1-system' 
-- AND TABLE_NAME = 'appointments' 
-- AND COLUMN_NAME IN ('clinic', 'department', 'booking_channel', 'patient_email', 'patient_contact');

ALTER TABLE `appointments` 
ADD COLUMN `clinic` VARCHAR(50) DEFAULT NULL COMMENT 'Clinic location (main, satellite1, satellite2)' AFTER `doctor_id`,
ADD COLUMN `department` VARCHAR(100) DEFAULT NULL COMMENT 'Department name' AFTER `clinic`,
ADD COLUMN `booking_channel` ENUM('online','walkin','phone','reception') DEFAULT 'online' COMMENT 'How the appointment was booked' AFTER `is_walkin`,
ADD COLUMN `patient_email` VARCHAR(100) DEFAULT NULL COMMENT 'Patient email from booking form' AFTER `patient_id`,
ADD COLUMN `patient_contact` VARCHAR(20) DEFAULT NULL COMMENT 'Patient contact from booking form' AFTER `patient_email`;

-- =====================================================
-- 2. QUERY TO CHECK IF PATIENT EXISTS
-- =====================================================
-- Check if patient exists by email or contact number
SELECT 
    id, 
    hospital_id, 
    first_name, 
    last_name, 
    email, 
    contact_number 
FROM patients 
WHERE (email = :email OR contact_number = :contact_number)
AND status = 'active'
LIMIT 1;

-- =====================================================
-- 3. QUERY TO CREATE NEW PATIENT (if not exists)
-- =====================================================
-- Note: This is a minimal patient record. In production, you may want to 
-- require more fields or create a temporary patient record that needs completion
INSERT INTO patients (
    hospital_id,
    first_name,
    last_name,
    email,
    contact_number,
    gender,  -- Required field - you may need to add this to the form
    birth_date,  -- Required field - you may need to add this to the form
    status,
    created_by
) VALUES (
    :hospital_id,  -- Generate unique hospital ID (e.g., PAT-YYYYMMDD-XXXX)
    :first_name,
    :last_name,
    :email,
    :contact_number,
    :gender,  -- Default or from form
    :birth_date,  -- Default or from form
    'active',
    NULL  -- Public booking, no user_id
);

-- =====================================================
-- 4. QUERY TO GENERATE APPOINTMENT NUMBER
-- =====================================================
-- Generate unique appointment number: APT-YYYYMMDD-XXXX
SELECT CONCAT('APT-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', 
    LPAD(COALESCE(MAX(CAST(SUBSTRING(appointment_number, -4) AS UNSIGNED)), 0) + 1, 4, '0')
) AS appointment_number
FROM appointments
WHERE appointment_number LIKE CONCAT('APT-', DATE_FORMAT(NOW(), '%Y%m%d'), '-%');

-- =====================================================
-- 5. MAIN INSERT QUERY FOR APPOINTMENT
-- =====================================================
INSERT INTO appointments (
    appointment_number,
    patient_id,
    patient_email,
    patient_contact,
    doctor_id,
    clinic,
    department,
    appointment_type,
    appointment_date,
    appointment_time,
    reason,
    priority_level,
    is_walkin,
    booking_channel,
    status,
    created_by,
    created_at
) VALUES (
    :appointment_number,
    :patient_id,
    :patient_email,
    :patient_contact,
    :doctor_id,
    :clinic,
    :department,
    :appointment_type,  -- Map from visit_type: in_person->consultation, teleconsultation->teleconsult, walkin_intent->consultation
    :appointment_date,
    :appointment_time,
    :reason,
    :priority_level,  -- Default: 'medium'
    :is_walkin,  -- 1 if visit_type = 'walkin_intent', else 0
    :booking_channel,  -- 'online' from form
    'scheduled',
    NULL,  -- Public booking
    NOW()
);

-- =====================================================
-- 6. MAPPING LOGIC (for reference in PHP)
-- =====================================================
-- visit_type from form -> appointment_type in database:
-- 'in_person' -> 'consultation'
-- 'teleconsultation' -> 'teleconsult'
-- 'walkin_intent' -> 'consultation' (with is_walkin = 1)

-- =====================================================
-- 7. QUERY TO CHECK DOCTOR AVAILABILITY (Optional)
-- =====================================================
-- Check if doctor has conflicting appointments
SELECT COUNT(*) as conflict_count
FROM appointments
WHERE doctor_id = :doctor_id
AND appointment_date = :appointment_date
AND appointment_time = :appointment_time
AND status NOT IN ('cancelled', 'no_show', 'completed')
LIMIT 1;

-- =====================================================
-- 8. QUERY TO GET APPOINTMENT DETAILS AFTER INSERT
-- =====================================================
SELECT 
    a.id,
    a.appointment_number,
    a.appointment_date,
    a.appointment_time,
    a.status,
    p.first_name,
    p.last_name,
    p.email,
    p.contact_number,
    u.first_name as doctor_first_name,
    u.last_name as doctor_last_name,
    a.department,
    a.clinic
FROM appointments a
LEFT JOIN patients p ON a.patient_id = p.id
LEFT JOIN users u ON a.doctor_id = u.id
WHERE a.id = :appointment_id;

