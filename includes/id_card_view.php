<?php
// Renders the school-ID front + back cards.
// Expects the variables set by includes/id_card_data.php.
// Optional: $capFront / $capBack caption labels.
?>
<div class="idc-item">
    <span class="idp-cap"><?= e($capFront ?? 'Front') ?></span>
    <div class="idcard id-front">
        <div class="idf-head">
            <img src="<?= BASE_URL ?>/assets/img/seait-logo.png" alt="SEAIT">
            <div class="idf-school">
                <span class="idf-name">South East Asian<br>Institute of Technology, Inc.</span>
                <span class="idf-addr">National Highway, Brgy. Crossing Rubber, Tupi, South Cotabato</span>
                <span class="idf-sec">SEC REG NO: CN200828158</span>
            </div>
        </div>
        <div class="idf-mid">
            <div class="idf-photo">
                <?php if ($photoUrl): ?>
                    <img class="face" id="pvFace" src="<?= e($photoUrl) ?>" alt="ID photo">
                <?php else: ?>
                    <img class="face" id="pvFace" alt="ID photo" hidden>
                    <span class="idf-noface" id="pvNoFace"><?= icon('user', 46) ?></span>
                <?php endif; ?>
                <div class="idf-sigbox">
                    <img class="idf-sig" id="pvSig" src="<?= e($sigUrl) ?>" alt="signature"
                         <?= $sigUrl ? '' : 'hidden' ?>>
                    <span class="idf-sig-lbl" id="pvSigLbl" <?= $sigUrl ? 'hidden' : '' ?>>Signature</span>
                </div>
            </div>
            <div class="idf-qrwrap">
                <div class="idf-qr" id="pvQr"><?= $qrSvg ?></div>
                <div class="idf-snum" id="pvSnum"><?= e($studentNo) ?></div>
            </div>
        </div>
        <div class="idf-names">
            <div class="idf-first"><?= e($idFirst) ?></div>
            <div class="idf-last"><?= e($idLast) ?></div>
            <div class="idf-rule"></div>
            <div class="idf-course"><?= e($idCourse) ?></div>
        </div>
    </div>
</div>

<div class="idc-item">
    <span class="idp-cap"><?= e($capBack ?? 'Back') ?></span>
    <div class="idcard id-back">
        <div class="idb-sy">
            <div class="idb-sy-head">
                <span>School Year</span><span>1st Sem</span><span>2nd Sem</span><span>Summer</span>
            </div>
            <?php foreach ($yearRows as $yr): ?>
            <div class="idb-sy-row">
                <span><?= $yr ?>-<?= $yr + 1 ?></span><span></span><span></span><span></span>
            </div>
            <?php endforeach; ?>
            <div class="idb-strip" style="top:<?= 20 + $stripRow * 19 - 2 ?>px;background:<?= $stripColor ?>;transform:rotate(-2.5deg);">
                <img src="<?= BASE_URL ?>/assets/img/seait-logo.png" alt="">
                <span><?= e($stripLbl) ?></span>
            </div>
        </div>
        <div class="idb-vitals">
            <span class="vh">Birth Date</span><span class="vh">Blood Type</span>
            <span class="vv"><?= e(date('F j, Y', strtotime($app['dob']))) ?></span>
            <span class="vv" id="pvBlood"><?= e($bloodType) ?: '—' ?></span>
        </div>
        <div class="idb-emg-h">In Case of Emergency Please Contact</div>
        <div class="idb-emg">
            <div class="idb-emg-name" id="pvEmgName"><?= e($emgName) ?: '—' ?></div>
            <div class="idb-emg-line" id="pvEmgAddr"><?= e($emgAddr) ?: '—' ?></div>
            <div class="idb-emg-line" id="pvEmgPhone"><?= e($emgPhone) ?: '—' ?></div>
        </div>
        <p class="idb-cert">This is to certify that the person whose name and picture
        appear here is a bonafide student of South East Asian Institute of Technology, Inc.</p>
        <div class="idb-foot">
            <svg class="idb-psig" viewBox="0 0 140 46" xmlns="http://www.w3.org/2000/svg">
                <path d="M8 34 C 20 6, 34 44, 48 14 S 66 38, 78 18 C 84 8, 92 34, 102 22 S 122 10, 132 20"
                      fill="none" stroke="#16294a" stroke-width="2.2" stroke-linecap="round"/>
                <path d="M60 38 C 74 30, 96 32, 118 26" fill="none" stroke="#16294a" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
            <div class="idb-pname">ENGR. JOHN PAUL S. TAMAYO, MCE-SG</div>
            <div class="idb-ptitle">School President</div>
        </div>
    </div>
</div>
