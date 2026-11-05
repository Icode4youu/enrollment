-- ============================================================
-- Migration v3: GCash reference number + activity log
-- Run: mysql -u root seait_enrollment < migrate_v3.sql
-- ============================================================
USE seait_enrollment;

ALTER TABLE payments ADD COLUMN ref_no VARCHAR(60) DEFAULT NULL AFTER amount;
ALTER TABLE payments ADD COLUMN remarks VARCHAR(255) DEFAULT NULL AFTER status;

CREATE TABLE IF NOT EXISTS activity_log (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  applicant_id INT NOT NULL,
  message      VARCHAR(255) NOT NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE
);

-- Priority numbers are no longer used (fully online enrollment)
ALTER TABLE applicants DROP COLUMN priority_no;
