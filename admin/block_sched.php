<?php
require_once __DIR__ . '/auth.php';
require_office('department');

$block_id = (int)($_GET['block'] ?? 0);
$block = db()->query("SELECT * FROM blocks WHERE id=$block_id")->fetch_assoc();
if (!$block) redirect('admin/blocks.php');

$errors = [];
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'delete') {
        $sid = (int)$_POST['schedule_id'];
        db()->query("DELETE FROM schedules WHERE id=$sid AND block_id=$block_id");
        $ok = 'Schedule entry removed.';
    } elseif ($act === 'regenerate') {
        require_once __DIR__ . '/../includes/scheduler.php';
        db()->query("DELETE FROM schedules WHERE block_id=$block_id");
        generate_block_schedule($block_id);
        $ok = 'A new random schedule was generated for this block.';
    } elseif ($act === 'add') {
        $day     = $_POST['day_of_week'] ?? '';
        $start   = $_POST['time_start'] ?? '';
        $end     = $_POST['time_end'] ?? '';
        $subject = trim($_POST['subject'] ?? '');
        $room    = trim($_POST['room'] ?? '');
        $teacher = trim($_POST['instructor'] ?? '');
        $newT    = trim($_POST['new_teacher'] ?? '');
        if ($newT) {
            $teacher = strtoupper($newT);
            $stmt = db()->prepare("INSERT IGNORE INTO teachers (name) VALUES (?)");
            $stmt->bind_param('s', $teacher);
            $stmt->execute();
        }
        if (!in_array($day, ['M','T','W','TH','F','S'])) $errors[] = 'Please select a valid day.';
        if (!preg_match('/^\d{2}:\d{2}/', $start) || !preg_match('/^\d{2}:\d{2}/', $end) || $start >= $end)
            $errors[] = 'Invalid time range — start must be earlier than end.';
        if (!$subject) $errors[] = 'Subject is required.';
        if (!$teacher) $errors[] = 'Please choose a teacher.';
        if (!$errors) {
            // Teacher conflict: same teacher, same day, overlapping time — in ANY block
            $stmt = db()->prepare(
                "SELECT s.*, b.name AS bname FROM schedules s
                 JOIN blocks b ON b.id = s.block_id
                 WHERE s.instructor=? AND s.day_of_week=? AND s.time_start < ? AND s.time_end > ?
                 LIMIT 1"
            );
            $stmt->bind_param('ssss', $teacher, $day, $end, $start);
            $stmt->execute();
            $conflict = $stmt->get_result()->fetch_assoc();
            if ($conflict) {
                $errors[] = "$teacher already teaches {$conflict['subject']} ({$conflict['bname']}) on $day " .
                    substr($conflict['time_start'], 0, 5) . '–' . substr($conflict['time_end'], 0, 5) . '.';
            } else {
                // Same block can't have two classes at an overlapping time
                $stmt = db()->prepare(
                    "SELECT * FROM schedules WHERE block_id=? AND day_of_week=?
                     AND time_start < ? AND time_end > ? LIMIT 1"
                );
                $stmt->bind_param('isss', $block_id, $day, $end, $start);
                $stmt->execute();
                if ($stmt->get_result()->fetch_assoc()) {
                    $errors[] = "This block already has a class on $day at that time.";
                } else {
                    // Room conflict: same room, same day, overlapping time — in ANY block
                    $roomConflict = null;
                    if ($room !== '') {
                        $stmt = db()->prepare(
                            "SELECT s.*, b.name AS bname FROM schedules s
                             JOIN blocks b ON b.id = s.block_id
                             WHERE s.room=? AND s.day_of_week=? AND s.time_start < ? AND s.time_end > ?
                             LIMIT 1"
                        );
                        $stmt->bind_param('ssss', $room, $day, $end, $start);
                        $stmt->execute();
                        $roomConflict = $stmt->get_result()->fetch_assoc();
                    }
                    if ($roomConflict) {
                        $errors[] = "Room $room is already booked on $day " .
                            substr($roomConflict['time_start'], 0, 5) . '–' . substr($roomConflict['time_end'], 0, 5) .
                            " ({$roomConflict['subject']}, {$roomConflict['bname']}).";
                    } else {
                        $stmt = db()->prepare(
                            "INSERT INTO schedules (block_id, day_of_week, time_start, time_end, subject, instructor, room)
                             VALUES (?,?,?,?,?,?,?)"
                        );
                        $stmt->bind_param('issssss', $block_id, $day, $start, $end, $subject, $teacher, $room);
                        $stmt->execute();
                        $ok = 'Schedule entry added.';
                    }
                }
            }
        }
    }
}

$sched = db()->query(
    "SELECT * FROM schedules WHERE block_id=$block_id
     ORDER BY FIELD(day_of_week,'M','T','W','TH','F','S'), time_start"
)->fetch_all(MYSQLI_ASSOC);
$teachers = db()->query("SELECT * FROM teachers ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$page_title = 'Admin - ' . $block['name'] . ' Schedule';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
?>
    <a class="back-link" href="<?= BASE_URL ?>/admin/blocks.php"><?= icon('arrow-left', 16) ?> Back to Blocks</a>
    <?php
    $bannerTitle = $block['name'] . ' Schedule';
    $bannerSub   = 'Add or remove class entries. Teachers can\'t be double-booked at the same day & time.';
    $bannerIcon  = 'calendar'; $bannerArt = 'calendar';
    include __DIR__ . '/page_banner.php';
    ?>

    <?php foreach ($errors as $e): ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>
    <?php if ($ok): ?><div class="alert alert-ok"><?= e($ok) ?></div><?php endif; ?>

    <div class="card" style="max-width:none;margin-bottom:26px;">
        <h3 class="section-title"><?= icon('calendar', 18) ?> Add Schedule Entry</h3>
        <form method="post" id="schedForm">
            <input type="hidden" name="action" value="add">
            <div class="form-grid" style="grid-template-columns:repeat(3,1fr);">
                <div class="field">
                    <label>Day <span class="req">*</span></label>
                    <select name="day_of_week" required>
                        <option value="">Select day</option>
                        <?php foreach (['M'=>'Monday','T'=>'Tuesday','W'=>'Wednesday','TH'=>'Thursday','F'=>'Friday','S'=>'Saturday'] as $k => $v): ?>
                            <option value="<?= $k ?>" <?= (($_POST['day_of_week'] ?? '') === $k) ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Start Time <span class="req">*</span></label>
                    <input type="time" name="time_start" value="<?= e($_POST['time_start'] ?? '') ?>" required>
                </div>
                <div class="field">
                    <label>End Time <span class="req">*</span></label>
                    <input type="time" name="time_end" value="<?= e($_POST['time_end'] ?? '') ?>" required>
                </div>
                <div class="field">
                    <label>Subject <span class="req">*</span></label>
                    <input type="text" name="subject" placeholder="e.g. IT 101" value="<?= e($_POST['subject'] ?? '') ?>" required>
                </div>
                <div class="field">
                    <label>Teacher <span class="req">*</span> <small id="tHint" style="font-weight:400;color:var(--muted);"></small></label>
                    <select name="instructor">
                        <option value="">Select teacher</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?= e($t['name']) ?>" <?= (($_POST['instructor'] ?? '') === $t['name']) ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Room</label>
                    <input type="text" name="room" placeholder="e.g. 3064 LEC" value="<?= e($_POST['room'] ?? '') ?>">
                </div>
                <div class="field full">
                    <label>Or add a new teacher <small style="font-weight:400;color:var(--muted);">(optional — overrides dropdown)</small></label>
                    <input type="text" name="new_teacher" placeholder="e.g. DEL ROSARIO" value="<?= e($_POST['new_teacher'] ?? '') ?>">
                </div>
            </div>
            <div class="form-actions" style="margin-top:18px;text-align:left;">
                <button class="btn" type="submit"><?= icon('check', 16) ?> Add to Schedule</button>
            </div>
        </form>
    </div>

    <div class="table-card">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;padding:16px 20px;border-bottom:1px solid var(--border);">
            <h3 class="section-title" style="margin:0;"><?= icon('list', 18) ?> Current Schedule (<?= count($sched) ?> entries)</h3>
            <form method="post" onsubmit="return confirm('Delete the current schedule and generate a new random one?');">
                <input type="hidden" name="action" value="regenerate">
                <button class="btn btn-sm btn-secondary" type="submit"><?= icon('rotate', 14) ?> Randomize Schedule</button>
            </form>
        </div>
        <div style="padding:18px 20px;">
            <?php $grid_del = true; include __DIR__ . '/../includes/sched_grid.php'; ?>
        </div>
    </div>
<?php include __DIR__ . '/nav_close.php'; ?>
<script>
(function () {
    var f = document.getElementById('schedForm');
    var day = f.day_of_week, st = f.time_start, en = f.time_end, sel = f.instructor, hint = document.getElementById('tHint');
    function refresh() {
        if (!day.value || !st.value || !en.value) { hint.textContent = ''; return; }
        fetch('<?= BASE_URL ?>/admin/avail_teachers.php?day=' + encodeURIComponent(day.value) +
              '&start=' + encodeURIComponent(st.value) + '&end=' + encodeURIComponent(en.value))
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var free = 0;
                Array.prototype.forEach.call(sel.options, function (o) {
                    if (!o.value) return;
                    o.disabled = d.busy.indexOf(o.value) !== -1;
                    o.textContent = o.value + (o.disabled ? ' — busy at this time' : '');
                    if (!o.disabled) free++;
                });
                if (sel.selectedOptions[0] && sel.selectedOptions[0].disabled) sel.value = '';
                hint.textContent = '(' + free + ' of ' + (d.free.length + d.busy.length) + ' free)';
            });
    }
    [day, st, en].forEach(function (el) { el.addEventListener('change', refresh); });
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
