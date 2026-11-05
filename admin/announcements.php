<?php
require_once __DIR__ . '/auth.php';
require_office('registrar');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $body  = trim($_POST['body'] ?? '');
        if (!$title || !$body) {
            $errors[] = 'Title and message are both required.';
        } else {
            $stmt = db()->prepare("INSERT INTO announcements (title, body) VALUES (?, ?)");
            $stmt->bind_param('ss', $title, $body);
            $stmt->execute();
            flash('ok', 'Announcement posted.');
            redirect('admin/announcements.php');
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        db()->query("UPDATE announcements SET is_active = 1 - is_active WHERE id=$id");
        redirect('admin/announcements.php');
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        db()->query("DELETE FROM announcements WHERE id=$id");
        flash('ok', 'Announcement deleted.');
        redirect('admin/announcements.php');
    }
}

$items = db()->query("SELECT * FROM announcements ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

$page_title = 'Admin - Announcements';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
$bannerTitle = 'Announcements';
$bannerSub   = 'Active announcements appear on every student\'s dashboard.';
$bannerIcon  = 'megaphone'; $bannerArt = 'megaphone';
include __DIR__ . '/page_banner.php';
?>

    <?php if ($m = flash('ok')): ?><div class="alert alert-success" style="max-width:none;margin:0 0 18px;"><?= e($m) ?></div><?php endif; ?>
    <?php foreach ($errors as $err): ?><div class="alert alert-error" style="max-width:none;margin:0 0 12px;"><?= e($err) ?></div><?php endforeach; ?>

    <div class="mini-card" style="max-width:none;margin-bottom:22px;">
        <h3><?= icon('megaphone', 17) ?> Post an Announcement</h3>
        <form method="post">
            <input type="hidden" name="action" value="add">
            <div class="field" style="margin-bottom:14px;">
                <label>Title</label>
                <input type="text" name="title" maxlength="120" placeholder="e.g. Enrollment deadline extended" required>
            </div>
            <div class="field" style="margin-bottom:14px;">
                <label>Message</label>
                <textarea name="body" rows="3" required style="width:100%;padding:11px 13px;border:1.5px solid var(--border);border-radius:10px;font:inherit;font-size:.88rem;resize:vertical;"></textarea>
            </div>
            <button class="btn btn-sm" type="submit"><?= icon('send', 15) ?> Post Announcement</button>
        </form>
    </div>

    <div class="mini-card" style="max-width:none;">
        <h3><?= icon('list', 17) ?> All Announcements</h3>
        <?php if (!$items): ?><p style="color:var(--muted);font-size:.88rem;">No announcements yet.</p><?php endif; ?>
        <?php foreach ($items as $a): ?>
        <div class="doc-row" style="align-items:flex-start;">
            <div class="doc-icon" style="background:<?= $a['is_active'] ? 'var(--orange-soft)' : '#eef1f6' ?>;color:<?= $a['is_active'] ? 'var(--orange-dark)' : 'var(--muted)' ?>;"><?= icon('megaphone', 17) ?></div>
            <div class="doc-name">
                <strong><?= e($a['title']) ?></strong>
                <small style="display:block;line-height:1.5;"><?= nl2br(e($a['body'])) ?></small>
                <small><?= e(date('M j, Y g:i A', strtotime($a['created_at']))) ?></small>
            </div>
            <span class="step-pill <?= $a['is_active'] ? 'done' : 'waiting' ?>"><?= $a['is_active'] ? 'Visible' : 'Hidden' ?></span>
            <form method="post" style="display:flex;gap:6px;">
                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <button class="btn btn-sm btn-secondary" name="action" value="toggle" title="<?= $a['is_active'] ? 'Hide' : 'Show' ?>">
                    <?= icon($a['is_active'] ? 'eye' : 'check', 14) ?>
                </button>
                <button class="btn btn-sm btn-red" name="action" value="delete" title="Delete" onclick="return confirm('Delete this announcement?');"><?= icon('trash', 14) ?></button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
<?php include __DIR__ . '/nav_close.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
