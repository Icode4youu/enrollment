<?php
require_once __DIR__ . '/auth.php';
require_office('department');

$errors = [];
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'add') {
        $name = strtoupper(trim($_POST['name'] ?? ''));
        if (!$name) {
            $errors[] = 'Teacher name is required.';
        } else {
            $stmt = db()->prepare("INSERT IGNORE INTO teachers (name) VALUES (?)");
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $ok = $stmt->affected_rows ? 'Teacher added.' : "$name is already on the list.";
        }
    } elseif ($act === 'rename') {
        $id   = (int)$_POST['teacher_id'];
        $name = strtoupper(trim($_POST['name'] ?? ''));
        $row  = db()->query("SELECT name FROM teachers WHERE id=$id")->fetch_assoc();
        if (!$row) {
            $errors[] = 'Teacher not found.';
        } elseif (!$name) {
            $errors[] = 'Teacher name is required.';
        } elseif ($name !== $row['name'] &&
                  db()->query("SELECT id FROM teachers WHERE name='" . db()->real_escape_string($name) . "'")->num_rows) {
            $errors[] = "$name is already on the list.";
        } else {
            $stmt = db()->prepare("UPDATE teachers SET name=? WHERE id=?");
            $stmt->bind_param('si', $name, $id);
            $stmt->execute();
            // keep schedule rows pointing at the renamed teacher
            $stmt = db()->prepare("UPDATE schedules SET instructor=? WHERE instructor=?");
            $stmt->bind_param('ss', $name, $row['name']);
            $stmt->execute();
            $ok = 'Teacher renamed.';
        }
    } elseif ($act === 'delete') {
        $id  = (int)$_POST['teacher_id'];
        $row = db()->query("SELECT name FROM teachers WHERE id=$id")->fetch_assoc();
        if ($row) {
            $stmt = db()->prepare("DELETE FROM teachers WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            // free the slots instead of leaving a dangling name
            $tba  = 'TBA';
            $stmt = db()->prepare("UPDATE schedules SET instructor=? WHERE instructor=?");
            $stmt->bind_param('ss', $tba, $row['name']);
            $stmt->execute();
            $ok = 'Teacher removed. Their schedule entries were set to TBA.';
        }
    } elseif ($act === 'fix') {
        // assign a teacher / room to a TBA schedule entry
        $sid     = (int)$_POST['schedule_id'];
        $teacher = trim($_POST['instructor'] ?? '');
        $room    = trim($_POST['room'] ?? '');
        $s = db()->query("SELECT * FROM schedules WHERE id=$sid")->fetch_assoc();
        if (!$s) {
            $errors[] = 'Schedule entry not found.';
        } else {
            if ($teacher !== '' && $s['instructor'] === 'TBA') {
                $stmt = db()->prepare(
                    "SELECT s.id, b.name AS bname, s.subject FROM schedules s
                     JOIN blocks b ON b.id=s.block_id
                     WHERE s.instructor=? AND s.day_of_week=? AND s.time_start < ? AND s.time_end > ? AND s.id<>?
                     LIMIT 1"
                );
                $stmt->bind_param('ssssi', $teacher, $s['day_of_week'], $s['time_end'], $s['time_start'], $sid);
                $stmt->execute();
                $c = $stmt->get_result()->fetch_assoc();
                if ($c) {
                    $errors[] = "$teacher already teaches {$c['subject']} ({$c['bname']}) at that time.";
                }
            }
            if (!$errors && $room !== '' && $s['room'] === 'TBA') {
                $stmt = db()->prepare(
                    "SELECT s.id, b.name AS bname, s.subject FROM schedules s
                     JOIN blocks b ON b.id=s.block_id
                     WHERE s.room=? AND s.day_of_week=? AND s.time_start < ? AND s.time_end > ? AND s.id<>?
                     LIMIT 1"
                );
                $stmt->bind_param('ssssi', $room, $s['day_of_week'], $s['time_end'], $s['time_start'], $sid);
                $stmt->execute();
                $c = $stmt->get_result()->fetch_assoc();
                if ($c) {
                    $errors[] = "Room $room is already booked at that time ({$c['subject']}, {$c['bname']}).";
                }
            }
            if (!$errors) {
                $newT = ($s['instructor'] === 'TBA' && $teacher !== '') ? $teacher : $s['instructor'];
                $newR = ($s['room'] === 'TBA') ? ($room !== '' ? $room : 'TBA') : $s['room'];
                $stmt = db()->prepare("UPDATE schedules SET instructor=?, room=? WHERE id=?");
                $stmt->bind_param('ssi', $newT, $newR, $sid);
                $stmt->execute();
                $ok = 'Schedule entry updated.';
            }
        }
    }
}

$teachers = db()->query(
    "SELECT t.id, t.name,
            COUNT(s.id)                AS sessions,
            COUNT(DISTINCT s.block_id) AS blocks,
            COALESCE(SUM(TIME_TO_SEC(TIMEDIFF(s.time_end, s.time_start)))/3600, 0) AS hrs,
            GROUP_CONCAT(DISTINCT s.subject ORDER BY s.subject SEPARATOR ', ') AS subjects
     FROM teachers t
     LEFT JOIN schedules s ON s.instructor = t.name
     GROUP BY t.id, t.name
     ORDER BY hrs DESC, t.name"
)->fetch_all(MYSQLI_ASSOC);

// schedule entries still needing a teacher or a room
$tbaRows = db()->query(
    "SELECT s.*, b.name AS bname FROM schedules s
     JOIN blocks b ON b.id = s.block_id
     WHERE s.instructor='TBA' OR s.room='TBA'
     ORDER BY b.name, FIELD(s.day_of_week,'M','T','W','TH','F','S'), s.time_start"
)->fetch_all(MYSQLI_ASSOC);

// stat-card figures
$totalT   = count($teachers);
$avgHrs   = $totalT ? array_sum(array_column($teachers, 'hrs')) / $totalT : 0;
$busiest  = $totalT ? array_reduce($teachers, fn($c, $t) => ($c === null || $t['hrs'] > $c['hrs']) ? $t : $c) : null;
$tbaCount = count($tbaRows);

// teachers free at a given slot (for the TBA fixer dropdowns)
$freeStmt = db()->prepare(
    "SELECT name FROM teachers WHERE name NOT IN (
         SELECT instructor FROM schedules WHERE day_of_week=? AND time_start < ? AND time_end > ?
     ) ORDER BY name"
);
$free_at = function ($day, $start, $end) use ($freeStmt) {
    $freeStmt->bind_param('sss', $day, $end, $start);
    $freeStmt->execute();
    return array_column($freeStmt->get_result()->fetch_all(MYSQLI_ASSOC), 'name');
};

$LOAD_CAP = 20; // hours — bar scale + color threshold
$fmt = fn($h) => rtrim(rtrim(number_format($h, 2), '0'), '.');

$page_title = 'Admin - Teachers';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
$bannerTitle = 'Teachers';
$bannerSub   = 'Manage the teacher pool used by the schedule generator and manual assignment.';
$bannerIcon  = 'users'; $bannerArt = 'users';
include __DIR__ . '/page_banner.php';
?>
    <?php foreach ($errors as $e): ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>
    <?php if ($ok): ?><div class="alert alert-ok"><?= e($ok) ?></div><?php endif; ?>

    <div class="stat-cards">
        <div class="stat-card c-blue"><div class="sc-ic"><?= icon('users', 18) ?></div><div class="lbl">Teachers</div><div class="num"><?= $totalT ?></div></div>
        <div class="stat-card c-green"><div class="sc-ic"><?= icon('clock', 18) ?></div><div class="lbl">Avg load / wk</div><div class="num"><?= $fmt($avgHrs) ?>h</div></div>
        <div class="stat-card c-orange"><div class="sc-ic"><?= icon('user', 18) ?></div><div class="lbl">Busiest: <?= e($busiest['name'] ?? '—') ?></div><div class="num"><?= $busiest ? $fmt($busiest['hrs']) . 'h' : '—' ?></div></div>
        <div class="stat-card c-red"><div class="sc-ic"><?= icon('info', 18) ?></div><div class="lbl">Unassigned slots</div><div class="num"><?= $tbaCount ?></div></div>
    </div>

    <div class="mini-card" style="max-width:none;margin-bottom:22px;">
        <h3><?= icon('users', 15) ?> Add Teacher</h3>
        <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
            <input type="hidden" name="action" value="add">
            <div class="field" style="margin:0;"><label style="margin-bottom:4px;">Teacher name</label><input type="text" name="name" placeholder="e.g. DEL ROSARIO" required style="padding:9px 13px;"></div>
            <button class="btn" type="submit" style="padding:11px 18px;"><?= icon('check', 15) ?> Add Teacher</button>
        </form>
    </div>

    <div class="table-card" style="margin-top:18px;">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;padding:16px 20px;border-bottom:1px solid var(--border);">
            <h3 class="section-title" style="margin:0;"><?= icon('users', 18) ?> Faculty Load (<?= $totalT ?> teachers)</h3>
            <input type="search" id="tSearch" placeholder="Search teacher…" style="padding:8px 14px;min-width:200px;">
        </div>
        <table class="admin-table" id="tTable">
            <tr><th>Teacher</th><th>Sessions / wk</th><th style="min-width:180px;">Load / wk</th><th>Blocks</th><th>Subjects</th><th style="width:140px;">Actions</th></tr>
            <?php
            $palette = [
                ['#eaf1fe', '#3573f0'], ['#e3f8ec', '#12a15c'], ['#fff1e8', '#d4510f'],
                ['#fdecea', '#e14b42'], ['#f3e8fd', '#8b46d9'], ['#e8f7f7', '#0e8a8a'],
            ];
            foreach ($teachers as $t):
                $ini  = strtoupper(substr($t['name'], 0, 2));
                $pc   = $palette[crc32($t['name']) % count($palette)];
                $pct  = min(100, round($t['hrs'] / $LOAD_CAP * 100));
                $col  = $t['hrs'] > 18 ? 'var(--red)' : ($t['hrs'] > 12 ? 'var(--orange)' : 'var(--green)');
                $subs = $t['subjects'] ? explode(', ', $t['subjects']) : [];
            ?>
            <tr data-name="<?= e(strtolower($t['name'])) ?>">
                <td>
                    <div class="t-namecell" style="display:flex;align-items:center;gap:2px;">
                        <span class="avatar-sm" style="margin:0;background:<?= $pc[0] ?>;color:<?= $pc[1] ?>;"><?= e($ini) ?></span>
                        <strong class="nm" style="margin-left:8px;"><?= e($t['name']) ?></strong>
                        <form method="post" class="ren" style="display:none;gap:6px;">
                            <input type="hidden" name="action" value="rename">
                            <input type="hidden" name="teacher_id" value="<?= (int)$t['id'] ?>">
                            <input type="text" name="name" value="<?= e($t['name']) ?>" required style="padding:5px 9px;width:140px;">
                            <button class="btn btn-sm" type="submit"><?= icon('check', 13) ?></button>
                        </form>
                    </div>
                </td>
                <td><?= (int)$t['sessions'] ?></td>
                <td>
                    <strong><?= $fmt($t['hrs']) ?>h</strong>
                    <div class="usage-bar" style="margin-top:5px;"><div style="width:<?= $pct ?>%;background:<?= $col ?>;"></div></div>
                </td>
                <td><?= (int)$t['blocks'] ?></td>
                <td>
                    <?php foreach ($subs as $sb): ?>
                        <span style="display:inline-block;background:var(--blue-soft);color:var(--blue);border-radius:999px;padding:3px 10px;font-size:.68rem;font-weight:700;margin:1px 2px 1px 0;"><?= e($sb) ?></span>
                    <?php endforeach; ?>
                    <?php if (!$subs): ?><span style="color:var(--muted);">—</span><?php endif; ?>
                </td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <a class="btn btn-sm btn-secondary" title="View timetable" href="<?= BASE_URL ?>/admin/teacher_sched.php?id=<?= (int)$t['id'] ?>"><?= icon('calendar', 13) ?></a>
                        <button class="btn btn-sm btn-secondary edt" type="button" title="Rename"><?= icon('edit', 13) ?></button>
                        <form method="post" onsubmit="return confirm('Remove <?= e($t['name']) ?>? Their schedule entries will be set to TBA.');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="teacher_id" value="<?= (int)$t['id'] ?>">
                            <button class="btn btn-sm btn-danger" type="submit" title="Remove"><?= icon('x', 13) ?></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$teachers): ?><tr><td colspan="6" style="text-align:center;color:var(--muted);padding:20px;">No teachers yet — add one above.</td></tr><?php endif; ?>
        </table>
    </div>

    <div class="table-card" style="margin-top:18px;">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;padding:16px 20px;border-bottom:1px solid var(--border);">
            <div>
                <h3 class="section-title" style="margin:0;"><?= icon('info', 18) ?> Unassigned Schedule Entries (<?= $tbaCount ?>)</h3>
                <p style="font-size:.84rem;color:var(--muted);margin:4px 0 0;">Slots the generator couldn't fill. Pick a free teacher or type a room, then save.</p>
            </div>
            <?php if ($tbaCount): ?><span class="badge rejected"><?= $tbaCount ?> needs fixing</span><?php endif; ?>
        </div>
        <table class="admin-table">
            <tr><th>Block</th><th>Day</th><th>Time</th><th>Subject</th><th>Teacher</th><th>Room</th><th></th></tr>
            <?php foreach ($tbaRows as $r):
                $freeT = $free_at($r['day_of_week'], $r['time_start'], $r['time_end']);
            ?>
            <tr>
                <td><strong><?= e($r['bname']) ?></strong></td>
                <td><?= e($r['day_of_week']) ?></td>
                <td style="white-space:nowrap;"><?= e(substr($r['time_start'],0,5)) ?> – <?= e(substr($r['time_end'],0,5)) ?></td>
                <td><strong><?= e($r['subject']) ?></strong></td>
                <td>
                    <?php if ($r['instructor'] === 'TBA'): ?>
                        <select form="fix<?= (int)$r['id'] ?>" name="instructor" style="padding:6px 10px;">
                            <option value="">— pick teacher —</option>
                            <?php foreach ($freeT as $fn): ?><option value="<?= e($fn) ?>"><?= e($fn) ?></option><?php endforeach; ?>
                        </select>
                    <?php else: ?><?= e($r['instructor']) ?><?php endif; ?>
                </td>
                <td>
                    <?php if ($r['room'] === 'TBA'): ?>
                        <input form="fix<?= (int)$r['id'] ?>" type="text" name="room" placeholder="Room" style="padding:6px 10px;width:110px;">
                    <?php else: ?><?= e($r['room']) ?><?php endif; ?>
                </td>
                <td>
                    <form method="post" id="fix<?= (int)$r['id'] ?>">
                        <input type="hidden" name="action" value="fix">
                        <input type="hidden" name="schedule_id" value="<?= (int)$r['id'] ?>">
                        <button class="btn btn-sm" type="submit"><?= icon('check', 13) ?> Save</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$tbaRows): ?><tr><td colspan="7" style="text-align:center;color:var(--muted);padding:20px;">All schedule entries have a teacher and a room.</td></tr><?php endif; ?>
        </table>
    </div>
<?php include __DIR__ . '/nav_close.php'; ?>
<script>
// live search filter
document.getElementById('tSearch').addEventListener('input', function () {
    var q = this.value.toLowerCase();
    document.querySelectorAll('#tTable tr[data-name]').forEach(function (tr) {
        tr.style.display = tr.dataset.name.indexOf(q) === -1 ? 'none' : '';
    });
});

// pencil button toggles the inline rename form
document.querySelectorAll('.edt').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var cell = btn.closest('tr').querySelector('.t-namecell');
        var nm = cell.querySelector('.nm'), ren = cell.querySelector('.ren');
        var open = ren.style.display !== 'none';
        ren.style.display = open ? 'none' : 'flex';
        nm.style.display  = open ? '' : 'none';
        if (!open) ren.querySelector('input[name="name"]').focus();
    });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
