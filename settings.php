<?php
require_once __DIR__ . '/config.php';
require_user();
$user = current_user();
$app  = current_applicant();

$initials = strtoupper(substr($user['full_name'], 0, 1) . substr(strrpos($user['full_name'], ' ') !== false ? substr($user['full_name'], strrpos($user['full_name'], ' ') + 1, 1) : '', 0, 1));
$fullName = $user['full_name'];

$profileMsg = $profileErr = '';
$passMsg    = $passErr = '';
$wdMsg      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw_app'])) {
    if ($app && !in_array($app['status'], ['enrolled','rejected','withdrawn'])) {
        db()->query("UPDATE applicants SET status='withdrawn', block_id=NULL, requested_block_id=NULL, request_reason=NULL WHERE id=" . (int)$app['id']);
        log_activity($app['id'], 'Application withdrawn by the student');
        $app   = current_applicant();
        $wdMsg = 'Your application has been withdrawn. You can start a new one anytime.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_profile'])) {
        $name  = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if (!$name) $profileErr = 'Full name is required.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $profileErr = 'A valid email is required.';
        else {
            $stmt = db()->prepare("SELECT id FROM users WHERE email=? AND id<>?");
            $stmt->bind_param('si', $email, $user['id']);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) {
                $profileErr = 'That email is already used by another account.';
            } else {
                $upd = db()->prepare("UPDATE users SET full_name=?, email=? WHERE id=?");
                $upd->bind_param('ssi', $name, $email, $user['id']);
                $upd->execute();
                $_SESSION['user_name'] = $name;
                $user = current_user();
                $profileMsg = 'Profile updated successfully.';
            }
        }
    } elseif (isset($_POST['save_password'])) {
        $cur  = $_POST['current_password'] ?? '';
        $new  = $_POST['new_password'] ?? '';
        $new2 = $_POST['confirm_password'] ?? '';
        if (!password_verify($cur, $user['password_hash'])) $passErr = 'Current password is incorrect.';
        elseif (strlen($new) < 6) $passErr = 'New password must be at least 6 characters.';
        elseif ($new !== $new2) $passErr = 'New passwords do not match.';
        else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $upd = db()->prepare("UPDATE users SET password_hash=? WHERE id=?");
            $upd->bind_param('si', $hash, $user['id']);
            $upd->execute();
            $passMsg = 'Password changed successfully.';
        }
    }
}

$page_title = 'Settings';
include __DIR__ . '/includes/header.php';
?>
<div class="portal-shell">
    <?php include __DIR__ . '/includes/portal_nav.php'; ?>

    <div class="portal-main">
        <div class="welcome-hero anim">
            <span class="wh-deco"><?= icon('settings', 210) ?></span>
            <div>
                <span class="wh-hi">Account</span>
                <h2>Settings</h2>
                <p class="wh-sub">Manage your profile and password</p>
            </div>
        </div>

        <div class="mini-grid anim d1" style="align-items:start;">
            <div class="mini-card">
                <h3><?= icon('user', 17) ?> Profile</h3>
                <?php if ($profileErr): ?><div class="alert alert-error" style="margin:0 0 16px;max-width:none;"><?= e($profileErr) ?></div><?php endif; ?>
                <?php if ($profileMsg): ?><div class="alert alert-success" style="margin:0 0 16px;max-width:none;"><?= e($profileMsg) ?></div><?php endif; ?>
                <form method="post">
                    <div class="field" style="margin-bottom:16px;">
                        <label>Full Name</label>
                        <input type="text" name="full_name" value="<?= e($_POST['full_name'] ?? $user['full_name']) ?>" required>
                    </div>
                    <div class="field" style="margin-bottom:16px;">
                        <label>Email Address</label>
                        <input type="email" name="email" value="<?= e($_POST['email'] ?? $user['email']) ?>" required>
                    </div>
                    <button class="btn btn-sm" type="submit" name="save_profile" value="1"><?= icon('check', 15) ?> Save Profile</button>
                </form>
            </div>

            <div class="mini-card">
                <h3><?= icon('lock', 17) ?> Change Password</h3>
                <?php if ($passErr): ?><div class="alert alert-error" style="margin:0 0 16px;max-width:none;"><?= e($passErr) ?></div><?php endif; ?>
                <?php if ($passMsg): ?><div class="alert alert-success" style="margin:0 0 16px;max-width:none;"><?= e($passMsg) ?></div><?php endif; ?>
                <form method="post">
                    <div class="field" style="margin-bottom:16px;">
                        <label>Current Password</label>
                        <input type="password" name="current_password" required>
                    </div>
                    <div class="field" style="margin-bottom:16px;">
                        <label>New Password</label>
                        <input type="password" name="new_password" required>
                    </div>
                    <div class="field" style="margin-bottom:16px;">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                    <button class="btn btn-sm" type="submit" name="save_password" value="1"><?= icon('lock', 15) ?> Update Password</button>
                </form>
            </div>
        </div>

        <?php if ($app): ?>
        <div class="mini-card anim d2" style="max-width:none;margin-top:22px;">
            <h3><?= icon('file', 17) ?> My Application</h3>
            <?php if ($wdMsg): ?><div class="alert alert-success" style="margin:0 0 16px;max-width:none;"><?= e($wdMsg) ?></div><?php endif; ?>
            <div class="doc-row" style="margin-bottom:14px;">
                <div class="doc-icon"><?= icon('clipboard', 18) ?></div>
                <div class="doc-name">
                    <?= e($app['course']) ?> &middot; <?= e(sy_label()) ?>
                    <small>Application #<?= (int)$app['id'] ?> &middot; filed <?= e(date('M j, Y', strtotime($app['created_at']))) ?></small>
                </div>
                <span class="step-pill <?= in_array($app['status'], ['verified','enrolled']) ? 'done' : ($app['status'] === 'rejected' || $app['status'] === 'withdrawn' ? 'pending' : 'waiting') ?>">
                    <?= e(ucfirst($app['status'])) ?>
                </span>
            </div>
            <?php if (in_array($app['status'], ['enrolled'])): ?>
                <p style="color:var(--muted);font-size:.86rem;line-height:1.6;">
                    You are already enrolled — to withdraw, please visit the Registrar's office.
                </p>
            <?php elseif (in_array($app['status'], ['withdrawn','rejected'])): ?>
                <p style="color:var(--muted);font-size:.86rem;line-height:1.6;margin-bottom:14px;">
                    This application is <?= e($app['status']) ?>. You may file a new one while enrollment is open.
                </p>
                <a class="btn btn-sm" href="<?= BASE_URL ?>/index.php"><?= icon('file-plus', 15) ?> Start New Application</a>
            <?php else: ?>
                <p style="color:var(--muted);font-size:.86rem;line-height:1.6;margin-bottom:14px;">
                    Withdrawing cancels this application and releases your block slot. This cannot be undone.
                </p>
                <form method="post" onsubmit="return confirm('Withdraw your application? Your chosen block slot will be released.');">
                    <button class="btn btn-sm btn-red" type="submit" name="withdraw_app" value="1"><?= icon('x', 15) ?> Withdraw Application</button>
                </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
