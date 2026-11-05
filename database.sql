-- ============================================================
-- SEAIT Online Enrollment System - Database Schema
-- Import via phpMyAdmin or: mysql -u root < database.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS seait_enrollment
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE seait_enrollment;

-- ------------------------------------------------------------
-- Applicants (both new/transferee and old students)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS applicants (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT          DEFAULT NULL,
  applicant_type ENUM('new','old') NOT NULL,
  category      VARCHAR(60)  DEFAULT NULL,     -- Grade 11, New College Student, etc.
  course        VARCHAR(100) NOT NULL,
  year_level    TINYINT      NOT NULL DEFAULT 1,
  last_name     VARCHAR(80)  NOT NULL,
  first_name    VARCHAR(80)  NOT NULL,
  middle_name   VARCHAR(80)  DEFAULT NULL,
  ext_name      VARCHAR(20)  DEFAULT NULL,
  sex           ENUM('Male','Female') NOT NULL,
  dob           DATE         NOT NULL,
  pob           VARCHAR(150) NOT NULL,
  civil_status  VARCHAR(30)  NOT NULL,
  email         VARCHAR(120) DEFAULT NULL,
  phone         VARCHAR(30)  DEFAULT NULL,
  status        ENUM('pending','requirements','payment','blocking','submitted','verified','enrolled','rejected')
                NOT NULL DEFAULT 'pending',
  current_step  TINYINT      NOT NULL DEFAULT 0,
  block_id      INT          DEFAULT NULL,
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- Uploaded requirement documents
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS documents (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  applicant_id INT NOT NULL,
  doc_type     VARCHAR(40) NOT NULL,   -- psa, report_card, picture_2x2, school_id, clearance, medical_form, receipt
  file_name    VARCHAR(255) NOT NULL,
  file_path    VARCHAR(255) NOT NULL,
  status       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  remarks      VARCHAR(255) DEFAULT NULL,
  uploaded_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE
);

-- ------------------------------------------------------------
-- Blocks (sections) - 40 students each
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS blocks (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  name      VARCHAR(40) NOT NULL,
  course    VARCHAR(100) NOT NULL DEFAULT 'BSIT',
  capacity  INT NOT NULL DEFAULT 40
);

-- ------------------------------------------------------------
-- Teachers (for schedule assignment + conflict checking)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS teachers (
  id   INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE
);

-- ------------------------------------------------------------
-- Class schedule per block
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS schedules (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  block_id    INT NOT NULL,
  day_of_week ENUM('M','T','W','TH','F','S') NOT NULL,
  time_start  TIME NOT NULL,
  time_end    TIME NOT NULL,
  subject     VARCHAR(80) NOT NULL,
  instructor  VARCHAR(80) DEFAULT NULL,
  room        VARCHAR(40) DEFAULT NULL,
  FOREIGN KEY (block_id) REFERENCES blocks(id) ON DELETE CASCADE
);

-- ------------------------------------------------------------
-- Curriculum subjects (per course + year level + semester) — used
-- for the student subject load. Seeded by migrate_v10.sql / the app.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS subjects (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  course       VARCHAR(20)  NOT NULL,
  year_level   TINYINT      NOT NULL,
  semester     ENUM('1st','2nd','Summer') NOT NULL,
  subject_code VARCHAR(20)  NOT NULL,
  description  VARCHAR(255) NOT NULL,
  lec_units    TINYINT NOT NULL DEFAULT 0,
  lab_units    TINYINT NOT NULL DEFAULT 0,
  prereq       VARCHAR(120) DEFAULT NULL
);

-- ------------------------------------------------------------
-- Payments (GCash receipts)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payments (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  applicant_id INT NOT NULL,
  amount       DECIMAL(10,2) NOT NULL DEFAULT 500.00,
  receipt_doc  INT DEFAULT NULL,
  status       ENUM('pending','confirmed','rejected') NOT NULL DEFAULT 'pending',
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE
);

-- ------------------------------------------------------------
-- Portal user accounts (students + staff, role-based)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('student','admin') NOT NULL DEFAULT 'student',
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE applicants
  ADD CONSTRAINT fk_applicants_user
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

-- ------------------------------------------------------------
-- Admin users
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(100) DEFAULT NULL
);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Default admin: username "admin" / password "admin123"
-- (bcrypt hash of 'admin123')
INSERT INTO admins (username, password_hash, full_name) VALUES
('admin', '$2y$10$HhgaUwal.X0KbevcIhxGc.QNSv7dfRRL1CtqXQChoi9nDdY3RnFmm', 'Registrar Admin');

-- Portal admin account: admin@gmail.com / admin123
INSERT INTO users (full_name, email, password_hash, role) VALUES
('Registrar Admin', 'admin@gmail.com', '$2y$12$0Ngk2pTCjDWSU5fjhLYWKO61e9jeRUV9SS7miZcXNm2asRJYLNXna', 'admin');

-- Blocks
INSERT INTO blocks (name, course, capacity) VALUES
('Block 1', 'BSIT', 40),
('Block 2', 'BSIT', 40),
('Block 3', 'BSIT', 40),
('Block 4', 'BSIT', 40),
('Block 5', 'BSIT', 40),
('Block 6', 'BSIT', 40);

-- Teachers
INSERT INTO teachers (name) VALUES
('LUCTAWA'), ('DUMAN-AG'), ('MAVILA'), ('JUKILA'), ('SONGCO'),
('SANTOS'), ('REYES'), ('GARCIA'), ('VILLANUEVA'),
('DELA CRUZ'), ('FLORES'), ('RAMOS'), ('TORRES');

-- Sample schedules — 3 different patterns so blocks have different timetables
-- (Block 1&4 = early-morning, Block 2&5 = mid-morning, Block 3&6 = afternoon)
INSERT INTO schedules (block_id, day_of_week, time_start, time_end, subject, instructor, room)
SELECT b.id, d.day_of_week, d.time_start, d.time_end, d.subject, d.instructor, d.room
FROM blocks b
JOIN (
  -- Pattern 1: early-morning (IT heavy)
  SELECT 1 AS pat, 'M'  AS day_of_week, '07:00' AS time_start, '08:00' AS time_end, 'IT 125'  AS subject, 'LUCTAWA'  AS instructor, '3064 CLS' AS room
  UNION ALL SELECT 1, 'T',  '07:00', '08:00', 'IT 125',  'LUCTAWA',  '3064 CLS'
  UNION ALL SELECT 1, 'W',  '07:00', '08:30', 'IT 125',  'DUMAN-AG', '3064 LEC'
  UNION ALL SELECT 1, 'TH', '07:00', '08:30', 'IT 225',  'DUMAN-AG', '3064 LEC'
  UNION ALL SELECT 1, 'F',  '07:00', '08:30', 'IT 225',  'DUMAN-AG', '3064 LEC'
  UNION ALL SELECT 1, 'T',  '09:30', '10:30', 'IT 103',  'LUCTAWA',  '3064 CLS'
  UNION ALL SELECT 1, 'W',  '09:30', '10:30', 'IT 103',  'LUCTAWA',  '3064 LEC'
  UNION ALL SELECT 1, 'TH', '09:30', '10:30', 'IT 103',  'LUCTAWA',  '3064 LEC'
  UNION ALL SELECT 1, 'T',  '10:30', '12:30', 'IST 417', 'MAVILA',   '3064 LEC'
  UNION ALL SELECT 1, 'W',  '10:30', '12:30', 'IST 417', 'MAVILA',   '3064 LEC'
  UNION ALL SELECT 1, 'TH', '10:30', '12:30', 'IST 417', 'MAVILA',   '3064 LEC'
  UNION ALL SELECT 1, 'F',  '10:30', '12:30', 'IST 417', 'MAVILA',   '3064 LEC'
  UNION ALL SELECT 1, 'W',  '12:30', '14:00', 'IT 103',  'JUKILA',   '3064 LEC'
  UNION ALL SELECT 1, 'TH', '12:30', '14:00', 'IT 103',  'JUKILA',   '3064 CLS'
  UNION ALL SELECT 1, 'F',  '12:30', '14:00', 'MST 410', 'JUKILA',   '410'
  UNION ALL SELECT 1, 'T',  '14:00', '15:30', 'IT 103',  'JUKILA',   '3064 CLS'
  UNION ALL SELECT 1, 'F',  '14:00', '15:30', 'MST 410', 'JUKILA',   '410'
  UNION ALL SELECT 1, 'T',  '15:30', '17:30', 'MST 410', 'SONGCO',   '410'
  UNION ALL SELECT 1, 'W',  '15:30', '17:30', 'MST 410', 'SONGCO',   '410'
  UNION ALL SELECT 1, 'TH', '15:30', '17:30', 'MST 410', 'SONGCO',   '410'
  -- Pattern 2: mid-morning (GEC/PE mix)
  UNION ALL SELECT 2, 'M',  '08:00', '09:30', 'GEC 101', 'SANTOS',     '3021 LEC'
  UNION ALL SELECT 2, 'W',  '08:00', '09:30', 'GEC 101', 'SANTOS',     '3021 LEC'
  UNION ALL SELECT 2, 'F',  '08:00', '09:30', 'GEC 101', 'SANTOS',     '3021 LEC'
  UNION ALL SELECT 2, 'T',  '08:00', '09:30', 'IT 224',  'REYES',      '3021 CLS'
  UNION ALL SELECT 2, 'TH', '08:00', '09:30', 'IT 224',  'REYES',      '3021 CLS'
  UNION ALL SELECT 2, 'M',  '09:30', '11:00', 'PE 101',  'GARCIA',     'GYM'
  UNION ALL SELECT 2, 'W',  '09:30', '11:00', 'PE 101',  'GARCIA',     'GYM'
  UNION ALL SELECT 2, 'T',  '10:30', '12:00', 'IT 310',  'VILLANUEVA', '3021 LEC'
  UNION ALL SELECT 2, 'TH', '10:30', '12:00', 'IT 310',  'VILLANUEVA', '3021 LEC'
  UNION ALL SELECT 2, 'M',  '13:00', '14:30', 'NSTP 101','SANTOS',     'AVR'
  UNION ALL SELECT 2, 'F',  '13:00', '14:30', 'NSTP 101','SANTOS',     'AVR'
  UNION ALL SELECT 2, 'T',  '13:00', '15:00', 'MST 411', 'VILLANUEVA', '3021 CLS'
  UNION ALL SELECT 2, 'W',  '13:00', '15:00', 'MST 411', 'VILLANUEVA', '3021 CLS'
  -- Pattern 3: afternoon (CAP/major subjects)
  UNION ALL SELECT 3, 'M',  '13:00', '14:30', 'IT 326',  'DELA CRUZ',  '3064 LEC'
  UNION ALL SELECT 3, 'W',  '13:00', '14:30', 'IT 326',  'DELA CRUZ',  '3064 LEC'
  UNION ALL SELECT 3, 'F',  '13:00', '14:30', 'IT 326',  'DELA CRUZ',  '3064 LEC'
  UNION ALL SELECT 3, 'T',  '13:30', '15:00', 'GEC 102', 'FLORES',     '3022 LEC'
  UNION ALL SELECT 3, 'TH', '13:30', '15:00', 'GEC 102', 'FLORES',     '3022 LEC'
  UNION ALL SELECT 3, 'M',  '14:30', '16:00', 'CAP 101', 'RAMOS',      '3064 CLS'
  UNION ALL SELECT 3, 'W',  '14:30', '16:00', 'CAP 101', 'RAMOS',      '3064 CLS'
  UNION ALL SELECT 3, 'T',  '15:00', '16:30', 'MST 412', 'TORRES',     '3064 CLS'
  UNION ALL SELECT 3, 'TH', '15:00', '16:30', 'MST 412', 'TORRES',     '3064 CLS'
  UNION ALL SELECT 3, 'M',  '16:00', '17:30', 'PE 102',  'FLORES',     'GYM'
  UNION ALL SELECT 3, 'F',  '15:00', '16:30', 'PE 102',  'FLORES',     'GYM'
  UNION ALL SELECT 3, 'W',  '16:00', '17:30', 'RIZAL',   'DELA CRUZ',  '3022 LEC'
) d ON d.pat = MOD(b.id - 1, 3) + 1;
