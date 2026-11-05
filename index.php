<?php
require_once __DIR__ . '/config.php';
$page_title = 'Online Enrollment';
$enrOpen  = enrollment_is_open();
$enrClose = setting('enrollment_close');
include __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <span class="hero-orb orb-1"></span>
    <span class="hero-orb orb-2"></span>
    <span class="hero-orb orb-3"></span>
    <div class="hero-wrap">
        <div class="hero-left">
            <span class="kicker"><span class="kdot"<?= $enrOpen ? '' : ' style="background:var(--red);"' ?>></span> <?= $enrOpen ? 'Enrollment Open' : 'Enrollment Closed' ?> &mdash; <?= e(sy_label()) ?><?= $enrOpen && $enrClose ? ' &middot; until ' . e(date('F j, Y', strtotime($enrClose))) : '' ?></span>
            <h2>Enroll online.<br><em>Skip the lines.</em></h2>
            <p class="sub">Complete your entire SEAIT enrollment from home &mdash; submit requirements,
            pay via GCash, and request your class block in minutes.</p>
            <div class="hero-ctas">
                <?php if (current_user()): ?>
                    <a class="btn btn-lg" href="<?= BASE_URL ?>/dashboard.php"><?= icon('user', 19) ?> Go to My Dashboard</a>
                <?php else: ?>
                    <a class="btn btn-lg" href="<?= BASE_URL ?>/register.php"><?= icon('user', 19) ?> Create Free Account</a>
                    <a class="btn-ghost" href="<?= BASE_URL ?>/login.php">Login <?= icon('arrow-right', 16) ?></a>
                <?php endif; ?>
            </div>
            <div class="hero-stats">
                <div class="hstat"><strong>100%</strong><span>Online process</span></div>
                <div class="hstat"><strong>6</strong><span>Class blocks</span></div>
                <div class="hstat"><strong>&#8369;<?= number_format(ENROLLMENT_FEE, 0) ?></strong><span>Enrollment fee</span></div>
            </div>
        </div>

        <div class="hero-card-wrap">
            <span class="float-chip fc-1"><?= icon('check', 13) ?> Documents verified</span>
            <span class="float-chip fc-2"><?= icon('grid', 13) ?> 6 blocks open</span>
            <div class="hero-card">
                <img src="<?= BASE_URL ?>/assets/img/seait-logo.png" alt="SEAIT" style="width:52px;height:52px;margin-bottom:14px;">
                <h3>Start your application</h3>
                <p class="hc-sub">Choose your applicant type</p>
                <?php if (!$enrOpen): ?>
                <div style="background:var(--red-soft);color:var(--red);border-radius:14px;padding:16px 18px;font-size:.88rem;font-weight:600;text-align:center;margin-bottom:14px;">
                    <?= icon('clock', 18) ?><br>
                    Online enrollment is currently closed.
                    <?php if ($o = setting('enrollment_open')): ?><br><small style="font-weight:500;">Opens <?= e(date('F j, Y', strtotime($o))) ?></small><?php endif; ?>
                </div>
                <?php else: ?>
                <a class="pick-tile" href="<?= BASE_URL ?>/new_student.php">
                    <span class="tile-icn"><?= icon('cap', 26) ?></span>
                    <span class="pt-text">
                        <strong>New / Transferee</strong>
                        <small>Freshmen, returnees &amp; transferees</small>
                    </span>
                    <span class="pt-arrow"><?= icon('chevron-right', 20) ?></span>
                </a>
                <a class="pick-tile" href="<?= BASE_URL ?>/old_student.php">
                    <span class="tile-icn"><?= icon('users', 26) ?></span>
                    <span class="pt-text">
                        <strong>Old Student</strong>
                        <small>Continuing students of SEAIT</small>
                    </span>
                    <span class="pt-arrow"><?= icon('chevron-right', 20) ?></span>
                </a>
                <?php endif; ?>
                <div class="hc-flow">
                    <?php foreach ([['id-card','Registrar'],['medical','Medical'],['wallet','Payment'],['card','ID'],['grid','Block'],['calendar','Schedule']] as $f): ?>
                    <span class="hf-step"><?= icon($f[0], 12) ?><?= $f[1] ?></span>
                    <?php endforeach; ?>
                </div>
                <p class="hc-note">Already applied? <a href="<?= BASE_URL ?>/dashboard.php">Go to your dashboard</a></p>
            </div>
        </div>
    </div>
</section>

<?php $anns = active_announcements(); if ($anns): ?>
<section style="padding:0 20px;margin:-10px auto 34px;max-width:1180px;">
    <?php foreach ($anns as $an): ?>
    <div style="background:var(--orange-soft);border:1px solid rgba(242,101,34,.25);border-radius:14px;padding:14px 20px;display:flex;gap:13px;align-items:flex-start;margin-bottom:12px;">
        <span style="color:var(--orange-dark);flex-shrink:0;margin-top:1px;"><?= icon('megaphone', 18) ?></span>
        <div>
            <strong style="display:block;color:var(--navy);font-size:.9rem;"><?= e($an['title']) ?></strong>
            <span style="font-size:.83rem;color:#6b4d21;line-height:1.55;"><?= nl2br(e($an['body'])) ?></span>
        </div>
    </div>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<section class="how">
    <div class="how-inner">
        <span class="sec-kicker">Simple Process</span>
        <h3>Enroll in 4 easy steps</h3>
        <p class="sec-sub">Everything happens right here in the portal.</p>
        <div class="how-grid">
            <div class="how-step anim" style="animation-delay:0.00s;">
                <span class="how-num">1</span>
                <span class="how-icn"><?= icon('user', 24) ?></span>
                <h4>Create your account</h4>
                <p>Register with your email to save your progress and track your application.</p>
            </div>
            <div class="how-step anim" style="animation-delay:0.08s;">
                <span class="how-num">2</span>
                <span class="how-icn"><?= icon('upload', 24) ?></span>
                <h4>Upload requirements</h4>
                <p>Submit clear photos or scans of your documents &mdash; Registrar &amp; Medical.</p>
            </div>
            <div class="how-step anim" style="animation-delay:0.16s;">
                <span class="how-num">3</span>
                <span class="how-icn"><?= icon('wallet', 24) ?></span>
                <h4>Pay via GCash</h4>
                <p>Scan the QR code and upload your receipt for the &#8369;<?= number_format(ENROLLMENT_FEE, 0) ?> enrollment fee.</p>
            </div>
            <div class="how-step anim" style="animation-delay:0.24s;">
                <span class="how-num">4</span>
                <span class="how-icn"><?= icon('grid', 24) ?></span>
                <h4>Pick your block</h4>
                <p>Request a class block and schedule — the Department approves it on review.</p>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
