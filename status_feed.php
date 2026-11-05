<?php
// Lightweight JSON snapshot of the student's application state —
// dashboard.php polls this to refresh the page when anything changes.
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, must-revalidate');

if (!isset($_SESSION['user_id'])) { http_response_code(401); echo '{}'; exit; }
$app = current_applicant();
if (!$app) { echo '{"app":null}'; exit; }
$id = (int)$app['id'];

$docs = db()->query(
    "SELECT d.doc_type, d.status FROM documents d
     JOIN (SELECT doc_type, MAX(id) mid FROM documents WHERE applicant_id=$id GROUP BY doc_type) x
       ON d.id = x.mid"
)->fetch_all(MYSQLI_ASSOC);

$payment  = db()->query("SELECT status, amount FROM payments WHERE applicant_id=$id ORDER BY id DESC LIMIT 1")->fetch_assoc();
$block    = $app['block_id'] ? db()->query("SELECT name FROM blocks WHERE id=" . (int)$app['block_id'])->fetch_assoc() : null;
$reqBlock = $app['requested_block_id'] ? db()->query("SELECT name FROM blocks WHERE id=" . (int)$app['requested_block_id'])->fetch_assoc() : null;
$ccReq    = course_request_for((int)$_SESSION['user_id'], ['pending', 'approved', 'declined']);

echo json_encode([
    'app' => [
        'status'       => $app['status'],
        'current_step' => (int)$app['current_step'],
        'course'       => $app['course'],
    ],
    'docs'      => array_column($docs, 'status', 'doc_type'),
    'payment'   => $payment ?: null,
    'block'     => $block['name'] ?? null,
    'reqBlock'  => $reqBlock['name'] ?? null,
    'courseReq' => $ccReq ? $ccReq['status'] : null,
]);
