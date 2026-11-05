-- Migration: teachers table + different schedule per block
-- Run: mysql -h 127.0.0.1 --skip-ssl -u root seait_enrollment < migrate_schedules_v2.sql

CREATE TABLE IF NOT EXISTS teachers (
  id   INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE
);

INSERT IGNORE INTO teachers (name) VALUES
('LUCTAWA'), ('DUMAN-AG'), ('MAVILA'), ('JUKILA'), ('SONGCO'),
('SANTOS'), ('REYES'), ('GARCIA'), ('VILLANUEVA'),
('DELA CRUZ'), ('FLORES'), ('RAMOS'), ('TORRES');

-- Reseed: 3 different patterns so blocks have different timetables
DELETE FROM schedules;

INSERT INTO schedules (block_id, day_of_week, time_start, time_end, subject, instructor, room)
SELECT b.id, d.day_of_week, d.time_start, d.time_end, d.subject, d.instructor, d.room
FROM blocks b
JOIN (
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
