<?php
require_once __DIR__ . '/config.php';
if (!empty($_SESSION['user_id'])) redirect('index.php');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['confirm_password'] ?? '';

    if (!$name) $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
    if (strlen($pass) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($pass !== $pass2) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $stmt = db()->prepare("SELECT id FROM users WHERE email=?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $errors[] = 'That email is already registered. Try logging in instead.';
        }
    }

    if (!$errors) {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = db()->prepare("INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $name, $email, $hash);
        $stmt->execute();
        login_user(['id' => $stmt->insert_id, 'full_name' => $name, 'email' => $email]);
        redirect('index.php');
    }
}

$page_title = 'Register';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
    <div class="auth-side">
        <span class="wh-deco"><?= icon('cap', 190) ?></span>
        <img src="<?= BASE_URL ?>/assets/img/seait-logo.png" alt="SEAIT" class="auth-logo">
        <h3>Join SEAIT</h3>
        <p>Create your account and complete your enrollment &mdash; all online.</p>
        <ul class="auth-points">
            <li><?= icon('check', 15) ?> Save your progress anytime</li>
            <li><?= icon('check', 15) ?> Track your application status</li>
            <li><?= icon('check', 15) ?> Get notified on updates</li>
        </ul>
    </div>
    <div class="auth-form">
        <h2 class="card-title">Create Account</h2>
        <p class="card-sub">Register to start your online enrollment</p>
        <?php foreach ($errors as $err): ?><div class="alert alert-error" style="margin:0 0 12px;"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post">
            <div class="field" style="margin-bottom:14px;">
                <label>Full Name <span class="req">*</span></label>
                <input type="text" name="full_name" value="<?= e($_POST['full_name'] ?? '') ?>" required>
            </div>
            <div class="field" style="margin-bottom:14px;">
                <label>Email Address <span class="req">*</span></label>
                <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="field" style="margin-bottom:14px;">
                <label>Password <span class="req">*</span></label>
                <input type="password" name="password" placeholder="At least 6 characters" required>
            </div>
            <div class="field" style="margin-bottom:14px;">
                <label>Confirm Password <span class="req">*</span></label>
                <input type="password" name="confirm_password" required>
            </div>
            <div class="form-actions"><button class="btn btn-block" type="submit"><?= icon('check', 17) ?> Create Account</button></div>
        </form>
        <p class="note" style="text-align:center;margin-top:20px;font-size:.85rem;color:var(--muted);">
            Already have an account? <a href="<?= BASE_URL ?>/login.php" style="color:var(--orange-dark);font-weight:600;">Login here</a>
        </p>
        <p style="margin-top:12px;text-align:center;"><a class="back-link" href="<?= BASE_URL ?>/index.php"><?= icon('arrow-left', 15) ?> Back to home</a></p>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
