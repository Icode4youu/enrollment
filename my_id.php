<?php
require_once __DIR__ . '/config.php';
require_user();
$uid = (int)$_SESSION['user_id'];

// Latest application that has an ID submission
$app = db()->query(
    "SELECT a.* FROM applicants a
     JOIN (SELECT applicant_id, MAX(id) m FROM student_ids GROUP BY applicant_id) s
       ON s.applicant_id = a.id
     WHERE a.user_id=$uid ORDER BY a.id DESC LIMIT 1"
)->fetch_assoc();
$idData = $app ? id_card($app['id']) : null;
// Fall back to the latest application for the sidebar identity card
if (!$app) $app = db()->query(
    "SELECT * FROM applicants WHERE user_id=$uid ORDER BY id DESC LIMIT 1"
)->fetch_assoc();

$fullName = $app ? $app['first_name'] . ' ' . ($app['middle_name'] ? substr($app['middle_name'],0,1) . '. ' : '') . $app['last_name']
                 : ($_SESSION['user_name'] ?? 'Student');
$initials = strtoupper(substr($app['first_name'] ?? 'S', 0, 1) . substr($app['last_name'] ?? 'T', 0, 1));

$page_title = 'My School ID';
include __DIR__ . '/includes/header.php';
?>
<div class="portal-shell">
    <?php include __DIR__ . '/includes/portal_nav.php'; ?>

    <div class="portal-main">
        <div class="card" style="max-width:none;">
            <div class="step-head">
                <span class="step-head-icn"><?= icon('id-card', 24) ?></span>
                <div>
                    <h2 class="card-title" style="text-align:left;margin:0 0 4px;">My School ID</h2>
                    <p class="card-sub" style="text-align:left;margin:0;">
                        <?= $idData ? 'Your submitted school ID — present this for verification.' : 'Your school ID will appear here once you complete the School ID step.' ?>
                    </p>
                </div>
            </div>

            <?php if (!$idData): ?>
                <div class="empty-state"><?= icon('id-card', 34) ?>
                    <p>No ID yet — finish the <strong>School ID</strong> step in your enrollment application.</p>
                    <a class="btn btn-sm" href="<?= BASE_URL ?>/dashboard.php" style="margin-top:14px;"><?= icon('arrow-left', 15) ?> Back to Dashboard</a>
                </div>
            <?php else: ?>
                <div class="id-view">
                    <?php include __DIR__ . '/includes/id_card_data.php'; ?>
                    <?php include __DIR__ . '/includes/id_card_view.php'; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
