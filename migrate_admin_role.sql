-- Migration: add role column + create portal admin account
-- Run: mysql -h 127.0.0.1 --skip-ssl -u root seait_enrollment < migrate_admin_role.sql
ALTER TABLE users ADD COLUMN IF NOT EXISTS role ENUM('student','admin') NOT NULL DEFAULT 'student' AFTER password_hash;

-- admin@gmail.com / admin123
INSERT INTO users (full_name, email, password_hash, role) VALUES
('Registrar Admin', 'admin@gmail.com', '$2y$12$0Ngk2pTCjDWSU5fjhLYWKO61e9jeRUV9SS7miZcXNm2asRJYLNXna', 'admin');
