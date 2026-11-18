<?php
// The page a cadet sees on their own phone after scanning the QR code.
$kind = (($_GET['t'] ?? '') === 'cadets') ? 'cadets' : 'students';
$title = ($kind === 'cadets') ? '1st & 2nd Class' : 'Students';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Attendance Check-in</title>
<style>
*{box-sizing:border-box}
body{margin:0;font-family:"Segoe UI",system-ui,-apple-system,Roboto,Arial,sans-serif;background:#e8eef9;color:#16213a;font-size:17px}
[hidden]{display:none!important}
.top{background:#0d1b3d;color:#fff;padding:14px 16px;display:flex;align-items:center;gap:12px}
.top img{width:46px;height:46px;object-fit:contain}
.top .t{font-weight:700;font-size:18px}
.top .s{font-size:14px;opacity:.9}
.wrap{max-width:520px;margin:0 auto;padding:16px}
.card{background:#fff;border:1px solid #c9d3e6;padding:20px;margin-bottom:14px}
h2{margin:0 0 10px;font-size:21px}
.muted{color:#5a6785;font-size:15px;margin:4px 0}
label{display:block;font-weight:600;margin:12px 0 6px}
input[type=text]{width:100%;font-size:20px;padding:14px;border:2px solid #c9d3e6;background:#fbfbe9}
input[type=file]{width:100%;font-size:16px;padding:10px 0}
.btn{display:block;width:100%;border:0;color:#fff;font:inherit;font-weight:700;font-size:19px;padding:16px;margin-top:12px;cursor:pointer}
.btn:disabled{opacity:.55}
.go{background:#1a8cf0}.p{background:#26a96c}.a{background:#c9526a}.e{background:#e0a31a}.gray{background:#8a93a8}
.err{color:#a32a42;font-weight:600;margin:10px 0 0;min-height:1.2em}
.okcard{background:#e3f4e4;border-left:6px solid #26a96c}
.pill{display:inline-block;color:#fff;padding:4px 14px;font-weight:700;font-size:20px}
#prev{max-width:100%;max-height:220px;margin-top:10px}
a.link{display:block;text-align:center;margin-top:16px;color:#1768e8}
.foot{text-align:center;color:#5a6785;font-size:13px;margin:14px 0}
</style>
</head>
<body>
<div class="top">
  <img src="assets/img/logo.png" alt="" onerror="this.style.display='none'">
  <div><div class="t">CADET Attendance Check-in</div><div class="s"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> &bull; <span id="dateLbl">&nbsp;</span></div></div>
</div>
<div class="wrap">
  <div class="card" id="closed" hidden>
    <h2 id="closedTitle">Please wait</h2>
    <p class="muted" id="closedMsg"></p>
    <button class="btn go" id="again" type="button">Check again</button>
  </div>

  <div class="card" id="step1" hidden>
    <h2>Step 1: Your ID No</h2>
    <p class="muted">Type the ID No that the admin registered for you.</p>
    <label for="idno">ID No</label>
    <input id="idno" type="text" autocomplete="off" autocapitalize="characters" placeholder="Example: 31874">
    <button class="btn go" id="go" type="button">Continue</button>
    <p class="err" id="err1"></p>
  </div>

  <div class="card" id="step2" hidden>
    <h2 id="hello"></h2>
    <p class="muted" id="who"></p>
    <p class="muted" id="cur"></p>
    <p style="margin:14px 0 0"><b>Step 2: Choose your attendance</b></p>
    <button class="btn p" data-s="Present" type="button">Present</button>
    <button class="btn a" data-s="Absent" type="button">Absent</button>
    <button class="btn e" data-s="Excuse" type="button">Excuse</button>
    <div id="letterBox" hidden>
      <label for="letter">Photo of your medical or excuse letter</label>
      <input id="letter" type="file" accept="image/*" capture="environment">
      <img id="prev" alt="" hidden>
      <button class="btn e" id="sendEx" type="button">Send my excuse</button>
    </div>
    <p class="err" id="err2"></p>
    <a class="link" href="#" id="notme">Not you? Use another ID No</a>
  </div>

  <div class="card okcard" id="done" hidden>
    <h2>Recorded</h2>
    <p id="doneName" style="font-size:20px;font-weight:700;margin:6px 0"></p>
    <p><span class="pill" id="donePill"></span></p>
    <p class="muted" id="doneTime"></p>
    <p class="muted">You can change your answer until the ROTC officer submits the attendance. If you do not check in, you will be marked Absent.</p>
    <button class="btn gray" id="change" type="button">Change my answer</button>
    <a class="link" href="#" id="other">Use another ID No</a>
  </div>
  <div class="foot">SKSU Access ROTC Unit</div>
</div>

<script>
(function () {
  var KIND = <?= json_encode($kind) ?>;
  var API = 'api/checkin.php';
  var COLORS = {Present: '#26a96c', Absent: '#c9526a', Excuse: '#e0a31a'};
  var person = null;
  function $(id) { return document.getElementById(id); }
  function show(id) { ['closed', 'step1', 'step2', 'done'].forEach(function (x) { $(x).hidden = (x !== id); }); }
  function busy(on) { var b = document.querySelectorAll('button'); for (var i = 0; i < b.length; i++) b[i].disabled = on; }
  function json(r) { return r.json().catch(function () { throw new Error('The system did not answer. Check the Wi-Fi and try again.'); }); }
  function post(action, fd) {
    return fetch(API + '?action=' + action + '&t=' + KIND, {method: 'POST', body: fd}).then(json);
  }

  function info() {
    busy(false);
    fetch(API + '?action=info&t=' + KIND).then(json).then(function (j) {
      if (!j.ok) throw new Error(j.error);
      $('dateLbl').textContent = j.date;
      if (j.open) { show('step1'); $('idno').focus(); return; }
      $('closedTitle').textContent = j.submitted ? 'Check-in is closed' : 'Check-in is not open yet';
      $('closedMsg').textContent = j.submitted ? 'The ROTC officer already submitted today\'s attendance.' : 'Please wait for the ROTC officer to open the check-in, then tap "Check again".';
      show('closed');
    }).catch(function (e) {
      $('closedTitle').textContent = 'Cannot connect';
      $('closedMsg').textContent = e.message + ' Make sure your phone is on the same Wi-Fi as the system.';
      show('closed');
    });
  }

  function lookup() {
    var v = $('idno').value.trim();
    $('err1').textContent = '';
    if (!v) { $('err1').textContent = 'Please type your ID No.'; return; }
    var fd = new FormData(); fd.append('id_no', v);
    busy(true);
    post('lookup', fd).then(function (j) {
      busy(false);
      if (!j.ok) { if (j.closed) { info(); return; } $('err1').textContent = j.error; return; }
      person = j;
      $('hello').textContent = 'Hello, ' + j.name + '!';
      $('who').textContent = j.group + '  \u2022  ID No ' + j.id_no;
      $('cur').textContent = j.status ? 'Your answer now: ' + j.status : '';
      $('letterBox').hidden = true; $('letter').value = ''; $('prev').hidden = true; $('err2').textContent = '';
      show('step2');
    }).catch(function (e) { busy(false); $('err1').textContent = e.message; });
  }

  function finish(j) {
    $('doneName').textContent = j.name;
    $('donePill').textContent = j.status;
    $('donePill').style.background = COLORS[j.status] || '#26a96c';
    $('doneTime').textContent = 'Saved at ' + j.time;
    show('done');
  }

  function send(status, blob) {
    var fd = new FormData();
    fd.append('id_no', person.id_no); fd.append('status', status);
    if (blob) fd.append('letter', blob, 'letter.jpg');
    $('err2').textContent = '';
    busy(true);
    post('mark', fd).then(function (j) {
      busy(false);
      if (!j.ok) { if (j.closed) { info(); return; } $('err2').textContent = j.error; return; }
      person.has_letter = person.has_letter || status === 'Excuse';
      finish(j);
    }).catch(function (e) { busy(false); $('err2').textContent = e.message; });
  }

  function shrink(file, max, cb) {
    var img = new Image(), url = URL.createObjectURL(file);
    img.onload = function () {
      var s = Math.min(1, max / Math.max(img.width, img.height)), c = document.createElement('canvas');
      c.width = Math.round(img.width * s); c.height = Math.round(img.height * s);
      c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
      c.toBlob(function (b) { URL.revokeObjectURL(url); cb(b || file); }, 'image/jpeg', 0.82);
    };
    img.onerror = function () { URL.revokeObjectURL(url); cb(file); };
    img.src = url;
  }

  $('go').onclick = lookup;
  $('idno').onkeydown = function (e) { if (e.key === 'Enter') lookup(); };
  $('again').onclick = info;
  $('notme').onclick = function (e) { e.preventDefault(); $('idno').value = ''; show('step1'); };
  $('other').onclick = $('notme').onclick;
  $('change').onclick = function () { $('err2').textContent = ''; $('letterBox').hidden = true; show('step2'); };

  var opts = document.querySelectorAll('[data-s]');
  for (var i = 0; i < opts.length; i++) {
    opts[i].onclick = function () {
      var s = this.getAttribute('data-s');
      if (s === 'Excuse') { $('letterBox').hidden = false; $('err2').textContent = person.has_letter ? 'You already attached a letter today. Send again, or attach a new photo.' : ''; return; }
      $('letterBox').hidden = true;
      send(s, null);
    };
  }
  $('letter').onchange = function () {
    var f = this.files && this.files[0];
    if (!f) { $('prev').hidden = true; return; }
    $('prev').src = URL.createObjectURL(f); $('prev').hidden = false;
  };
  $('sendEx').onclick = function () {
    var f = $('letter').files && $('letter').files[0];
    if (!f) {
      if (person.has_letter) { send('Excuse', null); return; }
      $('err2').textContent = 'Please take or choose a photo of your medical or excuse letter.';
      return;
    }
    busy(true);
    shrink(f, 1400, function (blob) { busy(false); send('Excuse', blob); });
  };

  info();
})();
</script>
</body>
</html>
