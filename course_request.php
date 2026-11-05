<?php
require_once __DIR__ . '/config.php';
require_user();
$uid = (int)$_SESSION['user_id'];
$app = db()->query(
    "SELECT * FROM applicants WHERE user_id=$uid ORDER BY id DESC LIMIT 1"
)->fetch_assoc();

$errors = [];
$pending  = course_request_for($uid, ['pending']);
$approved = course_request_for($uid, ['approved']);
$canRequest = $app && !in_array($app['status'], ['rejected', 'withdrawn'], true)
              && !$pending && !$approved;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canRequest) {
    $newCourse = trim((string)($_POST['requested_course'] ?? ''));
    $reason    = trim((string)($_POST['reason'] ?? ''));
    if (!in_array($newCourse, course_list(), true)) {
        $errors[] = 'Please select the course you want to shift to.';
    } elseif ($newCourse === $app['course']) {
        $errors[] = 'Pick a different course — that is already your current course.';
    }
    if (!$reason) $errors[] = 'Please tell us why you want to change your course.';

    if (!$errors) {
        $stmt = db()->prepare(
            "INSERT INTO course_requests (user_id, applicant_id, current_course, requested_course, reason)
             VALUES (?,?,?,?,?)"
        );
        $stmt->bind_param('iisss', $uid, $app['id'], $app['course'], $newCourse, $reason);
        $stmt->execute();
        log_activity($app['id'], "Course change request submitted — {$app['course']} to $newCourse");
        flash('ok', 'Your course change request was sent to the Department for approval.');
        redirect('course_request.php');
    }
}

$requests = db()->query(
    "SELECT * FROM course_requests WHERE user_id=$uid ORDER BY id DESC"
)->fetch_all(MYSQLI_ASSOC);

$fullName = $app ? $app['first_name'] . ' ' . ($app['middle_name'] ? substr($app['middle_name'],0,1) . '. ' : '') . $app['last_name']
                 : ($_SESSION['user_name'] ?? 'Student');
$initials = strtoupper(substr($app['first_name'] ?? 'S', 0, 1) . substr($app['last_name'] ?? 'T', 0, 1));

$reqBadge = [
    'pending'  => ['#b45309', 'rgba(245,158,11,.15)', 'Pending review'],
    'approved' => ['#15803d', 'rgba(14,159,90,.15)', 'Approved — file a new application'],
    'declined' => ['#dc2626', 'rgba(220,38,38,.12)', 'Declined'],
    'consumed' => ['#64748b', 'rgba(100,116,139,.15)', 'Used — application filed'],
];

$page_title = 'Course Change Request';
include __DIR__ . '/includes/header.php';
?>
<div class="portal-shell">
    <?php include __DIR__ . '/includes/portal_nav.php'; ?>

    <div class="portal-main">
        <?php if ($m = flash('ok')): ?><div class="alert alert-success" style="max-width:none;margin:0 0 18px;"><?= e($m) ?></div><?php endif; ?>
        <?php foreach ($errors as $err): ?><div class="alert alert-error" style="max-width:none;margin:0 0 12px;"><?= e($err) ?></div><?php endforeach; ?>

        <div class="card" style="max-width:none;">
            <div class="step-head">
                <span class="step-head-icn"><?= icon('rotate', 24) ?></span>
                <div>
                    <h2 class="card-title" style="text-align:left;margin:0 0 4px;">Course Change Request</h2>
                    <p class="card-sub" style="text-align:left;margin:0;">Ask the Department to let you shift to a different course.</p>
                </div>
            </div>

            <?php if ($approved): ?>
            <div class="alert alert-success" style="max-width:none;margin:0 0 18px;">
                Your request to shift from <strong><?= e($approved['current_course']) ?></strong> to
                <strong><?= e($approved['requested_course']) ?></strong> was <strong>approved</strong>.
                Please file a new application — your uploaded requirements carry over,
                and the only payment is the <strong>&#8369;<?= number_format(ID_FEE, 2) ?> ID Fee</strong>
                instead of the enrollment fee.
                <?php if ($approved['remarks']): ?><br><small>Department note: <?= e($approved['remarks']) ?></small><?php endif; ?>
            </div>
            <form method="post" action="<?= BASE_URL ?>/preregistration.php" style="margin:0;">
                <input type="hidden" name="action" value="course_change">
                <button class="btn" type="submit"><?= icon('send', 17) ?> File New Application &amp; Pay ID Fee (<?= e($approved['requested_course']) ?>)</button>
            </form>

            <?php elseif ($pending): ?>
            <div class="alert alert-info" style="max-width:none;margin:0 0 18px;">
                Your request to shift from <strong><?= e($pending['current_course']) ?></strong> to
                <strong><?= e($pending['requested_course']) ?></strong> is waiting for the Department's decision.
                We'll notify you here once it's reviewed.
            </div>

            <?php elseif (!$app): ?>
            <div class="empty-state"><?= icon('info', 34) ?>
                <p>You don't have an application yet. <a href="<?= BASE_URL ?>/index.php">Apply first</a> — a course change needs a current course to change from.</p>
            </div>

            <?php elseif (!$canRequest): ?>
            <div class="empty-state"><?= icon('info', 34) ?>
                <p>Your latest application is <?= e($app['status']) ?> — there's no active course to change from. You can file a <a href="<?= BASE_URL ?>/index.php">new application</a> instead.</p>
            </div>

            <?php else: ?>
            <form method="post">
                <div class="form-grid">
                    <div class="field">
                        <label>Current Course</label>
                        <input type="text" value="<?= e($app['course']) ?>" disabled>
                    </div>
                    <div class="field">
                        <label for="requested_course">Shift To <span class="req">*</span></label>
                        <select id="requested_course" name="requested_course" required>
                            <option value="">Select Course</option>
                            <?php foreach (course_list() as $c): if ($c === $app['course']) continue; ?>
                                <option <?= (($_POST['requested_course'] ?? '') === $c) ? 'selected' : '' ?>><?= e($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field full">
                        <label for="reason">Reason for changing course <span class="req">*</span></label>
                        <textarea id="reason" name="reason" rows="3" required
                            style="width:100%;padding:11px 13px;border:1.5px solid var(--border);border-radius:10px;font:inherit;font-size:.88rem;resize:vertical;"
                            placeholder="e.g. I realized my skills fit IT better than my current course."><?= e($_POST['reason'] ?? '') ?></textarea>
                        <small style="color:var(--muted);font-size:.75rem;">The Department will approve or decline this request. If approved, you'll file a new application — your uploaded requirements carry over and the only payment is the &#8369;<?= number_format(ID_FEE, 2) ?> ID fee.</small>
                    </div>
                </div>
                <div class="form-actions">
                    <button class="btn" type="submit"><?= icon('send', 17) ?> Submit Request</button>
                </div>
            </form>
            <?php endif; ?>
        </div>

        <?php if ($requests): ?>
        <div class="card" style="max-width:none;">
            <h3 class="section-title" style="margin-top:0;"><?= icon('clipboard', 17) ?> Request History</h3>
            <?php foreach ($requests as $r): [$bc, $bb, $bt] = $reqBadge[$r['status']] ?? $reqBadge['pending']; ?>
            <div class="upload-row" style="margin-bottom:10px;">
                <span class="u-icn"><?= icon('rotate', 20) ?></span>
                <span class="u-label"><?= e($r['current_course']) ?> &rarr; <?= e($r['requested_course']) ?>
                    <small><?= e($r['reason']) ?></small>
                    <?php if ($r['remarks']): ?><small>Dept. note: <?= e($r['remarks']) ?></small><?php endif; ?>
                </span>
                <span style="font-size:.7rem;font-weight:700;color:<?= $bc ?>;background:<?= $bb ?>;padding:4px 10px;border-radius:999px;white-space:nowrap;"><?= e($bt) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
