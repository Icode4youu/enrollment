<?php
require_once __DIR__ . '/config.php';
require_user();

$errors = [];
// An approved course-change request lets the student re-apply for the
// requested course even while their old application is still active —
// the new application is billed the ID fee, not the enrollment fee.
$ccReq = course_request_for((int)$_SESSION['user_id'], ['approved']);
ensure_curriculum_schema(); // year_level column + subjects table

// Requirements + ID details carry over so nothing has to be uploaded twice
function carry_over_for_course_change($newId, $oldId) {
    db()->query(
        "INSERT INTO documents (applicant_id, doc_type, file_name, file_path, status, remarks, uploaded_at)
         SELECT $newId, doc_type, file_name, file_path, status, remarks, uploaded_at
         FROM documents WHERE applicant_id=" . (int)$oldId . " AND doc_type <> 'receipt'"
    );
    db()->query(
        "INSERT INTO student_ids (applicant_id, student_no, emergency_name,
          emergency_address, emergency_phone, blood_type, signature_path)
         SELECT $newId, student_no, emergency_name, emergency_address,
          emergency_phone, blood_type, signature_path
         FROM student_ids WHERE applicant_id=" . (int)$oldId . " ORDER BY id DESC LIMIT 1"
    );
}

// "File New Application" from the request page — the old application's
// personal details are copied too, so this goes straight to the ID-fee payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'course_change' && $ccReq) {
    $uid = (int)$_SESSION['user_id'];
    $old = db()->query("SELECT * FROM applicants WHERE id=" . (int)$ccReq['applicant_id'])->fetch_assoc();
    if ($old) {
        $stmt = db()->prepare(
            "INSERT INTO applicants
             (user_id, applicant_type, category, course, year_level, last_name, first_name, middle_name, ext_name,
              sex, dob, pob, civil_status, email, phone, status, is_course_change)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'pending', 1)"
        );
        $yearLvl = (int)$old['year_level'];
        $stmt->bind_param('isssissssssssss',
            $uid, $old['applicant_type'], $old['category'], $ccReq['requested_course'], $yearLvl,
            $old['last_name'], $old['first_name'], $old['middle_name'], $old['ext_name'],
            $old['sex'], $old['dob'], $old['pob'], $old['civil_status'], $old['email'], $old['phone']);
        $stmt->execute();
        $newId = $stmt->insert_id;

        $stmt = db()->prepare("UPDATE course_requests SET status='consumed', new_applicant_id=? WHERE id=?");
        $stmt->bind_param('ii', $newId, $ccReq['id']);
        $stmt->execute();
        carry_over_for_course_change($newId, (int)$ccReq['applicant_id']);
        log_activity($newId, 'Course change application filed for ' . $ccReq['requested_course'] .
                      ' — requirements carried over, ID fee applies');
        $_SESSION['applicant_id'] = $newId;
        flash('ok', 'Application for ' . $ccReq['requested_course'] .
             ' created — your requirements were carried over. Just pay the ₱' . number_format(ID_FEE, 2) . ' ID fee.');
        redirect('step_accounting.php');
    }
    redirect('course_request.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !enrollment_is_open()) {
    $errors[] = 'Online enrollment is currently closed.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = trim($_POST['category'] ?? '');
    $course   = $ccReq ? $ccReq['requested_course'] : trim($_POST['course'] ?? '');
    $yearLvl  = (int)($_POST['year_level'] ?? 0);
    $lname    = trim($_POST['last_name'] ?? '');
    $fname    = trim($_POST['first_name'] ?? '');
    $mname    = trim($_POST['middle_name'] ?? '');
    $ext      = trim($_POST['ext_name'] ?? '');
    $sex      = trim($_POST['sex'] ?? '');
    $dob      = trim($_POST['dob'] ?? '');
    $pob      = trim($_POST['pob'] ?? '');
    $civil    = trim($_POST['civil_status'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');

    if (!$category) $errors[] = 'Please select an applicant category.';
    if (!$course)   $errors[] = 'Please select a preferred course.';
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
        if ($active && !$ccReq) {
            flash('err', 'You already have an active application — continue it on your dashboard.');
            redirect('dashboard.php');
        }
        $isCC = $ccReq ? 1 : 0;
        $stmt = db()->prepare(
            "INSERT INTO applicants
             (user_id, applicant_type, category, course, year_level, last_name, first_name,
              middle_name, ext_name, sex, dob, pob, civil_status, email, phone, status, is_course_change)
             VALUES (?, 'new', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)"
        );
        $stmt->bind_param('ississssssssssi',
            $uid, $category, $course, $yearLvl, $lname, $fname, $mname, $ext,
            $sex, $dob, $pob, $civil, $email, $phone, $isCC);
        $stmt->execute();
        $newId = $stmt->insert_id;

        if ($ccReq) {
            $stmt = db()->prepare(
                "UPDATE course_requests SET status='consumed', new_applicant_id=? WHERE id=?"
            );
            $stmt->bind_param('ii', $newId, $ccReq['id']);
            $stmt->execute();
            carry_over_for_course_change($newId, (int)$ccReq['applicant_id']);
            log_activity($newId, 'Application submitted — approved course change to ' . $course .
                          ' (requirements carried over, ID fee applies)');
        } else {
            log_activity($newId, 'Application submitted — pre-registration received');
        }

        $_SESSION['applicant_id'] = $newId;
        redirect('confirm.php');
    }
}

$page_title = 'Online Pre-Registration';
include __DIR__ . '/includes/header.php';
?>
<div class="banner">
    <h2>Online Pre-Registration</h2>
    <div class="sem"><?= e(sy_label()) ?></div>
    <div class="for">(for New College Student, Returnee, Transferee and Former Student)</div>
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
    <?php if ($ccReq): ?>
        <div class="alert alert-info" style="margin:0 0 18px;">
            Your course change was approved — this application will be filed under
            <strong><?= e($ccReq['requested_course']) ?></strong>, and the payment step will be the
            <strong>&#8369;<?= number_format(ID_FEE, 2) ?> ID Fee</strong> instead of the enrollment fee.
        </div>
    <?php endif; ?>
    <h3 class="section-title"><?= icon('user', 18) ?> Personal Information</h3>
    <form method="post">
        <div class="form-grid">
            <div class="field full">
                <label>Applicant Category <span class="req">*</span></label>
                <select name="category" required>
                    <option value="">Select Category</option>
                    <?php foreach (['New College Student','Returnee','Transferee','Former Student'] as $c): ?>
                        <option <?= (($_POST['category'] ?? '') === $c) ? 'selected' : '' ?>><?= e($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Preferred Course <span class="req">*</span></label>
                <select name="course" required>
                    <option value="">Select Course</option>
                    <?php $selCourse = $_POST['course'] ?? ($ccReq['requested_course'] ?? ''); ?>
                    <?php foreach (course_list() as $c): ?>
                        <option <?= ($selCourse === $c) ? 'selected' : '' ?>><?= e($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Year Level <span class="req">*</span></label>
                <select name="year_level" required>
                    <option value="">Select Year Level</option>
                    <?php $selYl = (int)($_POST['year_level'] ?? 1); ?>
                    <?php for ($yl = 1; $yl <= 4; $yl++): ?>
                        <option value="<?= $yl ?>" <?= $selYl === $yl ? 'selected' : '' ?>><?= year_level_label($yl) ?></option>
                    <?php endfor; ?>
                </select>
                <small style="color:var(--muted);font-size:.75rem;">New college students are usually 1st Year.</small>
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
            <button class="btn" type="submit"><?= icon('send', 17) ?> Submit Pre-registration Form</button>
        </div>
    </form>
</div>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
