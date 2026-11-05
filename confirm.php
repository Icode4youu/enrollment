<?php
require_once __DIR__ . '/config.php';
require_user();

$app = current_applicant();
if (!$app || $app['applicant_type'] !== 'new') redirect('index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    db()->query("UPDATE applicants SET status='requirements', current_step=1 WHERE id=" . (int)$app['id']);
    redirect('step_registrar.php');
}

// Requirement list per category (shown before upload)
$reqs = [
    'New College Student' => ['PSA Birth Certificate', 'Report Card (Form 138)', '2x2 Picture', 'Medical Form'],
    'Returnee'            => ['PSA Birth Certificate', 'Old Card / Previous Records', '2x2 Picture', 'Medical Form'],
    'Transferee'          => ['PSA Birth Certificate', 'Transcript of Records / Honorable Dismissal', '2x2 Picture', 'Medical Form'],
    'Former Student'      => ['PSA Birth Certificate', 'Old Card / Previous Records', '2x2 Picture', 'Medical Form'],
];
$list = $reqs[$app['category']] ?? ['PSA Birth Certificate', 'Report Card', '2x2 Picture', 'Medical Form'];

$schedule = date('F j, Y', strtotime('+7 days'));

$page_title = 'Confirm Pre-Registration';
include __DIR__ . '/includes/header.php';
?>
<div class="banner">
    <h2>ONLINE PRE-REGISTRATION</h2>
    <div class="sem"><?= e(sy_label()) ?></div>
    <div class="for">(for New College Student, Returnee, Transferee and Former Student)</div>
</div>

<div class="card card-narrow">
    <div class="info-block">
        <h3><?= icon('calendar', 20) ?> Enrollment Period</h3>
        <p>Opens <?= e($schedule) ?> &mdash; all steps are completed <strong>online</strong>.</p>
    </div>
    <hr style="border:none;border-top:1px solid var(--border);margin:20px 0;">
    <div class="info-block">
        <h3><?= icon('clipboard', 20) ?> Requirements <small style="font-weight:400;color:var(--muted);">(to be uploaded online)</small></h3>
        <ol>
            <?php foreach ($list as $r): ?><li><?= e($r) ?></li><?php endforeach; ?>
            <li>&#8369; <?= number_format(ENROLLMENT_FEE, 0) ?> enrollment fee (GCash)</li>
        </ol>
    </div>
    <p style="color:#777;font-size:.88rem;margin-bottom:18px;">
        No need to visit the campus &mdash; you will upload scanned copies or clear photos
        of your documents in the next steps.
    </p>
    <form method="post" class="form-actions">
        <button class="btn btn-lg" type="submit" style="padding:14px 60px;">CONFIRM <?= icon('arrow-right', 18) ?></button>
    </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
