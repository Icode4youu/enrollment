<?php
// JSON: which teachers are free at a given day + time range
require_once __DIR__ . '/auth.php';
require_office('department');
header('Content-Type: application/json');

$day   = $_GET['day']   ?? '';
$start = $_GET['start'] ?? '';
$end   = $_GET['end']   ?? '';
$valid = in_array($day, ['M','T','W','TH','F','S'])
    && preg_match('/^\d{2}:\d{2}/', $start) && preg_match('/^\d{2}:\d{2}/', $end);

$free = $busy = [];
if ($valid) {
    $teachers = db()->query("SELECT name FROM teachers ORDER BY name")->fetch_all(MYSQLI_ASSOC);
    $stmt = db()->prepare(
        "SELECT DISTINCT instructor FROM schedules
         WHERE day_of_week=? AND time_start < ? AND time_end > ?"
    );
    $stmt->bind_param('sss', $day, $end, $start);
    $stmt->execute();
    $busy = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'instructor');
    foreach ($teachers as $t) {
        if (!in_array($t['name'], $busy)) $free[] = $t['name'];
    }
}
echo json_encode(['free' => $free, 'busy' => $busy]);
