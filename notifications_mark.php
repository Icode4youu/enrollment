<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo '{"ok":false}';
    exit;
}

$aid = (int)($_SESSION['applicant_id'] ?? 0);
if ($aid) {
    $r = db()->query("SELECT MAX(id) m FROM activity_log WHERE applicant_id=$aid")->fetch_assoc();
    $_SESSION['seen_activity'] = (int)($r['m'] ?? 0);
}
echo '{"ok":true}';
