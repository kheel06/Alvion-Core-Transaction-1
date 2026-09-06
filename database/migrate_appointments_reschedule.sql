-- =====================================================
-- Migration: Appointments columns for Reschedule & Cancel
-- =====================================================
-- Adds cancelled_at, cancellation_reason, updated_by so the
-- Reschedule & Cancel page can cancel appointments and track who updated.
-- Safe to run multiple times (only adds missing columns).

SET @db = DATABASE();

-- cancelled_at (for cancel action)
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'cancelled_at');
SET @sql = IF(@col = 0, 'ALTER TABLE appointments ADD COLUMN cancelled_at timestamp NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- cancellation_reason (for cancel action)
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'cancellation_reason');
SET @sql = IF(@col = 0, 'ALTER TABLE appointments ADD COLUMN cancellation_reason text DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- updated_by (for reschedule/cancel audit)
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'updated_by');
SET @sql = IF(@col = 0, 'ALTER TABLE appointments ADD COLUMN updated_by int(11) DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
