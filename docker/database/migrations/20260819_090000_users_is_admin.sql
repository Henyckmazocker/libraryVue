-- Migration: 20260819_090000_users_is_admin
-- Description: Add users.is_admin so administrator permission is data, not a hardcoded email

-- 1. Admin flag (IF NOT EXISTS via INFORMATION_SCHEMA — MySQL 8.0 compatible)
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_admin'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0',
  'SELECT ''is_admin column already exists'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Seed the current administrator. Guarantees there is no window without an admin and no
--    manual data-migration step. This is the last time this address appears in the repository,
--    and it appears in a migration — a historical record of a one-off fact — rather than in
--    code that runs on every request.
UPDATE users SET is_admin = 1 WHERE email = 'david.carvajal.abellan@gmail.com';
