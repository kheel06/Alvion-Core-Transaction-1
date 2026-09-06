-- =====================================================
-- Payments seed - run only when payments table has billing_id
-- (e.g. after 01_migrate_admin_schema.sql on a DB that had no payments table)
-- Requires: billing records (from seed_admin_data_part3) and users 13, 14
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

INSERT INTO `payments` (`payment_number`,`billing_id`,`patient_id`,`payment_method`,`amount`,`payment_date`,`reference_number`,`status`,`processed_by`,`notes`) VALUES
('PAY-20251201-0001',1,1,'cash',2500.00,'2025-12-01 10:15:00','CASH-001','completed',13,'Consultation fee'),
('PAY-20251203-0002',2,2,'cash',1800.00,'2025-12-03 11:30:00','CASH-002','completed',13,'Consultation'),
('PAY-20251205-0003',3,3,'philhealth',1200.00,'2025-12-05 15:30:00','PH-001','completed',13,'PhilHealth deduction'),
('PAY-20251210-0004',4,5,'cash',2000.00,'2025-12-10 12:30:00','CASH-003','completed',13,'Partial payment'),
('PAY-20260105-0005',6,11,'cash',800.00,'2026-01-05 09:30:00','CASH-004','completed',14,'Thyroid consult'),
('PAY-20260112-0006',7,14,'philhealth',1500.00,'2026-01-12 10:30:00','PH-002','completed',13,'Arthritis follow-up'),
('PAY-20260205-0007',9,22,'hmo',1800.00,'2026-02-05 12:00:00','HMO-001','completed',13,'Back pain consult'),
('PAY-20260210-0008',10,27,'cash',1000.00,'2026-02-10 15:30:00','CASH-005','completed',14,'Partial - cardiac'),
('PAY-20260201-0009',11,1,'cash',15000.00,'2026-02-01 14:00:00','CASH-006','completed',13,'Admission partial'),
('PAY-20260201-0010',11,1,'philhealth',5000.00,'2026-02-02 09:00:00','PH-003','completed',13,'PhilHealth benefit'),
('PAY-20260208-0011',13,7,'cash',32000.00,'2026-02-08 11:00:00','CASH-007','completed',13,'Maternity package')
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
