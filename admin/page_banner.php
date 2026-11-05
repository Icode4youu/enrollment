<?php
// Shared page hero — expects: $bannerTitle, optional $bannerSub, $bannerIcon, $bannerArt
$bannerIcon = $bannerIcon ?? 'shield';
$bannerArt  = $bannerArt ?? 'cap';
?>
<div class="dash-banner">
    <div>
        <div class="dash-date"><?= e(date('l, F j, Y')) ?></div>
        <h2><?= e($bannerTitle) ?></h2>
        <?php if (!empty($bannerSub)): ?><p><?= e($bannerSub) ?></p><?php endif; ?>
        <span class="dash-badge"><?= icon($bannerIcon, 13) ?> <?= e(ucfirst(admin_role())) ?> Portal</span>
    </div>
    <div class="dash-art"><?= icon($bannerArt, 110) ?></div>
</div>
