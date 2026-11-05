<?php
// Shared data for rendering the school-ID card preview.
// Expects $app (applicants row) and $idData (student_ids row or null).

$studentNo = $idData['student_no'] ?? date('Y') . '-' . str_pad((string)$app['id'], 5, '0', STR_PAD_LEFT);
$emgName   = $idData['emergency_name']    ?? '';
$emgAddr   = $idData['emergency_address'] ?? '';
$emgPhone  = $idData['emergency_phone']   ?? '';
$bloodType = $idData['blood_type']        ?? '';
$sigUrl    = $idData ? BASE_URL . '/' . $idData['signature_path'] : '';

$courseNames = [
    'BSIT'   => 'BS Information Technology',
    'BSCrim' => 'BS Criminology',
    'BEEd'   => 'Bachelor of Elementary Education',
    'BSEd'   => 'Bachelor of Secondary Education',
    'BSHM'   => 'BS Hospitality Management',
    'BSBA'   => 'BS Business Administration',
    'BSTM'   => 'BS Tourism Management',
];
$idCourse = $courseNames[$app['course']] ?? $app['course'];
$idFirst  = $app['first_name'] . ' ' . ($app['middle_name'] ? substr($app['middle_name'], 0, 1) . '.' : '')
          . ($app['ext_name'] ? ' ' . $app['ext_name'] : '');
$idLast   = strtoupper($app['last_name']);

// ID photo — dedicated upload, else 2x2 picture, else school ID scan
$photoPath = id_photo_src($app['id']);
$photoUrl  = $photoPath ? BASE_URL . '/' . $photoPath : null;

// Deterministic QR-style pattern seeded by the student number
$qrSeed = crc32('SEAIT-ID-' . $studentNo); mt_srand($qrSeed);
$N = 21; $cell = 10;
$qrSvg = '<svg viewBox="0 0 ' . ($N * $cell) . ' ' . ($N * $cell) . '" xmlns="http://www.w3.org/2000/svg"><rect width="100%" height="100%" fill="#fff"/>';
$finder = function ($x, $y) use ($cell) {
    $s = '';
    for ($i = 0; $i < 7; $i++) for ($j = 0; $j < 7; $j++) {
        $edge = ($i == 0 || $i == 6 || $j == 0 || $j == 6);
        $core = ($i >= 2 && $i <= 4 && $j >= 2 && $j <= 4);
        if ($edge || $core)
            $s .= '<rect x="' . (($x + $j) * $cell) . '" y="' . (($y + $i) * $cell) . '" width="' . $cell . '" height="' . $cell . '" fill="#111"/>';
    }
    return $s;
};
for ($i = 0; $i < $N; $i++) for ($j = 0; $j < $N; $j++) {
    $inFinder = ($i < 8 && $j < 8) || ($i < 8 && $j > $N - 9) || ($i > $N - 9 && $j < 8);
    if (!$inFinder && mt_rand(0, 1))
        $qrSvg .= '<rect x="' . ($j * $cell) . '" y="' . ($i * $cell) . '" width="' . $cell . '" height="' . $cell . '" fill="#111"/>';
}
$qrSvg .= $finder(0, 0) . $finder(0, $N - 7) . $finder($N - 7, 0) . '</svg>';

// Back of the ID — school-year table. Rows start at the school year the
// student first enrolled (earlier applications pull the window back), and
// the colored sticker marks the semester of enrollment.
$semTypes = ['1st' => '#f26522', '2nd' => '#2196d3', 'Summer' => '#8e6aae'];
$curSem = '1st';
$curSY  = null;
if (preg_match('/(1st|2nd|summer)\s*semester\D*(\d{4})\s*[-–]\s*(\d{4})/i', sy_label(), $m)) {
    $curSem = strtoupper($m[1][0]) === 'S' ? 'Summer' : $m[1];
    $curSY  = (int)$m[2];
}
$created   = strtotime($app['created_at']);
$createdSY = (int)date('n', $created) >= 6 ? (int)date('Y', $created) : (int)date('Y', $created) - 1;
if ($curSY === null) $curSY = $createdSY;
$baseSY = min($curSY, $createdSY);
if ($curSY - $baseSY > 4) $baseSY = $curSY; // keep the sticker inside the 5-row window
$yearRows   = range($baseSY, $baseSY + 4);
$stripColor = $semTypes[$curSem] ?? $semTypes['1st'];
$stripRow   = $curSY - $baseSY;
$stripLbl   = $curSem . ' Semester ' . $curSY . '-' . ($curSY + 1);
