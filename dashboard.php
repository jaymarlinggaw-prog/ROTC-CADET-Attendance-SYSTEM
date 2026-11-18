<?php
require __DIR__ . '/config.php';
require_login('admin');
$pages = ['students', 'cadets', 'qr', 'officers', 'departments', 'reports'];
$page = $_GET['page'] ?? 'students';
if (!in_array($page, $pages, true)) $page = 'students';
require __DIR__ . '/includes/admin_top.php';
require __DIR__ . "/pages/$page.php";
require __DIR__ . '/includes/admin_bottom.php';
