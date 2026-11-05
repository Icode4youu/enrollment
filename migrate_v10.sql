-- ============================================================
-- Migration v10: student year level + BSIT curriculum (subject loads)
-- Run: mysql -u root seait_enrollment < migrate_v10.sql
-- (The app also auto-applies this lazily via ensure_curriculum_schema(),
--  so running it by hand is optional.)
-- ============================================================
USE seait_enrollment;

-- Year level drives which subjects appear on a student's subject load
ALTER TABLE applicants
  ADD COLUMN IF NOT EXISTS year_level TINYINT NOT NULL DEFAULT 1 AFTER course;

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

-- BSIT curriculum (from the official prospectus). Skipped if already seeded.
INSERT INTO subjects (course, year_level, semester, subject_code, description, lec_units, lab_units, prereq)
SELECT * FROM (
  SELECT 'BSIT' c, 1 y, '1st' s, 'IT121'    code, 'Introduction to Computing' d, 2 lec, 1 lab, NULL pre UNION ALL
  SELECT 'BSIT', 1, '1st', 'IT122B',   'Computer Programming 1: Fundamentals of Programming', 2, 1, NULL UNION ALL
  SELECT 'BSIT', 1, '1st', 'IT123',    'Web Development 1', 2, 1, NULL UNION ALL
  SELECT 'BSIT', 1, '1st', 'IT326B',   'Social and Professional Issues in Computing', 2, 1, NULL UNION ALL
  SELECT 'BSIT', 1, '1st', 'GE114',    'Mathematics in Modern World', 3, 0, NULL UNION ALL
  SELECT 'BSIT', 1, '1st', 'GE115',    'Purposive Communication', 3, 0, NULL UNION ALL
  SELECT 'BSIT', 1, '1st', 'NSTP111C', 'Civic Welfare Training Service 1', 3, 0, NULL UNION ALL
  SELECT 'BSIT', 1, '1st', 'PE1',      'Physical Activities Toward Health and Fitness 1 (PATHFIT 1): Movement Competency Training or MCT', 2, 0, NULL UNION ALL
  SELECT 'BSIT', 1, '2nd', 'PE2',      'Physical Activities Toward Health and Fitness 2 (PATHFIT 2): Exercise-Based Fitness Activities', 2, 0, 'PE1' UNION ALL
  SELECT 'BSIT', 1, '2nd', 'NSTP112B', 'Civic Welfare Training Service 2', 3, 0, 'NSTP111C' UNION ALL
  SELECT 'BSIT', 1, '2nd', 'GEE3',     'Living in the IT Era', 3, 0, NULL UNION ALL
  SELECT 'BSIT', 1, '2nd', 'GE117',    'Science, Technology and Society', 3, 0, NULL UNION ALL
  SELECT 'BSIT', 1, '2nd', 'ITELEC1B', 'Object Oriented Programming', 2, 1, 'IT122B' UNION ALL
  SELECT 'BSIT', 1, '2nd', 'IT223B',   'Fundamentals of Database Systems', 2, 1, 'IT122B' UNION ALL
  SELECT 'BSIT', 1, '2nd', 'IT125',    'Introduction to Human Computer Interaction', 2, 1, 'IT122, IT122B' UNION ALL
  SELECT 'BSIT', 1, '2nd', 'IT127B',   'Computer Programming 2: Intermediate Programming', 2, 1, 'IT122B' UNION ALL
  SELECT 'BSIT', 2, '1st', 'PE3',      'Physical Activities Toward Health and Fitness 3 (PATHFIT 3): Menu of Dance, Sports, Martial Arts, Group Exercise, Outdoor and Adventure Activities', 2, 0, 'PE2' UNION ALL
  SELECT 'BSIT', 2, '1st', 'GE119',    'Life and Works of Rizal', 3, 0, NULL UNION ALL
  SELECT 'BSIT', 2, '1st', 'ITELEC2B', 'Mobile App Dev: Platform Technologies (Incl. Modeling and Simulation)', 2, 1, 'IT127B' UNION ALL
  SELECT 'BSIT', 2, '1st', 'ITELEC3B', 'Web Development 2: Web System and Technologies', 2, 1, 'IT123, ITELEC1B' UNION ALL
  SELECT 'BSIT', 2, '1st', 'IT225B',   'Networking 1', 2, 1, NULL UNION ALL
  SELECT 'BSIT', 2, '1st', 'IT224',    'Information Management 1', 2, 1, 'IT121' UNION ALL
  SELECT 'BSIT', 2, '1st', 'IT221',    'Data Structures and Algorithm', 2, 1, 'IT127, IT127B' UNION ALL
  SELECT 'BSIT', 2, '2nd', 'IT228',    'System Analysis and Design', 2, 1, 'IT221' UNION ALL
  SELECT 'BSIT', 2, '2nd', 'PE4',      'Physical Activities Toward Health and Fitness 4 (PATHFIT 4): Menu of Dance, Sports, Martial Arts, Group Exercise, Outdoor and Adventure Activities', 2, 0, 'PE3' UNION ALL
  SELECT 'BSIT', 2, '2nd', 'GE111',    'Understanding the Self', 3, 0, NULL UNION ALL
  SELECT 'BSIT', 2, '2nd', 'IT126',    'Discrete Mathematics', 3, 0, NULL UNION ALL
  SELECT 'BSIT', 2, '2nd', 'GEE1',     'Komunikasyon sa Akademikong Filipino', 3, 0, NULL UNION ALL
  SELECT 'BSIT', 2, '2nd', 'GE116',    'Ethics', 3, 0, NULL UNION ALL
  SELECT 'BSIT', 2, '2nd', 'IT227B',   'Application Dev''t & Emerging Tech.', 3, 0, NULL UNION ALL
  SELECT 'BSIT', 2, '2nd', 'IT226',    'Integrative Programming and Technologies', 2, 1, 'ITELEC2B, ITELEC3B, IT224, IT225B, ITELEC1B' UNION ALL
  SELECT 'BSIT', 3, '1st', 'IT227',    'Quantitative Methods', 3, 0, NULL UNION ALL
  SELECT 'BSIT', 3, '1st', 'IT322',    'Networking 2', 2, 1, 'IT225B' UNION ALL
  SELECT 'BSIT', 3, '1st', 'ACCTG111', 'Fundamentals of Accounting', 3, 0, NULL UNION ALL
  SELECT 'BSIT', 3, '1st', 'IT323',    'System Integration and Architecture 1', 2, 1, 'IT226' UNION ALL
  SELECT 'BSIT', 3, '1st', 'IT321',    'Advanced Database System', 2, 1, 'IT224, IT223B' UNION ALL
  SELECT 'BSIT', 3, '2nd', 'IT325',    'Information Assurance and Security 1', 2, 1, 'IT323' UNION ALL
  SELECT 'BSIT', 3, '2nd', 'ITELEC4',  'Human Computer & Interaction 2', 2, 1, 'IT125' UNION ALL
  SELECT 'BSIT', 3, '2nd', 'GE112',    'Readings in Philippine History', 3, 0, NULL UNION ALL
  SELECT 'BSIT', 3, '2nd', 'GEE2',     'Pagbasa at Pagsulat Tungo sa Pananaliksik', 3, 0, 'FIL111, GEE1' UNION ALL
  SELECT 'BSIT', 3, '2nd', 'GE113',    'Contemporary World', 3, 0, NULL UNION ALL
  SELECT 'BSIT', 4, '1st', 'IT400B',   'Practicum (486 Hours)', 6, 0, 'ITELEC4, IT325' UNION ALL
  SELECT 'BSIT', 4, '1st', 'IT421',    'Information and Assurance Security 2', 2, 1, 'IT325' UNION ALL
  SELECT 'BSIT', 4, '1st', 'IT411',    'Capstone Project and Research 1', 3, 0, 'IT325' UNION ALL
  SELECT 'BSIT', 4, '2nd', 'GE118',    'Art Appreciation', 3, 0, NULL UNION ALL
  SELECT 'BSIT', 4, '2nd', 'IT324B',   'New Trends of Technology', 2, 1, NULL UNION ALL
  SELECT 'BSIT', 4, '2nd', 'IT412',    'Capstone Project and Research 2', 3, 0, 'IT411' UNION ALL
  SELECT 'BSIT', 4, '2nd', 'IT423',    'System Administration & Maintenance', 2, 1, 'IT421' UNION ALL
  SELECT 'BSIT', 4, '2nd', 'ITELEC5',  'System Integration and Architecture 2', 2, 1, 'IT323'
) seed
WHERE NOT EXISTS (SELECT 1 FROM subjects LIMIT 1);
