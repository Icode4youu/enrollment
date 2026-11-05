<?php
require_once __DIR__ . '/auth.php';
require_office('department');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_block') {
    $name   = trim($_POST['name'] ?? '');
    $course = trim($_POST['course'] ?? 'BSIT');
    $cap    = max(1, (int)($_POST['capacity'] ?? 40));
    if (!$name) {
        $errors[] = 'Block name is required.';
    } else {
        $stmt = db()->prepare("INSERT INTO blocks (name, course, capacity) VALUES (?,?,?)");
        $stmt->bind_param('ssi', $name, $course, $cap);
        $stmt->execute();
        $newId = $stmt->insert_id;

        // Auto-generate a random 4-day schedule for the new block with
        // auto-assigned teachers (never double-books anyone).
        require_once __DIR__ . '/../includes/scheduler.php';
        generate_block_schedule($newId);
        redirect('admin/blocks.php');
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_block') {
    $bid    = (int)($_POST['block_id'] ?? 0);
    $name   = trim($_POST['name'] ?? '');
    $course = trim($_POST['course'] ?? 'BSIT');
    $cap    = max(1, (int)($_POST['capacity'] ?? 40));
    $taken  = db()->query(
        "SELECT COUNT(*) c FROM applicants a WHERE a.block_id=$bid AND a.status NOT IN ('withdrawn','rejected')
           AND NOT " . superseded_sql('a')
    )->fetch_assoc()['c'];
    if (!$name) {
        $errors[] = 'Block name is required.';
    } elseif ($cap < $taken) {
        $errors[] = "Capacity can't go below $taken — that many students are already assigned.";
    } else {
        $stmt = db()->prepare("UPDATE blocks SET name=?, course=?, capacity=? WHERE id=?");
        $stmt->bind_param('ssii', $name, $course, $cap, $bid);
        $stmt->execute();
        redirect('admin/blocks.php');
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'regen_all') {
    // Wipe every block's timetable and generate fresh random ones.
    require_once __DIR__ . '/../includes/scheduler.php';
    db()->query("DELETE FROM schedules");
    $ids = array_column(db()->query("SELECT id FROM blocks")->fetch_all(MYSQLI_ASSOC), 'id');
    foreach ($ids as $bid) generate_block_schedule($bid);
    redirect('admin/blocks.php');
}

$blocks = db()->query(
    "SELECT b.*, (SELECT COUNT(*) FROM applicants a WHERE a.block_id=b.id
        AND a.status NOT IN ('withdrawn','rejected') AND NOT " . superseded_sql('a') . ") AS taken
     FROM blocks b ORDER BY b.id"
)->fetch_all(MYSQLI_ASSOC);

$totCap   = array_sum(array_column($blocks, 'capacity'));
$totTaken = array_sum(array_column($blocks, 'taken'));

// Group the grid by course — one section per course instead of one long wall of cards
$byCourse = [];
foreach ($blocks as $b) $byCourse[$b['course'] ?: 'Other'][] = $b;

// Which days each block meets — for the day chips on the cards
$blockDays = [];
foreach (db()->query(
    "SELECT block_id, GROUP_CONCAT(DISTINCT day_of_week ORDER BY FIELD(day_of_week,'M','T','W','TH','F','S')) d
     FROM schedules GROUP BY block_id"
)->fetch_all(MYSQLI_ASSOC) as $r) {
    $blockDays[$r['block_id']] = explode(',', $r['d']);
}

$courseAccents = [
    'BSIT'   => 'acc-orange', 'BSCrim' => 'acc-blue',   'BEEd' => 'acc-green',
    'BSEd'   => 'acc-teal',   'BSHM'   => 'acc-violet', 'BSBA' => 'acc-gold',
    'BSTM'   => 'acc-navy',
];

$page_title = 'Admin - Blocks';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
$bannerTitle = 'Blocks / Sections';
$bannerSub   = 'Capacity, enrollment count, and class schedule per block.';
$bannerIcon  = 'grid'; $bannerArt = 'grid';
include __DIR__ . '/page_banner.php';
?>
    <?php foreach ($errors as $e): ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

    <div class="stat-cards">
        <div class="stat-card c-navy"><div class="sc-ic"><?= icon('grid', 18) ?></div><div class="lbl">Blocks</div><div class="num"><?= count($blocks) ?></div></div>
        <div class="stat-card c-blue"><div class="sc-ic"><?= icon('users', 18) ?></div><div class="lbl">Total Capacity</div><div class="num"><?= (int)$totCap ?></div></div>
        <div class="stat-card c-orange"><div class="sc-ic"><?= icon('cap', 18) ?></div><div class="lbl">Enrolled</div><div class="num"><?= (int)$totTaken ?></div></div>
        <div class="stat-card c-green"><div class="sc-ic"><?= icon('check-circle', 18) ?></div><div class="lbl">Slots Left</div><div class="num"><?= (int)max(0, $totCap - $totTaken) ?></div></div>
    </div>

    <div class="mini-card" style="max-width:none;margin-bottom:22px;">
        <h3 id="blkFormTitle"><?= icon('grid', 15) ?> Manage Blocks</h3>
        <div style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:14px;">
            <form method="post" id="blkForm" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                <input type="hidden" name="action" value="add_block" id="blkAction">
                <input type="hidden" name="block_id" value="" id="blkId">
                <div class="field" style="margin:0;"><label style="margin-bottom:4px;">Block name</label><input type="text" name="name" id="blkName" placeholder="e.g. Block 7" required style="padding:9px 13px;"></div>
                <div class="field" style="margin:0;width:120px;"><label style="margin-bottom:4px;">Course</label>
                    <select name="course" id="blkCourse" required style="padding:9px 13px;">
                        <?php foreach (course_list() as $c): ?>
                            <option value="<?= e($c) ?>"><?= e($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field" style="margin:0;width:100px;"><label style="margin-bottom:4px;">Capacity</label><input type="number" name="capacity" id="blkCap" value="40" min="1" style="padding:9px 13px;"></div>
                <button class="btn" type="submit" id="blkBtn" style="padding:11px 18px;"><?= icon('check', 15) ?> Add Block</button>
                <button class="btn btn-secondary" type="button" id="blkCancel" style="padding:11px 14px;display:none;">Cancel</button>
            </form>
            <form method="post" onsubmit="return confirm('Delete ALL block schedules and generate new random ones?');">
                <input type="hidden" name="action" value="regen_all">
                <button class="btn btn-secondary" type="submit" style="padding:11px 18px;"><?= icon('rotate', 15) ?> Randomize All Schedules</button>
            </form>
        </div>
    </div>

    <!-- Filter row -->
    <div class="blk-filters">
        <div class="bf-item">
            <label>Filter by Course</label>
            <select id="fCourse">
                <option value="">All Courses</option>
                <?php foreach (array_keys($byCourse) as $c): ?><option value="<?= e($c) ?>"><?= e($c) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="bf-item">
            <label>Filter by Availability</label>
            <select id="fAvail">
                <option value="">All</option>
                <option value="open">Available</option>
                <option value="full">Full</option>
            </select>
        </div>
        <div class="bf-item">
            <label>Sort by</label>
            <select id="fSort">
                <option value="name">Name (A–Z)</option>
                <option value="filled">Most Filled</option>
                <option value="free">Most Slots Left</option>
            </select>
        </div>
    </div>

    <?php foreach ($byCourse as $course => $cBlocks):
        $cCap   = array_sum(array_column($cBlocks, 'capacity'));
        $cTaken = array_sum(array_column($cBlocks, 'taken'));
        $allFull = !array_filter($cBlocks, fn($b) => $b['capacity'] - $b['taken'] > 0);
        $acc = $courseAccents[$course] ?? 'acc-navy';
    ?>
    <div class="course-sec <?= $acc ?>" data-course="<?= e($course) ?>">
        <div class="course-head">
            <h3><?= icon('cap', 17) ?> <?= e($course) ?>
                <?php if ($allFull): ?><span class="full-tag">Full</span><?php endif; ?></h3>
            <span><?= count($cBlocks) ?> block<?= count($cBlocks) === 1 ? '' : 's' ?> &middot; <?= (int)$cTaken ?>/<?= (int)$cCap ?> filled</span>
        </div>
        <div class="blocks-grid" style="max-width:none;">
            <?php foreach ($cBlocks as $b):
                $left = max(0, $b['capacity'] - $b['taken']);
                $pct = $b['capacity'] ? min(100, round($b['taken'] / $b['capacity'] * 100)) : 0;
                $full = $left === 0;
                $days = $blockDays[$b['id']] ?? [];
            ?>
            <div class="block-card<?= $full ? ' is-full' : '' ?>"
                 data-name="<?= e(strtolower($b['name'])) ?>"
                 data-taken="<?= (int)$b['taken'] ?>"
                 data-left="<?= (int)$left ?>"
                 data-avail="<?= $full ? 'full' : 'open' ?>">
                <div class="bc-head">
                    <div class="bc-titles">
                        <h3><?= e(strtoupper($b['name'])) ?></h3>
                        <span class="bc-course"><?= e($b['course']) ?></span>
                    </div>
                    <button class="card-edit" type="button" title="Edit block"
                            data-id="<?= (int)$b['id'] ?>"
                            data-name="<?= e($b['name']) ?>"
                            data-course="<?= e($b['course']) ?>"
                            data-cap="<?= (int)$b['capacity'] ?>"><?= icon('edit', 12) ?> Edit</button>
                </div>
                <div class="bc-body">
                    <div class="bc-donut<?= $full ? ' is-full' : '' ?>" style="--p:<?= $pct ?>;">
                        <span><?= (int)$b['taken'] ?><small>/<?= (int)$b['capacity'] ?></small></span>
                    </div>
                    <div class="bc-info">
                        <div class="bc-days">
                            <?php foreach (['M','T','W','TH','F','S'] as $d): ?>
                                <span class="<?= in_array($d, $days) ? 'on' : '' ?>"><?= $d ?></span>
                            <?php endforeach; ?>
                        </div>
                        <div class="bc-left" style="color:<?= $full ? 'var(--red)' : 'var(--green)' ?>">
                            <?= $full ? 'FULL' : $left . ' slots left' ?>
                        </div>
                        <div class="bc-sub"><?= (int)$b['taken'] ?> enrolled &middot; <?= $pct ?>% filled</div>
                    </div>
                </div>
                <div class="blk-actions">
                    <a class="btn btn-sm btn-secondary" href="<?= BASE_URL ?>/admin/block_sched.php?block=<?= (int)$b['id'] ?>"><?= icon('calendar', 14) ?> Manage Schedule</a>
                    <button class="btn btn-sm btn-secondary blk-details" type="button"
                            data-id="<?= (int)$b['id'] ?>" data-name="<?= e($b['name']) ?>"><?= icon('list', 14) ?> Manage Details</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

<!-- Block details modal -->
<div id="blkModal" style="display:none;position:fixed;inset:0;background:rgba(11,26,48,.55);z-index:1000;align-items:center;justify-content:center;padding:24px;">
    <div style="background:var(--surface);border-radius:16px;max-width:560px;width:100%;max-height:80vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,.3);">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 20px;border-bottom:1px solid var(--border);">
            <strong id="blkModalTitle" style="font-size:.95rem;"></strong>
            <button class="btn btn-sm btn-secondary" type="button" id="blkModalClose"><?= icon('x', 15) ?></button>
        </div>
        <div id="blkModalBody" style="flex:1;overflow:auto;padding:10px 20px 20px;min-height:120px;">
            <p style="color:var(--muted);padding:20px 0;text-align:center;">Loading…</p>
        </div>
    </div>
</div>

<script>
(function () {
    // --- Filters + sort ---
    var fCourse = document.getElementById('fCourse'),
        fAvail  = document.getElementById('fAvail'),
        fSort   = document.getElementById('fSort');

    function applyFilters() {
        var c = fCourse.value, a = fAvail.value;
        document.querySelectorAll('.course-sec').forEach(function (sec) {
            var visible = 0;
            if (c && sec.dataset.course !== c) { sec.style.display = 'none'; return; }
            sec.style.display = '';
            sec.querySelectorAll('.block-card').forEach(function (card) {
                var show = !a || card.dataset.avail === a;
                card.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            if (!visible) sec.style.display = 'none';
        });
    }
    function applySort() {
        var key = fSort.value;
        document.querySelectorAll('.blocks-grid').forEach(function (grid) {
            var cards = Array.from(grid.querySelectorAll('.block-card'));
            cards.sort(function (x, y) {
                if (key === 'filled') return (+y.dataset.taken) - (+x.dataset.taken);
                if (key === 'free')   return (+y.dataset.left) - (+x.dataset.left);
                return x.dataset.name.localeCompare(y.dataset.name);
            });
            cards.forEach(function (card) { grid.appendChild(card); });
        });
    }
    [fCourse, fAvail].forEach(function (el) { el.addEventListener('change', applyFilters); });
    fSort.addEventListener('change', applySort);

    // --- Edit mode ---
    var form = document.getElementById('blkForm'),
        act  = document.getElementById('blkAction'),
        bid  = document.getElementById('blkId'),
        nm   = document.getElementById('blkName'),
        crs  = document.getElementById('blkCourse'),
        cap  = document.getElementById('blkCap'),
        btn  = document.getElementById('blkBtn'),
        cxl  = document.getElementById('blkCancel'),
        ttl  = document.getElementById('blkFormTitle');

    document.querySelectorAll('.card-edit').forEach(function (el) {
        el.addEventListener('click', function () {
            act.value = 'edit_block';
            bid.value = el.dataset.id;
            nm.value  = el.dataset.name;
            crs.value = el.dataset.course;
            cap.value = el.dataset.cap;
            btn.innerHTML = 'Update Block';
            cxl.style.display = '';
            ttl.lastChild.textContent = ' Edit Block';
            window.scrollTo({ top: 0, behavior: 'smooth' });
            nm.focus();
        });
    });
    cxl.addEventListener('click', function () {
        act.value = 'add_block';
        bid.value = ''; nm.value = ''; crs.selectedIndex = 0; cap.value = 40;
        btn.innerHTML = 'Add Block';
        cxl.style.display = 'none';
        ttl.lastChild.textContent = ' Manage Blocks';
    });

    // --- Details modal ---
    var modal = document.getElementById('blkModal'),
        body  = document.getElementById('blkModalBody'),
        title = document.getElementById('blkModalTitle');
    function closeModal() { modal.style.display = 'none'; body.innerHTML = ''; }
    document.querySelectorAll('.blk-details').forEach(function (el) {
        el.addEventListener('click', function () {
            title.textContent = el.dataset.name + ' — Students';
            body.innerHTML = '<p style="color:var(--muted);padding:20px 0;text-align:center;">Loading…</p>';
            modal.style.display = 'flex';
            fetch('<?= BASE_URL ?>/admin/block_students.php?id=' + el.dataset.id)
                .then(function (r) { return r.text(); })
                .then(function (html) { body.innerHTML = html; })
                .catch(function () { body.innerHTML = '<p style="color:var(--red);padding:20px 0;text-align:center;">Failed to load.</p>'; });
        });
    });
    document.getElementById('blkModalClose').addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
})();
</script>
<?php include __DIR__ . '/nav_close.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
