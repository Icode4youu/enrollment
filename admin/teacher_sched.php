<?php
require_once __DIR__ . '/auth.php';
require_office('department');

$id      = (int)($_GET['id'] ?? 0);
$teacher = db()->query("SELECT * FROM teachers WHERE id=$id")->fetch_assoc();
if (!$teacher) redirect('admin/teachers.php');

$name  = $teacher['name'];
$sched = db()->query(
    "SELECT s.*, b.name AS bname FROM schedules s
     JOIN blocks b ON b.id = s.block_id
     WHERE s.instructor='" . db()->real_escape_string($name) . "'
     ORDER BY FIELD(s.day_of_week,'M','T','W','TH','F','S'), s.time_start"
)->fetch_all(MYSQLI_ASSOC);

$hrs = db()->query(
    "SELECT COALESCE(SUM(TIME_TO_SEC(TIMEDIFF(time_end,time_start)))/3600,0) h
     FROM schedules WHERE instructor='" . db()->real_escape_string($name) . "'"
)->fetch_assoc()['h'];

// weekly grid: 30-minute rows 07:00-20:00 (same layout as the student view)
$days = ['M', 'T', 'W', 'TH', 'F'];
if (in_array('S', array_column($sched, 'day_of_week'))) $days[] = 'S';
$STEP = 1800;
$segments = [];
for ($t = strtotime('07:00'); $t < strtotime('20:00'); $t += $STEP) {
    $segments[] = [$t, $t + $STEP];
}
$byDay = array_fill_keys($days, []);
foreach ($sched as $s) {
    if (isset($byDay[$s['day_of_week']])) $byDay[$s['day_of_week']][] = $s;
}
$dayNames = ['M'=>'Mon','T'=>'Tue','W'=>'Wed','TH'=>'Thu','F'=>'Fri','S'=>'Sat'];

$page_title = 'Admin - ' . $name . ' Timetable';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
?>
    <a class="back-link" href="<?= BASE_URL ?>/admin/teachers.php"><?= icon('arrow-left', 16) ?> Back to Teachers</a>
    <?php
    $bannerTitle = $name;
    $bannerSub   = count($sched) . ' sessions · ' . rtrim(rtrim(number_format($hrs, 2), '0'), '.') . ' hrs/week across ' . count(array_unique(array_column($sched, 'block_id'))) . ' block(s)';
    $bannerIcon  = 'user'; $bannerArt = 'users';
    include __DIR__ . '/page_banner.php';
    ?>

    <div class="card" style="max-width:none;">
        <table class="sched-table">
            <tr>
                <th>Time</th>
                <?php foreach ($days as $d): ?><th><?= $dayNames[$d] ?></th><?php endforeach; ?>
            </tr>
            <?php foreach ($segments as $i => [$segS, $segE]): ?>
            <tr>
                <td><strong><?= e(date('H:i', $segS)) ?> - <?= e(date('H:i', $segE)) ?></strong></td>
                <?php foreach ($days as $d):
                    $cov = null;
                    foreach ($byDay[$d] as $s) {
                        $st = strtotime($s['time_start']); $en = strtotime($s['time_end']);
                        if ($st <= $segS && $en > $segS) { $cov = [$s, $st, $en]; break; }
                    }
                    if ($cov && ($cov[1] === $segS || ($i === 0 && $cov[1] < $segS))):
                        $span = min(max(1, (int)ceil(($cov[2] - $cov[1]) / $STEP)), count($segments) - $i);
                        $s = $cov[0];
                ?>
                    <td rowspan="<?= $span ?>" class="cls-cell">
                        <span class="sub"><?= e($s['subject']) ?></span>
                        <span class="inst"><?= e($s['bname']) ?><?= $s['room'] ? ' &middot; ' . e($s['room']) : '' ?></span>
                    </td>
                <?php elseif ($cov): /* inside rowspan — cell already emitted */ ?>
                <?php else: ?>
                    <td></td>
                <?php endif; ?>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php if (!$sched): ?><p style="text-align:center;color:var(--muted);padding:20px;">No classes assigned to this teacher yet.</p><?php endif; ?>
    </div>
<?php include __DIR__ . '/nav_close.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
