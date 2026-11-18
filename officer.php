<?php
require __DIR__ . '/config.php';
require_login('officer');

$hints = [
    'students'    => 'Take the attendance of the students, or view the student records.',
    'cadets'      => 'Take the attendance of the 1st and 2nd Class Cadet Officers, or view their records.',
    'qr'          => 'Show or print the QR codes that cadets scan to check in.',
    'officers'    => 'View the ROTC officers. Only the admin can change them.',
    'departments' => 'View the departments and how many students each one has.',
    'reports'     => 'View and print the submitted attendance reports.',
];
$page = $_GET['page'] ?? 'students';
if (!isset($hints[$page])) $page = 'students';
$view = (($_GET['view'] ?? '') === 'records') ? 'records' : 'attendance';
$hint = $hints[$page];

require __DIR__ . '/includes/officer_top.php';
if ($page === 'students' || $page === 'cadets') {
    require __DIR__ . '/includes/officer_tabs.php';
    if ($view === 'records') {
        require __DIR__ . "/pages/$page.php";
    } elseif ($page === 'students') {
        require __DIR__ . '/pages/attendance.php';
    } else {
        require __DIR__ . '/pages/cadet_attendance.php';
    }
} else {
    require __DIR__ . "/pages/$page.php";
}
require __DIR__ . '/includes/officer_bottom.php';
