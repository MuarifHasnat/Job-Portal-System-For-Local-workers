<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (is_logged_in()) redirect('/jobportalsystem/index.php');

/* ------------ helpers ------------ */
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function csrf_token(){
  if (empty($_SESSION['csrf_reset'])) $_SESSION['csrf_reset'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf_reset'];
}
function check_csrf(){
  if ($_SERVER['REQUEST_METHOD']==='POST') {
    $ok = isset($_POST['csrf']) && hash_equals($_SESSION['csrf_reset'] ?? '', $_POST['csrf']);
    if (!$ok) { http_response_code(419); exit('Invalid CSRF token.'); }
  }
}
function ratelimit($key, $max=10, $window=600){
  $now = time();
  $_SESSION['rl'][$key] = array_filter($_SESSION['rl'][$key] ?? [], fn($t)=>$t>$now-$window);
  if (count($_SESSION['rl'][$key]) >= $max){ http_response_code(429); exit('Too many attempts. Try again later.'); }
  $_SESSION['rl'][$key][] = $now;
}

/* ------------ read token from URL and look up user by token hash ------------ */
$tokenRaw = $_GET['token'] ?? '';
$notice = ['type'=>null,'msg'=>''];
$user   = null;
$done   = false;

if (!$tokenRaw || strlen($tokenRaw) < 32) {
  $notice = ['type'=>'error','msg'=>'Invalid reset link.'];
} else {
  // Hash the raw token from URL; DB stores only the hash
  $tokenHash = hash('sha256', $tokenRaw);

  $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token_hash = ? AND reset_expires > NOW() LIMIT 1");
  $stmt->bind_param('s', $tokenHash);
  $stmt->execute();
  $user = $stmt->get_result()->fetch_assoc();

  if (!$user) {
    $notice = ['type'=>'error','msg'=>'This reset link is invalid or has expired. Please request a new one.'];
  }

  /* ------------ handle submit ------------ */
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    check_csrf();
    ratelimit('reset_pw');

    $p1 = $_POST['password']  ?? '';
    $p2 = $_POST['password2'] ?? '';

    // Basic strength rules (tweak as you like)
    $tooShort = strlen($p1) < 8;
    $weak = !(preg_match('/[A-Za-z]/',$p1) && preg_match('/\d/',$p1)); // letters + numbers

    if ($tooShort) {
      $notice = ['type'=>'error','msg'=>'Password must be at least 8 characters.'];
    } elseif ($weak) {
      $notice = ['type'=>'error','msg'=>'Use letters and numbers for a stronger password.'];
    } elseif (!hash_equals($p1, $p2)) {
      $notice = ['type'=>'error','msg'=>'Passwords do not match.'];
    } else {
      $hash = password_hash($p1, PASSWORD_DEFAULT);

      // Single-use: clear token fields immediately
      $upd = $conn->prepare("
        UPDATE users
        SET password_user = ?, reset_token_hash = NULL, reset_expires = NULL
        WHERE id = ?
        LIMIT 1
      ");
      $upd->bind_param('si', $hash, $user['id']);
      $upd->execute();

      $notice = ['type'=>'success','msg'=>'Password reset successful. You can now log in.'];
      $done   = true;

      // Clear only CSRF for this flow (not whole session)
      unset($_SESSION['csrf_reset']);
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Reset Password • Job Portal System For Local Workers</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;700&display=swap" rel="stylesheet">
  <style>
    :root{
      --brand-1:#6a5cff; --brand-2:#00d4ff; --brand-3:#ff7ac6;
      --text:#1f2937; --card-bg: rgba(255,255,255,0.75); --card-border: rgba(255,255,255,0.6);
    }
    *{box-sizing:border-box}
    body{
      min-height:100vh;margin:0;display:flex;align-items:center;justify-content:center;
      font-family: system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,'Poppins',sans-serif;color:var(--text);
      background:
        radial-gradient(1200px 600px at 10% 10%, rgba(255,122,198,0.25), transparent 60%),
        radial-gradient(1000px 500px at 90% 20%, rgba(106,92,255,0.25), transparent 60%),
        radial-gradient(900px 500px at 50% 100%, rgba(0,212,255,0.25), transparent 60%),
        linear-gradient(135deg,#f7f7ff 0%, #f0fbff 100%);
    }
    .wrap{width:100%;max-width:420px;padding:24px;}
    .glass{
      background:var(--card-bg);border:1px solid var(--card-border);border-radius:18px;backdrop-filter:blur(10px);
      box-shadow:0 10px 30px rgba(31,41,55,0.12), inset 0 1px 0 rgba(255,255,255,0.6);
      padding:28px 26px;
    }
    .logo{
      font-weight:700;font-size:14px;text-align:center;margin-bottom:18px;
      background:linear-gradient(90deg,var(--brand-1),var(--brand-2),var(--brand-3));
      -webkit-background-clip:text;background-clip:text;color:transparent;
    }
    .form-control{
      height:48px;border-radius:12px;border:1px solid #e5e7eb;background:#ffffffcc;width:100%;padding:0 12px;
    }
    .form-control:focus{outline:none;border-color:var(--brand-1);box-shadow:0 0 0 .2rem rgba(106,92,255,.15)}
    .btn-brand{
      height:48px;border:none;border-radius:12px;background:linear-gradient(90deg,var(--brand-1),var(--brand-2));
      color:#fff;font-weight:600;box-shadow:0 6px 16px rgba(106,92,255,.35);width:100%;cursor:pointer;
    }
    .btn-brand:hover{filter:brightness(1.05)}
    .tiny{font-size:12.5px;color:#6b7280;text-align:center}
    .link{color:#6a5cff;text-decoration:none;font-weight:600}
    .link:hover{text-decoration:underline}
    .alert{padding:10px 12px;border-radius:10px;margin-bottom:12px;font-size:14px}
    .alert-success{background:#ecfeff;color:#0e7490;border:1px solid #a5f3fc}
    .alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
    .mb-2{margin-bottom:12px}
    .hint{font-size:12px;color:#6b7280;margin:6px 0 10px}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="glass">
      <div class="logo">Job Portal System For Local Workers</div>

      <?php if($notice['type']): ?>
        <div class="alert <?= $notice['type']==='success'?'alert-success':'alert-error' ?>">
          <?= e($notice['msg']) ?>
        </div>
      <?php endif; ?>

      <?php if(empty($done) && $tokenRaw && !empty($user)): ?>
        <form method="post" autocomplete="off">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <div class="mb-2">
            <input type="password" name="password" class="form-control" placeholder="New password (min 8 chars)" required>
          </div>
          <div class="mb-2">
            <input type="password" name="password2" class="form-control" placeholder="Confirm new password" required>
          </div>
          <p class="hint">Tip: use a mix of letters and numbers.</p>
          <button class="btn-brand">Change Password</button>
        </form>
      <?php else: ?>
        <p class="tiny">Go to <a class="link" href="/jobportalsystem/auth/login.php">Login</a></p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
