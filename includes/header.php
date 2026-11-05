<?php require_once __DIR__ . '/../config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($page_title ?? 'Online Enrollment') ?> - SEAIT</title>
<script>document.documentElement.dataset.theme = localStorage.getItem('seait-theme') || ''; window.BASE_URL = <?= json_encode(BASE_URL) ?>;</script>
<link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/seait-logo.png">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body class="<?= e($body_class ?? '') ?>">
<header class="site-header">
    <a href="<?= BASE_URL ?>/index.php">
        <img class="logo-badge" src="<?= BASE_URL ?>/assets/img/seait-logo.png" alt="SEAIT logo">
        <div>
            <h1>South East Asian Institute of Technology</h1>
            <span class="sub-title">Online Enrollment Portal</span>
        </div>
    </a>
    <?php if (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false): ?>
    <nav class="site-nav">
        <button type="button" class="nav-link theme-toggle" id="themeToggle" aria-label="Toggle dark mode">
            <span class="ic-moon"><?= icon('moon', 16) ?></span>
            <span class="ic-sun"><?= icon('sun', 16) ?></span>
        </button>
        <a class="nav-link" href="<?= BASE_URL ?>/index.php"><?= icon('home', 15) ?> View Site</a>
        <?php if (!empty($_SESSION['admin_id'])): ?>
            <span class="nav-user"><?= icon('shield', 15) ?> <?= e(admin_name()) ?></span>
            <a class="nav-link" href="<?= BASE_URL ?>/admin/logout.php"><?= icon('logout', 15) ?> Logout</a>
        <?php endif; ?>
    </nav>
    <?php else: ?>
    <nav class="site-nav">
        <button type="button" class="nav-link theme-toggle" id="themeToggle" aria-label="Toggle dark mode">
            <span class="ic-moon"><?= icon('moon', 16) ?></span>
            <span class="ic-sun"><?= icon('sun', 16) ?></span>
        </button>
        <?php if ($u = current_user()): ?>
            <?php if (($u['role'] ?? 'student') === 'student' && ($aid = (int)($_SESSION['applicant_id'] ?? 0))):
                $seen = (int)($_SESSION['seen_activity'] ?? 0);
                $unread = db()->query(
                    "SELECT COUNT(*) c FROM activity_log WHERE applicant_id=$aid AND id>$seen"
                )->fetch_assoc()['c'];
                $notifs = db()->query(
                    "SELECT * FROM activity_log WHERE applicant_id=$aid ORDER BY id DESC LIMIT 7"
                )->fetch_all(MYSQLI_ASSOC);
            ?>
                <div class="notif-wrap">
                    <button type="button" class="nav-link" id="notifBell" title="Notifications" aria-label="Notifications">
                        <?= icon('bell', 16) ?>
                        <?php if ($unread): ?><span class="notif-badge" id="notifBadge"><?= (int)$unread ?></span><?php endif; ?>
                    </button>
                    <div class="notif-panel" id="notifPanel">
                        <div class="notif-head"><?= icon('bell', 14) ?> Notifications</div>
                        <?php if (!$notifs): ?>
                            <div class="notif-empty">No notifications yet.</div>
                        <?php endif; ?>
                        <?php foreach ($notifs as $n): ?>
                        <div class="notif-item<?= (int)$n['id'] > $seen ? ' unread' : '' ?>">
                            <span class="notif-icn"><?= icon('clock', 14) ?></span>
                            <div>
                                <?= e($n['message']) ?>
                                <small><?= e(date('M j, Y g:i A', strtotime($n['created_at']))) ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <a class="notif-foot" href="<?= BASE_URL ?>/dashboard.php#activity">View all activity</a>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (($u['role'] ?? 'student') === 'admin'): ?>
                <a class="nav-link" style="color:var(--orange-dark);font-weight:600;" href="<?= BASE_URL ?>/admin/dashboard.php"><?= icon('shield', 15) ?> Admin Panel</a>
            <?php endif; ?>
            <a class="nav-user" href="<?= BASE_URL ?>/dashboard.php"><?= icon('user', 15) ?> <?= e($u['full_name']) ?></a>
            <a class="nav-link" href="<?= BASE_URL ?>/logout.php"><?= icon('logout', 15) ?> Logout</a>
        <?php else: ?>
            <a class="nav-link" href="<?= BASE_URL ?>/login.php">Login</a>
            <a class="nav-link nav-cta" href="<?= BASE_URL ?>/register.php">Register</a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
</header>
<main>
