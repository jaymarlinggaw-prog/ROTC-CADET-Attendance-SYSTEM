<?php
require __DIR__ . '/config.php';
$to = $_GET['to'] ?? 'login';
if ($to === 'app') {
    if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }
    $target = $_SESSION['user']['role'] === 'admin' ? 'dashboard.php' : 'officer.php';
    $label = 'Preparing Dashboard...';
    $full = false;
} else {
    $target = 'login.php';
    $label = 'Preparing Login Form...';
    $full = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=1200">
<title>CADET Attendance Management System</title>
<link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<body class="splash-body">
<div class="splash <?= $full ? 'full' : 'mini' ?>">
  <?php if ($full): ?><div class="splash-photo"><img src="assets/img/campus.jpg" alt="" onerror="this.remove()"></div><?php endif; ?>
  <div class="stripes top"><span></span><span></span><span></span><span></span><span></span><span></span></div>
  <div class="splash-center">
    <?php if ($full): ?>
      <img class="splash-logo" src="assets/img/logo.png" alt="" onerror="this.remove()">
      <div class="splash-t">CADET</div>
      <div class="splash-s">ATTENDANCE MANAGEMENT SYSTEM</div>
      <div class="splash-tag">Monitoring Attendance &nbsp;&bull;&nbsp; Building a Better Future</div>
    <?php endif; ?>
    <div class="pct" id="pct">0%</div>
    <div class="bar"><i id="bar"></i></div>
    <div class="msg"><?= e($label) ?></div>
  </div>
  <div class="splash-foot"></div>
</div>
<script>
(function () {
  var p = 0, pct = document.getElementById('pct'), bar = document.getElementById('bar');
  var t = setInterval(function () {
    p += Math.ceil(Math.random() * 6);
    if (p >= 100) { p = 100; clearInterval(t); setTimeout(function () { location.replace(<?= json_encode($target) ?>); }, 250); }
    pct.textContent = p + '%'; bar.style.width = p + '%';
  }, 90);
})();
</script>
</body>
</html>
