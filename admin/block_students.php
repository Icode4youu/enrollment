<?php
require_once __DIR__ . '/auth.php';
require_office('department');

$id = (int)($_GET['id'] ?? 0);
$block = db()->query("SELECT * FROM blocks WHERE id=$id")->fetch_assoc();
if (!$block) { http_response_code(404); exit('Block not found'); }

$students = db()->query(
    "SELECT last_name, first_name, course, status FROM applicants a
     WHERE a.block_id=$id AND a.status NOT IN ('withdrawn','rejected')
       AND NOT " . superseded_sql('a') . "
     ORDER BY last_name, first_name"
)->fetch_all(MYSQLI_ASSOC);
?>
<table class="admin-table">
    <tr><th>Student</th><th>Course</th><th>Status</th></tr>
    <?php foreach ($students as $s): ?>
    <tr>
        <td><strong><?= e($s['last_name'] . ', ' . $s['first_name']) ?></strong></td>
        <td><?= e($s['course']) ?></td>
        <td><span class="badge <?= e($s['status']) ?>"><?= e($s['status']) ?></span></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$students): ?>
    <tr><td colspan="3" style="text-align:center;color:var(--muted);padding:26px;">No students assigned to this block yet.</td></tr>
    <?php endif; ?>
</table>
