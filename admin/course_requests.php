<?php
require_once __DIR__ . '/auth.php';
require_office('department');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rid     = (int)($_POST['id'] ?? 0);
    $action  = $_POST['action'] ?? '';
    $remarks = trim((string)($_POST['remarks'] ?? ''));
    $req = db()->query("SELECT * FROM course_requests WHERE id=$rid AND status='pending'")->fetch_assoc();
    if ($req && in_array($action, ['approve', 'decline'], true)) {
        $status = $action === 'approve' ? 'approved' : 'declined';
        $stmt = db()->prepare(
            "UPDATE course_requests SET status=?, remarks=?, decided_by=?, decided_at=NOW() WHERE id=?"
        );
        $aid = (int)$_SESSION['admin_id'];
        $stmt->bind_param('ssii', $status, $remarks, $aid, $rid);
        $stmt->execute();
        $msg = $action === 'approve'
            ? "Course change request approved — {$req['current_course']} to {$req['requested_course']}. File a new application; the fee is the " . '&#8369;' . number_format(ID_FEE, 2) . ' ID fee.'
            : "Course change request declined — {$req['current_course']} to {$req['requested_course']}" . ($remarks ? ": $remarks" : '.');
        log_activity((int)$req['applicant_id'], $msg);
        sms_applicant((int)$req['applicant_id'],
            'SEAIT: Your course change request (' . $req['current_course'] . ' to '
            . $req['requested_course'] . ') was ' . $status . '.'
            . ($action === 'approve'
                ? ' File your new application on the portal and pay the PHP ' . number_format(ID_FEE, 2) . ' ID fee.'
                : ($remarks ? ' Note: ' . $remarks : '')));
        flash('ok', "Request " . ucfirst($status) . ".");
    }
    redirect('admin/course_requests.php');
}

$requests = db()->query(
    "SELECT cr.*, a.first_name, a.last_name, a.middle_name, u.email
     FROM course_requests cr
     JOIN applicants a ON a.id = cr.applicant_id
     JOIN users u ON u.id = cr.user_id
     ORDER BY FIELD(cr.status,'pending','approved','consumed','declined'), cr.id DESC"
)->fetch_all(MYSQLI_ASSOC);

$badge = [
    'pending'  => ['#b45309', 'rgba(245,158,11,.15)'],
    'approved' => ['#15803d', 'rgba(14,159,90,.15)'],
    'declined' => ['#dc2626', 'rgba(220,38,38,.12)'],
    'consumed' => ['#64748b', 'rgba(100,116,139,.15)'],
];

$page_title = 'Admin - Course Change Requests';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
$bannerTitle = 'Course Change Requests';
$bannerSub   = 'Approve or decline student requests to shift to a different course.';
$bannerIcon  = 'rotate'; $bannerArt = 'rotate';
include __DIR__ . '/page_banner.php';
?>

    <?php if ($m = flash('ok')): ?><div class="alert alert-success" style="max-width:none;margin:0 0 18px;"><?= e($m) ?></div><?php endif; ?>

    <div class="mini-card" style="max-width:none;">
        <h3><?= icon('rotate', 17) ?> Requests</h3>
        <?php if (!$requests): ?>
            <div class="empty-state"><?= icon('rotate', 34) ?><p>No course change requests yet.</p></div>
        <?php else: foreach ($requests as $r): [$bc, $bb] = $badge[$r['status']] ?? $badge['pending']; ?>
            <div class="upload-row" style="margin-bottom:10px;align-items:flex-start;">
                <span class="u-icn" style="margin-top:2px;"><?= icon('user', 20) ?></span>
                <span class="u-label">
                    <?= e($r['first_name'] . ' ' . $r['last_name']) ?>
                    <small><?= e($r['email']) ?> &middot; <?= e(date('M j, Y g:i A', strtotime($r['created_at']))) ?></small>
                    <small style="font-weight:700;color:var(--navy);"><?= e($r['current_course']) ?> &rarr; <?= e($r['requested_course']) ?></small>
                    <small><em>&ldquo;<?= e($r['reason']) ?>&rdquo;</em></small>
                    <?php if ($r['remarks']): ?><small>Dept. note: <?= e($r['remarks']) ?></small><?php endif; ?>
                </span>
                <?php if ($r['status'] === 'pending'): ?>
                <form method="post" style="display:flex;gap:6px;align-items:center;margin:0;flex-shrink:0;">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="text" name="remarks" placeholder="Note (optional)"
                           style="font:inherit;font-size:.78rem;padding:7px 10px;border:1.5px solid var(--border);border-radius:8px;width:150px;">
                    <button class="btn btn-sm" type="submit" name="action" value="approve"
                            style="background:#15803d;"><?= icon('check', 14) ?> Approve</button>
                    <button class="btn btn-sm btn-outline" type="submit" name="action" value="decline"
                            style="color:#dc2626;border-color:#dc2626;"><?= icon('x', 14) ?> Decline</button>
                </form>
                <?php else: ?>
                <span style="font-size:.7rem;font-weight:700;color:<?= $bc ?>;background:<?= $bb ?>;padding:4px 10px;border-radius:999px;white-space:nowrap;"><?= e(ucfirst($r['status'])) ?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; endif; ?>
    </div>
<?php include __DIR__ . '/nav_close.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
