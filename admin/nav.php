<?php $current = basename($_SERVER['PHP_SELF']); ?>
<div class="admin-shell">
    <aside class="admin-side">
        <div class="side-label">Menu</div>
        <a href="<?= BASE_URL ?>/admin/dashboard.php" class="<?= $current === 'dashboard.php' ? 'on' : '' ?>">
            <?= icon('list', 17) ?><span>Applications</span>
        </a>
        <?php if (admin_can('department')): ?>
        <a href="<?= BASE_URL ?>/admin/course_requests.php" class="<?= $current === 'course_requests.php' ? 'on' : '' ?>">
            <?= icon('rotate', 17) ?><span>Course Requests</span>
        </a>
        <a href="<?= BASE_URL ?>/admin/blocks.php" class="<?= $current === 'blocks.php' ? 'on' : '' ?>">
            <?= icon('grid', 17) ?><span>Blocks</span>
        </a>
        <a href="<?= BASE_URL ?>/admin/teachers.php" class="<?= $current === 'teachers.php' ? 'on' : '' ?>">
            <?= icon('users', 17) ?><span>Teachers</span>
        </a>
        <?php endif; ?>
        <?php if (admin_can('registrar')): ?>
        <div class="side-label">Manage</div>
        <a href="<?= BASE_URL ?>/admin/announcements.php" class="<?= $current === 'announcements.php' ? 'on' : '' ?>">
            <?= icon('megaphone', 17) ?><span>Announcements</span>
        </a>
        <a href="<?= BASE_URL ?>/admin/reports.php" class="<?= $current === 'reports.php' ? 'on' : '' ?>">
            <?= icon('chart', 17) ?><span>Reports</span>
        </a>
        <?php endif; ?>
        <?php if (admin_role() === 'admin'): ?>
        <a href="<?= BASE_URL ?>/admin/users.php" class="<?= $current === 'users.php' ? 'on' : '' ?>">
            <?= icon('users', 17) ?><span>Users</span>
        </a>
        <a href="<?= BASE_URL ?>/admin/settings.php" class="<?= $current === 'settings.php' ? 'on' : '' ?>">
            <?= icon('settings', 17) ?><span>Settings</span>
        </a>
        <?php endif; ?>
        <div class="side-label">Other</div>
        <a href="<?= BASE_URL ?>/index.php"><?= icon('home', 17) ?><span>View Site</span></a>
        <div class="side-foot">
            <div class="who"><?= icon('user', 16) ?><span><?= e(admin_name()) ?> &middot; <?= e(ucfirst(admin_role())) ?></span></div>
            <a href="<?= BASE_URL ?>/admin/logout.php"><?= icon('logout', 16) ?><span>Logout</span></a>
        </div>
    </aside>
    <div class="admin-main">
