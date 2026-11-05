<?php
require_once __DIR__ . '/auth.php';
require_office();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enabled = isset($_POST['enrollment_enabled']) ? '1' : '0';
    $open    = trim($_POST['enrollment_open'] ?? '');
    $close   = trim($_POST['enrollment_close'] ?? '');
    $sy      = trim($_POST['school_year'] ?? '');
    $cap     = trim($_POST['enrollment_cap'] ?? '0');

    if ($open  && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $open))  $errors[] = 'Invalid opening date.';
    if ($close && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $close)) $errors[] = 'Invalid closing date.';
    if ($open && $close && $open > $close) $errors[] = 'Opening date must be before the closing date.';
    if (!$sy) $errors[] = 'School year label is required.';
    if ($cap !== '' && (!ctype_digit($cap) || (int)$cap < 0)) $errors[] = 'Enrollment cap must be a whole number (0 = no limit).';

    if (!$errors) {
        set_setting('enrollment_enabled', $enabled);
        set_setting('enrollment_open',    $open);
        set_setting('enrollment_close',   $close);
        set_setting('school_year',        $sy);
        set_setting('enrollment_cap',     $cap === '' ? '0' : $cap);
        flash('ok', 'Enrollment settings saved.');
        redirect('admin/settings.php');
    }
}

$enabled = setting('enrollment_enabled', '1') === '1';
$open    = setting('enrollment_open');
$close   = setting('enrollment_close');
$sy      = setting('school_year', '1st Semester S.Y. 2025-2026');
$cap     = setting('enrollment_cap', '0');
$isOpen  = enrollment_is_open();

$page_title = 'Admin - Settings';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
$bannerTitle = 'Enrollment Settings';
$bannerSub   = 'Control when students can file new applications.';
$bannerIcon  = 'settings'; $bannerArt = 'settings';
include __DIR__ . '/page_banner.php';
?>

    <?php if ($m = flash('ok')): ?><div class="alert alert-success" style="max-width:none;margin:0 0 18px;"><?= e($m) ?></div><?php endif; ?>
    <?php foreach ($errors as $err): ?><div class="alert alert-error" style="max-width:none;margin:0 0 12px;"><?= e($err) ?></div><?php endforeach; ?>

    <div class="mini-card" style="max-width:640px;">
        <h3><?= icon('settings', 17) ?> Enrollment Period</h3>
        <div style="margin-bottom:18px;padding:12px 15px;border-radius:12px;font-size:.87rem;font-weight:700;display:flex;align-items:center;gap:9px;<?= $isOpen ? 'background:var(--green-soft);color:var(--green);' : 'background:var(--red-soft);color:var(--red);' ?>">
            <?= icon($isOpen ? 'check-circle' : 'x', 17) ?>
            Enrollment is currently <?= $isOpen ? 'OPEN' : 'CLOSED' ?> to new applications.
        </div>
        <form method="post">
            <label style="display:flex;align-items:center;gap:10px;margin-bottom:18px;font-size:.9rem;font-weight:600;cursor:pointer;">
                <input type="checkbox" name="enrollment_enabled" value="1" <?= $enabled ? 'checked' : '' ?> style="width:17px;height:17px;">
                Accept new applications
            </label>
            <div class="form-grid">
                <div class="field">
                    <label>Opens on <small style="color:var(--muted);font-weight:400;">(blank = anytime)</small></label>
                    <input type="date" name="enrollment_open" value="<?= e($open) ?>">
                </div>
                <div class="field">
                    <label>Closes on <small style="color:var(--muted);font-weight:400;">(blank = no deadline)</small></label>
                    <input type="date" name="enrollment_close" value="<?= e($close) ?>">
                </div>
                <div class="field">
                    <label>Enrollment cap <small style="color:var(--muted);font-weight:400;">(0 = no limit)</small></label>
                    <input type="number" name="enrollment_cap" value="<?= e($cap) ?>" min="0" placeholder="e.g. 2000">
                </div>
                <div class="field full">
                    <label>School Year / Semester Label</label>
                    <input type="text" name="school_year" value="<?= e($sy) ?>" placeholder="e.g. 1st Semester S.Y. 2025-2026" required>
                </div>
            </div>
            <button class="btn btn-sm" type="submit"><?= icon('check', 15) ?> Save Settings</button>
        </form>
        <p style="margin-top:16px;color:var(--muted);font-size:.8rem;line-height:1.6;">
            When enrollment is closed, the application forms on the portal are hidden and submissions are rejected.
            Students with an ongoing application can still finish their steps.
        </p>
    </div>
<?php include __DIR__ . '/nav_close.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
