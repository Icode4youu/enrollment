-- Migration v9: course change requests + ID-fee payment flag
-- Run once:  mysql -u root seait_enrollment < migrate_v9.sql

ALTER TABLE applicants
    ADD COLUMN is_course_change TINYINT(1) NOT NULL DEFAULT 0 AFTER request_reason;

CREATE TABLE IF NOT EXISTS course_requests (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  user_id          INT          NOT NULL,
  applicant_id     INT          NOT NULL,     -- the application being changed from
  current_course   VARCHAR(100) NOT NULL,
  requested_course VARCHAR(100) NOT NULL,
  reason           TEXT         NOT NULL,
  status           ENUM('pending','approved','declined','consumed') NOT NULL DEFAULT 'pending',
  remarks          VARCHAR(255) DEFAULT NULL, -- department note on the decision
  decided_by       INT          DEFAULT NULL, -- admin users.id
  decided_at       TIMESTAMP    NULL,
  new_applicant_id INT          DEFAULT NULL, -- the re-application it produced
  created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (user_id), KEY (applicant_id), KEY (status),
  FOREIGN KEY (user_id)      REFERENCES users(id)      ON DELETE CASCADE,
  FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
