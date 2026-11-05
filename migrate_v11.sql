-- migrate_v11.sql — index + FK on applicants.block_id
-- Pre-check: run this first, it MUST return 0 rows before applying the ALTER
--   SELECT a.id FROM applicants a LEFT JOIN blocks b ON b.id=a.block_id
--    WHERE a.block_id IS NOT NULL AND b.id IS NULL;
-- (verified clean on 2026-11-04 — 0 orphans)

USE seait_enrollment;

ALTER TABLE applicants
  ADD INDEX idx_block (block_id),
  ADD CONSTRAINT fk_app_block FOREIGN KEY (block_id)
    REFERENCES blocks(id) ON DELETE SET NULL;
