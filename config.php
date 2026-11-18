<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Manila');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

// XAMPP defaults. Change these if your MySQL has a password.
const DB_HOST = 'localhost';
const DB_NAME = 'cadet_attendance';
const DB_USER = 'root';
const DB_PASS = '';

function db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+08:00'");
        ensure_schema($pdo);
    }
    return $pdo;
}

// Adds the newer columns and tables to an existing database (once per browser session),
// so updating the files is enough: nothing has to be imported by hand.
function ensure_schema(PDO $pdo): void {
    if ((int)($_SESSION['schema_v'] ?? 0) >= 3) return;
    $steps = [
        ['students', 'subject', "ALTER TABLE students ADD COLUMN subject ENUM('MS-1','MS-2') NULL AFTER course"],
        ['attendance', 'source', "ALTER TABLE attendance ADD COLUMN source VARCHAR(10) NOT NULL DEFAULT 'officer'"],
        ['attendance', 'checkin_at', "ALTER TABLE attendance ADD COLUMN checkin_at DATETIME NULL"],
        ['attendance', 'letter', "ALTER TABLE attendance ADD COLUMN letter VARCHAR(255) NULL"],
        ['cadet_attendance', 'source', "ALTER TABLE cadet_attendance ADD COLUMN source VARCHAR(10) NOT NULL DEFAULT 'officer'"],
        ['cadet_attendance', 'checkin_at', "ALTER TABLE cadet_attendance ADD COLUMN checkin_at DATETIME NULL"],
        ['cadet_attendance', 'letter', "ALTER TABLE cadet_attendance ADD COLUMN letter VARCHAR(255) NULL"],
    ];
    $ok = true;
    foreach ($steps as $s) {
        try {
            if (!$pdo->query("SHOW COLUMNS FROM `" . $s[0] . "` LIKE '" . $s[1] . "'")->fetch()) {
                $pdo->exec($s[2]);
            }
        } catch (Throwable $e) { $ok = false; }
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (skey VARCHAR(80) NOT NULL PRIMARY KEY, sval VARCHAR(255) NOT NULL) ENGINE=InnoDB");
        $pdo->exec("CREATE TABLE IF NOT EXISTS checkin_log (id INT AUTO_INCREMENT PRIMARY KEY, ip VARCHAR(45) NOT NULL, ts INT NOT NULL, INDEX idx_ip_ts (ip, ts)) ENGINE=InnoDB");
    } catch (Throwable $e) { $ok = false; }
    if ($ok) $_SESSION['schema_v'] = 3;
}

function setting_get(string $key, string $default = ''): string {
    $q = db()->prepare("SELECT sval FROM settings WHERE skey = ?");
    $q->execute([$key]);
    $v = $q->fetchColumn();
    return $v === false ? $default : (string)$v;
}

function setting_set(string $key, string $value): void {
    db()->prepare("INSERT INTO settings (skey, sval) VALUES (?, ?) ON DUPLICATE KEY UPDATE sval = VALUES(sval)")->execute([$key, $value]);
}

// One flag per group and day: has the ROTC officer opened the QR check-in?
function checkin_key(string $kind, string $date): string {
    return 'checkin_' . $kind . '_' . $date;
}

// The address phones should open (a guess; the admin can change it on the QR Codes page).
function base_url_guess(): string {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $h = strtolower(preg_replace('/:\d+$/', '', $host));
    $port = preg_match('/:(\d+)$/', $host, $m) ? ':' . $m[1] : '';
    if ($h === 'localhost' || $h === '127.0.0.1' || $h === '[::1]') {
        $ip = $_SERVER['SERVER_ADDR'] ?? '';
        if ($ip === '' || $ip === '::1' || $ip === '127.0.0.1') {
            $g = gethostbyname(gethostname());
            $ip = ($g && $g !== gethostname() && $g !== '127.0.0.1') ? $g : '';
        }
        if ($ip !== '') $h = $ip;
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $scheme . '://' . $h . $port . $dir;
}

function asset(string $path): string {
    $f = __DIR__ . '/' . $path;
    return $path . '?v=' . (is_file($f) ? filemtime($f) : time());
}

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}

function require_login(string $role): void {
    if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== $role) {
        header('Location: login.php');
        exit;
    }
}

function out(array $d): void { echo json_encode($d); exit; }

function api_guard(string ...$roles): void {
    header('Content-Type: application/json');
    set_exception_handler(function (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Server error. Make sure MySQL is running and database.sql is imported.']);
    });
    if (empty($_SESSION['user']) || !in_array($_SESSION['user']['role'], $roles, true)) {
        http_response_code(403);
        out(['ok' => false, 'error' => 'Not allowed. Please log in again.']);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $t = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals($_SESSION['csrf'] ?? '', $t)) {
            http_response_code(419);
            out(['ok' => false, 'error' => 'Session expired. Refresh the page and try again.']);
        }
    }
}

function json_in(): array { return json_decode(file_get_contents('php://input'), true) ?: []; }

function stats(): array {
    $pdo = db();
    $p = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE att_date = CURDATE() AND status = 'Present'");
    $p->execute();
    return [
        'students'    => (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn(),
        'active'      => (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status = 'Active'")->fetchColumn(),
        'officers'    => (int)$pdo->query("SELECT COUNT(*) FROM officers")->fetchColumn(),
        'departments' => (int)$pdo->query("SELECT COUNT(DISTINCT course) FROM students")->fetchColumn(),
        'present'     => (int)$p->fetchColumn(),
    ];
}

function icon(string $n): string {
    static $p = [
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'building' => '<path d="M3 21h18"/><path d="M5 21V10"/><path d="M9 21V10"/><path d="M15 21V10"/><path d="M19 21V10"/><path d="M12 3L2 9h20z"/>',
        'chart' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
        'printer' => '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'plus' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>',
        'edit' => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
        'trash' => '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
        'refresh' => '<polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>',
        'check' => '<polyline points="20 6 9 17 4 12"/>',
        'star' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        'clipboard' => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><polyline points="9 14 11 16 15 12"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
        'menu' => '<line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>',
    ];
    return '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$n] ?? '') . '</svg>';
}
