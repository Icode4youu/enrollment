<?php
require_once __DIR__ . '/auth.php';
if (!empty($_SESSION['admin_id'])) redirect('admin/dashboard.php');

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    // Staff accounts live in the users table — one login, role decides access
    $stmt = db()->prepare(
        "SELECT * FROM users WHERE email=? AND role IN ('admin','registrar','medical','accounting','department')"
    );
    $stmt->bind_param('s', $u);
    $stmt->execute();
    $a = $stmt->get_result()->fetch_assoc();
    $role = $a['role'] ?? null;
    if (!$a) {
        // Legacy admins table — treated as full admin
        $stmt = db()->prepare("SELECT * FROM admins WHERE username=?");
        $stmt->bind_param('s', $u);
        $stmt->execute();
        $a = $stmt->get_result()->fetch_assoc();
        $role = 'admin';
    }
    if ($a && password_verify($p, $a['password_hash'])) {
        $_SESSION['admin_id']   = $a['id'];
        $_SESSION['admin_name'] = $a['full_name'] ?: ($a['username'] ?? $a['email']);
        $_SESSION['admin_role'] = $role;
        redirect('admin/dashboard.php');
    }
    $err = 'Invalid username or password.';
}

$page_title = 'Admin Login';
include __DIR__ . '/../includes/header.php';
?>
<div class="banner"><h2>ADMIN / REGISTRAR</h2></div>
<?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>
<div class="card card-narrow">
    <div class="big-icn" style="background:var(--orange-soft);color:var(--orange-dark);"><?= icon('shield', 36) ?></div>
    <h2 class="card-title">Staff Login</h2>
    <p class="card-sub">Registrar, Medical, Accounting, Department &amp; Admin personnel only</p>
    <form method="post">
        <div class="field" style="margin-bottom:16px;">
            <label>Username or Email</label>
            <input type="text" name="username" required>
        </div>
        <div class="field" style="margin-bottom:16px;">
            <label>Password</label>
            <input type="password" name="password" required>
        </div>
        <div class="form-actions"><button class="btn btn-block" type="submit">Login</button></div>
    </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
