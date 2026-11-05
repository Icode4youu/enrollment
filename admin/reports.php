<?php
require_once __DIR__ . '/auth.php';
require_office('registrar');

// ---------- CSV export ----------
if (isset($_GET['export'])) {
    $which = $_GET['export'];
    $cols  = [];
    $rows  = [];
    $name  = 'report.csv';

    if ($which === 'applicants' || $which === 'enrolled') {
        $cols = ['ID','Last Name','First Name','Type','Category','Course','Email','Phone','Status','Block','Applied On'];
        $w = $which === 'enrolled' ? "WHERE a.status='enrolled'" : '';
        $rows = db()->query(
            "SELECT a.id, a.last_name, a.first_name, a.applicant_type, a.category, a.course,
                    a.email, a.phone, a.status,
                    (SELECT name FROM blocks b WHERE b.id=a.block_id) AS block_name,
                    a.created_at
             FROM applicants a $w ORDER BY a.created_at DESC"
        )->fetch_all(MYSQLI_ASSOC);
        $name = $which . '_' . date('Ymd') . '.csv';
    } elseif ($which === 'payments') {
        $cols = ['Applicant','Course','Amount','GCash Ref No.','Status','Date'];
        $rows = db()->query(
            "SELECT CONCAT(a.last_name, ', ', a.first_name) AS name, a.course,
                    p.amount, p.ref_no, p.status, p.created_at
             FROM payments p JOIN applicants a ON a.id = p.applicant_id
             ORDER BY p.created_at DESC"
        )->fetch_all(MYSQLI_ASSOC);
        $name = 'payments_' . date('Ymd') . '.csv';
    }

    if ($rows || $cols) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $cols);
        foreach ($rows as $r) fputcsv($out, array_values($r));
        fclose($out);
        exit;
    }
    redirect('admin/reports.php');
}

// ---------- Summary data ----------
$counts = ['total' => 0];
foreach (db()->query("SELECT status, COUNT(*) c FROM applicants GROUP BY status")->fetch_all(MYSQLI_ASSOC) as $r) {
    $counts[$r['status']] = $r['c'];
    $counts['total'] += $r['c'];
}
$pay = db()->query(
    "SELECT status, COUNT(*) c, COALESCE(SUM(amount),0) total FROM payments GROUP BY status"
)->fetch_all(MYSQLI_ASSOC);
$payBy = array_column($pay, null, 'status');
$collected = (float)($payBy['confirmed']['total'] ?? 0);

$perCourse = db()->query(
    "SELECT course, COUNT(*) c,
            SUM(status='enrolled') enrolled
     FROM applicants GROUP BY course ORDER BY c DESC"
)->fetch_all(MYSQLI_ASSOC);

$perBlock = db()->query(
    "SELECT b.name, b.course, b.capacity,
            (SELECT COUNT(*) FROM applicants a WHERE a.block_id=b.id AND a.status<>'withdrawn'
                AND NOT " . superseded_sql('a') . ") AS taken
     FROM blocks b ORDER BY b.id"
)->fetch_all(MYSQLI_ASSOC);

$recentPay = db()->query(
    "SELECT p.*, CONCAT(a.last_name, ', ', a.first_name) AS name
     FROM payments p JOIN applicants a ON a.id=p.applicant_id
     ORDER BY p.id DESC LIMIT 10"
)->fetch_all(MYSQLI_ASSOC);

$page_title = 'Admin - Reports';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
$bannerTitle = 'Reports';
$bannerSub   = 'Enrollment summary and data exports.';
$bannerIcon  = 'chart'; $bannerArt = 'chart';
include __DIR__ . '/page_banner.php';
?>
    <div class="mini-card" style="max-width:none;margin-bottom:22px;">
        <h3><?= icon('download', 15) ?> Export Data</h3>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a class="btn btn-sm btn-secondary" href="?export=applicants"><?= icon('download', 15) ?> Applicants CSV</a>
            <a class="btn btn-sm btn-secondary" href="?export=enrolled"><?= icon('download', 15) ?> Enrolled CSV</a>
            <a class="btn btn-sm btn-secondary" href="?export=payments"><?= icon('download', 15) ?> Payments CSV</a>
        </div>
    </div>

    <div class="stat-cards">
        <div class="stat-card c-navy"><div class="sc-ic"><?= icon('users', 18) ?></div><div class="lbl">Total Applications</div><div class="num"><?= (int)$counts['total'] ?></div></div>
        <div class="stat-card c-green"><div class="sc-ic"><?= icon('check-circle', 18) ?></div><div class="lbl">Enrolled</div><div class="num"><?= (int)($counts['enrolled'] ?? 0) ?></div></div>
        <div class="stat-card c-orange"><div class="sc-ic"><?= icon('eye', 18) ?></div><div class="lbl">Needs Review</div><div class="num"><?= (int)($counts['submitted'] ?? 0) ?></div></div>
        <div class="stat-card c-blue"><div class="sc-ic"><?= icon('wallet', 18) ?></div><div class="lbl">Confirmed Collections</div><div class="num" style="font-size:1.5rem;padding-top:8px;">&#8369;<?= number_format($collected, 0) ?></div></div>
    </div>

    <div class="review-grid" style="margin-top:22px;">
        <div class="table-card">
            <div class="table-head"><h3><?= icon('chart', 16) ?> Applications per Course</h3></div>
            <table class="admin-table">
                <tr><th>Course</th><th>Applications</th><th>Enrolled</th></tr>
                <?php foreach ($perCourse as $c): ?>
                <tr><td><?= e($c['course']) ?></td><td><?= (int)$c['c'] ?></td><td><?= (int)$c['enrolled'] ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$perCourse): ?><tr><td colspan="3" style="text-align:center;color:var(--muted);padding:24px;">No data yet.</td></tr><?php endif; ?>
            </table>
        </div>

        <div class="table-card">
            <div class="table-head"><h3><?= icon('grid', 16) ?> Block Fill</h3></div>
            <table class="admin-table">
                <tr><th>Block</th><th>Course</th><th>Taken</th><th>Capacity</th><th>%</th></tr>
                <?php foreach ($perBlock as $b):
                    $pct = $b['capacity'] ? round($b['taken'] / $b['capacity'] * 100) : 0; ?>
                <tr>
                    <td><strong><?= e($b['name']) ?></strong></td>
                    <td><?= e($b['course']) ?></td>
                    <td><?= (int)$b['taken'] ?></td>
                    <td><?= (int)$b['capacity'] ?></td>
                    <td><?= $pct ?>%</td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$perBlock): ?><tr><td colspan="5" style="text-align:center;color:var(--muted);padding:24px;">No blocks yet.</td></tr><?php endif; ?>
            </table>
        </div>
    </div>

    <div class="table-card" style="margin-top:22px;">
        <div class="table-head"><h3><?= icon('wallet', 16) ?> Recent Payments</h3></div>
        <table class="admin-table">
            <tr><th>Student</th><th>Amount</th><th>GCash Ref No.</th><th>Status</th><th>Date</th></tr>
            <?php foreach ($recentPay as $p): ?>
            <tr>
                <td><?= e($p['name']) ?></td>
                <td>&#8369;<?= number_format($p['amount'], 2) ?></td>
                <td><?= e($p['ref_no'] ?: '-') ?></td>
                <td><span class="badge <?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
                <td><?= e(date('M j, Y', strtotime($p['created_at']))) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$recentPay): ?><tr><td colspan="5" style="text-align:center;color:var(--muted);padding:24px;">No payments yet.</td></tr><?php endif; ?>
        </table>
    </div>
<?php include __DIR__ . '/nav_close.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
