<?php
require_once __DIR__ . '/config.php';
require_user();
$app = current_applicant();
if (!$app) redirect('dashboard.php');

$initials = strtoupper(substr($app['first_name'],0,1) . substr($app['last_name'],0,1));
$fullName = $app['first_name'] . ' ' . ($app['middle_name'] ? substr($app['middle_name'],0,1) . '. ' : '') . $app['last_name'];

$block = null;
$sched = [];
if ($app['block_id']) {
    $block = db()->query("SELECT * FROM blocks WHERE id=" . (int)$app['block_id'])->fetch_assoc();
    $sched = db()->query(
        "SELECT * FROM schedules WHERE block_id=" . (int)$app['block_id'] .
        " ORDER BY FIELD(day_of_week,'M','T','W','TH','F','S'), time_start"
    )->fetch_all(MYSQLI_ASSOC);
}

$page_title = 'My Schedule';
include __DIR__ . '/includes/header.php';
?>
<div class="portal-shell">
    <?php include __DIR__ . '/includes/portal_nav.php'; ?>

    <div class="portal-main">
        <div class="welcome-hero anim">
            <span class="wh-deco"><?= icon('calendar', 210) ?></span>
            <div>
                <span class="wh-hi">My Class Schedule</span>
                <h2><?= $block ? e($block['name']) : 'No block yet' ?></h2>
                <p class="wh-sub"><?= e($app['course']) ?> &middot; <?= e(sy_label()) ?></p>
            </div>
            <?php if ($block): ?>
                <button type="button" class="btn btn-white btn-sm" style="margin-left:auto;" onclick="window.print()"><?= icon('printer', 15) ?> Print Schedule</button>
            <?php endif; ?>
        </div>

        <?php if (!$block): ?>
            <div class="card anim d1" style="max-width:none;text-align:center;">
                <div class="empty-state">
                    <?= icon('grid', 30) ?>
                    <p>You don't have a class block yet. Request one during enrollment — the Department will approve it after review.</p>
                    <a class="btn btn-sm" href="<?= BASE_URL ?>/dashboard.php"><?= icon('arrow-left', 14) ?> Back to Dashboard</a>
                </div>
            </div>
        <?php else: ?>
        <div class="card anim d1" style="max-width:none;">
            <?php include __DIR__ . '/includes/sched_grid.php'; ?>
        </div>
        <div class="card anim d2" style="max-width:none;">
            <h3 class="section-title" style="margin-bottom:6px;"><?= icon('list', 18) ?> My Subject Load</h3>
            <p style="color:var(--muted);font-size:.82rem;margin:0 0 16px;">
                Official subjects for <?= e($app['course']) ?> &middot; <?= e(year_level_label((int)($app['year_level'] ?? 1))) ?> &middot; <?= e(sy_label()) ?>
            </p>
            <?php include __DIR__ . '/includes/subject_load.php'; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
