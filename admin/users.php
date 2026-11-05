<?php
require_once __DIR__ . '/auth.php';
require_office();

$roles = ['registrar' => 'Registrar', 'medical' => 'Medical', 'accounting' => 'Accounting',
          'department' => 'Department', 'admin' => 'Admin', 'student' => 'Student'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $name  = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role  = $_POST['role'] ?? '';
        $pass  = $_POST['password'] ?? '';
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !isset($roles[$role]) || strlen($pass) < 6) {
            flash('err', 'Provide a name, valid email, role, and password (min 6 chars).');
        } elseif (db()->query("SELECT id FROM users WHERE email='" . db()->real_escape_string($email) . "'")->num_rows) {
            flash('err', 'That email is already registered.');
        } else {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $stmt = db()->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES (?,?,?,?)");
            $stmt->bind_param('ssss', $name, $email, $hash, $role);
            $stmt->execute();
            flash('ok', $roles[$role] . ' account created for ' . $email . '.');
        }
        redirect('admin/users.php');
    }
    if (isset($_POST['reset_pass'])) {
        $uid  = (int)$_POST['reset_pass'];
        $pass = $_POST['new_password'] ?? '';
        if (strlen($pass) < 6) {
            flash('err', 'New password must be at least 6 characters.');
        } else {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $stmt = db()->prepare("UPDATE users SET password_hash=? WHERE id=?");
            $stmt->bind_param('si', $hash, $uid);
            $stmt->execute();
            flash('ok', 'Password reset.');
        }
        redirect('admin/users.php');
    }
    if (isset($_POST['delete_user'])) {
        $uid = (int)$_POST['delete_user'];
        if ($uid === (int)$_SESSION['admin_id']) {
            flash('err', "You can't delete your own account.");
        } else {
            db()->query("DELETE FROM users WHERE id=$uid");
            flash('ok', 'User deleted.');
        }
        redirect('admin/users.php');
    }
}

$users = db()->query("SELECT id, full_name, email, role, created_at FROM users ORDER BY role, id")->fetch_all(MYSQLI_ASSOC);
$roleC = array_count_values(array_column($users, 'role'));
$staffC = ($roleC['registrar'] ?? 0) + ($roleC['medical'] ?? 0) + ($roleC['accounting'] ?? 0) + ($roleC['department'] ?? 0);

$page_title = 'Manage Users';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
$bannerTitle = 'Manage Users';
$bannerSub   = 'Create staff accounts, reset passwords, and control office access.';
$bannerIcon  = 'users'; $bannerArt = 'users';
include __DIR__ . '/page_banner.php';
?>
<div class="stat-cards">
    <div class="stat-card c-navy"><div class="sc-ic"><?= icon('users', 18) ?></div><div class="lbl">Total Users</div><div class="num"><?= count($users) ?></div></div>
    <div class="stat-card c-blue"><div class="sc-ic"><?= icon('cap', 18) ?></div><div class="lbl">Students</div><div class="num"><?= $roleC['student'] ?? 0 ?></div></div>
    <div class="stat-card c-orange"><div class="sc-ic"><?= icon('shield', 18) ?></div><div class="lbl">Office Staff</div><div class="num"><?= $staffC ?></div></div>
    <div class="stat-card c-green"><div class="sc-ic"><?= icon('key', 18) ?></div><div class="lbl">Admins</div><div class="num"><?= $roleC['admin'] ?? 0 ?></div></div>
</div>
<?php if ($m = flash('err')): ?><div class="alert alert-error" style="margin:14px 0;"><?= e($m) ?></div><?php endif; ?>
<?php if ($m = flash('ok')): ?><div class="alert alert-ok" style="margin:14px 0;"><?= e($m) ?></div><?php endif; ?>

<div class="mini-card" style="margin-bottom:22px;">
    <h3><?= icon('user', 16) ?> Add Staff Account</h3>
    <form method="post" style="display:grid;grid-template-columns:1fr 1fr 160px 160px auto;gap:10px;align-items:end;">
        <div class="field" style="text-align:left;"><label>Full Name</label><input name="full_name" required></div>
        <div class="field" style="text-align:left;"><label>Email</label><input type="email" name="email" required></div>
        <div class="field" style="text-align:left;"><label>Role</label>
            <select name="role"><?php foreach ($roles as $rv => $rl): ?>
                <option value="<?= $rv ?>"><?= $rl ?></option>
            <?php endforeach; ?></select>
        </div>
        <div class="field" style="text-align:left;"><label>Password</label><input name="password" required minlength="6"></div>
        <button class="btn btn-sm" name="add_user" value="1"><?= icon('check', 15) ?> Add</button>
    </form>
</div>

<div class="mini-card">
    <h3><?= icon('users', 16) ?> Users (<?= count($users) ?>)</h3>
    <table class="tbl">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Created</th><th style="width:230px;">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
            <td><?= e($u['full_name']) ?></td>
            <td class="sm"><?= e($u['email']) ?></td>
            <td><span class="badge"><?= e($roles[$u['role']] ?? $u['role']) ?></span></td>
            <td class="sm"><?= e(date('M j, Y', strtotime($u['created_at']))) ?></td>
            <td style="display:flex;gap:6px;">
                <form method="post" style="display:flex;gap:6px;">
                    <input type="hidden" name="reset_pass" value="<?= (int)$u['id'] ?>">
                    <input name="new_password" placeholder="New password" minlength="6" required
                           style="width:120px;padding:5px 9px;border:1px solid var(--border);border-radius:8px;font-size:.8rem;">
                    <button class="btn btn-sm btn-secondary" title="Reset password"><?= icon('key', 14) ?></button>
                </form>
                <?php if ($u['id'] !== (int)$_SESSION['admin_id']): ?>
                <form method="post" onsubmit="return confirm('Delete <?= e($u['email']) ?>?');">
                    <button class="btn btn-sm btn-red" name="delete_user" value="<?= (int)$u['id'] ?>" title="Delete"><?= icon('x', 14) ?></button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/nav_close.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
