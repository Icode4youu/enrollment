<?php
require_once __DIR__ . '/config.php';
require_user();
$app = current_applicant();
if (!$app) redirect('index.php');

// Registrar documents differ per applicant type
$docs = $app['applicant_type'] === 'new'
    ? ['psa' => ['PSA Birth Certificate', 'file'], 'report_card' => ['Report Card / Old Card / TOR', 'clipboard'], 'picture_2x2' => ['2x2 Picture', 'image']]
    : ['school_id' => ['School ID', 'id-card'], 'clearance' => ['Clearance', 'shield']];

$errors = [];

// Latest status per doc type (rejected docs must be re-uploaded)
$latest = db()->query(
    "SELECT doc_type, file_name, status, remarks FROM documents
     WHERE applicant_id=" . (int)$app['id'] . " ORDER BY id DESC"
)->fetch_all(MYSQLI_ASSOC);
$docStatus = $docFile = $docRemarks = [];
foreach ($latest as $d) {
    if (!isset($docStatus[$d['doc_type']])) {
        $docStatus[$d['doc_type']]  = $d['status'];
        $docFile[$d['doc_type']]    = $d['file_name'];
        $docRemarks[$d['doc_type']] = $d['remarks'];
    }
}
$have = array_keys(array_filter($docStatus, fn($s) => $s !== 'rejected'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($docs as $type => [$label, $icn]) {
        $res = save_document($app['id'], $type, 'file_' . $type);
        if (is_array($res) && isset($res['error'])) $errors[] = "$label: {$res['error']}";
    }
    $fresh = db()->query(
        "SELECT doc_type, status FROM documents
         WHERE applicant_id=" . (int)$app['id'] . " ORDER BY id DESC"
    )->fetch_all(MYSQLI_ASSOC);
    $st = [];
    foreach ($fresh as $d) if (!isset($st[$d['doc_type']])) $st[$d['doc_type']] = $d['status'];
    foreach ($docs as $type => [$label, $icn]) {
        if (!isset($st[$type]) || $st[$type] === 'rejected') $errors[] = "$label is required.";
    }
    if (!$errors) {
        [$url, $step] = next_enrollment_step($app);
        db()->query("UPDATE applicants SET current_step=" . (int)$step . " WHERE id=" . (int)$app['id']);
        redirect($url);
    }
    $latest = db()->query(
        "SELECT doc_type, file_name, status, remarks FROM documents
         WHERE applicant_id=" . (int)$app['id'] . " ORDER BY id DESC"
    )->fetch_all(MYSQLI_ASSOC);
    $docStatus = $docFile = $docRemarks = [];
    foreach ($latest as $d) {
        if (!isset($docStatus[$d['doc_type']])) {
            $docStatus[$d['doc_type']]  = $d['status'];
            $docFile[$d['doc_type']]    = $d['file_name'];
            $docRemarks[$d['doc_type']] = $d['remarks'];
        }
    }
    $have = array_keys(array_filter($docStatus, fn($s) => $s !== 'rejected'));
}

$page_title = 'Registrar Requirements';
include __DIR__ . '/includes/header.php';
?>
<?php $step_active = 1; include __DIR__ . '/includes/step_track.php'; ?>

<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<div class="card card-narrow">
    <div class="step-head">
        <span class="step-head-icn"><?= icon('clipboard', 24) ?></span>
        <div>
            <h2 class="card-title" style="text-align:left;margin:0 0 4px;">Registrar Requirements</h2>
            <p class="card-sub" style="text-align:left;margin:0;">Upload the required documents &mdash; no campus visit needed.</p>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data">
        <?php foreach ($docs as $type => [$label, $icn]):
            $st = $docStatus[$type] ?? null;
            $rejected = $st === 'rejected';
            $done = $st && !$rejected;
        ?>
        <div class="upload-row <?= $rejected ? 'is-rejected' : '' ?>">
            <span class="u-icn"><?= icon($icn, 20) ?></span>
            <span class="u-label">
                Upload <?= e($label) ?>
                <?php if ($rejected): ?>
                    <small class="rejected-tag">Rejected &mdash; please upload a new file</small>
                    <?php if ($docRemarks[$type]): ?>
                        <small class="rejected-tag" style="display:block;">Reason: <?= e($docRemarks[$type]) ?></small>
                    <?php endif; ?>
                <?php elseif ($done): ?>
                    <small class="uploaded-tag">Uploaded: <?= e($docFile[$type]) ?></small>
                <?php else: ?>
                    <small>JPG, PNG or PDF &middot; max 5 MB</small>
                <?php endif; ?>
            </span>
            <input type="file" name="file_<?= e($type) ?>" accept=".jpg,.jpeg,.png,.pdf" <?= $done ? '' : 'required' ?>>
            <span class="chosen-file" style="font-size:.78rem;color:var(--muted);"></span>
        </div>
        <?php endforeach; ?>
        <p class="file-hint">Clear photos or scans are accepted.</p>
        <div class="form-actions">
            <button class="btn" type="submit"><?= icon('send', 17) ?> Submit Documents</button>
        </div>
    </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
