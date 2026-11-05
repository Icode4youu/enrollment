<?php
require_once __DIR__ . '/config.php';
require_user();
$app = current_applicant();
if (!$app) redirect('index.php');

$errors = [];

// Course-change re-applications pay the ID fee instead of the enrollment fee
$isCC     = !empty($app['is_course_change']);
$fee      = $isCC ? ID_FEE : ENROLLMENT_FEE;
$feeLabel = $isCC ? 'ID Fee' : 'Enrollment Fee';

// If the previous payment was rejected, the student may re-upload a receipt
$payment = db()->query(
    "SELECT * FROM payments WHERE applicant_id=" . (int)$app['id'] . " ORDER BY id DESC LIMIT 1"
)->fetch_assoc();
$payRejected = $payment && $payment['status'] === 'rejected';
$payDone     = $payment && !$payRejected;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = save_document($app['id'], 'receipt', 'receipt');
    $refNo = trim((string)($_POST['ref_no'] ?? ''));
    if (is_array($res) && isset($res['error'])) {
        $errors[] = $res['error'];
    } elseif (!$payment && !$res) {
        $errors[] = 'Please upload your receipt.';
    } elseif ($payRejected && !$res) {
        $errors[] = 'Please upload a new receipt.';
    } else {
        if ($payment) {
            if ($res) {
                $reset = $payRejected ? ", status='pending'" : '';
                $stmt = db()->prepare("UPDATE payments SET receipt_doc=?, ref_no=?" . $reset . " WHERE id=?");
                $stmt->bind_param('isi', $res, $refNo, $payment['id']);
                $stmt->execute();
            }
        } else {
            $stmt = db()->prepare("INSERT INTO payments (applicant_id, amount, ref_no, receipt_doc) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('idsi', $app['id'], $fee, $refNo, $res);
            $stmt->execute();
            log_activity($app['id'], 'Payment receipt submitted');
        }
        [$url, $step] = next_enrollment_step($app);
        $set = in_array($app['status'], ['blocking','submitted','verified','enrolled'], true)
            ? "current_step=" . (int)$step
            : "status='payment', current_step=" . (int)$step;
        db()->query("UPDATE applicants SET $set WHERE id=" . (int)$app['id']);
        redirect($url);
    }
}

// Best option: a pre-rendered GCash QR with the exact fee baked in
// (assets/img/gcash-qr-500.png / gcash-qr-350.png — real EMVCo payloads,
// amount pre-fills when scanned). Falls back to the school's static QR,
// then a deterministic QR-style placeholder.
$qrPayload = gcash_emv_payload($fee);
$feeQr = __DIR__ . '/assets/img/gcash-qr-' . (int)$fee . '.png';
$qrImg = __DIR__ . '/assets/img/gcash-qr.png';
$qrHasAmount = $qrPayload !== null && file_exists($feeQr);
if ($qrHasAmount) {
    $qr = '<img class="qr-box" src="' . BASE_URL . '/assets/img/gcash-qr-' . (int)$fee . '.png" alt="GCash QR — ₱' . number_format($fee, 2) . '">';
} elseif (file_exists($qrImg)) {
    $qr = '<img class="qr-box" src="' . BASE_URL . '/assets/img/gcash-qr.png" alt="GCash QR">';
} else {
$seed = crc32('SEAIT-GCASH'); mt_srand($seed);
$N = 21; $cell = 10;
$qr = '<svg class="qr-box" viewBox="0 0 ' . ($N * $cell) . ' ' . ($N * $cell) . '" xmlns="http://www.w3.org/2000/svg"><rect width="100%" height="100%" fill="#fff"/>';
$finder = function ($x, $y) use ($cell) {
    $s = '';
    for ($i = 0; $i < 7; $i++) for ($j = 0; $j < 7; $j++) {
        $edge = ($i == 0 || $i == 6 || $j == 0 || $j == 6);
        $core = ($i >= 2 && $i <= 4 && $j >= 2 && $j <= 4);
        if ($edge || $core)
            $s .= '<rect x="' . (($x + $j) * $cell) . '" y="' . (($y + $i) * $cell) . '" width="' . $cell . '" height="' . $cell . '" fill="#111"/>';
    }
    return $s;
};
for ($i = 0; $i < $N; $i++) for ($j = 0; $j < $N; $j++) {
    $inFinder = ($i < 8 && $j < 8) || ($i < 8 && $j > $N - 9) || ($i > $N - 9 && $j < 8);
    if (!$inFinder && mt_rand(0, 1))
        $qr .= '<rect x="' . ($j * $cell) . '" y="' . ($i * $cell) . '" width="' . $cell . '" height="' . $cell . '" fill="#111"/>';
}
$qr .= $finder(0, 0) . $finder(0, $N - 7) . $finder($N - 7, 0) . '</svg>';
}

$page_title = 'Accounting';
include __DIR__ . '/includes/header.php';
?>
<?php $step_active = 3; include __DIR__ . '/includes/step_track.php'; ?>

<?php if ($m = flash('ok')): ?><div class="alert alert-success"><?= e($m) ?></div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<div class="card card-narrow">
    <div class="step-head">
        <span class="step-head-icn"><?= icon('wallet', 24) ?></span>
        <div>
            <h2 class="card-title" style="text-align:left;margin:0 0 4px;">Accounting</h2>
            <p class="card-sub" style="text-align:left;margin:0;">Pay <strong>&#8369;<?= number_format($fee, 2) ?></strong> <?= e($feeLabel) ?> via GCash and upload the receipt.</p>
        </div>
    </div>

    <?php if ($payRejected): ?>
        <div class="alert alert-error" style="margin:0 auto 20px;">Your previous payment was rejected. Please upload a new receipt.</div>
    <?php endif; ?>

    <div style="text-align:center;">
        <?= $qr ?>
        <p style="font-weight:700;color:var(--navy);display:flex;align-items:center;justify-content:center;gap:8px;"><?= icon('qr', 20) ?> Scan to Pay (GCash)</p>
        <p style="color:var(--muted);font-size:.88rem;">Account Name: <span style="color:var(--blue);font-weight:600;">SEAIT-ACCOUNTING</span></p>
        <?php if ($qrHasAmount): ?>
        <p style="color:var(--green);font-size:.82rem;font-weight:600;">&#8369;<?= number_format($fee, 2) ?> is already filled in when you scan &mdash; just confirm the payment.</p>
        <?php endif; ?>
    </div>

    <form method="post" enctype="multipart/form-data" style="margin-top:22px;">
        <div class="field" style="margin-bottom:16px;text-align:left;">
            <label for="ref_no">GCash Reference Number</label>
            <input type="text" id="ref_no" name="ref_no" maxlength="60" placeholder="e.g. 1234 567 890123"
                   value="<?= e($_POST['ref_no'] ?? ($payment['ref_no'] ?? '')) ?>">
            <small style="color:var(--muted);font-size:.75rem;">Find it in your GCash transaction receipt &mdash; helps us verify faster.</small>
        </div>
        <div class="upload-row">
            <span class="u-icn"><?= icon('receipt', 20) ?></span>
            <span class="u-label">Upload Receipt
                <?php if ($payDone): ?>
                    <small class="uploaded-tag">Receipt submitted &middot; <?= e(ucfirst($payment['status'])) ?></small>
                <?php else: ?>
                    <small>JPG, PNG or PDF &middot; max 5 MB</small>
                <?php endif; ?>
            </span>
            <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf" <?= $payDone ? '' : 'required' ?>>
            <span class="chosen-file" style="font-size:.78rem;color:var(--muted);"></span>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit"><?= icon('send', 17) ?> Submit Receipt</button>
        </div>
    </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
