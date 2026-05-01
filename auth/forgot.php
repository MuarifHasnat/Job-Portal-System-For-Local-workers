<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (is_logged_in()) redirect('/jobportalsystem/index.php');

/* ---------------- helpers ---------------- */
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function csrf_token(){
  if (empty($_SESSION['csrf_forgot'])) $_SESSION['csrf_forgot'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf_forgot'];
}
function check_csrf(){
  if ($_SERVER['REQUEST_METHOD']==='POST') {
    $ok = isset($_POST['csrf']) && hash_equals($_SESSION['csrf_forgot'] ?? '', $_POST['csrf']);
    if (!$ok) { http_response_code(419); exit('Invalid CSRF token.'); }
  }
}
function ratelimit($key, $max=8, $window=600){
  $now=time();
  $_SESSION['rl'][$key] = array_filter($_SESSION['rl'][$key] ?? [], fn($t)=>$t>$now-$window);
  if (count($_SESSION['rl'][$key]) >= $max){
    http_response_code(429); exit('Too many attempts. Try again later.');
  }
  $_SESSION['rl'][$key][]=$now;
}

/* -------------- state --------------- */
$step = $_SESSION['reset_step'] ?? 1;   // 1=email, 2=question, 3=reset
$error = $msg = '';
$question = '';

/* -------------- submit handling --------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();
  ratelimit('forgot');

  // STEP 1: user provided email
  if (isset($_POST['flow']) && $_POST['flow']==='email') {
    $email = trim($_POST['email'] ?? '');

    // Avoid user enumeration
    $generic = "If the account exists, you'll be asked the saved security question.";

    if ($email !== '') {
      $stmt = $conn->prepare("SELECT id, security_question FROM users WHERE email = ? LIMIT 1");
      $stmt->bind_param('s', $email);
      $stmt->execute();
      $user = $stmt->get_result()->fetch_assoc();

      if ($user && !empty($user['security_question'])) {
        $_SESSION['reset_user_id'] = (int)$user['id'];
        $_SESSION['reset_email']   = $email;
        $_SESSION['reset_step']    = 2;
      } else {
        $_SESSION['reset_user_id'] = null;
        $_SESSION['reset_email']   = $email;
        $_SESSION['reset_step']    = 2;
        $_SESSION['fake_question'] = true;
      }
      $msg = $generic;
      $step = 2;
    } else {
      $error = "Please enter your registered email.";
      $step = 1;
    }
  }

  // STEP 2: verify security answer
  elseif (isset($_POST['flow']) && $_POST['flow']==='answer') {
    $uid    = $_SESSION['reset_user_id'] ?? null;
    $answer = trim($_POST['security_answer'] ?? '');

    if ($answer === '') {
      $error = "Please enter your answer.";
      $step  = 2;
    } else {
      $ansNorm = mb_strtolower(trim($answer));
      $verified = false;

      if ($uid) {
        $check = $conn->prepare("SELECT security_answer FROM users WHERE id=? LIMIT 1");
        $check->bind_param('i',$uid);
        $check->execute();
        if ($row = $check->get_result()->fetch_assoc()) {
          $verified = password_verify($answer, $row['security_answer'])
                   || password_verify($ansNorm, $row['security_answer']);
        }
      } else {
        password_verify($ansNorm, '$2y$10$abcdefghijklmnopqrstuvwxyzABCDEu0V6m6x3x8aS3Wwq6f7a.');
      }

      if ($verified) {
        $_SESSION['reset_step'] = 3;
        $step = 3;
      } else {
        $error = "Incorrect answer. Try again.";
        $_SESSION['reset_step'] = 2;
        $step = 2;
      }
    }
  }

  // STEP 3: reset password
  elseif (isset($_POST['flow']) && $_POST['flow']==='reset') {
    $uid     = $_SESSION['reset_user_id'] ?? null;
    $newpass = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$uid) {
      $error = "Session expired. Please start again.";
      $_SESSION['reset_step'] = 1; $step = 1;
    } elseif ($newpass !== $confirm) {
      $error = "Passwords do not match."; $step = 3;
    } elseif (strlen($newpass) < 8) {
      $error = "Password must be at least 8 characters."; $step = 3;
    } else {
      $hash = password_hash($newpass, PASSWORD_BCRYPT);
      $up = $conn->prepare("UPDATE users SET password_user=? WHERE id=?");
      $up->bind_param('si',$hash,$uid);
      $up->execute();

      unset($_SESSION['reset_user_id'], $_SESSION['reset_email'], $_SESSION['reset_step'], $_SESSION['fake_question']);

      $msg  = "✅ Password reset successfully! <a href='/jobportalsystem/auth/login.php'>Log in</a>";
      $step = 0;
    }
  }
}

/* -------------- load security question --------------- */
if ($step == 2) {
  if (!($_SESSION['fake_question'] ?? false) && !empty($_SESSION['reset_user_id'])) {
    $q = $conn->prepare("SELECT security_question FROM users WHERE id=? LIMIT 1");
    $q->bind_param('i', $_SESSION['reset_user_id']);
    $q->execute();
    $row = $q->get_result()->fetch_assoc();
    $question = $row['security_question'] ?? '';
  } else {
    $question = "What is your saved security answer?";
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Forgot Password • Job Portal System</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#f0f9ff; --accent:#0ea5e9; --accent-dark:#0369a1; --text:#0f172a;
  }
  *{box-sizing:border-box}
  body{
    font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
    background:var(--bg); display:flex; justify-content:center; align-items:center; min-height:100vh; margin:0; color:var(--text);
  }
  .card{background:#fff; padding:28px 24px; border-radius:18px; box-shadow:0 12px 32px rgba(2,12,27,.1); width:100%; max-width:430px;}
  h2{margin:0 0 14px; text-align:center; color:var(--accent-dark); font-size:1.25rem; font-weight:800;}
  p.helper{margin:6px 0 16px; text-align:center; color:#64748b; font-size:.9rem;}
  label{font-weight:600; font-size:.9rem; margin:8px 0 6px; display:block;}
  input{width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:12px; margin-bottom:12px; font-size:.95rem;}
  input:focus{outline:none; border-color:var(--accent); box-shadow:0 0 0 2px rgba(14,165,233,.2);}
  button{width:100%; padding:11px 14px; border:none; border-radius:12px; background:linear-gradient(90deg,var(--accent),var(--accent-dark)); color:#fff; font-weight:700; cursor:pointer; box-shadow:0 10px 22px rgba(14,165,233,.28);}
  button:hover{filter:brightness(1.05)}
  .msg{padding:10px 14px; border-radius:12px; margin-bottom:12px; font-size:.9rem; text-align:center;}
  .error{background:#fee2e2; color:#991b1b;}
  .success{background:#dcfce7; color:#166534;}
  .back{display:block; text-align:center; margin-top:12px; font-size:.85rem; color:var(--accent-dark); text-decoration:none; font-weight:600;}
</style>
</head>
<body>
  <div class="card">
    <h2>Forgot Password</h2>

    <!-- 🔥 Dynamic helper text per step -->
    <?php if ($step === 1): ?>
      <p class="helper">Enter your registered email to continue.</p>
    <?php elseif ($step === 2): ?>
      <p class="helper">Answer your saved security question.</p>
    <?php elseif ($step === 3): ?>
      <p class="helper">Create a new password for your account.</p>
    <?php elseif ($step === 0): ?>
      <p class="helper">Your password has been reset successfully.</p>
    <?php endif; ?>

    <?php if ($error): ?><div class="msg error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($msg): ?><div class="msg success"><?= $msg ?></div><?php endif; ?>

    <?php if ($step === 1): ?>
      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="flow" value="email">
        <label>Email</label>
        <input type="email" name="email" placeholder="Enter your registered email" required>
        <button>Next</button>
      </form>

    <?php elseif ($step === 2): ?>
      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="flow" value="answer">
        <label>Security Question</label>
        <input type="text" value="<?= e($question) ?>" disabled>
        <label>Your Answer</label>
        <input type="text" name="security_answer" placeholder="Type your saved answer" required>
        <button>Verify</button>
      </form>
      <a class="back" href="/jobportalsystem/auth/forgot.php">Start over</a>

    <?php elseif ($step === 3): ?>
      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="flow" value="reset">
        <label>New Password</label>
        <input type="password" name="new_password" placeholder="At least 8 characters" required>
        <label>Confirm New Password</label>
        <input type="password" name="confirm_password" placeholder="Re-type password" required>
        <button>Change Password</button>
      </form>
    <?php endif; ?>

    <?php if ($step !== 0): ?>
      <a class="back" href="/jobportalsystem/auth/login.php">← Back to login</a>
    <?php endif; ?>
  </div>
</body>
</html>
