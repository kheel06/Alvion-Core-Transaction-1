-- =====================================================
-- Billing Table
-- =====================================================
-- This table stores hospital billing information
-- Used for financial reports, payment tracking, and revenue management

CREATE TABLE IF NOT EXISTS `billing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bill_number` varchar(20) NOT NULL COMMENT 'Unique bill number (e.g., BILL-2024-001)',
  `patient_id` int(11) NOT NULL COMMENT 'Reference to patients table',
  `admission_id` int(11) DEFAULT NULL COMMENT 'Reference to admissions table (if inpatient)',
  `appointment_id` int(11) DEFAULT NULL COMMENT 'Reference to appointments table (if outpatient)',
  `teleconsultation_id` int(11) DEFAULT NULL COMMENT 'Reference to teleconsultations table',
  `total_amount` decimal(12,2) DEFAULT 0.00 COMMENT 'Total bill amount',
  `paid_amount` decimal(12,2) DEFAULT 0.00 COMMENT 'Amount paid so far',
  `balance_amount` decimal(12,2) DEFAULT 0.00 COMMENT 'Remaining balance (total_amount - paid_amount)',
  `payment_status` enum('pending','partial','paid','overdue') DEFAULT 'pending',
  `bill_date` date NOT NULL COMMENT 'Date when bill was created',
  `due_date` date DEFAULT NULL COMMENT 'Payment due date',
  `payment_method` enum('cash','credit_card','debit_card','insurance','hmo','philhealth') DEFAULT 'cash',
  `insurance_provider` varchar(100) DEFAULT NULL COMMENT 'Insurance company name',
  `philhealth_benefits` decimal(10,2) DEFAULT 0.00 COMMENT 'PhilHealth coverage amount',
  `created_by` int(11) DEFAULT NULL COMMENT 'User who created the bill',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `bill_number` (`bill_number`),
  KEY `patient_id` (`patient_id`),
  KEY `admission_id` (`admission_id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `teleconsultation_id` (`teleconsultation_id`),
  KEY `created_by` (`created_by`),
  KEY `bill_date` (`bill_date`),
  KEY `payment_status` (`payment_status`),
  KEY `payment_method` (`payment_method`),
  CONSTRAINT `fk_billing_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_billing_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- Sample Data for Billing
-- =====================================================
-- Note: This assumes you have patients and users in your database
-- Adjust patient_id and created_by values based on your actual data

-- Get sample patient IDs (using subquery to get existing patients)
-- If no patients exist, these will be NULL and the INSERT will fail gracefully
SET @patient1_id = (SELECT id FROM patients LIMIT 1 OFFSET 0);
SET @patient2_id = (SELECT id FROM patients LIMIT 1 OFFSET 1);
SET @patient3_id = (SELECT id FROM patients LIMIT 1 OFFSET 2);
SET @patient4_id = (SELECT id FROM patients LIMIT 1 OFFSET 3);
SET @patient5_id = (SELECT id FROM patients LIMIT 1 OFFSET 4);

-- Get sample user ID for created_by
SET @user1_id = (SELECT id FROM users WHERE role_id IN (SELECT id FROM roles WHERE role_name = 'admin' OR role_name = 'billing_staff') LIMIT 1);

-- Insert sample billing records
-- Only insert if we have at least one patient
INSERT INTO `billing` (
    `bill_number`, 
    `patient_id`, 
    `admission_id`, 
    `appointment_id`, 
    `teleconsultation_id`,
    `total_amount`, 
    `paid_amount`, 
    `balance_amount`, 
    `payment_status`, 
    `bill_date`, 
    `due_date`, 
    `payment_method`, 
    `insurance_provider`, 
    `philhealth_benefits`, 
    `created_by`
) 
SELECT 
    CONCAT('BILL-', DATE_FORMAT(CURDATE(), '%Y%m%d'), '-', LPAD(ROW_NUMBER() OVER (ORDER BY id), 3, '0')) as bill_number,
    id as patient_id,
    NULL as admission_id,
    NULL as appointment_id,
    NULL as teleconsultation_id,
    ROUND(500 + (RAND() * 5000), 2) as total_amount,
    CASE 
        WHEN RAND() < 0.3 THEN ROUND(500 + (RAND() * 5000), 2) -- 30% fully paid
        WHEN RAND() < 0.6 THEN ROUND((500 + (RAND() * 5000)) * 0.5, 2) -- 30% partial
        ELSE 0.00 -- 40% pending
    END as paid_amount,
    0.00 as balance_amount, -- Will be calculated
    CASE 
        WHEN RAND() < 0.3 THEN 'paid'
        WHEN RAND() < 0.6 THEN 'partial'
        WHEN RAND() < 0.85 THEN 'pending'
        ELSE 'overdue'
    END as payment_status,
    DATE_SUB(CURDATE(), INTERVAL FLOOR(RAND() * 30) DAY) as bill_date,
    DATE_ADD(DATE_SUB(CURDATE(), INTERVAL FLOOR(RAND() * 30) DAY), INTERVAL 30 DAY) as due_date,
    ELT(1 + FLOOR(RAND() * 6), 'cash', 'credit_card', 'debit_card', 'insurance', 'hmo', 'philhealth') as payment_method,
    CASE 
        WHEN RAND() < 0.3 THEN ELT(1 + FLOOR(RAND() * 5), 'Maxicare', 'Medicard', 'Intellicare', 'PhilCare', 'Pacific Cross')
        ELSE NULL
    END as insurance_provider,
    CASE 
        WHEN RAND() < 0.2 THEN ROUND(1000 + (RAND() * 3000), 2)
        ELSE 0.00
    END as philhealth_benefits,
    @user1_id as created_by
FROM patients
WHERE id IS NOT NULL
LIMIT 20;

-- Update balance_amount based on payment_status
UPDATE `billing` 
SET `balance_amount` = `total_amount` - `paid_amount`
WHERE `balance_amount` = 0.00 OR `balance_amount` IS NULL;

-- Update payment_status based on amounts
UPDATE `billing`
SET `payment_status` = CASE
    WHEN `paid_amount` >= `total_amount` THEN 'paid'
    WHEN `paid_amount` > 0 AND `paid_amount` < `total_amount` THEN 'partial'
    WHEN `due_date` < CURDATE() AND `balance_amount` > 0 THEN 'overdue'
    ELSE 'pending'
END;

-- =====================================================
-- Alternative: Manual Sample Data (if auto-generation doesn't work)
-- =====================================================
-- Uncomment and adjust these if the above SELECT doesn't work
/*
INSERT INTO `billing` (
    `bill_number`, 
    `patient_id`, 
    `total_amount`, 
    `paid_amount`, 
    `balance_amount`, 
    `payment_status`, 
    `bill_date`, 
    `due_date`, 
    `payment_method`, 
    `created_by`
) VALUES
('BILL-2024-001', 1, 5000.00, 5000.00, 0.00, 'paid', CURDATE() - INTERVAL 5 DAY, CURDATE() + INTERVAL 25 DAY, 'cash', 1),
('BILL-2024-002', 1, 3500.00, 2000.00, 1500.00, 'partial', CURDATE() - INTERVAL 10 DAY, CURDATE() + INTERVAL 20 DAY, 'credit_card', 1),
('BILL-2024-003', 2, 8000.00, 0.00, 8000.00, 'pending', CURDATE() - INTERVAL 3 DAY, CURDATE() + INTERVAL 27 DAY, 'cash', 1),
('BILL-2024-004', 2, 12000.00, 0.00, 12000.00, 'overdue', CURDATE() - INTERVAL 35 DAY, CURDATE() - INTERVAL 5 DAY, 'insurance', 1),
('BILL-2024-005', 3, 2500.00, 2500.00, 0.00, 'paid', CURDATE() - INTERVAL 15 DAY, CURDATE() + INTERVAL 15 DAY, 'philhealth', 1),
('BILL-2024-006', 3, 6000.00, 3000.00, 3000.00, 'partial', CURDATE() - INTERVAL 7 DAY, CURDATE() + INTERVAL 23 DAY, 'debit_card', 1),
('BILL-2024-007', 4, 4500.00, 4500.00, 0.00, 'paid', CURDATE() - INTERVAL 20 DAY, CURDATE() + INTERVAL 10 DAY, 'hmo', 1),
('BILL-2024-008', 4, 9000.00, 0.00, 9000.00, 'pending', CURDATE() - INTERVAL 2 DAY, CURDATE() + INTERVAL 28 DAY, 'cash', 1),
('BILL-2024-009', 5, 15000.00, 10000.00, 5000.00, 'partial', CURDATE() - INTERVAL 12 DAY, CURDATE() + INTERVAL 18 DAY, 'insurance', 1),
('BILL-2024-010', 5, 3000.00, 3000.00, 0.00, 'paid', CURDATE() - INTERVAL 25 DAY, CURDATE() + INTERVAL 5 DAY, 'philhealth', 1);
*/

-- =====================================================
-- Create indexes for better performance
-- =====================================================
-- Most indexes are already included in the CREATE TABLE statement
-- Additional composite indexes for common queries:
-- CREATE INDEX (skip if already exist — MySQL does not support IF NOT EXISTS for indexes)
-- CREATE INDEX `idx_billing_date_status` ON `billing` (`bill_date`, `payment_status`);
-- CREATE INDEX `idx_billing_patient_date` ON `billing` (`patient_id`, `bill_date`);
-- CREATE INDEX `idx_billing_status_method` ON `billing` (`payment_status`, `payment_method`);

-- =====================================================
-- Notes:
-- =====================================================
-- 1. This table requires the `patients` table to exist
-- 2. Foreign key to `users` table is optional (created_by can be NULL)
-- 3. Foreign keys to `admissions`, `appointments`, `teleconsultations` are optional
-- 4. Bill numbers should be unique and follow a pattern
-- 5. Balance amount is automatically calculated: total_amount - paid_amount
-- 6. Payment status should be updated when payments are made
-- 7. Run this file after creating the patients and users tables

