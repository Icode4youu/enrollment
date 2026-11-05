<?php
require_once __DIR__ . '/config.php';
require_user();
$app = current_applicant();
if (!$app || !$app['block_id']) redirect('dashboard.php');
if (!empty($app['user_id']) && (int)$app['user_id'] !== (int)$_SESSION['user_id']) redirect('dashboard.php');
if (!in_array($app['status'], ['submitted','verified','enrolled'])) redirect('dashboard.php');

$block = db()->query("SELECT * FROM blocks WHERE id=" . (int)$app['block_id'])->fetch_assoc();
$sched = db()->query(
    "SELECT * FROM schedules WHERE block_id=" . (int)$app['block_id'] .
    " ORDER BY FIELD(day_of_week,'M','T','W','TH','F','S'), time_start"
)->fetch_all(MYSQLI_ASSOC);
$payment = db()->query(
    "SELECT * FROM payments WHERE applicant_id=" . (int)$app['id'] . " ORDER BY id DESC LIMIT 1"
)->fetch_assoc();

$fullName = $app['first_name'] . ' ' . ($app['middle_name'] ? $app['middle_name'] . ' ' : '') . $app['last_name'] . ($app['ext_name'] ? ' ' . $app['ext_name'] : '');

$page_title = 'Enrollment Slip';
include __DIR__ . '/includes/header.php';
?>
<div class="portal-main" style="margin-left:0;">
    <div class="slip-wrap anim">
        <div class="slip-actions no-print">
            <a class="btn btn-sm btn-secondary" href="<?= BASE_URL ?>/dashboard.php"><?= icon('arrow-left', 15) ?> Back to Dashboard</a>
            <button type="button" class="btn btn-sm" onclick="window.print()"><?= icon('printer', 15) ?> Print / Save as PDF</button>
        </div>

        <div class="slip">
            <div class="slip-head">
                <img src="<?= BASE_URL ?>/assets/img/seait-logo.png" alt="SEAIT">
                <div>
                    <h2>South East Asian Institute of Technology</h2>
                    <p>Online Enrollment Portal &middot; <?= e(sy_label()) ?></p>
                </div>
            </div>
            <div class="slip-title">CERTIFICATE OF REGISTRATION</div>

            <div class="slip-grid">
                <div><span>Student Name</span><strong><?= e($fullName) ?></strong></div>
                <div><span>Course</span><strong><?= e($app['course']) ?></strong></div>
                <div><span>Year Level</span><strong><?= e(year_level_label((int)($app['year_level'] ?? 1))) ?></strong></div>
                <div><span>Applicant Type</span><strong><?= $app['applicant_type'] === 'new' ? e($app['category'] ?? 'New Student') : 'Old Student' ?></strong></div>
                <div><span>Block / Section</span><strong><?= e($block['name']) ?></strong></div>
                <div><span>Enrollment Fee</span><strong>&#8369;<?= number_format(ENROLLMENT_FEE, 2) ?> &mdash; <?= $payment ? e(ucfirst($payment['status'])) : 'Pending' ?></strong></div>
                <div><span>Status</span><strong><?= e(strtoupper($app['status'])) ?></strong></div>
            </div>

            <div class="slip-title" style="font-size:.95rem;margin:26px 0 14px;">STUDENT SUBJECT LOAD</div>
            <?php include __DIR__ . '/includes/subject_load.php'; ?>

            <div class="slip-title" style="font-size:.95rem;margin:30px 0 14px;">CLASS SCHEDULE</div>
            <?php include __DIR__ . '/includes/sched_grid.php'; ?>

            <div class="slip-foot">
                <div class="sig"><span></span>Student's Signature</div>
                <div class="sig"><span></span>Registrar</div>
            </div>
            <p class="slip-note">Issued <?= e(date('F j, Y')) ?> &middot; This is a system-generated document.</p>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
