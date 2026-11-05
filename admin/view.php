<?php
require_once __DIR__ . '/auth.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$app = db()->query("SELECT * FROM applicants WHERE id=$id")->fetch_assoc();
if (!$app) redirect('admin/dashboard.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Superseded applications are read-only — every action must happen on the
    // student's current application, the only one their portal shows
    $latestId = latest_application_id($id);
    if ($latestId !== $id) {
        flash('err', "This application was superseded by application #$latestId — act on that one instead.");
        redirect('admin/view.php?id=' . $latestId);
    }
    if (isset($_POST['doc_id'], $_POST['doc_action'])) {
        $did = (int)$_POST['doc_id'];
        $st = $_POST['doc_action'] === 'approve' ? 'approved' : 'rejected';
        $remarks = trim((string)($_POST['remarks'] ?? ''));
        $doc = db()->query("SELECT doc_type, status FROM documents WHERE id=$did AND applicant_id=$id")->fetch_assoc();
        if ($st === 'rejected' && ($doc['status'] ?? '') === 'approved') {
            flash('err', 'An approved document can no longer be rejected.');
            redirect('admin/view.php?id=' . $id);
        }
        if (!doc_office_ok($doc['doc_type'] ?? '') || ($doc['doc_type'] ?? '') === 'receipt') {
            flash('err', ($doc['doc_type'] ?? '') === 'receipt'
                ? 'Receipts are reviewed with the GCash payment below.'
                : 'This document belongs to a different office.');
            redirect('admin/view.php?id=' . $id);
        }
        // Course-change applications share the same physical file (same file_path),
        // so the decision must apply to every copy of it, not just this row
        $fp = db()->query("SELECT file_path FROM documents WHERE id=$did")->fetch_assoc()['file_path'] ?? '';
        $stmt = db()->prepare("UPDATE documents SET status=?, remarks=? WHERE file_path=?");
        $stmt->bind_param('sss', $st, $remarks, $fp);
        $stmt->execute();
        $label = doc_label($doc['doc_type'] ?? 'document');
        log_activity($id, $st === 'approved' ? "$label was approved"
            : "$label was rejected" . ($remarks ? ": $remarks" : ''));
        sms_applicant($id, 'SEAIT: Your ' . $label . ' was ' . $st
            . ($st === 'rejected' && $remarks ? " — $remarks" : '') . '.');
        flash('ok', $st === 'approved' ? 'Document approved.' : 'Document rejected.');
    }
    if (isset($_POST['payment_action'])) {
        if (!admin_can('accounting')) {
            flash('err', 'Only Accounting can review payments.');
            redirect('admin/view.php?id=' . $id);
        }
        $st = $_POST['payment_action'] === 'confirm' ? 'confirmed' : 'rejected';
        $remarks = trim((string)($_POST['remarks'] ?? ''));
        $curPay = db()->query("SELECT status FROM payments WHERE applicant_id=$id ORDER BY id DESC LIMIT 1")->fetch_assoc();
        if ($st === 'rejected' && ($curPay['status'] ?? '') === 'confirmed') {
            flash('err', 'A confirmed payment can no longer be rejected.');
            redirect('admin/view.php?id=' . $id);
        }
        $stmt = db()->prepare("UPDATE payments SET status=?, remarks=? WHERE applicant_id=?");
        $stmt->bind_param('ssi', $st, $remarks, $id);
        $stmt->execute();
        // Keep the receipt document's status in sync with the payment decision —
        // the payment row is the single place Accounting reviews GCash proof
        $docSt = $st === 'confirmed' ? 'approved' : 'rejected';
        $stmt = db()->prepare(
            "UPDATE documents SET status=?, remarks=?
             WHERE doc_type='receipt' AND status<>?
               AND file_path IN (SELECT file_path FROM (
                   SELECT file_path FROM documents WHERE applicant_id=? AND doc_type='receipt'
               ) t)"
        );
        $stmt->bind_param('sssi', $docSt, $remarks, $docSt, $id);
        $stmt->execute();
        log_activity($id, $st === 'confirmed' ? 'Payment confirmed'
            : 'Payment was rejected' . ($remarks ? ": $remarks" : ''));
        sms_applicant($id, 'SEAIT: Your GCash payment was ' . $st
            . ($st === 'rejected' && $remarks ? " — $remarks" : '') . '.');
        flash('ok', $st === 'confirmed' ? 'Payment confirmed.' : 'Payment rejected.');
    }
    if (isset($_POST['assign_block'])) {
        if (!admin_can('department')) {
            flash('err', 'Only the Department can assign blocks.');
            redirect('admin/view.php?id=' . $id);
        }
        $bid = (int)$_POST['assign_block'];
        $conn = db();
        $conn->begin_transaction();
        try {
            // Lock the block row so concurrent assignments to the last slot serialize
            $blk = $conn->query("SELECT * FROM blocks WHERE id=$bid FOR UPDATE")->fetch_assoc();
            if (!$blk) throw new Exception('Block not found.');
            if ($blk['course'] !== $app['course'])
                throw new Exception($blk['name'] . ' is a ' . $blk['course'] . ' block — pick a ' . $app['course'] . ' block.');
            $taken = $conn->query(
                "SELECT COUNT(*) c FROM applicants a
                 WHERE a.block_id=$bid AND a.status NOT IN ('withdrawn','rejected') AND a.id<>$id
                   AND NOT " . superseded_sql('a')
            )->fetch_assoc()['c'];
            if ($taken >= (int)$blk['capacity']) {
                throw new Exception($blk['name'] . ' is already full (' . $blk['capacity'] . ' slots).');
            }
            $advance = $app['status'] === 'blocking' ? ", status='submitted', current_step=5" : '';
            $conn->query("UPDATE applicants SET block_id=$bid, requested_block_id=NULL, request_reason=NULL$advance WHERE id=$id");
            $conn->commit();
            $wasReq = (int)$app['requested_block_id'] === $bid;
            log_activity($id, ($wasReq ? 'Block request approved — assigned to ' : 'Assigned to ')
                . $blk['name'] . ' by the Department');
            sms_applicant($id, 'SEAIT: You were assigned to ' . $blk['name'] . ' (' . $blk['course']
                . '). Check My Schedule on the portal.');
            flash('ok', 'Assigned to ' . $blk['name'] . '.');
        } catch (Exception $ex) {
            $conn->rollback();
            flash('err', $ex->getMessage());
        }
    }
    if (isset($_POST['decline_block'])) {
        if (!admin_can('department')) {
            flash('err', 'Only the Department can decline block requests.');
            redirect('admin/view.php?id=' . $id);
        }
        $req = db()->query("SELECT name FROM blocks WHERE id=" . (int)$app['requested_block_id'])->fetch_assoc();
        $cur = $app['block_id'] ? db()->query("SELECT name FROM blocks WHERE id=" . (int)$app['block_id'])->fetch_assoc() : null;
        db()->query("UPDATE applicants SET requested_block_id=NULL, request_reason=NULL WHERE id=$id");
        log_activity($id, 'Your request for ' . ($req['name'] ?? 'a block') . ' was declined — ' .
            ($cur ? 'you remain in ' . $cur['name'] . '.' : 'please choose another block.'));
        sms_applicant($id, 'SEAIT: Your block request for ' . ($req['name'] ?? 'a block')
            . ' was declined. ' . ($cur ? 'You remain in ' . $cur['name'] . '.' : 'Please choose another block.'));
        flash('ok', 'Block request declined.');
    }
    if (isset($_POST['app_status'])) {
        if (admin_role() !== 'department') {
            flash('err', 'Only the Department can decide application status.');
            redirect('admin/view.php?id=' . $id);
        }
        $st = db()->real_escape_string($_POST['app_status']);
        if ($st === 'rejected' && $app['status'] === 'enrolled') {
            flash('err', 'An enrolled student cannot be rejected.');
            redirect('admin/view.php?id=' . $id);
        }
        if ($st === 'enrolled') {
            $missing = [];
            foreach (array_keys(required_docs($app['applicant_type'])) as $t) {
                if (doc_status($id, $t) !== 'approved') $missing[] = doc_label($t);
            }
            $p = db()->query("SELECT status FROM payments WHERE applicant_id=$id ORDER BY id DESC LIMIT 1")->fetch_assoc();
            if (!$p || $p['status'] !== 'confirmed') $missing[] = 'confirmed payment';
            if (!$app['block_id']) $missing[] = 'a class block';
            if ($missing) {
                flash('err', 'Cannot mark Enrolled yet — missing: ' . implode(', ', $missing) . '.');
                redirect('admin/view.php?id=' . $id);
            }
            $cap = (int) setting('enrollment_cap', '0');
            if ($cap > 0) {
                $cnt = (int) db()->query("SELECT COUNT(*) c FROM applicants WHERE status='enrolled'")->fetch_assoc()['c'];
                if ($cnt >= $cap) {
                    flash('err', "Enrollment cap reached — $cnt of $cap slots are already taken.");
                    redirect('admin/view.php?id=' . $id);
                }
            }
        }
        if (in_array($st, ['enrolled','rejected','submitted'])) {
            db()->query("UPDATE applicants SET status='$st' WHERE id=$id");
            if ($st === 'enrolled') {
                // Reviewed docs should no longer show as pending — include
                // carried-over copies of the same files on other applications
                db()->query("UPDATE documents SET status='approved'
                    WHERE status='pending' AND file_path IN (
                        SELECT file_path FROM (SELECT file_path FROM documents WHERE applicant_id=$id) t
                    )");
                db()->query("UPDATE payments SET status='confirmed' WHERE applicant_id=$id AND status='pending'");
                // Course change completed — withdraw the superseded applications
                // (the student stayed enrolled in the old course until now)
                db()->query("UPDATE applicants SET status='withdrawn'
                    WHERE status NOT IN ('withdrawn','rejected') AND " . superseded_sql('applicants'));
            }
            log_activity($id, [
                'enrolled'  => 'Officially enrolled — congratulations!',
                'rejected'  => 'Application was rejected',
                'submitted' => 'Application moved back to review',
            ][$st]);
            $smsMsg = [
                'enrolled' => 'SEAIT: Congratulations! You are now OFFICIALLY ENROLLED for ' . sy_label() . '.',
                'rejected' => 'SEAIT: We regret to inform you that your enrollment application was rejected.',
            ];
            if (!empty($smsMsg[$st])) sms_applicant($id, $smsMsg[$st]);
            flash('ok', [
                'enrolled'  => 'Applicant is now Enrolled.',
                'rejected'  => 'Application rejected.',
                'submitted' => 'Moved back to review.',
            ][$st]);
        }
    }
    redirect('admin/view.php?id=' . $id);
}

// Only the newest upload per doc type — a re-upload replaces the rejected entry
$docs = db()->query(
    "SELECT d.* FROM documents d
     JOIN (SELECT doc_type, MAX(id) AS mid FROM documents WHERE applicant_id=$id GROUP BY doc_type) x
       ON d.id = x.mid
     ORDER BY d.uploaded_at"
)->fetch_all(MYSQLI_ASSOC);
$payment = db()->query("SELECT * FROM payments WHERE applicant_id=$id ORDER BY id DESC LIMIT 1")->fetch_assoc();
$receiptDoc = null;
if ($payment) {
    $rid = (int)$payment['receipt_doc'];
    if ($rid) {
        $receiptDoc = db()->query("SELECT * FROM documents WHERE id=$rid AND applicant_id=$id")->fetch_assoc();
    }
    if (!$receiptDoc) {
        $receiptDoc = db()->query(
            "SELECT * FROM documents WHERE applicant_id=$id AND doc_type='receipt' ORDER BY id DESC LIMIT 1"
        )->fetch_assoc();
    }
}
// Course-change apps: the enrollment fee was paid on the ORIGINAL application —
// shown read-only here; its own application page holds the review buttons
$rootPayment = $rootReceiptDoc = null;
if (!empty($app['is_course_change'])) {
    $rootId = original_application_id($id);
    if ($rootId !== $id) {
        $rootPayment = db()->query(
            "SELECT * FROM payments WHERE applicant_id=$rootId ORDER BY id DESC LIMIT 1"
        )->fetch_assoc();
        if ($rootPayment && $rootPayment['receipt_doc']) {
            $rootReceiptDoc = db()->query(
                "SELECT * FROM documents WHERE id=" . (int)$rootPayment['receipt_doc']
            )->fetch_assoc();
        }
    }
}
$block = $app['block_id'] ? db()->query("SELECT * FROM blocks WHERE id=" . (int)$app['block_id'])->fetch_assoc() : null;
// Only blocks for this applicant's course can be offered
$blockOptions = db()->query(
    "SELECT b.*, (SELECT COUNT(*) FROM applicants a
      WHERE a.block_id=b.id AND a.status NOT IN ('withdrawn','rejected')
        AND NOT " . superseded_sql('a') . ") AS taken
     FROM blocks b WHERE b.course = '" . db()->real_escape_string($app['course']) . "'
     ORDER BY b.id"
)->fetch_all(MYSQLI_ASSOC);

// What still blocks enrollment — all required docs approved + payment confirmed + block assigned
$enrollMissing = [];
foreach (required_docs($app['applicant_type']) as $t => $lbl) {
    if (doc_status($id, $t) !== 'approved') $enrollMissing[] = $lbl . ' approval';
}
if (!$payment || $payment['status'] !== 'confirmed') $enrollMissing[] = 'confirmed payment';
if (!$app['block_id']) $enrollMissing[] = 'a class block';

$docIcons = [
    'psa' => 'file', 'report_card' => 'clipboard', 'picture_2x2' => 'image',
    'school_id' => 'id-card', 'clearance' => 'shield', 'medical_form' => 'medical',
    'receipt' => 'receipt', 'id_photo' => 'image',
];
$initials = strtoupper(substr($app['first_name'],0,1) . substr($app['last_name'],0,1));

$page_title = 'Review Applicant';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
?>
    <a class="back-link" href="<?= BASE_URL ?>/admin/dashboard.php"><?= icon('arrow-left', 16) ?> Back to applications</a>

    <?php $latestAppId = latest_application_id($id);
    if ($latestAppId !== $id): ?>
    <div class="alert alert-error" style="margin:14px 0;display:flex;align-items:center;gap:8px;">
        <?= icon('alert', 16) ?> This application was superseded by the student's course-change application
        — <a href="<?= BASE_URL ?>/admin/view.php?id=<?= $latestAppId ?>" style="font-weight:700;color:inherit;">review application #<?= $latestAppId ?> instead</a>.
        Decisions made here will not show on the student's portal.
    </div>
    <?php endif; ?>

    <?php if ($m = flash('err')): ?><div class="alert alert-error" style="margin:14px 0;"><?= e($m) ?></div><?php endif; ?>

    <div class="profile-card" style="margin:16px 0 24px;max-width:none;">
        <div class="profile-head">
            <div class="avatar"><?= e($initials) ?></div>
            <div>
                <div class="p-name"><?= e($app['last_name'] . ', ' . $app['first_name'] . ' ' . ($app['ext_name'] ?? '')) ?></div>
                <div class="p-meta">
                    <?= $app['applicant_type'] === 'new' ? e($app['category'] ?? 'New Student') : 'Old Student' ?>
                    &middot; <?= e($app['course']) ?> &middot; <?= e(year_level_label((int)($app['year_level'] ?? 1))) ?>
                    <?= $block ? ' &middot; ' . e($block['name']) : '' ?>
                </div>
            </div>
            <div class="p-status">
                <small>Status</small>
                <span class="status-tag st-<?= e($app['status']) ?>"><?= e(ucfirst($app['status'])) ?></span>
            </div>
        </div>
    </div>

    <div class="review-grid">
        <div class="mini-card">
            <h3><?= icon('user', 16) ?> Applicant Info</h3>
            <div class="side-info">
                <div class="row"><span>Sex</span><span><?= e($app['sex']) ?></span></div>
                <div class="row"><span>Date of Birth</span><span><?= e(date('M j, Y', strtotime($app['dob']))) ?></span></div>
                <div class="row"><span>Place of Birth</span><span><?= e($app['pob']) ?></span></div>
                <div class="row"><span>Civil Status</span><span><?= e($app['civil_status']) ?></span></div>
                <div class="row"><span>Email</span><span><?= e($app['email'] ?: '-') ?></span></div>
                <div class="row"><span>Phone</span><span><?= e($app['phone'] ?: '-') ?></span></div>
                <div class="row"><span>Block</span><span><?= $block ? e($block['name']) : '-' ?></span></div>
                <div class="row"><span>Applied</span><span><?= e(date('M j, Y g:i A', strtotime($app['created_at']))) ?></span></div>
                <div class="row"><span>Status</span><span><span class="badge <?= e($app['status']) ?>"><?= e($app['status']) ?></span></span></div>
            </div>

            <?php if (admin_role() === 'department'): ?>
            <h3 style="margin-top:26px;"><?= icon('scale', 16) ?> Decision</h3>
            <form method="post" style="display:flex;flex-direction:column;gap:9px;">
                <?php if ($app['status'] === 'enrolled'): ?>
                    <div style="background:var(--green-soft);color:var(--green);border-radius:12px;padding:13px 15px;font-weight:700;font-size:.87rem;display:flex;align-items:center;gap:8px;">
                        <?= icon('check-circle', 18) ?> Enrolled — nothing left to do.
                    </div>
                <?php elseif ($app['status'] === 'rejected'): ?>
                    <div style="background:var(--red-soft);color:var(--red);border-radius:12px;padding:13px 15px;font-weight:700;font-size:.87rem;display:flex;align-items:center;gap:8px;">
                        <?= icon('x', 18) ?> Application rejected.
                    </div>
                <?php else: ?>
                    <button class="btn btn-sm btn-green" name="app_status" value="enrolled"
                        <?= $enrollMissing ? 'disabled style="opacity:.5;cursor:not-allowed;"' : '' ?>>
                        <?= icon('cap', 15) ?> Mark Enrolled</button>
                    <?php if ($enrollMissing): ?>
                        <small style="color:var(--muted);font-size:.78rem;">Needs: <?= e(implode(', ', $enrollMissing)) ?></small>
                    <?php endif; ?>
                <?php endif; ?>
                <button class="btn btn-sm btn-secondary" name="app_status" value="submitted"><?= icon('rotate', 15) ?> Back to Review</button>
                <?php if (!in_array($app['status'], ['rejected', 'enrolled'])): ?>
                <button class="btn btn-sm btn-red" name="app_status" value="rejected" onclick="return confirm('Reject this application?');"><?= icon('x', 15) ?> Reject Application</button>
                <?php endif; ?>
            </form>
            <?php endif; ?>
        </div>

        <div>
            <div class="mini-card" style="margin-bottom:22px;">
                <h3><?= icon('folder', 16) ?> Submitted Documents (<?= count($docs) ?>)</h3>
                <?php if (!$docs): ?><p style="color:var(--muted);font-size:.88rem;">No documents uploaded yet.</p><?php endif; ?>
                <?php foreach ($docs as $d):
                    $docLabel = doc_label($d['doc_type']);
                    $docUrl   = BASE_URL . '/' . ltrim($d['file_path'], '/');
                    $isImg    = in_array(strtolower(pathinfo($d['file_name'], PATHINFO_EXTENSION)), ['jpg','jpeg','png']);
                ?>
                <div class="doc-row <?= e($d['status']) ?>">
                    <div class="doc-icon"><?= icon($docIcons[$d['doc_type']] ?? 'file', 18) ?></div>
                    <div class="doc-name">
                        <a href="#" class="docview" data-url="<?= e($docUrl) ?>" data-img="<?= $isImg ? 1 : 0 ?>" data-name="<?= e($docLabel) ?>"><?= e($docLabel) ?></a>
                        <small><?= e($d['file_name']) ?> &middot; <?= e(date('M j, Y', strtotime($d['uploaded_at']))) ?></small>
                        <?php if ($d['status'] === 'rejected' && $d['remarks']): ?>
                            <small style="color:var(--red);"><?= e($d['remarks']) ?></small>
                        <?php endif; ?>
                    </div>
                    <span class="step-pill <?= $d['status']==='approved'?'done':($d['status']==='rejected'?'pending':'waiting') ?>"><?= e(ucfirst($d['status'])) ?></span>
                    <button class="btn btn-sm btn-secondary docview" type="button" title="View" data-url="<?= e($docUrl) ?>" data-img="<?= $isImg ? 1 : 0 ?>" data-name="<?= e($docLabel) ?>"><?= icon('eye', 14) ?></button>
                    <form method="post" style="display:flex;gap:6px;">
                        <input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>">
                        <input type="hidden" name="remarks" value="">
                        <?php if ($d['doc_type'] === 'receipt'): ?>
                            <span class="sm tsec" title="Reviewed with the GCash payment below">Accounting</span>
                        <?php elseif (doc_office_ok($d['doc_type'])): ?>
                            <?php if ($d['status'] !== 'approved'): ?>
                            <button class="btn btn-sm btn-green" name="doc_action" value="approve" title="Approve"><?= icon('check', 14) ?></button>
                            <?php endif; ?>
                            <?php if ($d['status'] === 'pending'): ?>
                            <button class="btn btn-sm btn-red" name="doc_action" value="reject" title="Reject" onclick="return askReason(this);"><?= icon('x', 14) ?></button>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="sm tsec" title="Reviewed by <?= e(ucfirst(doc_office($d['doc_type']))) ?>"><?= e(ucfirst(doc_office($d['doc_type']))) ?></span>
                        <?php endif; ?>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="mini-card">
                <h3><?= icon('wallet', 16) ?> Payment</h3>
                <?php if ($payment): ?>
                <div class="doc-row <?= e($payment['status']) ?>">
                    <div class="doc-icon"><?= icon('receipt', 18) ?></div>
                    <div class="doc-name">
                        <?= !empty($app['is_course_change']) ? 'ID Fee' : 'Enrollment Fee' ?> via GCash
                        <small>&#8369;<?= number_format($payment['amount'],2) ?><?= $payment['ref_no'] ? ' &middot; Ref# ' . e($payment['ref_no']) : '' ?> &middot; <?= e(date('M j, Y', strtotime($payment['created_at']))) ?></small>
                        <?php if ($payment['status'] === 'rejected' && $payment['remarks']): ?>
                            <small style="color:var(--red);"><?= e($payment['remarks']) ?></small>
                        <?php endif; ?>
                    </div>
                    <span class="step-pill <?= $payment['status']==='confirmed'?'done':($payment['status']==='rejected'?'pending':'waiting') ?>"><?= e(ucfirst($payment['status'])) ?></span>
                    <?php if ($receiptDoc):
                        $recUrl = BASE_URL . '/' . ltrim($receiptDoc['file_path'], '/');
                        $recImg = in_array(strtolower(pathinfo($receiptDoc['file_name'], PATHINFO_EXTENSION)), ['jpg','jpeg','png']);
                    ?>
                    <button class="btn btn-sm btn-secondary docview" type="button" title="View Receipt"
                            data-url="<?= e($recUrl) ?>" data-img="<?= $recImg ? 1 : 0 ?>" data-name="Payment Receipt"><?= icon('eye', 14) ?></button>
                    <?php endif; ?>
                    <?php if (admin_can('accounting')): ?>
                    <form method="post" style="display:flex;gap:6px;">
                        <input type="hidden" name="remarks" value="">
                        <?php if ($payment['status'] !== 'confirmed'): ?>
                        <button class="btn btn-sm btn-green" name="payment_action" value="confirm">Confirm</button>
                        <?php endif; ?>
                        <?php if ($payment['status'] === 'pending'): ?>
                        <button class="btn btn-sm btn-red" name="payment_action" value="reject" onclick="return askReason(this);">Reject</button>
                        <?php endif; ?>
                    </form>
                    <?php endif; ?>
                </div>
                <?php else: ?><p style="color:var(--muted);font-size:.88rem;">No payment submitted yet.</p><?php endif; ?>
                <?php if ($rootPayment):
                    $rpUrl = $rootReceiptDoc ? BASE_URL . '/' . ltrim($rootReceiptDoc['file_path'], '/') : '';
                    $rpImg = $rootReceiptDoc && in_array(strtolower(pathinfo($rootReceiptDoc['file_name'], PATHINFO_EXTENSION)), ['jpg','jpeg','png']);
                ?>
                <div class="doc-row <?= e($rootPayment['status']) ?>" style="opacity:.85;">
                    <div class="doc-icon"><?= icon('receipt', 18) ?></div>
                    <div class="doc-name">
                        Enrollment Fee via GCash
                        <small>&#8369;<?= number_format($rootPayment['amount'],2) ?><?= $rootPayment['ref_no'] ? ' &middot; Ref# ' . e($rootPayment['ref_no']) : '' ?> &middot; <?= e(date('M j, Y', strtotime($rootPayment['created_at']))) ?> &middot; original application</small>
                    </div>
                    <span class="step-pill <?= $rootPayment['status']==='confirmed'?'done':($rootPayment['status']==='rejected'?'pending':'waiting') ?>"><?= e(ucfirst($rootPayment['status'])) ?></span>
                    <?php if ($rpUrl): ?>
                    <button class="btn btn-sm btn-secondary docview" type="button" title="View Receipt"
                            data-url="<?= e($rpUrl) ?>" data-img="<?= $rpImg ? 1 : 0 ?>" data-name="Enrollment Fee Receipt"><?= icon('eye', 14) ?></button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="mini-card" style="margin-top:22px;">
                <h3><?= icon('grid', 16) ?> Block Assignment <small style="font-weight:400;color:var(--muted);">(Department)</small></h3>
                <?php
                $reqBlock = $app['requested_block_id']
                    ? db()->query("SELECT * FROM blocks WHERE id=" . (int)$app['requested_block_id'])->fetch_assoc()
                    : null;
                if ($reqBlock): ?>
                <div class="doc-row" style="background:var(--blue-soft,#e8f0fe);border-radius:10px;">
                    <div class="doc-icon"><?= icon('grid', 18) ?></div>
                    <div class="doc-name">
                        <?= $block ? 'Change requested:' : 'Student requested' ?> <strong><?= e($reqBlock['name']) ?></strong>
                        <small><?= $block ? 'from ' . e($block['name']) . ' — ' : '' ?>pending approval</small>
                        <?php if (trim((string)$app['request_reason']) !== ''): ?>
                            <small style="color:var(--navy);font-weight:600;">Reason: <?= e($app['request_reason']) ?></small>
                        <?php endif; ?>
                    </div>
                    <?php if (admin_can('department')): ?>
                    <form method="post" style="display:flex;gap:6px;">
                        <button class="btn btn-sm btn-green" name="assign_block" value="<?= (int)$reqBlock['id'] ?>" title="Approve request"><?= icon('check', 14) ?></button>
                        <button class="btn btn-sm btn-red" name="decline_block" value="1" title="Decline request" onclick="return confirm('Decline this block request? The student will pick again.');"><?= icon('x', 14) ?></button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if (!admin_can('department')): ?>
                    <p style="color:var(--muted);font-size:.85rem;">Block assignment is handled by the Department.
                    <?= $block ? 'Currently: <strong>' . e($block['name']) . '</strong>' : 'Not yet assigned.' ?></p>
                <?php elseif (!in_array($app['status'], ['rejected','withdrawn'])): ?>
                <form method="post" style="display:flex;flex-direction:column;gap:10px;">
                    <div class="field" style="text-align:left;">
                        <label for="assign_block">Class Block</label>
                        <select id="assign_block" name="assign_block" required>
                            <?php foreach ($blockOptions as $b):
                                $left = (int)$b['capacity'] - (int)$b['taken'];
                                $full = $left <= 0 && (int)$app['block_id'] !== (int)$b['id'];
                            ?>
                            <option value="<?= (int)$b['id'] ?>"
                                <?= (int)$app['block_id'] === (int)$b['id'] ? 'selected' : '' ?>
                                <?= $full ? 'disabled' : '' ?>>
                                <?= e($b['name']) ?> (<?= e($b['course']) ?>) — <?= $full ? 'FULL' : $left . ' slot' . ($left === 1 ? '' : 's') . ' left' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-sm" type="submit"><?= icon('check', 15) ?> <?= $block ? 'Reassign Block' : 'Assign Block' ?></button>
                </form>
                <?php else: ?>
                    <p style="color:var(--muted);font-size:.88rem;">Not available — application is <?= e($app['status']) ?>.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php include __DIR__ . '/nav_close.php'; ?>

<div id="docModal" style="display:none;position:fixed;inset:0;background:rgba(11,26,48,.78);z-index:1000;align-items:center;justify-content:center;padding:24px;">
    <div style="background:var(--surface);border-radius:16px;max-width:880px;width:100%;max-height:92vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,.35);">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:13px 18px;border-bottom:1px solid var(--border);">
            <strong id="docTitle" style="font-size:.95rem;"></strong>
            <button class="btn btn-sm btn-secondary" type="button" id="docClose"><?= icon('x', 15) ?></button>
        </div>
        <div id="docBody" style="flex:1;overflow:auto;background:#f4f6fa;display:flex;align-items:center;justify-content:center;min-height:200px;"></div>
    </div>
</div>
<script>
function askReason(btn) {
    var r = prompt('Reason for rejection (shown to the student, optional):');
    if (r === null) return false;
    btn.form.querySelector('[name=remarks]').value = r;
    return true;
}
(function () {
    var dm = document.getElementById('docModal'),
        db = document.getElementById('docBody'),
        dt = document.getElementById('docTitle');
    function closeDoc() { dm.style.display = 'none'; db.innerHTML = ''; }
    document.querySelectorAll('.docview').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            dt.textContent = el.dataset.name;
            db.innerHTML = el.dataset.img === '1'
                ? '<img src="' + el.dataset.url + '" style="max-width:100%;max-height:80vh;display:block;" alt="">'
                : '<iframe src="' + el.dataset.url + '" style="width:100%;height:80vh;border:0;"></iframe>';
            dm.style.display = 'flex';
        });
    });
    document.getElementById('docClose').addEventListener('click', closeDoc);
    dm.addEventListener('click', function (e) { if (e.target === dm) closeDoc(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeDoc(); });
})();
</script>

<?php if ($m = flash('ok')): ?>
<div id="okModal" style="position:fixed;inset:0;background:rgba(11,26,48,.5);display:flex;align-items:center;justify-content:center;z-index:1000;padding:20px;">
    <div style="background:var(--surface);border-radius:20px;padding:36px 40px;max-width:380px;width:100%;text-align:center;box-shadow:0 24px 60px rgba(0,0,0,.25);animation:okPop .18s ease-out;">
        <div style="width:64px;height:64px;border-radius:50%;background:var(--green-soft);color:var(--green);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
            <?= icon('check-circle', 34) ?>
        </div>
        <h3 style="margin:0 0 6px;font-size:1.15rem;">Done!</h3>
        <p style="color:var(--muted);font-size:.9rem;margin:0 0 22px;"><?= e($m) ?></p>
        <button class="btn" style="width:100%;" onclick="document.getElementById('okModal').remove()">OK</button>
    </div>
</div>
<style>@keyframes okPop{from{transform:scale(.92);opacity:0}to{transform:scale(1);opacity:1}}</style>
<script>
document.getElementById('okModal').addEventListener('click', function (e) {
    if (e.target === this) this.remove();
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
