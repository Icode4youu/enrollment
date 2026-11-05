<?php
require_once __DIR__ . '/config.php';
require_user();
ensure_curriculum_schema(); // year_level column + subjects table

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !enrollment_is_open()) {
    $errors[] = 'Online enrollment is currently closed.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course = trim($_POST['course'] ?? '');
    $yearLvl = (int)($_POST['year_level'] ?? 0);
    $lname  = trim($_POST['last_name'] ?? '');
    $fname  = trim($_POST['first_name'] ?? '');
    $mname  = trim($_POST['middle_name'] ?? '');
    $ext    = trim($_POST['ext_name'] ?? '');
    $sex    = trim($_POST['sex'] ?? '');
    $dob    = trim($_POST['dob'] ?? '');
    $pob    = trim($_POST['pob'] ?? '');
    $civil  = trim($_POST['civil_status'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $phone  = trim($_POST['phone'] ?? '');

    if (!$course)   $errors[] = 'Please select your course.';
    if ($yearLvl < 1 || $yearLvl > 4) $errors[] = 'Please select your year level.';
    if (!$lname || !$fname) $errors[] = 'Last name and first name are required.';
    if (!in_array($sex, ['Male','Female'])) $errors[] = 'Please select your sex.';
    if (!$dob)      $errors[] = 'Date of birth is required.';
    if (!$pob)      $errors[] = 'Place of birth is required.';
    if (!$civil)    $errors[] = 'Please select your civil status.';
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    // Normalize PH mobile to +63 format (accepts 09..., 9..., or 63...)
    if ($phone !== '') {
        $pd = preg_replace('/\D/', '', $phone);
        if (str_starts_with($pd, '63')) $pd = substr($pd, 2);
        if (str_starts_with($pd, '0'))  $pd = substr($pd, 1);
        $phone = $pd !== '' ? '+63' . $pd : '';
        if (!preg_match('/^\+639\d{9}$/', $phone)) {
            $errors[] = 'Contact number must be a Philippine mobile number (e.g. +639123456789).';
        }
    }

    if (!$errors) {
        // One active application per account — rejected/withdrawn ones may be re-filed
        $uid = (int)$_SESSION['user_id'];
        $active = db()->query(
            "SELECT id FROM applicants WHERE user_id=$uid AND status NOT IN ('rejected','withdrawn') LIMIT 1"
        )->fetch_assoc();
        if ($active) {
            flash('err', 'You already have an active application — continue it on your dashboard.');
            redirect('dashboard.php');
        }
        $stmt = db()->prepare(
            "INSERT INTO applicants
             (user_id, applicant_type, category, course, year_level, last_name, first_name,
              middle_name, ext_name, sex, dob, pob, civil_status, email, phone, status, current_step)
             VALUES (?, 'old', 'Old Student', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'requirements', 1)"
        );
        $stmt->bind_param('ississsssssss',
            $uid, $course, $yearLvl, $lname, $fname, $mname, $ext,
            $sex, $dob, $pob, $civil, $email, $phone);
        $stmt->execute();

        $_SESSION['applicant_id'] = $stmt->insert_id;
        log_activity($stmt->insert_id, 'Application submitted');
        redirect('step_registrar.php');
    }
}

$page_title = 'Old Student Enrollment';
include __DIR__ . '/includes/header.php';
?>
<div class="banner">
    <h2>Old Student Enrollment</h2>
    <div class="sem"><?= e(sy_label()) ?></div>
    <div class="for">Complete everything online &mdash; requirements, payment and blocking.</div>
</div>

<?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<?php if (!enrollment_is_open()): ?>
<div class="card" style="max-width:640px;text-align:center;">
    <div class="empty-state">
        <?= icon('clock', 34) ?>
        <p><strong>Online enrollment is currently closed.</strong><br>
        <?php if ($open = setting('enrollment_open')): ?>Applications open on <?= e(date('F j, Y', strtotime($open))) ?>.<?php endif; ?>
        Please check back later or contact the Registrar's office.</p>
        <a class="btn btn-sm" href="<?= BASE_URL ?>/index.php"><?= icon('arrow-left', 14) ?> Back to Home</a>
    </div>
</div>
<?php else: ?>
<div class="card" style="max-width:900px;">
    <h3 class="section-title"><?= icon('user', 18) ?> Personal Information</h3>
    <form method="post">
        <div class="form-grid">
            <div class="field">
                <label>Course <span class="req">*</span></label>
                <select name="course" required>
                    <option value="">Select Course</option>
                    <?php foreach (course_list() as $c): ?>
                        <option <?= (($_POST['course'] ?? '') === $c) ? 'selected' : '' ?>><?= e($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Year Level <span class="req">*</span></label>
                <select name="year_level" required>
                    <option value="">Select Year Level</option>
                    <?php $selYl = (int)($_POST['year_level'] ?? 0); ?>
                    <?php for ($yl = 1; $yl <= 4; $yl++): ?>
                        <option value="<?= $yl ?>" <?= $selYl === $yl ? 'selected' : '' ?>><?= year_level_label($yl) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="field">
                <label>Last Name <span class="req">*</span></label>
                <input type="text" name="last_name" value="<?= e($_POST['last_name'] ?? '') ?>" required>
            </div>
            <div class="field">
                <label>First Name <span class="req">*</span></label>
                <input type="text" name="first_name" value="<?= e($_POST['first_name'] ?? '') ?>" required>
            </div>
            <div class="field">
                <label>Middle Name</label>
                <input type="text" name="middle_name" value="<?= e($_POST['middle_name'] ?? '') ?>">
            </div>
            <div class="field">
                <label>Extension Name (Jr, II, III)</label>
                <input type="text" name="ext_name" placeholder="Jr, II, III" value="<?= e($_POST['ext_name'] ?? '') ?>">
            </div>
            <div class="field">
                <label>Sex <span class="req">*</span></label>
                <select name="sex" required>
                    <option value="">Select Sex</option>
                    <option <?= (($_POST['sex'] ?? '') === 'Male') ? 'selected' : '' ?>>Male</option>
                    <option <?= (($_POST['sex'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
                </select>
            </div>
            <div class="field">
                <label>Date of Birth <span class="req">*</span></label>
                <input type="date" name="dob" value="<?= e($_POST['dob'] ?? '') ?>" required>
            </div>
            <div class="field">
                <label>Place of Birth <span class="req">*</span></label>
                <input type="text" name="pob" value="<?= e($_POST['pob'] ?? '') ?>" required>
            </div>
            <div class="field">
                <label>Civil Status <span class="req">*</span></label>
                <select name="civil_status" required>
                    <option value="">Select Civil Status</option>
                    <?php foreach (['Single','Married','Widowed','Separated'] as $c): ?>
                        <option <?= (($_POST['civil_status'] ?? '') === $c) ? 'selected' : '' ?>><?= e($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Email Address</label>
                <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="field">
                <label>Contact Number</label>
                <div class="phone-wrap">
                    <span class="ph-prefix">+63</span>
                    <input type="tel" name="phone" value="<?= e(preg_replace('/^\+63/', '', $_POST['phone'] ?? '')) ?>"
                           inputmode="numeric" maxlength="10" pattern="9[0-9]{9}"
                           title="10-digit Philippine mobile number"
                           oninput="this.value=this.value.replace(/\D/g,'').replace(/^0+/,'').slice(0,10)">
                </div>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit"><?= icon('send', 17) ?> Submit</button>
        </div>
    </form>
</div>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
