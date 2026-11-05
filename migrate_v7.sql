-- ============================================================
-- Migration v7: office-based staff roles + seeded staff accounts
-- Run: mysql -u root seait_enrollment < migrate_v7.sql
-- ============================================================
USE seait_enrollment;

ALTER TABLE users
  MODIFY role ENUM('student','registrar','medical','accounting','department','admin')
  NOT NULL DEFAULT 'student';

-- Staff accounts — password for all: staff123 (change after demo)
INSERT INTO users (full_name, email, password_hash, role) VALUES
('Registrar Staff',   'registrar@seait.edu',   '$2y$12$T//PwZtHuVNdgOJ.R00jGu.1Pcf6TF5y5aLA00SXxH0ksChTHsjy2', 'registrar'),
('Clinic Nurse',      'medical@seait.edu',     '$2y$12$T//PwZtHuVNdgOJ.R00jGu.1Pcf6TF5y5aLA00SXxH0ksChTHsjy2', 'medical'),
('Accounting Staff',  'accounting@seait.edu',  '$2y$12$T//PwZtHuVNdgOJ.R00jGu.1Pcf6TF5y5aLA00SXxH0ksChTHsjy2', 'accounting'),
('Department Head',   'department@seait.edu',  '$2y$12$T//PwZtHuVNdgOJ.R00jGu.1Pcf6TF5y5aLA00SXxH0ksChTHsjy2', 'department')
ON DUPLICATE KEY UPDATE role = VALUES(role);
