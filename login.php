<?php
require __DIR__ . '/config.php';
if (!empty($_SESSION['user'])) { header('Location: splash.php?to=app'); exit; }

$msg = '';
$ok = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['user_type'] ?? '';
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        $msg = 'Session expired. Please try again.';
    } elseif (!in_array($type, ['admin', 'officer'], true) || $user === '' || $pass === '') {
        $msg = 'Please fill in all fields.';
    } else {
        try {
            $st = db()->prepare("SELECT u.*, o.status AS ostatus FROM users u LEFT JOIN officers o ON o.id = u.officer_id WHERE u.username = ? AND u.role = ?");
            $st->execute([$user, $type]);
            $row = $st->fetch();
            if ($row && password_verify($pass, $row['password_hash'])) {
                if ($type === 'officer' && ($row['ostatus'] ?? '') !== 'Active') {
                    $msg = 'This officer account is inactive. Contact the admin.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['csrf'] = bin2hex(random_bytes(16));
                    $_SESSION['user'] = ['id' => (int)$row['id'], 'username' => $row['username'], 'role' => $row['role'], 'fullname' => $row['fullname']];
                    $ok = 'Log in Successfully as ' . ($type === 'admin' ? 'admin' : 'ROTC officer');
                }
            } else {
                $msg = 'Invalid username, password, or user type.';
            }
        } catch (PDOException $e) {
            $msg = 'Cannot reach the database. Start MySQL in XAMPP and import database.sql.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=1200">
<title>Login - CADET Attendance Management System</title>
<?php if ($ok): ?><meta http-equiv="refresh" content="1;url=splash.php?to=app"><?php endif; ?>
<link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<body class="login-body">
<div class="login">
  <section class="login-left">
    <div class="stripes"><span></span><span></span><span></span><span></span><span></span><span></span></div>
    <img class="logo-lg" src="assets/img/logo.png" alt="" onerror="this.style.display='none'">
    <h2>CADET</h2>
    <h3>ATTENDANCE MANAGEMENT SYSTEM</h3>
    <p class="quote">&ldquo;Great Education Builds a Brighter Future&rdquo;</p>
    <div class="green-bar"></div>
  </section>
  <section class="login-right">
    <h1>WELCOME BACK!</h1>
    <p class="sub">Please Login to your account</p>
    <?php if ($msg): ?><div class="alert bad"><?= e($msg) ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="alert good"><?= e($ok) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <label for="user_type">User Type</label>
      <select id="user_type" name="user_type">
        <option value="admin" <?= ($_POST['user_type'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
        <option value="officer" <?= ($_POST['user_type'] ?? '') === 'officer' ? 'selected' : '' ?>>ROTC Officer</option>
      </select>
      <label for="username">Username</label>
      <input id="username" name="username" value="<?= e($_POST['username'] ?? '') ?>" required>
      <label for="password">Password</label>
      <input id="password" name="password" type="password" required>
      <div class="row-between">
        <label class="check"><input type="checkbox" id="showpw"> Show Password</label>
        <a href="#" id="forgot">Forgot Password?</a>
      </div>
      <button class="btn blue" type="submit">Login</button>
      <button class="btn red" type="button" id="cancel">Cancel</button>
    </form>
  </section>
</div>
<script>
document.getElementById('showpw').onchange = function () { document.getElementById('password').type = this.checked ? 'text' : 'password'; };
document.getElementById('forgot').onclick = function (e) { e.preventDefault(); alert('Please contact the system admin to reset your password.'); };
document.getElementById('cancel').onclick = function () { document.querySelector('form').reset(); };
</script>
</body>
</html>
