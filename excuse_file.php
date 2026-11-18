<?php
require __DIR__ . '/config.php';
if (empty($_SESSION['user'])) { http_response_code(403); exit('Please log in first.'); }

$kind = (($_GET['k'] ?? '') === 'cadets') ? 'cadets' : 'students';
$pid = (int)($_GET['pid'] ?? 0);
$d = $_GET['d'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) { http_response_code(400); exit('Bad date.'); }

$att = ($kind === 'cadets') ? 'cadet_attendance' : 'attendance';
$fk = ($kind === 'cadets') ? 'cadet_id' : 'student_id';
$q = db()->prepare("SELECT letter FROM $att WHERE $fk = ? AND att_date = ?");
$q->execute([$pid, $d]);
$rel = (string)$q->fetchColumn();
if ($rel === '' || strpos($rel, '..') !== false) { http_response_code(404); exit('There is no letter for this cadet on this date.'); }

$path = __DIR__ . '/uploads/' . $rel;
$info = is_file($path) ? @getimagesize($path) : false;
if (!$info) { http_response_code(404); exit('The letter file was not found.'); }
header('Content-Type: ' . $info['mime']);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');
readfile($path);
