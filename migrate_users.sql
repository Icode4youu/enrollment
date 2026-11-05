-- ============================================================
-- Migration: add student user accounts
-- Run this ONCE on your existing database (phpMyAdmin > SQL tab,
-- or: mysql -u root seait_enrollment < migrate_users.sql)
-- If you get "Duplicate column name 'user_id'", it is already applied.
-- ============================================================
USE seait_enrollment;

CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE applicants
  ADD COLUMN user_id INT DEFAULT NULL AFTER id,
  ADD KEY idx_applicants_user (user_id),
  ADD CONSTRAINT fk_applicants_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;
