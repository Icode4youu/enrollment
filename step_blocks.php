<?php
require_once __DIR__ . '/config.php';
require_user();
$app = current_applicant();
if (!$app) redirect('index.php');

// Finish the school-ID step before blocking
[$needUrl] = next_enrollment_step($app);
if ($needUrl === 'step_id.php') redirect('step_id.php');

$block = $app['block_id']
    ? db()->query("SELECT * FROM blocks WHERE id=" . (int)$app['block_id'])->fetch_assoc()
    : null;
$requested = $app['requested_block_id']
    ? db()->query("SELECT * FROM blocks WHERE id=" . (int)$app['requested_block_id'])->fetch_assoc()
    : null;

$page_title = 'Blocking';
include __DIR__ . '/includes/header.php';
?>
<?php $step_active = 5; include __DIR__ . '/includes/step_track.php'; ?>

<div class="card card-narrow">
<div class="step-head">
    <span class="step-head-icn"><?= icon('grid', 24) ?></span>
    <div>
        <h2 class="card-title" style="text-align:left;margin:0 0 4px;">Blocking</h2>
        <p class="card-sub" style="text-align:left;margin:0;">The Department assigns your class block.</p>
    </div>
</div>

<?php if ($block): ?>
    <div class="info-block" style="text-align:center;">
        <h3><?= icon('check-circle', 20) ?> Your Block: <?= e($block['name']) ?></h3>
        <p>You are assigned to <strong><?= e($block['name']) ?></strong> (<?= e($block['course']) ?>).</p>
    </div>
    <?php if ($requested): ?>
    <div class="info-block" style="text-align:center;background:var(--orange-soft);">
        <h3><?= icon('clock', 20) ?> Change Request Pending</h3>
        <p>You asked to move to <strong><?= e($requested['name']) ?></strong> — awaiting Department approval.
           You stay in <?= e($block['name']) ?> unless the change is approved.</p>
    </div>
    <?php endif; ?>
    <div class="form-actions" style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
        <a class="btn" href="<?= BASE_URL ?>/my_schedule.php"><?= icon('calendar', 16) ?> View My Schedule</a>
        <?php if (!$requested): ?>
        <a class="btn btn-secondary" href="<?= BASE_URL ?>/schedules.php"><?= icon('grid', 16) ?> Browse &amp; Request a Change</a>
        <?php endif; ?>
    </div>
<?php elseif ($requested): ?>
    <div class="info-block" style="text-align:center;">
        <h3><?= icon('clock', 20) ?> Request Pending Approval</h3>
        <p>You requested <strong><?= e($requested['name']) ?></strong>. The Department will approve or reassign your block after reviewing your documents and payment.</p>
    </div>
    <div class="form-actions" style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
        <a class="btn" href="<?= BASE_URL ?>/dashboard.php"><?= icon('home', 16) ?> Go to Dashboard</a>
        <a class="btn btn-secondary" href="<?= BASE_URL ?>/schedules.php"><?= icon('grid', 16) ?> Browse Schedules</a>
    </div>
<?php else: ?>
    <div class="info-block" style="text-align:center;">
        <h3><?= icon('clock', 20) ?> Waiting for Block Assignment</h3>
        <p>All your steps are complete. The Department will assign your class block
           once your documents and payment are verified — check your dashboard for updates.
           You can also browse the schedules and request a preferred block.</p>
    </div>
    <div class="form-actions" style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
        <a class="btn" href="<?= BASE_URL ?>/dashboard.php"><?= icon('home', 16) ?> Go to Dashboard</a>
        <a class="btn btn-secondary" href="<?= BASE_URL ?>/schedules.php"><?= icon('grid', 16) ?> Browse Schedules</a>
    </div>
<?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
