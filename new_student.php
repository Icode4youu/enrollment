<?php
require_once __DIR__ . '/config.php';
require_user();
$page_title = 'New / Transferee';
include __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <span class="hero-orb orb-1"></span>
    <span class="hero-orb orb-3"></span>
    <div class="hero-wrap">
        <div class="hero-left">
            <span class="kicker"><span class="kdot"></span> New Student / Transferee &middot; <?= e(sy_label()) ?></span>
            <h2>Start your<br><em>journey.</em></h2>
            <p class="sub">Fill up the pre-registration form to begin your SEAIT application.
            The whole process takes about 5 minutes &mdash; everything happens right here.</p>
            <div class="journey-steps">
                <div class="jstep"><span>1</span> Answer the pre-registration form</div>
                <div class="jstep"><span>2</span> Upload your requirements</div>
                <div class="jstep"><span>3</span> Pay &#8369;<?= number_format(ENROLLMENT_FEE, 0) ?> via GCash</div>
                <div class="jstep"><span>4</span> Pick your class block &amp; schedule</div>
            </div>
        </div>

        <div class="hero-card-wrap">
            <span class="float-chip fc-1"><?= icon('clock', 13) ?> ~5 minutes</span>
            <div class="hero-card">
                <img src="<?= BASE_URL ?>/assets/img/seait-logo.png" alt="SEAIT" style="width:48px;height:48px;margin-bottom:14px;">
                <h3>What you'll need</h3>
                <p class="hc-sub">Prepare these before you start</p>
                <ul class="req-list">
                    <li><?= icon('check', 15) ?> PSA Birth Certificate <small>(photo/scan)</small></li>
                    <li><?= icon('check', 15) ?> Report Card / Form 138</li>
                    <li><?= icon('check', 15) ?> 2x2 ID Picture</li>
                    <li><?= icon('check', 15) ?> Certificate of Good Moral</li>
                    <li><?= icon('check', 15) ?> GCash for the &#8369;<?= number_format(ENROLLMENT_FEE, 0) ?> fee</li>
                </ul>
                <?php if (enrollment_is_open()): ?>
                <a class="btn btn-lg btn-block" href="<?= BASE_URL ?>/preregistration.php"><?= icon('arrow-right', 18) ?> Start Pre-registration</a>
                <?php else: ?>
                <div style="background:var(--red-soft);color:var(--red);border-radius:14px;padding:14px 16px;font-size:.87rem;font-weight:600;text-align:center;">
                    Online enrollment is currently closed.
                </div>
                <?php endif; ?>
                <p class="hc-note">Your progress is saved to your account &mdash; balikan mo anytime.</p>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
