-- migrate_v13.sql — enrollment cap: max students allowed to be 'enrolled'
-- 2000 = default cap; 0/blank = unlimited. Editable in Admin > Settings.
INSERT INTO settings (`key`, `value`) VALUES ('enrollment_cap', '2000')
ON DUPLICATE KEY UPDATE `value` = '2000';
