<?php
require_once __DIR__ . '/config.php';
require_user();
$app = current_applicant();
if (!$app) redirect('index.php');

// Blocks are assigned by the Department — students browse schedules and
// request a block (or a change) from the Schedules page.
redirect('schedules.php');
