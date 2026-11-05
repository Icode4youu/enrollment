<?php
// Returns just the schedule table for a block (used by the Preview modal)
require_once __DIR__ . '/config.php';
require_user();

$id = (int)($_GET['block'] ?? 0);
$block = db()->query("SELECT * FROM blocks WHERE id=$id")->fetch_assoc();
if (!$block) { http_response_code(404); echo '<p style="padding:20px;">Block not found.</p>'; exit; }

$sched = db()->query(
    "SELECT * FROM schedules WHERE block_id=$id
     ORDER BY FIELD(day_of_week,'M','T','W','TH','F','S'), time_start"
)->fetch_all(MYSQLI_ASSOC);
?>
<table class="sched-table">
    <tr><th>Day</th><th>Time</th><th>Subject</th><th>Instructor</th><th>Room</th></tr>
    <?php foreach ($sched as $s): ?>
    <tr>
        <td><?= e($s['day_of_week']) ?></td>
        <td><?= e(date('H:i', strtotime($s['time_start']))) ?> - <?= e(date('H:i', strtotime($s['time_end']))) ?></td>
        <td><span class="sub"><?= e($s['subject']) ?></span></td>
        <td><?= e($s['instructor']) ?></td>
        <td><?= e($s['room']) ?></td>
    </tr>
    <?php endforeach; ?>
</table>
