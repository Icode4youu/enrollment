-- ============================================================
-- Migration v8: School ID step (digital signature + emergency
-- contact printed on the student ID card)
-- Run: mysql -u root seait_enrollment < migrate_v8.sql
-- ============================================================
USE seait_enrollment;

CREATE TABLE IF NOT EXISTS student_ids (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  applicant_id      INT NOT NULL,
  student_no        VARCHAR(30)  NOT NULL,
  emergency_name    VARCHAR(120) NOT NULL,
  emergency_address VARCHAR(180) NOT NULL,
  emergency_phone   VARCHAR(30)  NOT NULL,
  blood_type        VARCHAR(10)  DEFAULT NULL,
  signature_path    VARCHAR(255) NOT NULL,
  submitted_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE
);
