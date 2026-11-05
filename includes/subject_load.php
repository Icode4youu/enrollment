<?php
// Student Subject Load — the official subject list for this applicant's
// course + year level + the current semester. Expects:
//   $app     — applicants row (course, year_level, names, applicant_type)
//   $block   — optional blocks row
// Renders the table styled after the school's printed subject load form.
$loadSubs   = subject_load($app['course'], (int)($app['year_level'] ?? 1));
$loadSem    = current_semester();
$loadYearLv = (int)($app['year_level'] ?? 1);
// "S.Y. 2025-2026" portion of the school-year label
$loadSY = preg_match('/(20\d{2}\s*[-–]\s*20?\d{2})/', sy_label(), $m) ? $m[1] : sy_label();
$loadName = $app['last_name'] . ', ' . $app['first_name']
    . ($app['middle_name'] ? ' ' . substr($app['middle_name'], 0, 1) . '.' : '')
    . ($app['ext_name'] ? ' ' . $app['ext_name'] : '');
$loadId   = id_card((int)$app['id'])['student_no'] ?? null;
$loadType = $app['applicant_type'] === 'new' ? 'New' : 'Old';
$totLec = 0; $totLab = 0;
?>
<div class="sload">
    <div class="sload-meta">
        <div><span>Name</span><strong><?= e($loadName) ?></strong></div>
        <div><span>Student ID</span><strong><?= $loadId ? e($loadId) : '&mdash;' ?></strong></div>
        <div><span>Course</span><strong><?= e($app['course']) ?></strong></div>
        <div><span>Year Level</span><strong><?= e(year_level_label($loadYearLv)) ?></strong></div>
        <div><span>School Year</span><strong><?= e($loadSY) ?></strong></div>
        <div><span>Semester</span><strong><?= e($loadSem) ?></strong></div>
        <div><span>Type</span><strong><?= e($loadType) ?></strong></div>
        <?php if (!empty($block)): ?>
        <div><span>Block</span><strong><?= e($block['name']) ?></strong></div>
        <?php endif; ?>
    </div>

    <?php if (!$loadSubs): ?>
        <p style="color:var(--muted);text-align:center;padding:18px 0;font-size:.9rem;">
            No subject list is published for <?= e($app['course']) ?> <?= e(year_level_label($loadYearLv)) ?> this semester.
        </p>
    <?php else: ?>
    <table class="sload-table">
        <thead>
            <tr>
                <th style="width:34px;">#</th>
                <th>Subject Code</th>
                <th style="text-align:left;">Subject Description</th>
                <th>Lecture Units</th>
                <th>Laboratory Units</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($loadSubs as $i => $s):
                $totLec += (int)$s['lec_units']; $totLab += (int)$s['lab_units']; ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td class="sload-code"><?= e($s['subject_code']) ?></td>
                <td style="text-align:left;"><?= e($s['description']) ?></td>
                <td><?= (int)$s['lec_units'] ?></td>
                <td><?= (int)$s['lab_units'] ?></td>
                <td><?= (int)$s['lec_units'] + (int)$s['lab_units'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="text-align:right;"><strong>Total Unit(s):</strong></td>
                <td><strong><?= $totLec ?></strong></td>
                <td><strong><?= $totLab ?></strong></td>
                <td><strong><?= $totLec + $totLab ?></strong></td>
            </tr>
        </tfoot>
    </table>
    <?php endif; ?>
</div>
