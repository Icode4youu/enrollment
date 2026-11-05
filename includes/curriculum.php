<?php
// ------------------------------------------------------------
// Curriculum / subject load
//
// The official subject list per course + year level + semester,
// transcribed from the BSIT prospectus. A student's "subject load"
// is the set of subjects for their course, year level and the
// current semester (taken from the school_year setting).
// ------------------------------------------------------------

// BSIT prospectus: [course, year_level, semester, code, description, lec, lab, prereq]
function curriculum_seed_rows() {
    $r = [];
    $add = function ($yr, $sem, $code, $desc, $lec, $lab, $pre = '') use (&$r) {
        $r[] = ['BSIT', $yr, $sem, $code, $desc, $lec, $lab, $pre];
    };

    // ---- First Year, First Semester ----
    $add(1, '1st', 'IT121',    'Introduction to Computing', 2, 1);
    $add(1, '1st', 'IT122B',   'Computer Programming 1: Fundamentals of Programming', 2, 1);
    $add(1, '1st', 'IT123',    'Web Development 1', 2, 1);
    $add(1, '1st', 'IT326B',   'Social and Professional Issues in Computing', 2, 1);
    $add(1, '1st', 'GE114',    'Mathematics in Modern World', 3, 0);
    $add(1, '1st', 'GE115',    'Purposive Communication', 3, 0);
    $add(1, '1st', 'NSTP111C', 'Civic Welfare Training Service 1', 3, 0);
    $add(1, '1st', 'PE1',      'Physical Activities Toward Health and Fitness 1 (PATHFIT 1): Movement Competency Training or MCT', 2, 0);

    // ---- First Year, Second Semester ----
    $add(1, '2nd', 'PE2',      'Physical Activities Toward Health and Fitness 2 (PATHFIT 2): Exercise-Based Fitness Activities', 2, 0, 'PE1');
    $add(1, '2nd', 'NSTP112B', 'Civic Welfare Training Service 2', 3, 0, 'NSTP111C');
    $add(1, '2nd', 'GEE3',     'Living in the IT Era', 3, 0);
    $add(1, '2nd', 'GE117',    'Science, Technology and Society', 3, 0);
    $add(1, '2nd', 'ITELEC1B', 'Object Oriented Programming', 2, 1, 'IT122B');
    $add(1, '2nd', 'IT223B',   'Fundamentals of Database Systems', 2, 1, 'IT122B');
    $add(1, '2nd', 'IT125',    'Introduction to Human Computer Interaction', 2, 1, 'IT122, IT122B');
    $add(1, '2nd', 'IT127B',   'Computer Programming 2: Intermediate Programming', 2, 1, 'IT122B');

    // ---- Second Year, First Semester ----
    $add(2, '1st', 'PE3',      'Physical Activities Toward Health and Fitness 3 (PATHFIT 3): Menu of Dance, Sports, Martial Arts, Group Exercise, Outdoor and Adventure Activities', 2, 0, 'PE2');
    $add(2, '1st', 'GE119',    'Life and Works of Rizal', 3, 0);
    $add(2, '1st', 'ITELEC2B', 'Mobile App Dev: Platform Technologies (Incl. Modeling and Simulation)', 2, 1, 'IT127B');
    $add(2, '1st', 'ITELEC3B', 'Web Development 2: Web System and Technologies', 2, 1, 'IT123, ITELEC1B');
    $add(2, '1st', 'IT225B',   'Networking 1', 2, 1);
    $add(2, '1st', 'IT224',    'Information Management 1', 2, 1, 'IT121');
    $add(2, '1st', 'IT221',    'Data Structures and Algorithm', 2, 1, 'IT127, IT127B');

    // ---- Second Year, Second Semester ----
    $add(2, '2nd', 'IT228',    'System Analysis and Design', 2, 1, 'IT221');
    $add(2, '2nd', 'PE4',      'Physical Activities Toward Health and Fitness 4 (PATHFIT 4): Menu of Dance, Sports, Martial Arts, Group Exercise, Outdoor and Adventure Activities', 2, 0, 'PE3');
    $add(2, '2nd', 'GE111',    'Understanding the Self', 3, 0);
    $add(2, '2nd', 'IT126',    'Discrete Mathematics', 3, 0);
    $add(2, '2nd', 'GEE1',     'Komunikasyon sa Akademikong Filipino', 3, 0);
    $add(2, '2nd', 'GE116',    'Ethics', 3, 0);
    $add(2, '2nd', 'IT227B',   "Application Dev't & Emerging Tech.", 3, 0);
    $add(2, '2nd', 'IT226',    'Integrative Programming and Technologies', 2, 1, 'ITELEC2B, ITELEC3B, IT224, IT225B, ITELEC1B');

    // ---- Third Year, First Semester ----
    $add(3, '1st', 'IT227',    'Quantitative Methods', 3, 0);
    $add(3, '1st', 'IT322',    'Networking 2', 2, 1, 'IT225B');
    $add(3, '1st', 'ACCTG111', 'Fundamentals of Accounting', 3, 0);
    $add(3, '1st', 'IT323',    'System Integration and Architecture 1', 2, 1, 'IT226');
    $add(3, '1st', 'IT321',    'Advanced Database System', 2, 1, 'IT224, IT223B');

    // ---- Third Year, Second Semester ----
    $add(3, '2nd', 'IT325',    'Information Assurance and Security 1', 2, 1, 'IT323');
    $add(3, '2nd', 'ITELEC4',  'Human Computer & Interaction 2', 2, 1, 'IT125');
    $add(3, '2nd', 'GE112',    'Readings in Philippine History', 3, 0);
    $add(3, '2nd', 'GEE2',     'Pagbasa at Pagsulat Tungo sa Pananaliksik', 3, 0, 'FIL111, GEE1');
    $add(3, '2nd', 'GE113',    'Contemporary World', 3, 0);

    // ---- Fourth Year, First Semester ----
    $add(4, '1st', 'IT400B',   'Practicum (486 Hours)', 6, 0, 'ITELEC4, IT325');
    $add(4, '1st', 'IT421',    'Information and Assurance Security 2', 2, 1, 'IT325');
    $add(4, '1st', 'IT411',    'Capstone Project and Research 1', 3, 0, 'IT325');

    // ---- Fourth Year, Second Semester ----
    $add(4, '2nd', 'GE118',    'Art Appreciation', 3, 0);
    $add(4, '2nd', 'IT324B',   'New Trends of Technology', 2, 1);
    $add(4, '2nd', 'IT412',    'Capstone Project and Research 2', 3, 0, 'IT411');
    $add(4, '2nd', 'IT423',    'System Administration & Maintenance', 2, 1, 'IT421');
    $add(4, '2nd', 'ITELEC5',  'System Integration and Architecture 2', 2, 1, 'IT323');

    return $r;
}

// Create the subjects table + year_level column and seed BSIT — idempotent,
// runs the equivalent of migrate_v10.sql on first use so nothing is manual.
function ensure_curriculum_schema() {
    static $done = false;
    if ($done) return;
    $done = true;
    $conn = db();

    if (!$conn->query("SHOW COLUMNS FROM applicants LIKE 'year_level'")->num_rows) {
        $conn->query("ALTER TABLE applicants ADD COLUMN year_level TINYINT NOT NULL DEFAULT 1 AFTER course");
    }
    if (!$conn->query("SHOW TABLES LIKE 'subjects'")->num_rows) {
        $conn->query(
            "CREATE TABLE subjects (
               id          INT AUTO_INCREMENT PRIMARY KEY,
               course      VARCHAR(20)  NOT NULL,
               year_level  TINYINT      NOT NULL,
               semester    ENUM('1st','2nd','Summer') NOT NULL,
               subject_code VARCHAR(20)  NOT NULL,
               description  VARCHAR(255) NOT NULL,
               lec_units   TINYINT NOT NULL DEFAULT 0,
               lab_units   TINYINT NOT NULL DEFAULT 0,
               prereq      VARCHAR(120) DEFAULT NULL
             )"
        );
        $ins = $conn->prepare(
            "INSERT INTO subjects (course, year_level, semester, subject_code, description, lec_units, lab_units, prereq)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        foreach (curriculum_seed_rows() as $r) {
            $ins->bind_param('sisssiis', $r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[6], $r[7]);
            $ins->execute();
        }
    }
}

// '1st' | '2nd' | 'Summer' from the school_year setting label
function current_semester() {
    if (preg_match('/(1st|2nd|summer)/i', sy_label(), $m)) {
        return strtoupper($m[1][0]) === 'S' ? 'Summer' : $m[1];
    }
    return '1st';
}

function year_level_label($n) {
    return (int)$n . ['', 'st', 'nd', 'rd', 'th'][(int)$n] . ' Year';
}

// The official subject load for a course + year level + current semester.
// Returns [] for courses/levels with no curriculum data yet.
function subject_load($course, $year_level, $semester = null) {
    ensure_curriculum_schema();
    $sem = $semester ?? current_semester();
    $stmt = db()->prepare(
        "SELECT * FROM subjects WHERE course=? AND year_level=? AND semester=?
         ORDER BY id"
    );
    $yl = (int)$year_level;
    $stmt->bind_param('sis', $course, $yl, $sem);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
