<?php
require_once __DIR__ . '/config.php';
if (!empty($_SESSION['user_id'])) redirect('index.php');

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $stmt = db()->prepare("SELECT * FROM users WHERE email=?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    if ($u && password_verify($pass, $u['password_hash'])) {
        session_regenerate_id(true);
        // Staff accounts go straight to their office dashboard, not the student portal
        if (($u['role'] ?? 'student') !== 'student') {
            $_SESSION['admin_id']   = $u['id'];
            $_SESSION['admin_name'] = $u['full_name'] ?: $u['email'];
            $_SESSION['admin_role'] = $u['role'];
            redirect('admin/dashboard.php');
        }
        login_user($u);
        redirect(empty($_SESSION['applicant_id']) ? 'index.php' : 'dashboard.php');
    }
    $err = 'Invalid email or password.';
}

$page_title = 'Login';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
    <div class="auth-side">
        <span class="wh-deco"><?= icon('cap', 190) ?></span>
        <img src="<?= BASE_URL ?>/assets/img/seait-logo.png" alt="SEAIT" class="auth-logo">
        <h3>Welcome to SEAIT</h3>
        <p>Online Enrollment Portal &mdash; enroll from anywhere, no lines needed.</p>
        <ul class="auth-points">
            <li><?= icon('check', 15) ?> Upload requirements online</li>
            <li><?= icon('check', 15) ?> Pay via GCash</li>
            <li><?= icon('check', 15) ?> Choose your class block</li>
        </ul>
    </div>
    <div class="auth-form">
        <h2 class="card-title">Student Login</h2>
        <p class="card-sub">Login to continue your enrollment application</p>
        <?php if ($err): ?><div class="alert alert-error" style="margin:0 0 18px;"><?= e($err) ?></div><?php endif; ?>
        <form method="post">
            <div class="field" style="margin-bottom:16px;">
                <label>Email Address</label>
                <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="field" style="margin-bottom:16px;">
                <label>Password</label>
                <input type="password" name="password" required>
                <small style="display:block;text-align:right;margin-top:6px;">
                    <a href="<?= BASE_URL ?>/forgot_password.php" style="color:var(--muted);">Forgot password?</a>
                </small>
            </div>
            <div class="form-actions"><button class="btn btn-block" type="submit"><?= icon('lock', 17) ?> Login</button></div>
        </form>
        <p class="note" style="text-align:center;margin-top:22px;font-size:.85rem;color:var(--muted);">
            No account yet? <a href="<?= BASE_URL ?>/register.php" style="color:var(--orange-dark);font-weight:600;">Register here</a>
        </p>
        <p style="margin-top:14px;text-align:center;"><a class="back-link" href="<?= BASE_URL ?>/index.php"><?= icon('arrow-left', 15) ?> Back to home</a></p>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
