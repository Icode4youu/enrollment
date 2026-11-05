-- ============================================================
-- Migration v5: student-requested block (pending admin approval)
-- Run: mysql -u root seait_enrollment < migrate_v5.sql
-- ============================================================
USE seait_enrollment;

ALTER TABLE applicants
  ADD COLUMN requested_block_id INT DEFAULT NULL AFTER block_id,
  ADD KEY idx_req_block (requested_block_id),
  ADD CONSTRAINT fk_applicants_req_block
    FOREIGN KEY (requested_block_id) REFERENCES blocks(id) ON DELETE SET NULL;
