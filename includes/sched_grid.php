<?php
// Weekly timetable grid. Expects $sched = array of schedule rows
// (day_of_week, time_start, time_end, subject, instructor, room).
$days = ['M', 'T', 'W', 'TH', 'F'];
foreach ($sched as $s) {
    if ($s['day_of_week'] === 'S') { $days[] = 'S'; break; }
}
$STEP = 900; // 15 min — matches the 15-minute-aligned class times
// Trim the grid to the actual class range — no dead rows before/after
if ($sched) {
    $t0 = min(array_map(fn($s) => strtotime($s['time_start']), $sched));
    $t1 = max(array_map(fn($s) => strtotime($s['time_end']), $sched));
} else {
    $t0 = strtotime('07:00'); $t1 = strtotime('08:00');
}
$t0 = (int)(floor($t0 / $STEP) * $STEP);
$t1 = (int)(ceil($t1 / $STEP) * $STEP);
$segments = [];
for ($t = $t0; $t < $t1; $t += $STEP) {
    $segments[] = [$t, $t + $STEP];
}
$byDay = array_fill_keys($days, []);
foreach ($sched as $s) {
    if (isset($byDay[$s['day_of_week']])) $byDay[$s['day_of_week']][] = $s;
}
?>
<?php if (!$sched): ?>
<p style="color:var(--muted);text-align:center;padding:26px 0;font-size:.9rem;">No classes scheduled for this block yet.</p>
<?php else: ?>
<table class="sched-table">
    <tr>
        <th>Time</th>
        <?php foreach ($days as $d): ?><th><?= $d ?></th><?php endforeach; ?>
    </tr>
    <?php foreach ($segments as $i => [$segS, $segE]): ?>
    <tr>
        <td class="t-time"><?= e(date('h:i', $segS)) ?> &ndash; <?= e(date('h:i A', $segE)) ?></td>
        <?php foreach ($days as $d):
            $cov = null;
            foreach ($byDay[$d] as $s) {
                $st = strtotime($s['time_start']); $en = strtotime($s['time_end']);
                if ($st < $segE && $en > $segS) { $cov = [$s, $st, $en]; break; }
            }
            if ($cov && (($cov[1] >= $segS && $cov[1] < $segE) || ($i === 0 && $cov[1] < $segS))):
                $span = min(max(1, (int)ceil(($cov[2] - $segS) / $STEP)), count($segments) - $i);
                $s = $cov[0];
        ?>
            <td rowspan="<?= $span ?>" class="cls-cell">
                <span class="sub"><?= e($s['subject']) ?></span>
                <span class="inst"><?= e(date('g:i', $cov[1])) ?>&ndash;<?= e(date('g:i A', $cov[2])) ?> &middot; <?= e($s['instructor']) ?><?= $s['room'] ? ' &middot; ' . e($s['room']) : '' ?></span>
                <?php if (!empty($grid_del)): ?>
                <form method="post" class="cell-del" onsubmit="return confirm('Remove this schedule entry?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="schedule_id" value="<?= (int)$s['id'] ?>">
                    <button type="submit" title="Remove entry"><?= icon('x', 11) ?></button>
                </form>
                <?php endif; ?>
            </td>
        <?php elseif ($cov): /* inside rowspan — cell already emitted */ ?>
        <?php else: ?>
            <td></td>
        <?php endif; ?>
        <?php endforeach; ?>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>
