<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (is_logged_in()) redirect('/jobportalsystem/index.php');

/* ----------------- local helpers ----------------- */
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function csrf_token(){
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf'];
}
function verify_csrf(){
  if ($_SERVER['REQUEST_METHOD']==='POST'){
    $ok = isset($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf']);
    if (!$ok){ http_response_code(419); exit('Invalid CSRF token.'); }
  }
}
function ratelimit($key, $max=20, $window=600){
  $now=time();
  $_SESSION['rl'][$key] = array_filter($_SESSION['rl'][$key] ?? [], fn($t)=>$t>$now-$window);
  if (count($_SESSION['rl'][$key]) >= $max){ http_response_code(429); exit('Too many attempts. Try again later.'); }
  $_SESSION['rl'][$key][]=$now;
}

/* precomputed bcrypt to flatten timing when user not found */
const DUMMY_BCRYPT = '$2y$10$abcdefghijklmnopqrstuvwxyzABCDEu0V6m6x3x8aS3Wwq6f7a.';

/* ----------------- handle login ----------------- */
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  ratelimit('login');

  $identity = trim($_POST['email'] ?? '');   // email or phone in one box
  $pass     = $_POST['password'] ?? '';

  if ($identity !== '' && $pass !== '') {
    // Try both email/phone with one query so the UI stays simple
    $stmt = $conn->prepare("
      SELECT id, name, password_user, role
      FROM users
      WHERE email = ? OR phone = ?
      LIMIT 1
    ");
    $stmt->bind_param('ss', $identity, $identity);
    $stmt->execute();
    $res = $stmt->get_result();

    $ok = false; $row = null;
    if ($res && ($row = $res->fetch_assoc())) {
      $ok = password_verify($pass, $row['password_user']);
    } else {
      // ensure similar timing when user not found
      password_verify($pass, DUMMY_BCRYPT);
    }

    if ($ok) {
      // extra hardening
      session_regenerate_id(true);
      $_SESSION['user'] = [
        'id'   => (int)$row['id'],
        'name' => $row['name'],
        'role' => $row['role'],
      ];

      // 🔹 smart redirect:
      //    - if admin → go to admin dashboard
      //    - else if return_to is set → go there
      //    - else → main index
      if ($row['role'] === 'admin') {
        $dest = '/jobportalsystem/admin/dashboard.php';
      } else {
        $dest = $_SESSION['return_to'] ?? '/jobportalsystem/index.php';
      }
      unset($_SESSION['return_to']);

      redirect($dest);
    } else {
      $error = "Invalid email/phone or password.";
    }
  } else {
    $error = "Please enter your email/phone and password.";
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Login • Job Portal System For Local Workers</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    :root{
      --bg: radial-gradient(circle at top, #e0f2fe 0%, #bae6fd 50%, #93c5fd 100%);
      --accent:#0ea5e9; --accent-dark:#0369a1;
      --card-bg: rgba(255,255,255,0.86); --card-border: rgba(255,255,255,0.6);
      --text:#0f172a;
    }
    body{
      min-height:100vh; margin:0; display:flex; align-items:center; justify-content:center;
      background:var(--bg); font-family:'Inter',system-ui,sans-serif; color:var(--text);
    }
    .login-wrap{
      width:100%; max-width:420px; padding:24px;
      position:relative; /* needed for hidden admin hotspot */
    }
    .glass{
      background:var(--card-bg); border:1px solid var(--card-border); border-radius:20px;
      backdrop-filter: blur(12px); box-shadow:0 15px 40px rgba(15,23,42,0.12);
      padding:32px 28px; transition:transform .2s ease;
    }
    .glass:hover{ transform: translateY(-3px); }
    .logo{ font-weight:700; text-align:center; font-size:15px; margin-bottom:28px; color:var(--accent-dark); }

    label{font-weight:600; font-size:.9rem; margin:10px 0 6px; display:block;}
    .input-wrap{ position:relative; }
    .form-control{
      width:100%; padding:.65rem .9rem; border-radius:12px; border:1px solid #cbd5e1;
      background:#ffffff; font-size:.95rem; margin-bottom:14px;
    }
    .form-control:focus{ outline:none; border-color:var(--accent); box-shadow:0 0 0 2px rgba(14,165,233,.22); }
    .toggle{ position:absolute; right:12px; top:50%; transform:translateY(-50%); cursor:pointer; opacity:.75; }

    .btn-primary{
      width:100%; border:none; border-radius:12px; padding:.75rem; font-weight:700; color:#fff;
      background:linear-gradient(90deg, var(--accent), var(--accent-dark));
      box-shadow:0 8px 20px rgba(14,165,233,.3); cursor:pointer; transition:filter .2s, transform .1s;
    }
    .btn-primary:hover{ filter:brightness(1.05); }
    .btn-primary:active{ transform:scale(.98); }

    .tiny{ font-size:.85rem; color:#64748b; text-align:center; margin-top:12px; }
    .tiny a{ color:var(--accent-dark); font-weight:600; text-decoration:none; }
    .tiny a:hover{ text-decoration:underline; }

    .alert{ border-radius:12px; padding:10px 14px; background:#fee2e2; color:#b91c1c; font-size:.9rem; margin-bottom:16px; }

    /* 🔹 hidden admin hotspot (top-left, invisible) */
    #admin-hotspot{
      position:absolute;
      top:6px;
      left:6px;
      width:24px;
      height:24px;
      opacity:0;          /* fully hidden */
      cursor:pointer;
      z-index:5;
    }
  </style>
</head>
<body>
  <div class="login-wrap">
    <!-- 🔹 invisible click area for admin shortcut -->
    <div id="admin-hotspot"></div>

    <div class="glass">
      <div class="logo">💼 Job Portal System For Local Workers</div>

      <?php if ($error): ?>
        <div class="alert"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" autocomplete="off" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <label>Email or Phone</label>
        <input type="text" name="email" class="form-control" placeholder="Email or phone" required>

        <label>Password</label>
        <div class="input-wrap">
          <input type="password" id="pw" name="password" class="form-control" placeholder="Password" required>
          <span class="toggle" id="pwToggle">👁️</span>
        </div>

        <button class="btn-primary">Log In</button>
      </form>

      <p class="tiny" style="margin-top:10px;">
        <a href="/jobportalsystem/auth/forgot.php">Forgot Password?</a>
      </p>
      <p class="tiny">
        Don’t have an account? <a href="/jobportalsystem/auth/register.php">Sign up</a>
      </p>
    </div>
  </div>

  <script>
    // password show/hide
    const pw = document.getElementById('pw');
    const t  = document.getElementById('pwToggle');
    t.addEventListener('click', () => {
      pw.type = pw.type === 'password' ? 'text' : 'password';
      t.textContent = pw.type === 'password' ? '👁️' : '🙈';
    });

    // 🔹 hidden admin: keyboard shortcut CTRL + SHIFT + A
    document.addEventListener('keydown', function(e) {
      if (e.ctrlKey && e.shiftKey && (e.key === 'A' || e.key === 'a')) {
        window.location.href = '/jobportalsystem/admin/login.php';
      }
    });

    // 🔹 hidden admin: click invisible hotspot
    document.getElementById('admin-hotspot').addEventListener('click', function() {
      window.location.href = '/jobportalsystem/admin/login.php';
    });
  </script>
</body>
</html>
