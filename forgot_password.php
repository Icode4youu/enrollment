<?php
require_once __DIR__ . '/config.php';
if (!empty($_SESSION['user_id'])) redirect('dashboard.php');

$sent    = false;
$devLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $stmt  = db()->prepare("SELECT id, email, full_name FROM users WHERE email=?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();

    if ($u) {
        $token = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $token);
        $exp   = date('Y-m-d H:i:s', time() + 3600); // 1 hour
        $ins = db()->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?,?,?)");
        $ins->bind_param('iss', $u['id'], $hash, $exp);
        $ins->execute();

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $link   = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL . '/reset_password.php?token=' . $token;
        $body   = "Hi {$u['full_name']},\n\n" .
                  "Click the link below to reset your SEAIT portal password (valid for 1 hour):\n\n" .
                  "$link\n\nIf you did not request this, you can ignore this email.";
        if (!@mail($u['email'], 'SEAIT Portal - Password Reset', $body, 'From: no-reply@seait.edu.ph')) {
            // mail() not configured (e.g. local XAMPP) — show the link so the flow still works
            $devLink = $link;
        }
    }
    $sent = true; // never reveal whether the email is registered
}

$page_title = 'Forgot Password';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
    <div class="auth-side">
        <span class="wh-deco"><?= icon('cap', 190) ?></span>
        <img src="<?= BASE_URL ?>/assets/img/seait-logo.png" alt="SEAIT" class="auth-logo">
        <h3>Forgot your password?</h3>
        <p>Enter your registered email and we'll send you a link to reset it.</p>
    </div>
    <div class="auth-form">
        <h2 class="card-title">Reset Password</h2>
        <p class="card-sub">We'll email you a password reset link</p>

        <?php if ($sent): ?>
            <div class="alert alert-success" style="margin:0 0 16px;">
                If an account exists for that email, a reset link has been sent. It expires in 1 hour.
            </div>
            <?php if ($devLink): ?>
                <div class="alert alert-error" style="margin:0 0 16px;">
                    Email is not configured on this server, so the reset link is shown here instead:<br>
                    <a href="<?= e($devLink) ?>" style="color:var(--navy);word-break:break-all;font-weight:700;"><?= e($devLink) ?></a>
                </div>
            <?php endif; ?>
            <p class="note" style="text-align:center;font-size:.85rem;color:var(--muted);">
                <a href="<?= BASE_URL ?>/login.php" style="color:var(--orange-dark);font-weight:600;">Back to login</a>
            </p>
        <?php else: ?>
            <form method="post">
                <div class="field" style="margin-bottom:16px;">
                    <label>Email Address</label>
                    <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>
                </div>
                <div class="form-actions"><button class="btn btn-block" type="submit"><?= icon('mail', 17) ?> Send Reset Link</button></div>
            </form>
            <p class="note" style="text-align:center;margin-top:22px;font-size:.85rem;color:var(--muted);">
                Remembered it? <a href="<?= BASE_URL ?>/login.php" style="color:var(--orange-dark);font-weight:600;">Back to login</a>
            </p>
        <?php endif; ?>
        <p style="margin-top:14px;text-align:center;"><a class="back-link" href="<?= BASE_URL ?>/index.php"><?= icon('arrow-left', 15) ?> Back to home</a></p>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
