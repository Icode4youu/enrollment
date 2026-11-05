<?php
require_once __DIR__ . '/config.php';
require_user();
$app = current_applicant();
if (!$app) redirect('index.php');

$page_title = 'Submitted';
include __DIR__ . '/includes/header.php';
?>
<div class="banner"><h2>ONLINE ENROLLMENT</h2></div>

<div class="modal-overlay show" id="successModal">
    <div class="modal">
        <div class="check"><?= icon('check', 40) ?></div>
        <h2>SUBMITTED SUCCESSFULLY!</h2>
        <p>Thank you!<br>You are a successful <?= e($app['course']) ?> student! :)</p>
        <div class="modal-actions">
            <a class="btn btn-secondary" href="<?= BASE_URL ?>/dashboard.php">Go to Dashboard</a>
            <button class="btn" onclick="closeModal('successModal')">Close</button>
        </div>
    </div>
</div>

<div class="card card-narrow" style="text-align:center;">
    <h2 class="card-title">Application Received</h2>
    <p class="card-sub">Your enrollment application is now being reviewed. The Department will
    approve your requested class block once your documents and payment are verified.<br>
    You can check your status anytime on your dashboard.</p>
    <a class="btn" href="<?= BASE_URL ?>/dashboard.php">View My Dashboard</a>
</div>
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
<script>
if (window.confetti) {
    confetti({ particleCount: 150, spread: 75, origin: { y: .55 } });
    setTimeout(function () { confetti({ particleCount: 90, angle: 60,  spread: 60, origin: { x: 0 } }); }, 280);
    setTimeout(function () { confetti({ particleCount: 90, angle: 120, spread: 60, origin: { x: 1 } }); }, 480);
}
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
