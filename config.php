<?php
// ============================================================
// SEAIT Online Enrollment System - Configuration
// ============================================================

define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');                 // XAMPP default has no password
define('DB_NAME', 'seait_enrollment');

define('BASE_URL', '/enrollment');     // change if folder name differs
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_UPLOAD', 5 * 1024 * 1024); // 5 MB
define('ALLOWED_EXT', ['jpg', 'jpeg', 'png', 'pdf']);

define('ENROLLMENT_FEE', 500.00);      // PHP 500 shown in screenshots
define('ID_FEE', 350.00);              // fee for an approved course-change re-application

// EMVCo payload decoded from the school's static GCash QR (assets/img/gcash-qr.png).
// The payment step injects the amount tag into this so scanning the QR in the
// GCash app pre-fills the exact fee. Leave empty to show the static image instead.
define('GCASH_QR_PAYLOAD', '00020101021127830012com.p2pqrpay0111GXCHPHM2XXX02089996440303152170200000006560417DWQM4TK3JDNWLZNLE5204601653036085802PH5908RE**A T.6009Poblacion61041234630463F9');

date_default_timezone_set('Asia/Manila');
session_start();

require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/gcash_qr.php';
require_once __DIR__ . '/includes/curriculum.php';

// ------------------------------------------------------------
// Database connection (mysqli)
// ------------------------------------------------------------
function db() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die('Database connection failed: ' . $conn->connect_error .
                ' — is MySQL running in XAMPP?');
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

// ------------------------------------------------------------
// Helpers
// ------------------------------------------------------------
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function redirect($path) {
    header('Location: ' . BASE_URL . '/' . ltrim($path, '/'));
    exit;
}

function flash($key, $val = null) {
    if ($val === null) {
        $v = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $v;
    }
    $_SESSION['flash'][$key] = $val;
}

function current_applicant() {
    if (empty($_SESSION['applicant_id'])) return null;
    $id = (int)$_SESSION['applicant_id'];
    $res = db()->query("SELECT * FROM applicants WHERE id = $id");
    return $res ? $res->fetch_assoc() : null;
}

function current_user() {
    if (empty($_SESSION['user_id'])) return null;
    $id = (int)$_SESSION['user_id'];
    $res = db()->query("SELECT * FROM users WHERE id = $id");
    return $res ? $res->fetch_assoc() : null;
}

function require_user() {
    if (empty($_SESSION['user_id'])) redirect('login.php');
}

// Set the login session and resume the user's latest application, if any
function login_user($u) {
    $_SESSION['user_id']   = $u['id'];
    $_SESSION['user_name'] = $u['full_name'] ?: $u['email'];
    $res = db()->query(
        "SELECT id FROM applicants WHERE user_id=" . (int)$u['id'] . " ORDER BY id DESC LIMIT 1"
    );
    if ($row = $res->fetch_assoc()) $_SESSION['applicant_id'] = $row['id'];
}

// Save an uploaded requirement file; returns document id or null
function save_document($applicant_id, $doc_type, $file_input) {
    if (empty($_FILES[$file_input]) || $_FILES[$file_input]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$file_input];
    if ($f['error'] !== UPLOAD_ERR_OK)            return ['error' => 'Upload failed. Please try again.'];
    if ($f['size'] > MAX_UPLOAD)                  return ['error' => 'File too large (max 5 MB).'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXT))             return ['error' => 'Only JPG, PNG or PDF files are allowed.'];

    $dir = UPLOAD_DIR . $applicant_id . '/';
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    $safe = $doc_type . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . $safe)) {
        return ['error' => 'Could not save the file.'];
    }

    $stmt = db()->prepare(
        "INSERT INTO documents (applicant_id, doc_type, file_name, file_path)
         VALUES (?, ?, ?, ?)"
    );
    $rel = 'uploads/' . $applicant_id . '/' . $safe;
    $stmt->bind_param('isss', $applicant_id, $doc_type, $f['name'], $rel);
    $stmt->execute();
    // A fresh upload on an already-decided application sends it back to review
    db()->query("UPDATE applicants SET status='submitted' WHERE id=" . (int)$applicant_id .
                " AND status IN ('verified','enrolled')");
    log_activity($applicant_id, 'Uploaded ' . doc_label($doc_type));
    return $stmt->insert_id;
}

// Human-readable label for a document type
function doc_label($doc_type) {
    static $labels = [
        'psa' => 'PSA Birth Certificate', 'report_card' => 'Report Card',
        'picture_2x2' => '2x2 Picture', 'school_id' => 'School ID',
        'clearance' => 'Clearance', 'medical_form' => 'Medical Form',
        'receipt' => 'Payment Receipt', 'id_photo' => 'ID Photo',
    ];
    return $labels[$doc_type] ?? ucwords(str_replace('_', ' ', $doc_type));
}

// Record an event on the applicant's activity timeline
function log_activity($applicant_id, $message) {
    $stmt = db()->prepare("INSERT INTO activity_log (applicant_id, message) VALUES (?, ?)");
    $stmt->bind_param('is', $applicant_id, $message);
    $stmt->execute();
}

// Official course offerings — single source for all course dropdowns
function course_list() {
    return ['BSIT','BSCrim','BEEd','BSEd','BSHM','BSBA','BSTM'];
}

// Which document types each applicant type must submit online
function required_docs($applicant_type) {
    if ($applicant_type === 'new') {
        return ['psa' => 'PSA Birth Certificate', 'report_card' => 'Report Card / Old Card',
                'picture_2x2' => '2x2 Picture', 'medical_form' => 'Medical Form'];
    }
    return ['school_id' => 'School ID', 'clearance' => 'Clearance',
            'medical_form' => 'Medical Form'];
}

// Latest status of one document type (null when never uploaded)
function doc_status($applicant_id, $doc_type) {
    $row = db()->query(
        "SELECT status FROM documents WHERE applicant_id=" . (int)$applicant_id .
        " AND doc_type='" . db()->real_escape_string($doc_type) . "' ORDER BY id DESC LIMIT 1"
    )->fetch_assoc();
    return $row['status'] ?? null;
}

// Latest school-ID submission (student no, emergency contact, digital signature)
function id_card($applicant_id) {
    static $hasTable = null;
    if ($hasTable === null) {
        $hasTable = (bool)db()->query("SHOW TABLES LIKE 'student_ids'")->num_rows;
    }
    if (!$hasTable) return null;
    return db()->query(
        "SELECT * FROM student_ids WHERE applicant_id=" . (int)$applicant_id .
        " ORDER BY id DESC LIMIT 1"
    )->fetch_assoc();
}

// Best available ID photo: dedicated ID upload, else 2x2 picture, else school ID scan
function id_photo_src($applicant_id) {
    $rows = db()->query(
        "SELECT doc_type, file_path, file_name, status FROM documents
         WHERE applicant_id=" . (int)$applicant_id . "
           AND doc_type IN ('id_photo','picture_2x2','school_id')
         ORDER BY id DESC"
    )->fetch_all(MYSQLI_ASSOC);
    $best = [];
    foreach ($rows as $r) {
        if (isset($best[$r['doc_type']]) || $r['status'] === 'rejected') continue;
        if (!in_array(strtolower(pathinfo($r['file_name'], PATHINFO_EXTENSION)), ['jpg','jpeg','png'])) continue;
        $best[$r['doc_type']] = $r['file_path'];
    }
    foreach (['id_photo','picture_2x2','school_id'] as $t) {
        if (isset($best[$t])) return $best[$t];
    }
    return null;
}

// Follow the course-change request chain back to the original application id
// (a student may change course more than once: 15 -> 25 -> 32)
function original_application_id($app_id) {
    static $hasTable = null;
    if ($hasTable === null) {
        $hasTable = (bool)db()->query("SHOW TABLES LIKE 'course_requests'")->num_rows;
    }
    $id = (int)$app_id;
    if (!$hasTable) return $id;
    for ($i = 0; $i < 6; $i++) {
        $prev = db()->query(
            "SELECT applicant_id FROM course_requests WHERE new_applicant_id=$id LIMIT 1"
        )->fetch_assoc();
        if (!$prev) break;
        $id = (int)$prev['applicant_id'];
    }
    return $id;
}

// Follow the course-change chain FORWARD to the student's newest application
// — the one their portal actually shows (e.g. 15 -> 25 -> 32 returns 32)
function latest_application_id($app_id) {
    static $hasTable = null;
    if ($hasTable === null) {
        $hasTable = (bool)db()->query("SHOW TABLES LIKE 'course_requests'")->num_rows;
    }
    $id = (int)$app_id;
    if (!$hasTable) return $id;
    for ($i = 0; $i < 6; $i++) {
        $next = db()->query(
            "SELECT new_applicant_id FROM course_requests
             WHERE applicant_id=$id AND new_applicant_id IS NOT NULL LIMIT 1"
        )->fetch_assoc();
        if (!$next) break;
        $id = (int)$next['new_applicant_id'];
    }
    return $id;
}

// SQL predicate: TRUE when this application was superseded by a course-change
// successor — the portal only ever shows the student's newest application
function superseded_sql($alias = 'a') {
    static $hasTable = null;
    if ($hasTable === null) {
        $hasTable = (bool)db()->query("SHOW TABLES LIKE 'course_requests'")->num_rows;
    }
    return $hasTable
        ? "$alias.id IN (SELECT applicant_id FROM course_requests WHERE new_applicant_id IS NOT NULL)"
        : "0";
}

// Latest course-change request by this user in a given status set
function course_request_for($user_id, $statuses = ['pending', 'approved']) {
    static $hasTable = null;
    if ($hasTable === null) {
        $hasTable = (bool)db()->query("SHOW TABLES LIKE 'course_requests'")->num_rows;
    }
    if (!$hasTable) return null;
    $in = "'" . implode("','", array_map('strval', (array)$statuses)) . "'";
    return db()->query(
        "SELECT * FROM course_requests WHERE user_id=" . (int)$user_id .
        " AND status IN ($in) ORDER BY id DESC LIMIT 1"
    )->fetch_assoc();
}

// First enrollment step that still needs action; returns [url, step number]
function next_enrollment_step($app) {
    $id = (int)$app['id'];
    foreach (array_keys(required_docs($app['applicant_type'])) as $t) {
        $s = doc_status($id, $t);
        if (!$s || $s === 'rejected') {
            return $t === 'medical_form' ? ['step_medical.php', 2] : ['step_registrar.php', 1];
        }
    }
    $p = db()->query(
        "SELECT status FROM payments WHERE applicant_id=$id ORDER BY id DESC LIMIT 1"
    )->fetch_assoc();
    if (!$p || $p['status'] === 'rejected') return ['step_accounting.php', 3];
    if (!id_card($id)) return ['step_id.php', 4];
    if (!$app['block_id']) return ['step_blocks.php', 5];
    return ['dashboard.php', (int)$app['current_step']];
}

// ------------------------------------------------------------
// Site settings (key-value `settings` table)
// ------------------------------------------------------------
function setting($key, $default = '') {
    $k = db()->real_escape_string($key);
    $res = db()->query("SELECT `value` FROM settings WHERE `key`='$k'");
    $row = $res ? $res->fetch_assoc() : null;
    return $row ? $row['value'] : $default;
}

function set_setting($key, $value) {
    $stmt = db()->prepare(
        "INSERT INTO settings (`key`, `value`) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
    );
    $stmt->bind_param('ss', $key, $value);
    $stmt->execute();
}

// School-year / semester label shown across the portal
function sy_label() {
    return setting('school_year', '1st Semester S.Y. 2025-2026');
}

// Is enrollment currently accepting new applications?
function enrollment_is_open() {
    if (setting('enrollment_enabled', '1') === '0') return false;
    $today = date('Y-m-d');
    $open  = setting('enrollment_open');
    $close = setting('enrollment_close');
    if ($open  && $today < $open)  return false;
    if ($close && $today > $close) return false;
    return true;
}

// Active announcements for the student-facing pages
function active_announcements() {
    $res = db()->query("SELECT * FROM announcements WHERE is_active=1 ORDER BY id DESC LIMIT 5");
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

// ------------------------------------------------------------
// SMS notifications via iProgSMS — best-effort, never blocks the page
// ------------------------------------------------------------
define('SMS_API_KEY', '7eb31a75ea0fc4789752c90262e6ab0184b6cee5');
define('SMS_SENDER', 'SEAIT');

function send_sms($phone, $message) {
    // Normalize to 639XXXXXXXXX (iProgSMS PH format)
    $p = preg_replace('/\D/', '', (string)$phone);
    if (str_starts_with($p, '0'))  $p = '63' . substr($p, 1);
    if (!str_starts_with($p, '63')) $p = '63' . $p;
    if (!preg_match('/^639\d{9}$/', $p)) return false;

    $ch = curl_init('https://www.iprogsms.com/api/v1/sms_messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_POSTFIELDS     => http_build_query([
            'api_token'    => SMS_API_KEY,
            'phone_number' => $p,
            'message'      => $message,
            'sender_name'  => SMS_SENDER,
        ]),
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $res !== false && $code < 400;
}

// Look up the applicant's registered phone and text them — no-op if none
function sms_applicant($applicant_id, $message) {
    $phone = db()->query(
        "SELECT phone FROM applicants WHERE id=" . (int)$applicant_id
    )->fetch_assoc()['phone'] ?? '';
    if ($phone !== '') send_sms($phone, $message);
}

// Statuses that count as a live application (blocks filing a new one)
function active_app_statuses() {
    return "('pending','requirements','payment','blocking','submitted','verified','enrolled')";
}
