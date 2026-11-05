-- ============================================================
-- Migration v6: reason for block-change requests
-- Run: mysql -u root seait_enrollment < migrate_v6.sql
-- ============================================================
USE seait_enrollment;

ALTER TABLE applicants
  ADD COLUMN request_reason VARCHAR(255) DEFAULT NULL AFTER requested_block_id;
