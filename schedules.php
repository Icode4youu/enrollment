<?php
require_once __DIR__ . '/config.php';
require_user();
$app = current_applicant();
$user = current_user();

// Request a block change (or a first block) — pending Department approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $app && !in_array($app['status'], ['rejected','withdrawn'])) {
    $bid    = (int)($_POST['request_block'] ?? 0);
    $reason = trim((string)($_POST['reason'] ?? ''));
    $blk = db()->query("SELECT * FROM blocks WHERE id=$bid")->fetch_assoc();
    if (!$blk) {
        flash('err', 'Block not found.');
    } elseif ($blk['course'] !== $app['course']) {
        flash('err', 'That block is for a different course.');
    } elseif ((int)$app['block_id'] === $bid) {
        flash('err', 'You are already in ' . $blk['name'] . '.');
    } elseif ($app['block_id'] && $reason === '') {
        flash('err', 'Please provide a reason for the schedule change.');
    } else {
        $taken = db()->query(
            "SELECT COUNT(*) c FROM applicants WHERE block_id=$bid AND status NOT IN ('withdrawn','rejected')"
        )->fetch_assoc()['c'];
        if ($taken >= (int)$blk['capacity']) {
            flash('err', $blk['name'] . ' is already full. Please pick another.');
        } else {
            $stmt = db()->prepare("UPDATE applicants SET requested_block_id=?, request_reason=? WHERE id=?");
            $stmt->bind_param('isi', $bid, $reason, $app['id']);
            $stmt->execute();
            log_activity($app['id'], ($app['block_id'] ? 'Requested a change to ' : 'Requested ')
                . $blk['name'] . ' — pending Department approval'
                . ($reason !== '' ? ' (Reason: ' . $reason . ')' : ''));
            flash('ok', 'Request for ' . $blk['name'] . ' submitted — pending Department approval.');
        }
    }
    redirect('schedules.php');
}

// Students only see blocks for their own course
$blocks = db()->query(
    "SELECT b.*, (SELECT COUNT(*) FROM applicants a
      WHERE a.block_id = b.id AND a.status NOT IN ('withdrawn','rejected')) AS taken
     FROM blocks b" . ($app ? " WHERE b.course='" . db()->real_escape_string($app['course']) . "'" : "") . "
     ORDER BY b.id"
)->fetch_all(MYSQLI_ASSOC);

$myBlock = $app && $app['block_id']
    ? db()->query("SELECT * FROM blocks WHERE id=" . (int)$app['block_id'])->fetch_assoc()
    : null;
$reqBlock = $app && $app['requested_block_id']
    ? db()->query("SELECT * FROM blocks WHERE id=" . (int)$app['requested_block_id'])->fetch_assoc()
    : null;

$initials = $app ? strtoupper(substr($app['first_name'],0,1) . substr($app['last_name'],0,1))
               : strtoupper(substr($user['full_name'] ?? 'S',0,1));
$fullName = $app ? $app['first_name'] . ' ' . ($app['middle_name'] ? substr($app['middle_name'],0,1) . '. ' : '') . $app['last_name']
                 : ($user['full_name'] ?? 'Student');

$page_title = 'Schedules';
include __DIR__ . '/includes/header.php';
?>
<div class="portal-shell">
    <?php include __DIR__ . '/includes/portal_nav.php'; ?>

    <div class="portal-main">
        <div class="welcome-hero anim">
            <span class="wh-deco"><?= icon('calendar', 210) ?></span>
            <div>
                <span class="wh-hi">Class Schedules</span>
                <h2><?= $app ? 'Block Timetables' : 'All Block Timetables' ?></h2>
                <p class="wh-sub"><?= $app ? 'Blocks for ' . e($app['course']) . ' &middot; ' : 'Browse every block\'s schedule &middot; ' ?><?= e(sy_label()) ?></p>
            </div>
        </div>

        <?php if ($m = flash('err')): ?><div class="alert alert-error anim d1" style="max-width:none;margin:0 0 18px;"><?= e($m) ?></div><?php endif; ?>
        <?php if ($m = flash('ok')): ?><div class="alert alert-success anim d1" style="max-width:none;margin:0 0 18px;"><?= e($m) ?></div><?php endif; ?>

        <?php if ($reqBlock): ?>
        <div class="anim d1" style="max-width:none;margin:0 0 18px;background:var(--orange-soft);border-radius:var(--radius);padding:15px 20px;display:flex;gap:12px;align-items:center;">
            <span style="color:var(--orange-dark);flex-shrink:0;"><?= icon('clock', 19) ?></span>
            <div style="flex:1;font-size:.88rem;">
                <strong><?= $myBlock ? 'Change request pending:' : 'Request pending:' ?></strong>
                <?= e($reqBlock['name']) ?> — awaiting Department approval.
            </div>
            <span style="color:var(--muted);font-size:.78rem;">You may request a different block below to replace it.</span>
        </div>
        <?php endif; ?>

        <?php if (!$blocks): ?>
        <div class="mini-card anim d1" style="margin-bottom:22px;">
            <div class="empty-state"><?= icon('calendar', 34) ?>
                <p>No blocks have been posted for <?= e($app['course'] ?? 'your course') ?> yet — check back soon.</p>
            </div>
        </div>
        <?php endif; ?>
        <?php foreach ($blocks as $i => $b):
            $sched = db()->query(
                "SELECT * FROM schedules WHERE block_id=" . (int)$b['id']
            )->fetch_all(MYSQLI_ASSOC);
            $left   = max(0, (int)$b['capacity'] - (int)$b['taken']);
            $isMine = $app && (int)$app['block_id'] === (int)$b['id'];
            $isReq  = $reqBlock && (int)$reqBlock['id'] === (int)$b['id'];
            $canReq = $app && !$isMine && !$isReq && $left > 0
                      && !in_array($app['status'], ['rejected','withdrawn']);
        ?>
        <div class="mini-card anim d<?= min($i + 1, 4) ?>" style="margin-bottom:22px;">
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
                <h3 style="margin:0;"><?= icon('grid', 17) ?> <?= e($b['name']) ?></h3>
                <span style="color:var(--muted);font-size:.82rem;"><?= e($b['course']) ?> &middot; <?= (int)$b['taken'] ?>/<?= (int)$b['capacity'] ?> students</span>
                <?php if ($isMine): ?>
                    <span class="step-pill done" style="margin-left:auto;">Your Block</span>
                <?php elseif ($isReq): ?>
                    <span class="step-pill pending" style="margin-left:auto;">Requested</span>
                <?php elseif ($left <= 0): ?>
                    <span class="step-pill pending" style="margin-left:auto;">Full</span>
                <?php else: ?>
                    <span class="step-pill waiting" style="margin-left:auto;"><?= $left ?> slot<?= $left === 1 ? '' : 's' ?> left</span>
                <?php endif; ?>
            </div>

            <?php include __DIR__ . '/includes/sched_grid.php'; ?>

            <?php if ($canReq): ?>
            <form method="post" class="req-form" style="margin-top:14px;text-align:right;"
                  data-need-reason="<?= $myBlock ? '1' : '0' ?>"
                  data-name="<?= e($b['name']) ?>">
                <input type="hidden" name="reason" value="">
                <input type="hidden" name="request_block" value="<?= (int)$b['id'] ?>">
                <button class="btn btn-sm" type="submit">
                    <?= icon('send', 14) ?> <?= $myBlock ? 'Request Change to this Block' : 'Request this Block' ?>
                </button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="modal-overlay" id="reqModal">
    <div class="modal" style="text-align:left;max-width:520px;">
        <div class="step-head" style="margin-bottom:18px;">
            <span class="step-head-icn"><?= icon('send', 22) ?></span>
            <div>
                <h2 style="margin:0 0 3px;" id="reqTitle">Request Block</h2>
                <p style="margin:0;font-size:.85rem;" id="reqSub">The Department will review your request.</p>
            </div>
        </div>
        <div class="field" id="reqReasonField" style="text-align:left;">
            <label>Reason for the schedule change <span class="req">*</span></label>
            <textarea id="reqReason" placeholder="e.g. Conflicts with my work schedule"></textarea>
            <div class="field-err" id="reqErr">Please provide a reason for the change.</div>
        </div>
        <div class="modal-actions" style="margin-top:22px;justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" onclick="closeModal('reqModal')">Cancel</button>
            <button type="button" class="btn" id="reqSubmit"><?= icon('send', 15) ?> Submit Request</button>
        </div>
    </div>
</div>
<script>
(function () {
    var pendingForm = null;
    var reasonField = document.getElementById('reqReasonField');
    var reasonInput = document.getElementById('reqReason');
    var errEl = document.getElementById('reqErr');

    document.querySelectorAll('.req-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            pendingForm = form;
            var needReason = form.dataset.needReason === '1';
            document.getElementById('reqTitle').textContent =
                (needReason ? 'Request change to ' : 'Request ') + form.dataset.name;
            reasonField.style.display = needReason ? 'block' : 'none';
            errEl.style.display = 'none';
            reasonInput.value = '';
            openModal('reqModal');
            if (needReason) setTimeout(function () { reasonInput.focus(); }, 80);
        });
    });

    document.getElementById('reqSubmit').addEventListener('click', function () {
        if (!pendingForm) return;
        if (pendingForm.dataset.needReason === '1') {
            var r = reasonInput.value.trim();
            if (r === '') { errEl.style.display = 'block'; reasonInput.focus(); return; }
            pendingForm.querySelector('[name=reason]').value = r;
        }
        closeModal('reqModal');
        pendingForm.submit();
    });
    document.getElementById('reqModal').addEventListener('click', function (e) {
        if (e.target === this) closeModal('reqModal');
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal('reqModal');
    });
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
