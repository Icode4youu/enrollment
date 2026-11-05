<?php $pnav = basename($_SERVER['PHP_SELF']); ?>
<aside class="portal-side">
    <div class="ps-user">
        <div class="avatar"><?= e($initials) ?></div>
        <div class="ps-name"><?= e($fullName) ?></div>
        <?php if ($app): ?>
            <div class="ps-meta"><?= e($app['course']) ?> &middot; <?= $app['applicant_type'] === 'new' ? e($app['category'] ?? 'New Student') : 'Old Student' ?></div>
        <?php else: ?>
            <div class="ps-meta">SEAIT Student</div>
        <?php endif; ?>
    </div>
    <div class="side-label">Menu</div>
    <a class="<?= $pnav === 'dashboard.php' ? 'on' : '' ?>" href="<?= BASE_URL ?>/dashboard.php"><?= icon('home', 17) ?><span>Overview</span></a>
    <a class="<?= $pnav === 'my_schedule.php' ? 'on' : '' ?>" href="<?= BASE_URL ?>/my_schedule.php"><?= icon('calendar', 17) ?><span>My Schedule</span></a>
    <a class="<?= $pnav === 'schedules.php' ? 'on' : '' ?>" href="<?= BASE_URL ?>/schedules.php"><?= icon('grid', 17) ?><span>Schedules</span></a>
    <a class="<?= $pnav === 'my_id.php' ? 'on' : '' ?>" href="<?= BASE_URL ?>/my_id.php"><?= icon('id-card', 17) ?><span>My ID</span></a>
    <a class="<?= $pnav === 'course_request.php' ? 'on' : '' ?>" href="<?= BASE_URL ?>/course_request.php"><?= icon('rotate', 17) ?><span>Course Change Requests</span></a>
    <a class="<?= $pnav === 'settings.php' ? 'on' : '' ?>" href="<?= BASE_URL ?>/settings.php"><?= icon('settings', 17) ?><span>Settings</span></a>
    <div class="ps-foot">
        <a href="<?= BASE_URL ?>/logout.php"><?= icon('logout', 16) ?><span>Logout</span></a>
    </div>
</aside>
