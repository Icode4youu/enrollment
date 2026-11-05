<?php
require_once __DIR__ . '/config.php';
if (!empty($_SESSION['user_id'])) redirect('dashboard.php');

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$reset = null;
if ($token !== '') {
    $hash = hash('sha256', $token);
    $stmt = db()->prepare(
        "SELECT * FROM password_resets
         WHERE token_hash=? AND used=0 AND expires_at > NOW()
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $reset = $stmt->get_result()->fetch_assoc();
}

$errors = [];
$done   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset) {
    $new  = $_POST['password'] ?? '';
    $new2 = $_POST['confirm_password'] ?? '';
    if (strlen($new) < 6)  $errors[] = 'Password must be at least 6 characters.';
    elseif ($new !== $new2) $errors[] = 'Passwords do not match.';
    else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $upd  = db()->prepare("UPDATE users SET password_hash=? WHERE id=?");
        $upd->bind_param('si', $hash, $reset['user_id']);
        $upd->execute();
        // Invalidate this token and any older ones for the same account
        $del = db()->prepare("UPDATE password_resets SET used=1 WHERE user_id=?");
        $del->bind_param('i', $reset['user_id']);
        $del->execute();
        $done = true;
    }
}

$page_title = 'Set New Password';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
    <div class="auth-side">
        <span class="wh-deco"><?= icon('cap', 190) ?></span>
        <img src="<?= BASE_URL ?>/assets/img/seait-logo.png" alt="SEAIT" class="auth-logo">
        <h3>Almost there</h3>
        <p>Choose a new password for your SEAIT portal account.</p>
    </div>
    <div class="auth-form">
        <h2 class="card-title">Set New Password</h2>

        <?php if ($done): ?>
            <p class="card-sub">Your password has been updated</p>
            <div class="alert alert-success" style="margin:0 0 16px;">Password changed successfully. You can now log in.</div>
            <div class="form-actions"><a class="btn btn-block" href="<?= BASE_URL ?>/login.php"><?= icon('lock', 17) ?> Go to Login</a></div>
        <?php elseif (!$reset): ?>
            <p class="card-sub">This link is no longer valid</p>
            <div class="alert alert-error" style="margin:0 0 16px;">This reset link is invalid or has already expired. Please request a new one.</div>
            <div class="form-actions"><a class="btn btn-block" href="<?= BASE_URL ?>/forgot_password.php"><?= icon('mail', 17) ?> Request New Link</a></div>
        <?php else: ?>
            <p class="card-sub">Enter your new password below</p>
            <?php foreach ($errors as $err): ?><div class="alert alert-error" style="margin:0 0 12px;"><?= e($err) ?></div><?php endforeach; ?>
            <form method="post">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div class="field" style="margin-bottom:16px;">
                    <label>New Password</label>
                    <input type="password" name="password" placeholder="At least 6 characters" required>
                </div>
                <div class="field" style="margin-bottom:16px;">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required>
                </div>
                <div class="form-actions"><button class="btn btn-block" type="submit"><?= icon('key', 17) ?> Update Password</button></div>
            </form>
        <?php endif; ?>
        <p style="margin-top:14px;text-align:center;"><a class="back-link" href="<?= BASE_URL ?>/login.php"><?= icon('arrow-left', 15) ?> Back to login</a></p>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
