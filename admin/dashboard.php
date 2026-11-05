<?php
require_once __DIR__ . '/auth.php';
require_admin();

$role    = admin_role();
$filter  = $_GET['status'] ?? '';
$courseF = $_GET['course'] ?? '';
$typeF   = $_GET['type'] ?? '';
$q       = trim((string)($_GET['q'] ?? ''));
$all     = isset($_GET['all']);

// Each office's working queue — applicants that need action from that office
$queues = [
    'registrar'  => "a.id IN (SELECT applicant_id FROM documents
                     WHERE status='pending' AND doc_type NOT IN ('medical_form','receipt'))",
    'medical'    => "a.id IN (SELECT applicant_id FROM documents WHERE status='pending' AND doc_type='medical_form')",
    'accounting' => "a.id IN (SELECT applicant_id FROM payments WHERE status='pending')",
    'department' => "(a.status IN ('blocking','submitted','verified') OR a.requested_block_id IS NOT NULL)",
];
$queueWhere = $queues[$role] ?? '';

$conds = [];
// Default view = this office's queue (explicit filters/search/All overrides it)
if ($queueWhere && !$filter && !$all && $q === '' && !$courseF && !$typeF) $conds[] = $queueWhere;
if ($filter)  $conds[] = "a.status='" . db()->real_escape_string($filter) . "'";
if ($courseF) $conds[] = "a.course='" . db()->real_escape_string($courseF) . "'";
if ($typeF)   $conds[] = "a.applicant_type='" . db()->real_escape_string($typeF) . "'";
if ($q !== '') {
    $like = '%' . db()->real_escape_string($q) . '%';
    $conds[] = "(a.last_name LIKE '$like' OR a.first_name LIKE '$like' OR a.email LIKE '$like')";
}
$where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

$apps = db()->query(
    "SELECT a.*, (SELECT name FROM blocks b WHERE b.id=a.block_id) AS block_name,
            (SELECT COUNT(*) FROM course_requests cr
              WHERE cr.applicant_id=a.id AND cr.new_applicant_id IS NOT NULL) AS superseded
     FROM applicants a $where ORDER BY a.created_at DESC"
)->fetch_all(MYSQLI_ASSOC);

$counts = ['total' => 0];
foreach (db()->query("SELECT status, COUNT(*) c FROM applicants GROUP BY status")->fetch_all(MYSQLI_ASSOC) as $r) {
    $counts[$r['status']] = $r['c'];
    $counts['total'] += $r['c'];
}
$queueCount = $queueWhere
    ? db()->query("SELECT COUNT(*) c FROM applicants a WHERE $queueWhere")->fetch_assoc()['c']
    : $counts['total'];

// Office-specific counts for stat cards
$docC = [];
foreach (db()->query("SELECT status, COUNT(*) c FROM documents WHERE doc_type='medical_form' GROUP BY status")->fetch_all(MYSQLI_ASSOC) as $r)
    $docC[$r['status']] = $r['c'];
$regDocs = db()->query("SELECT COUNT(*) c FROM documents WHERE status='pending' AND doc_type NOT IN ('medical_form','receipt')")->fetch_assoc()['c'];
$payC = [];
foreach (db()->query("SELECT status, COUNT(*) c FROM payments GROUP BY status")->fetch_all(MYSQLI_ASSOC) as $r)
    $payC[$r['status']] = $r['c'];
$reqCount = db()->query("SELECT COUNT(*) c FROM applicants WHERE requested_block_id IS NOT NULL")->fetch_assoc()['c'];
$blkCount = db()->query("SELECT COUNT(*) c FROM blocks")->fetch_assoc()['c'];

// Filter dropdown data
$courseList = array_column(
    db()->query("SELECT DISTINCT course FROM applicants WHERE course IS NOT NULL AND course<>'' ORDER BY course")->fetch_all(MYSQLI_ASSOC),
    'course'
);

// 7-day application trend → sparkline points
$week = [];
for ($i = 6; $i >= 0; $i--) $week[date('Y-m-d', strtotime("-$i days"))] = 0;
foreach (db()->query(
    "SELECT DATE(created_at) d, COUNT(*) c FROM applicants
     WHERE created_at >= CURDATE() - INTERVAL 6 DAY GROUP BY d"
)->fetch_all(MYSQLI_ASSOC) as $r) $week[$r['d']] = (int)$r['c'];
$wkMax = max(1, max($week));
$sparkPts = [];
$i = 0;
foreach ($week as $c) { $sparkPts[] = round($i * 100 / 6, 1) . ',' . round(30 - ($c / $wkMax) * 26, 1); $i++; }
$spark = implode(' ', $sparkPts);

$headers = [
    'registrar'  => ['Registrar Queue', 'Registrar documents awaiting review.'],
    'medical'    => ['Medical Queue', 'Students with medical forms awaiting review.'],
    'accounting' => ['Accounting Queue', 'Students with payments awaiting confirmation.'],
    'department' => ['Department Queue', 'Students waiting for a block or an application decision.'],
    'admin'      => ['Enrollment Applications', 'Review and manage student enrollment submissions.'],
];
[$pageTitle, $pageSub] = $headers[$role] ?? $headers['admin'];

$statCards = [
    'registrar' => [
        ['',         'Pending Docs',   $queueCount,               'folder',       'c-orange'],
        ['submitted','Awaiting Decision', $counts['submitted'] ?? 0, 'eye',       'c-blue'],
        ['verified', 'Verified',       $counts['verified'] ?? 0,  'shield',       'c-navy'],
        ['enrolled', 'Enrolled',       $counts['enrolled'] ?? 0,  'check-circle', 'c-green'],
        ['rejected', 'Rejected',       $counts['rejected'] ?? 0,  'x',            'c-red'],
    ],
    'medical' => [
        ['',         'Pending Forms',  $queueCount,               'medical',      'c-orange'],
        [null,       'Approved',       $docC['approved'] ?? 0,    'check-circle', 'c-green'],
        [null,       'Rejected',       $docC['rejected'] ?? 0,    'x',            'c-red'],
        ['all',      'Total Applicants', $counts['total'],        'users',        'c-navy'],
    ],
    'accounting' => [
        ['',         'Pending Payments', $queueCount,             'wallet',       'c-orange'],
        [null,       'Confirmed',      $payC['confirmed'] ?? 0,   'check-circle', 'c-green'],
        [null,       'Rejected',       $payC['rejected'] ?? 0,    'x',            'c-red'],
        ['all',      'Total Applicants', $counts['total'],        'users',        'c-navy'],
    ],
    'department' => [
        ['',         'My Queue',        $queueCount,                'eye',          'c-orange'],
        [null,       'Change Requests', $reqCount,                  'rotate',       'c-blue'],
        ['enrolled', 'Enrolled',        $counts['enrolled'] ?? 0,   'check-circle', 'c-green'],
        [null,       'Blocks',          $blkCount,                  'grid',         'c-navy'],
    ],
    'admin' => [
        ['',          'Total Applicants', $counts['total'],            'users',        'c-navy'],
        ['submitted', 'Needs Review',     $counts['submitted'] ?? 0,   'eye',          'c-orange'],
        ['verified',  'Verified',         $counts['verified'] ?? 0,    'shield',       'c-blue'],
        ['enrolled',  'Enrolled',         $counts['enrolled'] ?? 0,    'check-circle', 'c-green'],
        ['rejected',  'Rejected',         $counts['rejected'] ?? 0,    'x',            'c-red'],
    ],
][$role] ?? [];

$page_title = 'Admin - Applications';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
?>
    <!-- Hero banner -->
    <div class="dash-banner">
        <div>
            <div class="dash-date"><?= e(date('l, F j, Y')) ?></div>
            <h2><?= e($pageTitle) ?></h2>
            <p><?= e($pageSub) ?></p>
            <span class="dash-badge"><?= icon('shield', 13) ?> <?= e(ucfirst($role)) ?> Portal</span>
        </div>
        <div class="dash-art"><?= icon('cap', 110) ?></div>
    </div>

    <div class="stat-cards">
        <?php foreach ($statCards as [$st, $lbl, $n, $ic, $cls]):
            $tag  = $st === null ? 'div' : 'a';
            $href = $st === 'all' ? '?all=1' : ($st === null ? '' : ($st ? "?status=$st" : '?'));
        ?>
        <<?= $tag ?> class="stat-card <?= $cls ?>"<?= $href ? " href=\"$href\"" : '' ?>>
            <div class="sc-ic"><?= icon($ic, 18) ?></div>
            <div class="lbl"><?= $lbl ?></div>
            <div class="num" data-count="<?= (int)$n ?>"><?= (int)$n ?></div>
            <svg class="spark" viewBox="0 0 100 30" preserveAspectRatio="none"><polyline points="<?= $spark ?>"/></svg>
        </<?= $tag ?>>
        <?php endforeach; ?>
    </div>

    <!-- Filters -->
    <form class="filter-card" method="get">
        <div class="filter-head"><?= icon('search', 15) ?> Filters <small>Search by name, course, or status</small></div>
        <div class="filter-row">
            <div class="fg-search">
                <?= icon('search', 15) ?>
                <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search by name or email&hellip;">
            </div>
            <button class="btn btn-sm" type="submit">Search</button>
            <a class="btn btn-sm btn-secondary" href="?">Reset</a>
        </div>
        <div class="filter-selects">
            <div class="fg-field">
                <label>Course</label>
                <select name="course" onchange="this.form.submit()">
                    <option value="">All Courses</option>
                    <?php foreach ($courseList as $c): ?>
                        <option value="<?= e($c) ?>" <?= $courseF === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fg-field">
                <label>Type</label>
                <select name="type" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <option value="new" <?= $typeF === 'new' ? 'selected' : '' ?>>New Student</option>
                    <option value="old" <?= $typeF === 'old' ? 'selected' : '' ?>>Old Student</option>
                </select>
            </div>
            <div class="fg-field">
                <label>Status</label>
                <select name="status" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <?php foreach (['pending','requirements','payment','blocking','submitted','verified','enrolled','rejected','withdrawn'] as $s): ?>
                        <option value="<?= $s ?>" <?= $filter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?> (<?= $counts[$s] ?? 0 ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($queueWhere): ?>
            <div class="fg-field">
                <label>View</label>
                <select name="all" onchange="this.form.submit()">
                    <option value="" <?= !$all ? 'selected' : '' ?>>My Queue (<?= $queueCount ?>)</option>
                    <option value="1" <?= $all ? 'selected' : '' ?>>All (<?= $counts['total'] ?>)</option>
                </select>
            </div>
            <?php endif; ?>
        </div>
    </form>

    <div class="table-card">
        <div class="table-head">
            <h3><?= icon('list', 16) ?> Applicants</h3>
            <span class="tbl-count"><?= count($apps) ?> result<?= count($apps) === 1 ? '' : 's' ?><?= $q !== '' ? ' for &ldquo;' . e($q) . '&rdquo;' : '' ?></span>
        </div>
        <table class="admin-table">
            <tr>
                <th>Student</th><th>Type</th><th>Course</th>
                <th>Block</th><th>Status</th><th>Applied</th><th></th>
            </tr>
            <?php foreach ($apps as $a):
                $ini = strtoupper(substr($a['first_name'],0,1) . substr($a['last_name'],0,1)); ?>
            <tr class="<?= $a['status'] === 'submitted' ? 'needs-review' : '' ?>" <?= $a['superseded'] ? 'style="opacity:.55;"' : '' ?>>
                <td>
                    <span class="avatar-sm"><?= e($ini) ?></span>
                    <strong><?= e($a['first_name'] . ' ' . $a['last_name']) ?></strong>
                    <br><small style="color:var(--muted);margin-left:44px;"><?= e($a['email'] ?: '-') ?> &middot; ID <?= (int)$a['id'] ?></small>
                </td>
                <td><span class="type-tag t-<?= e($a['applicant_type']) ?>"><?= e(ucfirst($a['applicant_type'])) ?></span></td>
                <td><?= e($a['course']) ?></td>
                <td><?= e($a['block_name'] ?? '-') ?></td>
                <td>
                    <span class="badge <?= e($a['status']) ?>"><?= e($a['status']) ?></span>
                    <?php if ($a['superseded']): ?>
                    <br><span class="badge" style="background:#e2e8f0;color:#64748b;margin-top:4px;">superseded</span>
                    <?php endif; ?>
                </td>
                <td><?= e(date('M j, Y', strtotime($a['created_at']))) ?></td>
                <td><a class="btn btn-sm btn-secondary" href="<?= BASE_URL ?>/admin/view.php?id=<?= (int)$a['id'] ?>">Review <?= icon('chevron-right', 13) ?></a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$apps): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:40px;">
                No applicants match the current filters. <a href="?" style="color:var(--blue);">Reset filters</a>
            </td></tr>
            <?php endif; ?>
        </table>
    </div>
<?php include __DIR__ . '/nav_close.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
