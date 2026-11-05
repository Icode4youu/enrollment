<?php
require_once __DIR__ . '/auth.php';
unset($_SESSION['admin_id'], $_SESSION['admin_name']);
redirect('admin/index.php');
