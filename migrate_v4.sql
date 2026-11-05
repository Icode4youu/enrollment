-- ============================================================
-- Migration v4: site settings, announcements, password resets,
--               withdrawn application status
-- Run: mysql -u root seait_enrollment < migrate_v4.sql
-- ============================================================
USE seait_enrollment;

-- Site settings (key-value): enrollment period, school year, etc.
CREATE TABLE IF NOT EXISTS settings (
  `key`   VARCHAR(50) PRIMARY KEY,
  `value` VARCHAR(255) NOT NULL DEFAULT ''
);

INSERT INTO settings (`key`, `value`) VALUES
  ('enrollment_enabled', '1'),
  ('enrollment_open',    '2025-06-01'),
  ('enrollment_close',   '2026-11-30'),
  ('school_year',        '1st Semester S.Y. 2025-2026')
ON DUPLICATE KEY UPDATE `value` = `value`;

-- Students may withdraw an application
ALTER TABLE applicants MODIFY COLUMN status
  ENUM('pending','requirements','payment','blocking','submitted','verified','enrolled','rejected','withdrawn')
  NOT NULL DEFAULT 'pending';

-- Admin-posted announcements shown on the portal
CREATE TABLE IF NOT EXISTS announcements (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  title      VARCHAR(120) NOT NULL,
  body       TEXT         NOT NULL,
  is_active  TINYINT(1)   NOT NULL DEFAULT 1,
  created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- Password reset tokens (forgot password)
CREATE TABLE IF NOT EXISTS password_resets (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT          NOT NULL,
  token_hash CHAR(64)     NOT NULL,
  expires_at DATETIME     NOT NULL,
  used       TINYINT(1)   NOT NULL DEFAULT 0,
  created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_token (token_hash),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Speed up status filtering / payment ref lookups
ALTER TABLE applicants ADD INDEX idx_status (status);
ALTER TABLE payments   ADD INDEX idx_ref (ref_no);
