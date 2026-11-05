<?php
// Numbered step tracker. Set $step_active (1-5) before including.
$__steps = [
    1 => 'Registrar',
    2 => 'Medical',
    3 => 'Accounting',
    4 => 'ID',
    5 => 'Blocking',
];
$__active = $step_active ?? 1;
?>
<div class="step-track">
    <?php foreach ($__steps as $n => $lbl):
        $cls = $n < $__active ? 'done' : ($n === $__active ? 'current' : '');
    ?>
        <div class="st-item <?= $cls ?>">
            <span class="st-dot"><?= $n < $__active ? icon('check', 15) : $n ?></span>
            <span class="st-lbl"><?= $lbl ?></span>
        </div>
        <?php if ($n < count($__steps)): ?><div class="st-line <?= $n < $__active ? 'done' : '' ?>"></div><?php endif; ?>
    <?php endforeach; ?>
</div>
