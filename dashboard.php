<?php
require_once __DIR__ . '/config.php';
require_user();
$app = current_applicant();
if (!$app) redirect('index.php');
$user = current_user();

// Only the newest upload per doc type — a re-upload replaces the rejected entry
$docs = db()->query(
    "SELECT d.* FROM documents d
     JOIN (SELECT doc_type, MAX(id) AS mid FROM documents WHERE applicant_id=" . (int)$app['id'] . " GROUP BY doc_type) x
       ON d.id = x.mid
     ORDER BY d.uploaded_at DESC"
)->fetch_all(MYSQLI_ASSOC);
$payment = db()->query(
    "SELECT * FROM payments WHERE applicant_id=" . (int)$app['id'] . " ORDER BY id DESC LIMIT 1"
)->fetch_assoc();
$fee      = !empty($app['is_course_change']) ? ID_FEE : ENROLLMENT_FEE;
$feeLabel = !empty($app['is_course_change']) ? 'ID Fee' : 'Enrollment Fee';
// Course-change apps: the enrollment fee was paid on the ORIGINAL application —
// walk back the whole request chain (a student may change course more than once)
// so we show the ₱500 enrollment-fee record, not a previous app's ₱350 ID fee
$oldPayment = null;
if (!empty($app['is_course_change'])) {
    $rootId = original_application_id($app['id']);
    if ($rootId !== (int)$app['id']) {
        $oldPayment = db()->query(
            "SELECT * FROM payments WHERE applicant_id=$rootId ORDER BY id DESC LIMIT 1"
        )->fetch_assoc();
    }
}
$block = $app['block_id']
    ? db()->query("SELECT * FROM blocks WHERE id=" . (int)$app['block_id'])->fetch_assoc()
    : null;
$activity = db()->query(
    "SELECT * FROM activity_log WHERE applicant_id=" . (int)$app['id'] . " ORDER BY id DESC LIMIT 8"
)->fetch_all(MYSQLI_ASSOC);
if ($activity) $_SESSION['seen_activity'] = (int)$activity[0]['id'];

$statusLabels = [
    'pending'      => 'Pre-registration received',
    'requirements' => 'Submitting requirements',
    'payment'      => 'Payment submitted',
    'blocking'     => 'Block selection',
    'submitted'    => 'Submitted - under review',
    'verified'     => 'Verified by Registrar',
    'enrolled'     => 'OFFICIALLY ENROLLED',
    'rejected'     => 'Application returned',
    'withdrawn'    => 'Withdrawn by student',
];

$order = ['pending','requirements','payment','blocking','submitted','verified','enrolled'];
$idx = array_search($app['status'], $order);
$idx = $idx === false ? 0 : $idx;
$idDone = (bool)id_card((int)$app['id']);
$steps = [
    'Applied' => $idx >= 0,
    'Requirements' => $idx >= 2,
    'Payment' => $idx >= 3,
    'ID' => $idDone || $idx >= 4,
    'Blocking' => $idx >= 5 || !empty($app['block_id']),
    'Review' => $idx >= 5,
    'Enrolled' => $app['status'] === 'enrolled',
];
$activeMap = [
    'pending' => 'Applied', 'requirements' => 'Requirements', 'payment' => 'Payment',
    'blocking' => 'Blocking', 'submitted' => 'Review', 'verified' => 'Review',
    'enrolled' => 'Enrolled', 'rejected' => 'Review', 'withdrawn' => 'Applied',
];
$activeStep = $activeMap[$app['status']] ?? 'Applied';
[$needUrl] = next_enrollment_step($app);
if ($needUrl === 'step_id.php') $activeStep = 'ID';
$progressPct = $app['status'] === 'enrolled' ? 100 : round($idx / 6 * 100);

$stepUrls = [
    0 => 'step_registrar.php', 1 => 'step_registrar.php', 2 => 'step_medical.php',
    3 => 'step_accounting.php', 4 => 'step_id.php', 5 => 'step_blocks.php',
];
// next_enrollment_step is authoritative — it skips already-finished work
// (e.g. a course-change application whose requirements were carried over)
$nextUrl = str_starts_with($needUrl, 'step_') ? $needUrl
        : ($stepUrls[$app['current_step']] ?? 'step_registrar.php');
$inProgress = in_array($app['status'], ['pending','requirements','payment'])
    || ($app['status'] === 'blocking' && !$app['requested_block_id']);

$reqTypes = array_keys(required_docs($app['applicant_type']));
$haveTypes = array_column($docs, 'doc_type');
$uploaded = count(array_intersect($reqTypes, $haveTypes));

// Latest status per doc type — surface rejections as alerts
$latestByType = $remarkByType = [];
foreach ($docs as $d) {
    if (!isset($latestByType[$d['doc_type']])) {
        $latestByType[$d['doc_type']] = $d['status'];
        $remarkByType[$d['doc_type']] = $d['remarks'];
    }
}
$docLabels = required_docs($app['applicant_type']) + ['receipt' => 'Payment Receipt'];
$rejectedTypes = [];
foreach ($latestByType as $t => $s) {
    if ($s === 'rejected' && $t !== 'receipt') $rejectedTypes[$t] = $docLabels[$t] ?? ucwords(str_replace('_', ' ', $t));
}
$payRejected = $payment && $payment['status'] === 'rejected';

// Send the student back to the earliest step that has a rejected upload
$reuploadUrl = null;
if ($rejectedTypes || $payRejected) {
    $rej = array_keys($latestByType, 'rejected');
    $reuploadUrl = 'step_registrar.php';
    if (!array_diff($rej, ['medical_form', 'receipt'])) {
        $reuploadUrl = in_array('medical_form', $rej) ? 'step_medical.php' : 'step_accounting.php';
    }
    if ($payRejected && !array_diff($rej, ['receipt'])) $reuploadUrl = 'step_accounting.php';
}

$initials = strtoupper(substr($app['first_name'],0,1) . substr($app['last_name'],0,1));
$fullName = $app['first_name'] . ' ' . ($app['middle_name'] ? substr($app['middle_name'],0,1) . '. ' : '') . $app['last_name'];
$docIcons = [
    'psa' => 'file', 'report_card' => 'clipboard', 'picture_2x2' => 'image',
    'school_id' => 'id-card', 'clearance' => 'shield', 'medical_form' => 'medical',
    'receipt' => 'receipt', 'id_photo' => 'image',
];

$page_title = 'My Dashboard';
include __DIR__ . '/includes/header.php';
?>
<div class="portal-shell">
    <?php include __DIR__ . '/includes/portal_nav.php'; ?>

    <div class="portal-main">
        <div id="dashContent">
        <?php if ($m = flash('err')): ?><div class="alert alert-error" style="max-width:none;margin:0 0 22px;"><?= e($m) ?></div><?php endif; ?>
        <div class="welcome-hero anim">
            <span class="wh-deco"><?= icon('cap', 210) ?></span>
            <div>
                <span class="wh-hi">Welcome back,</span>
                <h2><?= e($app['first_name']) ?> <?= e($app['last_name']) ?></h2>
                <p class="wh-sub"><?= e($app['course']) ?> &middot; <?= $app['applicant_type'] === 'new' ? e($app['category'] ?? 'New Student') : 'Old Student' ?> &middot; Applied <?= e(date('F j, Y', strtotime($app['created_at']))) ?></p>
            </div>
            <span class="wh-status"><?= e($statusLabels[$app['status']] ?? $app['status']) ?></span>
        </div>

        <?php foreach (active_announcements() as $an): ?>
        <div class="anim d1" style="max-width:none;margin:0 0 16px;background:var(--orange-soft);border:1px solid rgba(242,101,34,.25);border-radius:var(--radius);padding:15px 20px;display:flex;gap:14px;align-items:flex-start;">
            <span style="color:var(--orange-dark);flex-shrink:0;margin-top:1px;"><?= icon('megaphone', 19) ?></span>
            <div style="flex:1;min-width:0;">
                <strong style="display:block;color:var(--navy);font-size:.92rem;"><?= e($an['title']) ?></strong>
                <span style="font-size:.84rem;color:#6b4d21;line-height:1.55;"><?= nl2br(e($an['body'])) ?></span>
                <small style="display:block;margin-top:5px;color:var(--muted);font-size:.72rem;"><?= e(date('F j, Y', strtotime($an['created_at']))) ?></small>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if ($app['status'] === 'enrolled'): ?>
        <div class="anim d1" style="max-width:none;margin:0 0 22px;background:linear-gradient(120deg,#0e9f5a,#12b76a);border-radius:var(--radius);padding:20px 24px;display:flex;align-items:center;gap:18px;flex-wrap:wrap;color:#fff;box-shadow:var(--shadow);">
            <span style="width:52px;height:52px;border-radius:50%;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;flex-shrink:0;"><?= icon('cap', 28) ?></span>
            <div style="flex:1;min-width:220px;">
                <strong style="font-size:1.05rem;display:block;">You are officially enrolled!</strong>
                <span style="font-size:.85rem;opacity:.92;"><?= e($app['course']) ?><?= $block ? ' &middot; ' . e($block['name']) : '' ?></span>
            </div>
            <a class="btn btn-sm" style="background:#fff;color:#0e9f5a;" href="<?= BASE_URL ?>/enrollment_slip.php"><?= icon('receipt', 15) ?> Enrollment Slip / COR</a>
        </div>
        <?php endif; ?>

        <?php if ($rejectedTypes || $payRejected): ?>
        <div class="alert alert-error anim d1" style="max-width:none;margin:0 0 22px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
            <?= icon('info', 20) ?>
            <div style="flex:1;min-width:220px;">
                <strong>Action needed:</strong>
                <?php foreach ($rejectedTypes as $t => $label): ?>
                    <?= e($label) ?> was rejected<?= $remarkByType[$t] ? ' &mdash; ' . e($remarkByType[$t]) : '' ?>.<br>
                <?php endforeach; ?>
                <?php if ($payRejected): ?>Your payment was rejected<?= $payment['remarks'] ? ' &mdash; ' . e($payment['remarks']) : '' ?>.<?php endif; ?>
            </div>
            <a class="btn btn-sm btn-red" data-inline href="<?= BASE_URL ?>/<?= e($reuploadUrl) ?>">Re-upload Now</a>
        </div>
        <?php endif; ?>

        <div class="progress-card anim d1">
            <div class="pc-head">
                <h3><?= icon('clipboard', 17) ?> Enrollment Progress</h3>
                <?php if ($inProgress): ?>
                    <a class="btn btn-sm" data-inline href="<?= BASE_URL ?>/<?= e($nextUrl) ?>">Continue <?= icon('arrow-right', 14) ?></a>
                <?php endif; ?>
            </div>
            <div class="progress-line">
                <div class="pl-fill" style="width:<?= $progressPct ?>%"></div>
                <?php foreach ($steps as $label => $done):
                    $cls = $done ? 'done' : ($label === $activeStep ? 'active' : ''); ?>
                <div class="pl-step <?= $cls ?>">
                    <div class="pl-dot"><?= $done ? icon('check', 15) : array_search($label, array_keys($steps)) + 1 ?></div>
                    <div class="pl-label"><?= e($label) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="dash-stats">
            <div class="dstat anim d2">
                <span class="ds-icn" style="background:var(--blue-soft);color:var(--blue);"><?= icon('folder', 21) ?></span>
                <div><strong><span data-count="<?= $uploaded ?>">0</span>/<?= count($reqTypes) ?></strong><span>Documents uploaded</span></div>
            </div>
            <div class="dstat anim d3">
                <span class="ds-icn" style="background:var(--orange-soft);color:var(--orange-dark);"><?= icon('wallet', 21) ?></span>
                <div><strong><?= $payment ? e(ucfirst($payment['status'])) : 'Not yet' ?></strong><span><?= e($feeLabel) ?> payment</span></div>
            </div>
            <div class="dstat anim d4">
                <span class="ds-icn" style="background:var(--green-soft);color:var(--green);"><?= icon('grid', 21) ?></span>
                <div><strong><?= $block ? e($block['name']) : 'Not yet' ?></strong><span>Class block</span></div>
            </div>
        </div>

        <div class="mini-grid anim d3">
            <div>
            <div class="mini-card">
                <h3><?= icon('folder', 17) ?> My Documents</h3>
                <?php
                $reqDocs = array_filter($docs, fn($d) => $d['doc_type'] !== 'receipt');
                if (!$reqDocs): ?>
                    <div class="empty-state">
                        <?= icon('upload', 30) ?>
                        <p>No documents uploaded yet.</p>
                        <?php if ($inProgress): ?>
                            <a class="btn btn-sm" data-inline href="<?= BASE_URL ?>/<?= e($nextUrl) ?>">Upload Now <?= icon('arrow-right', 14) ?></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php foreach ($reqDocs as $d):
                    $isImg = in_array(strtolower(pathinfo($d['file_name'], PATHINFO_EXTENSION)), ['jpg','jpeg','png']);
                ?>
                <div class="doc-row">
                    <?php if ($isImg): ?>
                    <a class="doc-icon" style="overflow:hidden;padding:0;" href="<?= BASE_URL ?>/<?= e($d['file_path']) ?>" target="_blank">
                        <img src="<?= BASE_URL ?>/<?= e($d['file_path']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block;">
                    </a>
                    <?php else: ?>
                    <div class="doc-icon"><?= icon($docIcons[$d['doc_type']] ?? 'file', 18) ?></div>
                    <?php endif; ?>
                    <div class="doc-name">
                        <a href="<?= BASE_URL ?>/<?= e($d['file_path']) ?>" target="_blank"><?= e(doc_label($d['doc_type'])) ?></a>
                        <small><?= e(date('M j, Y g:i A', strtotime($d['uploaded_at']))) ?></small>
                        <?php if ($d['status'] === 'rejected' && $d['remarks']): ?>
                            <small style="color:var(--red);"><?= e($d['remarks']) ?></small>
                        <?php endif; ?>
                    </div>
                    <span class="step-pill <?= $d['status'] === 'approved' ? 'done' : ($d['status'] === 'rejected' ? 'pending' : 'waiting') ?>">
                        <?= e(ucfirst($d['status'])) ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="mini-card" id="activity" style="margin-top:22px;">
                <h3><?= icon('clock', 17) ?> Recent Activity</h3>
                <?php if (!$activity): ?>
                    <p style="color:var(--muted);font-size:.87rem;">No activity yet.</p>
                <?php endif; ?>
                <?php foreach ($activity as $a): ?>
                <div class="doc-row">
                    <div class="doc-icon" style="background:var(--orange-soft);color:var(--orange-dark);width:34px;height:34px;border-radius:10px;"><?= icon('clock', 15) ?></div>
                    <div class="doc-name">
                        <?= e($a['message']) ?>
                        <small><?= e(date('M j, Y g:i A', strtotime($a['created_at']))) ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            </div>

            <div class="mini-card">
                <h3><?= icon('wallet', 17) ?> Payment &amp; Section</h3>
                <div class="doc-row">
                    <div class="doc-icon"><?= icon('receipt', 18) ?></div>
                    <div class="doc-name">
                        <?= e($feeLabel) ?>
                        <small>&#8369;<?= number_format($payment ? (float)$payment['amount'] : $fee, 2) ?> via GCash</small>
                    </div>
                    <span class="step-pill <?= $payment ? ($payment['status'] === 'confirmed' ? 'done' : 'pending') : 'waiting' ?>">
                        <?= $payment ? e(ucfirst($payment['status'])) : 'Not submitted' ?>
                    </span>
                </div>
                <?php if ($oldPayment): ?>
                <div class="doc-row">
                    <div class="doc-icon"><?= icon('receipt', 18) ?></div>
                    <div class="doc-name">
                        Enrollment Fee
                        <small>&#8369;<?= number_format((float)$oldPayment['amount'], 2) ?> via GCash &middot; paid on your original application</small>
                    </div>
                    <span class="step-pill <?= $oldPayment['status'] === 'confirmed' ? 'done' : 'pending' ?>">
                        <?= e(ucfirst($oldPayment['status'])) ?>
                    </span>
                </div>
                <?php endif; ?>
                <div class="doc-row">
                    <div class="doc-icon"><?= icon('building', 18) ?></div>
                    <div class="doc-name">
                        Block / Section
                        <small><?php
                            if ($app['requested_block_id']) {
                                $rb = db()->query("SELECT name FROM blocks WHERE id=" . (int)$app['requested_block_id'])->fetch_assoc();
                                echo ($block ? 'Change to ' : 'Requested: ') . e($rb['name'] ?? '?') . ' — pending approval';
                            } else echo 'Assigned class block';
                        ?></small>
                    </div>
                    <strong style="color:var(--navy);"><?php
                        if ($block) echo e($block['name']);
                        elseif ($app['requested_block_id']) echo e($rb['name'] ?? '—');
                        else echo '—';
                    ?></strong>
                </div>
                <?php if ($block): ?>
                <div style="margin-top:16px;">
                    <a class="btn btn-sm btn-secondary" href="<?= BASE_URL ?>/my_schedule.php"><?= icon('calendar', 15) ?> View My Class Schedule</a>
                </div>
                <?php endif; ?>

                <h3 style="margin-top:28px;"><?= icon('info', 17) ?> What's next?</h3>
                <?php if ($app['status'] === 'blocking' && $app['requested_block_id']): ?>
                    <p style="color:var(--muted);font-size:.87rem;line-height:1.6;">Your block request is pending Department approval. You may pick a different block while waiting.</p>
                    <a class="btn btn-sm btn-secondary" style="margin-top:12px;" href="<?= BASE_URL ?>/step_blocks.php"><?= icon('grid', 14) ?> Change Requested Block</a>
                <?php elseif ($inProgress): ?>
                    <p style="color:var(--muted);font-size:.87rem;line-height:1.6;">You still have unfinished steps. Continue where you left off:</p>
                    <a class="btn btn-sm" data-inline style="margin-top:12px;" href="<?= BASE_URL ?>/<?= e($nextUrl) ?>">Continue Enrollment Steps <?= icon('arrow-right', 14) ?></a>
                <?php elseif ($app['status'] === 'enrolled'): ?>
                    <p style="color:var(--green);font-size:.87rem;font-weight:600;line-height:1.6;">Congratulations! You are officially enrolled.</p>
                <?php elseif ($app['status'] === 'rejected'): ?>
                    <p style="color:var(--red);font-size:.87rem;line-height:1.6;">Your application was returned. Please contact the Registrar's office.</p>
                <?php elseif ($app['status'] === 'withdrawn'): ?>
                    <p style="color:var(--muted);font-size:.87rem;line-height:1.6;">You withdrew this application. You may file a new one while enrollment is open.</p>
                    <a class="btn btn-sm" style="margin-top:12px;" href="<?= BASE_URL ?>/index.php"><?= icon('file-plus', 15) ?> Start New Application</a>
                <?php else: ?>
                    <p style="color:var(--muted);font-size:.87rem;line-height:1.6;">All steps submitted. The Registrar is reviewing your application — check back here for updates.</p>
                <?php endif; ?>
                <?php if (in_array($app['status'], ['submitted','verified','enrolled']) && $app['block_id']): ?>
                    <a class="btn btn-sm btn-secondary" style="margin-top:14px;" href="<?= BASE_URL ?>/enrollment_slip.php"><?= icon('receipt', 15) ?> Enrollment Slip / COR</a>
                <?php endif; ?>
            </div>
        </div>
        </div>

        <div id="stepView" class="step-view" hidden>
            <div class="sv-head">
                <a class="btn btn-sm btn-secondary" href="<?= BASE_URL ?>/dashboard.php"><?= icon('arrow-left', 15) ?> Back to Dashboard</a>
                <span class="sv-title"><?= icon('clipboard', 16) ?> Online Enrollment</span>
            </div>
            <div id="stepBody"><div class="step-loading"></div></div>
        </div>
    </div>
</div>
<script>
// Live dashboard — poll the status feed and reload when anything changes
// (doc approved/rejected, payment review, block assigned, enrollment, ...)
(function () {
    var snap = null;
    function tick() {
        if (document.hidden) return;
        fetch('<?= BASE_URL ?>/status_feed.php', {credentials: 'same-origin'})
            .then(function (r) { return r.json(); })
            .then(function (j) {
                var s = JSON.stringify(j);
                if (snap === null) { snap = s; return; }
                if (s === snap) return;
                // Don't reload mid-form — wait until the inline step view is closed
                var stepView = document.getElementById('stepView');
                if (stepView && !stepView.hidden) { snap = s; return; }
                location.reload();
            })
            .catch(function () {});
    }
    tick();
    setInterval(tick, 9000);
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
