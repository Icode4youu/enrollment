<?php
require_once __DIR__ . '/../config.php';

function require_admin() {
    if (empty($_SESSION['admin_id'])) redirect('admin/index.php');
}

function admin_name() { return $_SESSION['admin_name'] ?? 'Admin'; }
function admin_role() { return $_SESSION['admin_role'] ?? 'admin'; }

// admin can do anything; office roles are limited to their own function
function admin_can(...$offices) {
    $r = admin_role();
    return $r === 'admin' || in_array($r, $offices, true);
}

// Gate an entire page to specific offices (admin always allowed)
function require_office(...$offices) {
    require_admin();
    if (!admin_can(...$offices)) {
        http_response_code(403);
        exit('Access denied — this page belongs to a different office.');
    }
}

// Which office reviews a given document type
function doc_office($doc_type) {
    if ($doc_type === 'medical_form') return 'medical';
    if ($doc_type === 'receipt')      return 'accounting';
    if ($doc_type === 'id_photo')     return 'department';
    return 'registrar';
}
function doc_office_ok($doc_type) { return admin_can(doc_office($doc_type)); }
