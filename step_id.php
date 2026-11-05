<?php
require_once __DIR__ . '/config.php';
require_user();
$app = current_applicant();
if (!$app) redirect('index.php');

// Earlier steps must be finished first (docs + payment)
[$needUrl] = next_enrollment_step($app);
if (in_array($needUrl, ['step_registrar.php', 'step_medical.php', 'step_accounting.php'], true)) {
    redirect($needUrl);
}

$existing = id_card((int)$app['id']);
$errors   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A new photo upload replaces the previous ID photo
    $pres = save_document($app['id'], 'id_photo', 'id_photo');
    if (is_array($pres) && isset($pres['error'])) $errors[] = 'ID Photo: ' . $pres['error'];

    $studentNo = trim((string)($_POST['student_no'] ?? ''));
    $emgName   = trim((string)($_POST['emg_name'] ?? ''));
    $emgAddr   = trim((string)($_POST['emg_address'] ?? ''));
    $emgPhone  = trim((string)($_POST['emg_phone'] ?? ''));
    $bloodType = trim((string)($_POST['blood_type'] ?? ''));
    $sigData   = (string)($_POST['signature_data'] ?? '');
    if (!in_array($bloodType, ['', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], true)) {
        $bloodType = '';
    }

    // Normalize PH mobile to +63 format (accepts 09..., 9..., or 63...)
    $pd = preg_replace('/\D/', '', $emgPhone);
    if (str_starts_with($pd, '63')) $pd = substr($pd, 2);
    if (str_starts_with($pd, '0'))  $pd = substr($pd, 1);
    $emgPhone = $pd !== '' ? '+63' . $pd : '';

    if ($studentNo === '' || strlen($studentNo) > 30) $errors[] = 'Student number is required.';
    if ($emgName === '') $errors[] = 'Emergency contact person is required.';
    if ($emgAddr === '') $errors[] = 'Emergency contact address is required.';
    if (!preg_match('/^\+639\d{9}$/', $emgPhone)) {
        $errors[] = 'Emergency contact number must be a Philippine mobile number (e.g. +639123456789).';
    }

    // Digital signature — keep the previous one when the pad is left untouched
    $sigPath = $existing['signature_path'] ?? null;
    if (strpos($sigData, 'data:image/png;base64,') === 0) {
        $bin = base64_decode(substr($sigData, 22), true);
        if ($bin === false || strlen($bin) < 200) {
            $errors[] = 'Signature could not be read — please sign again.';
        } else {
            $dir = UPLOAD_DIR . $app['id'] . '/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $fname = 'id_signature_' . time() . '_' . bin2hex(random_bytes(4)) . '.png';
            if (file_put_contents($dir . $fname, $bin) === false) {
                $errors[] = 'Could not save the signature file.';
            } else {
                $sigPath = 'uploads/' . $app['id'] . '/' . $fname;
            }
        }
    }
    if (!$sigPath) $errors[] = 'Please sign inside the signature box.';
    if (!id_photo_src($app['id'])) $errors[] = 'Please upload your ID photo.';

    if (!$errors) {
        if ($existing) {
            $stmt = db()->prepare(
                "UPDATE student_ids SET student_no=?, emergency_name=?, emergency_address=?,
                 emergency_phone=?, blood_type=?, signature_path=? WHERE id=?"
            );
            $stmt->bind_param('ssssssi', $studentNo, $emgName, $emgAddr, $emgPhone, $bloodType, $sigPath, $existing['id']);
        } else {
            $stmt = db()->prepare(
                "INSERT INTO student_ids
                 (applicant_id, student_no, emergency_name, emergency_address, emergency_phone, blood_type, signature_path)
                 VALUES (?,?,?,?,?,?,?)"
            );
            $stmt->bind_param('issssss', $app['id'], $studentNo, $emgName, $emgAddr, $emgPhone, $bloodType, $sigPath);
        }
        $stmt->execute();
        log_activity($app['id'], 'School ID details submitted');
        [$url, $step] = next_enrollment_step($app);
        if (!in_array($app['status'], ['submitted', 'verified', 'enrolled'], true)) {
            db()->query("UPDATE applicants SET status='blocking', current_step=" . (int)$step .
                        " WHERE id=" . (int)$app['id']);
        } else {
            db()->query("UPDATE applicants SET current_step=" . (int)$step . " WHERE id=" . (int)$app['id']);
        }
        redirect($url);
    }
}

// ---- Preview data -------------------------------------------------------
$idData = $existing;
include __DIR__ . '/includes/id_card_data.php';
// Show the values being typed after a failed submit
$studentNo = $_POST['student_no'] ?? $studentNo;
$emgName   = $_POST['emg_name']    ?? $emgName;
$emgAddr   = $_POST['emg_address'] ?? $emgAddr;
$emgPhone  = $_POST['emg_phone']   ?? $emgPhone;
$bloodType = $_POST['blood_type']  ?? $bloodType;

// ID photo upload status shown on the form row
$photoDoc  = db()->query(
    "SELECT status, file_name, remarks FROM documents
     WHERE applicant_id=" . (int)$app['id'] . " AND doc_type='id_photo'
     ORDER BY id DESC LIMIT 1"
)->fetch_assoc();
$photoRejected = $photoDoc && $photoDoc['status'] === 'rejected';
$photoUploaded = $photoDoc && !$photoRejected;

$page_title = 'School ID';
include __DIR__ . '/includes/header.php';
?>
<?php $step_active = 4; include __DIR__ . '/includes/step_track.php'; ?>

<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<div class="card" style="max-width:980px;">
    <div class="step-head">
        <span class="step-head-icn"><?= icon('id-card', 24) ?></span>
        <div>
            <h2 class="card-title" style="text-align:left;margin:0 0 4px;">School ID Application</h2>
            <p class="card-sub" style="text-align:left;margin:0;">Fill in the details printed on your school ID and sign digitally below.</p>
        </div>
    </div>

    <div class="idgrid">
        <form method="post" id="idForm" enctype="multipart/form-data">
            <input type="hidden" name="signature_data" id="signature_data" value="">

            <h3 class="section-title" style="margin-top:0;"><?= icon('id-card', 17) ?> ID Details</h3>
            <div class="upload-row <?= $photoRejected ? 'is-rejected' : '' ?>" style="margin-bottom:18px;">
                <span class="u-icn"><?= icon('image', 20) ?></span>
                <span class="u-label">Upload ID Photo
                    <?php if ($photoRejected): ?>
                        <small class="rejected-tag">Previous photo was rejected &mdash; upload a new one</small>
                        <?php if ($photoDoc['remarks']): ?>
                            <small class="rejected-tag" style="display:block;">Reason: <?= e($photoDoc['remarks']) ?></small>
                        <?php endif; ?>
                    <?php elseif ($photoUploaded): ?>
                        <small class="uploaded-tag">Uploaded: <?= e($photoDoc['file_name']) ?></small>
                    <?php elseif ($photoUrl): ?>
                        <small>Using your uploaded photo &mdash; upload to replace &middot; JPG or PNG &middot; max 5 MB</small>
                    <?php else: ?>
                        <small>2x2 picture &middot; JPG or PNG &middot; max 5 MB</small>
                    <?php endif; ?>
                </span>
                <input type="file" name="id_photo" id="id_photo" accept=".jpg,.jpeg,.png" <?= $photoUrl ? '' : 'required' ?>>
                <span class="chosen-file" style="font-size:.78rem;color:var(--muted);"></span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;">
                <div class="field">
                    <label for="student_no">Student Number <span class="req">*</span></label>
                    <input type="text" id="student_no" name="student_no" maxlength="30"
                           value="<?= e($studentNo) ?>" required>
                </div>
                <div class="field">
                    <label for="blood_type">Blood Type <small style="font-weight:400;">(optional)</small></label>
                    <select id="blood_type" name="blood_type">
                        <option value="">Unknown</option>
                        <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
                            <option <?= $bloodType === $bt ? 'selected' : '' ?>><?= e($bt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <small style="display:block;color:var(--muted);font-size:.75rem;margin:-8px 0 6px;">Student number is auto-generated — change it only if the Registrar gave you a different number.</small>

            <h3 class="section-title"><?= icon('phone', 17) ?> In Case of Emergency</h3>
            <div class="field" style="margin-bottom:14px;">
                <label for="emg_name">Contact Person <span class="req">*</span></label>
                <input type="text" id="emg_name" name="emg_name" maxlength="120"
                       placeholder="e.g. Juan D. Dela Cruz" value="<?= e($emgName) ?>" required>
            </div>
            <div class="field" style="margin-bottom:14px;">
                <label for="emg_address">Address <span class="req">*</span></label>
                <input type="text" id="emg_address" name="emg_address" maxlength="180"
                       placeholder="e.g. Polomolok, South Cotabato" value="<?= e($emgAddr) ?>" required>
            </div>
            <div class="field" style="margin-bottom:20px;">
                <label for="emg_phone">Contact Number <span class="req">*</span></label>
                <div class="phone-wrap">
                    <span class="ph-prefix">+63</span>
                    <input type="tel" id="emg_phone" name="emg_phone"
                           value="<?= e(preg_replace('/^\+63/', '', $emgPhone)) ?>"
                           inputmode="numeric" maxlength="10" pattern="9[0-9]{9}"
                           title="10-digit Philippine mobile number"
                           oninput="this.value=this.value.replace(/\D/g,'').replace(/^0+/,'').slice(0,10)" required>
                </div>
            </div>

            <h3 class="section-title"><?= icon('edit', 17) ?> Digital Signature</h3>
            <div class="sig-wrap">
                <canvas id="sigPad"></canvas>
                <span class="sig-hint" id="sigHint" <?= $sigUrl ? 'hidden' : '' ?>>Sign here using your mouse or finger</span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;">
                <small style="color:var(--muted);font-size:.75rem;">
                    <?= $sigUrl ? 'A signature is on file — sign again to replace it, or leave blank to keep it.' : 'This signature will be printed on your school ID.' ?>
                </small>
                <button type="button" class="btn btn-sm btn-secondary" id="sigClear"><?= icon('rotate', 13) ?> Clear</button>
            </div>

            <div class="form-actions">
                <button class="btn" type="submit"><?= icon('send', 17) ?> <?= $existing ? 'Update ID Details' : 'Submit ID Details' ?></button>
            </div>
        </form>

        <div class="id-prev">
            <?php
            $capFront = 'Live preview — front';
            $capBack  = 'Live preview — back';
            include __DIR__ . '/includes/id_card_view.php';
            ?>
        </div>
    </div>
</div>

<script>
(function () {
    // ---------- Signature pad ----------
    var pad  = document.getElementById('sigPad'),
        ctx  = pad.getContext('2d'),
        hint = document.getElementById('sigHint'),
        sigInput = document.getElementById('signature_data'),
        pvSig = document.getElementById('pvSig'),
        drawing = false, last = null;

    function sizePad() {
        var w = pad.clientWidth || 320, h = 210;
        var dpr = window.devicePixelRatio || 1;
        pad.width = w * dpr; pad.height = h * dpr;
        pad.style.height = h + 'px';
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.strokeStyle = '#16294a'; ctx.lineWidth = 2.4;
        ctx.lineCap = 'round'; ctx.lineJoin = 'round';
    }
    sizePad();

    function pos(e) {
        var r = pad.getBoundingClientRect();
        return { x: e.clientX - r.left, y: e.clientY - r.top };
    }
    var sigLbl = document.getElementById('pvSigLbl');
    function commit() {
        var url = pad.toDataURL('image/png');
        sigInput.value = url;
        pvSig.src = url;
        pvSig.hidden = false;
        if (sigLbl) sigLbl.hidden = true;
    }
    pad.addEventListener('pointerdown', function (e) {
        drawing = true;
        last = pos(e);
        pad.setPointerCapture(e.pointerId);
        e.preventDefault();
    });
    pad.addEventListener('pointermove', function (e) {
        if (!drawing) return;
        var p = pos(e);
        ctx.beginPath(); ctx.moveTo(last.x, last.y); ctx.lineTo(p.x, p.y); ctx.stroke();
        last = p;
        if (hint) hint.hidden = true;
    });
    ['pointerup', 'pointercancel'].forEach(function (ev) {
        pad.addEventListener(ev, function () { if (drawing) { drawing = false; commit(); } });
    });
    document.getElementById('sigClear').addEventListener('click', function () {
        ctx.clearRect(0, 0, pad.width, pad.height);
        sigInput.value = '';
        pvSig.hidden = true;
        pvSig.removeAttribute('src');
        if (sigLbl) sigLbl.hidden = false;
        if (hint) hint.hidden = false;
    });
    document.getElementById('idForm').addEventListener('submit', function (e) {
        if (!sigInput.value && pvSig.hidden) {
            e.preventDefault();
            pad.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (hint) { hint.style.color = 'var(--red)'; setTimeout(function () { hint.style.color = ''; }, 1600); }
        }
    });

    // ---------- Live preview bindings ----------
    function bind(id, fn) {
        var el = document.getElementById(id);
        if (!el) return;
        ['input', 'change'].forEach(function (ev) {
            el.addEventListener(ev, function () { fn(el.value); });
        });
    }
    bind('student_no', function (v) {
        document.getElementById('pvSnum').textContent = v;
        drawQR(v);
    });
    bind('blood_type',  function (v) { document.getElementById('pvBlood').textContent    = v || '—'; });
    bind('emg_name',    function (v) { document.getElementById('pvEmgName').textContent  = v || '—'; });
    bind('emg_address', function (v) { document.getElementById('pvEmgAddr').textContent  = v || '—'; });
    bind('emg_phone',   function (v) { document.getElementById('pvEmgPhone').textContent = v ? '+63' + v : '—'; });

    // Show the chosen photo on the ID preview right away
    var photoInput = document.getElementById('id_photo');
    if (photoInput) photoInput.addEventListener('change', function () {
        var f = photoInput.files[0];
        if (!f || !/^image\//.test(f.type)) return;
        var rd = new FileReader();
        rd.onload = function () {
            var face = document.getElementById('pvFace'),
                noface = document.getElementById('pvNoFace');
            face.src = rd.result;
            face.hidden = false;
            if (noface) noface.hidden = true;
        };
        rd.readAsDataURL(f);
    });

    // Deterministic QR-style pattern re-drawn when the student number changes
    function fnv(s) { var h = 0x811c9dc5; for (var i = 0; i < s.length; i++) { h ^= s.charCodeAt(i); h = Math.imul(h, 0x01000193); } return h >>> 0; }
    function rng(seed) {
        var a = seed >>> 0;
        return function () {
            a = a + 0x6D2B79F5 | 0;
            var t = Math.imul(a ^ a >>> 15, 1 | a);
            t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t;
            return ((t ^ t >>> 14) >>> 0) / 4294967296;
        };
    }
    function drawQR(text) {
        var N = 21, cell = 10, r = rng(fnv('SEAIT-ID-' + text));
        var s = '<svg viewBox="0 0 ' + N * cell + ' ' + N * cell + '" xmlns="http://www.w3.org/2000/svg"><rect width="100%" height="100%" fill="#fff"/>';
        for (var i = 0; i < N; i++) for (var j = 0; j < N; j++) {
            var inF = (i < 8 && j < 8) || (i < 8 && j > N - 9) || (i > N - 9 && j < 8);
            if (!inF && r() < .5) s += '<rect x="' + j * cell + '" y="' + i * cell + '" width="' + cell + '" height="' + cell + '" fill="#111"/>';
        }
        [[0, 0], [0, N - 7], [N - 7, 0]].forEach(function (f) {
            for (var i = 0; i < 7; i++) for (var j = 0; j < 7; j++) {
                if (i === 0 || i === 6 || j === 0 || j === 6 || (i >= 2 && i <= 4 && j >= 2 && j <= 4))
                    s += '<rect x="' + (f[1] + j) * cell + '" y="' + (f[0] + i) * cell + '" width="' + cell + '" height="' + cell + '" fill="#111"/>';
            }
        });
        document.getElementById('pvQr').innerHTML = s + '</svg>';
    }
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
