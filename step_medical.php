<?php
require_once __DIR__ . '/config.php';
require_user();
$app = current_applicant();
if (!$app) redirect('index.php');

$errors = [];

// Latest medical_form status — a rejected upload must be re-uploaded
$latest = db()->query(
    "SELECT status, file_name, remarks FROM documents WHERE applicant_id=" . (int)$app['id'] .
    " AND doc_type='medical_form' ORDER BY id DESC LIMIT 1"
)->fetch_assoc();
$docStatus = $latest['status'] ?? null;
$docFile   = $latest['file_name'] ?? '';
$docRemark = $latest['remarks'] ?? '';
$rejected  = $docStatus === 'rejected';
$done      = $docStatus && !$rejected;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = save_document($app['id'], 'medical_form', 'medical_form');
    if (is_array($res) && isset($res['error'])) {
        $errors[] = $res['error'];
    } else {
        $check = db()->query(
            "SELECT status FROM documents WHERE applicant_id=" . (int)$app['id'] .
            " AND doc_type='medical_form' ORDER BY id DESC LIMIT 1"
        )->fetch_assoc();
        if ($check && $check['status'] !== 'rejected') {
            [$url, $step] = next_enrollment_step($app);
            db()->query("UPDATE applicants SET current_step=" . (int)$step . " WHERE id=" . (int)$app['id']);
            redirect($url);
        }
        $errors[] = 'Please attach your medical form.';
    }
}

$page_title = 'Medical';
include __DIR__ . '/includes/header.php';
?>
<?php $step_active = 2; include __DIR__ . '/includes/step_track.php'; ?>

<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<div class="card card-narrow">
    <div class="step-head">
        <span class="step-head-icn"><?= icon('medical', 24) ?></span>
        <div>
            <h2 class="card-title" style="text-align:left;margin:0 0 4px;">Medical</h2>
            <p class="card-sub" style="text-align:left;margin:0;">Upload your medical form to proceed to the next step.</p>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data">
        <div class="upload-row <?= $rejected ? 'is-rejected' : '' ?>">
            <span class="u-icn"><?= icon('upload', 20) ?></span>
            <span class="u-label">Upload Medical Form
                <?php if ($rejected): ?>
                    <small class="rejected-tag">Previous file was rejected &mdash; upload a new one</small>
                    <?php if ($docRemark): ?>
                        <small class="rejected-tag" style="display:block;">Reason: <?= e($docRemark) ?></small>
                    <?php endif; ?>
                <?php elseif ($done): ?>
                    <small class="uploaded-tag">Uploaded: <?= e($docFile) ?></small>
                <?php else: ?>
                    <small>JPG, PNG or PDF &middot; max 5 MB</small>
                <?php endif; ?>
            </span>
            <input type="file" name="medical_form" accept=".jpg,.jpeg,.png,.pdf" <?= $done ? '' : 'required' ?>>
            <span class="chosen-file" style="font-size:.78rem;color:var(--muted);"></span>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit"><?= icon('send', 17) ?> Submit</button>
        </div>
    </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
