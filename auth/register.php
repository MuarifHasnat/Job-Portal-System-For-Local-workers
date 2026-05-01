<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (is_logged_in()) redirect('/jobportalsystem/index.php');

/* ---------- helpers ---------- */
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function csrf_token(){ if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function verify_csrf(){
  if ($_SERVER['REQUEST_METHOD']==='POST'){
    $ok = isset($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf']);
    if (!$ok){ http_response_code(419); exit('Invalid CSRF token.'); }
  }
}
function ratelimit($key, $max=12, $window=600){
  $now=time();
  $_SESSION['rl'][$key]=array_filter($_SESSION['rl'][$key]??[],fn($t)=>$t>$now-$window);
  if (count($_SESSION['rl'][$key]) >= $max){ http_response_code(429); exit('Too many attempts. Try again later.'); }
  $_SESSION['rl'][$key][]=$now;
}
function users_has_security_answer_hash(mysqli $conn): bool {
  $res = $conn->query("SHOW COLUMNS FROM users LIKE 'security_answer_hash'");
  return $res && $res->num_rows > 0;
}

/* ---------- handle post ---------- */
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  ratelimit('register');

  $name     = trim($_POST['name'] ?? '');
  $email    = trim($_POST['email'] ?? '');
  $phone    = trim($_POST['phone'] ?? '');
  $pass     = $_POST['password'] ?? '';
  $role     = $_POST['role'] ?? 'customer';
  $question = trim($_POST['security_question'] ?? '');
  $answer   = trim($_POST['security_answer'] ?? '');

  // ---- REQUIRED: name, email, phone, password, question, answer ----
  if (!$name || !$email || !$phone || !$pass || !$question || !$answer) {
    $error = 'All fields are required.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Please enter a valid email address.';
  } elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
    $error = 'Please enter a valid phone number.';
  } elseif (strlen($pass) < 6) {
    $error = 'Password must be at least 6 characters.';
  } else {
    $password_hash = password_hash($pass, PASSWORD_BCRYPT);
    $answer_hash   = password_hash($answer, PASSWORD_BCRYPT);

    try {
      // unique email
      $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
      $stmt->bind_param('s', $email);
      $stmt->execute();
      if ($stmt->get_result()->num_rows > 0) $error = 'This email is already in use.';
      $stmt->close();

      // unique phone
      if (!$error) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
        $stmt->bind_param('s', $phone);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) $error = 'This phone is already in use.';
        $stmt->close();
      }

      if (!$error) {
        $has_hash_col = users_has_security_answer_hash($conn);
        if ($has_hash_col) {
          $sql = "INSERT INTO users (name,email,phone,password_user,role,security_question,security_answer_hash)
                  VALUES (?,?,?,?,?,?,?)";
        } else {
          $sql = "INSERT INTO users (name,email,phone,password_user,role,security_question,security_answer)
                  VALUES (?,?,?,?,?,?,?)";
        }
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sssssss', $name, $email, $phone, $password_hash, $role, $question, $answer_hash);
        $stmt->execute();

        $_SESSION['user'] = ['id'=>$stmt->insert_id,'name'=>$name,'role'=>$role];
        $stmt->close();
        redirect('/jobportalsystem/index.php');
      }
    } catch (Throwable $e) {
      $error = $error ?: 'Could not create account. Please try again.';
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Sign Up • Job Portal System For Local Workers</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: radial-gradient(circle at top, #e0f2fe 0%, #bae6fd 50%, #93c5fd 100%);
      --accent:#0ea5e9; --accent-dark:#0369a1;
      --card-bg: rgba(255,255,255,0.88); --card-border: rgba(255,255,255,0.65);
      --text:#0f172a; --muted:#64748b;
    }
    body{min-height:100vh;margin:0;display:flex;align-items:center;justify-content:center;background:var(--bg);font-family:'Inter',system-ui,sans-serif;color:var(--text);}
    .register-wrap{width:100%;max-width:460px;padding:24px;}
    .glass{background:var(--card-bg);border:1px solid var(--card-border);border-radius:20px;backdrop-filter:blur(12px);box-shadow:0 15px 40px rgba(15,23,42,0.12);padding:32px 28px;transition:transform .2s ease;}
    .glass:hover{transform:translateY(-3px);}
    .logo{font-weight:700;text-align:center;font-size:15px;margin-bottom:26px;color:var(--accent-dark);}
    label{font-weight:600;font-size:.9rem;margin:10px 0 6px;display:block}
    .input-wrap{position:relative}
    .form-control,.form-select{width:100%;padding:.65rem .9rem;border-radius:12px;border:1px solid #cbd5e1;background:#fff;font-size:.95rem;margin-bottom:14px;}
    .form-control:focus,.form-select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 2px rgba(14,165,233,0.2);}
    .toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;opacity:.7;}
    .btn-primary{width:100%;border:none;border-radius:12px;padding:.75rem;font-weight:700;color:#fff;background:linear-gradient(90deg,var(--accent),var(--accent-dark));box-shadow:0 8px 20px rgba(14,165,233,0.3);cursor:pointer;transition:background .2s ease,transform .1s ease;}
    .btn-primary:hover{filter:brightness(1.05);}
    .btn-primary:active{transform:scale(0.98);}
    .alert{border-radius:10px;padding:10px 14px;background:#fee2e2;color:#b91c1c;font-size:.9rem;margin-bottom:16px;}
    .tiny{font-size:.85rem;color:#64748b;text-align:center;margin-top:18px;}
    .tiny a{color:var(--accent-dark);font-weight:600;text-decoration:none;}
    .tiny a:hover{text-decoration:underline;}
    .helper{font-size:.8rem;color:var(--muted);margin-top:-6px;margin-bottom:10px;}
  </style>
</head>
<body>
  <div class="register-wrap">
    <div class="glass">
      <div class="logo">💼 Job Portal System For Local Workers</div>

      <?php if($error): ?><div class="alert"><?= e($error) ?></div><?php endif; ?>

      <form method="post" autocomplete="off" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <label>Full Name</label>
        <input type="text" name="name" class="form-control" placeholder="Full Name" required>

        <label>Email</label>
        <input type="email" name="email" class="form-control" placeholder="you@example.com" required>

        <label>Phone</label>
        <input
          type="text"
          name="phone"
          class="form-control"
          placeholder="e.g. 017XXXXXXXX"
          pattern="[0-9+\-\s()]{11}"
          title="11 digits (you may use + - spaces or parentheses)"
          required>

        <label>Password</label>
        <div class="input-wrap">
          <input type="password" id="pw" name="password" class="form-control" placeholder="Password" minlength="6" required>
          <span class="toggle" id="pwToggle">👁️</span>
        </div>
        <div class="helper">Use at least 6 characters.</div>

        <label>Role</label>
        <select name="role" class="form-select" required>
          <option value="customer">Customer</option>
          <option value="worker">Worker</option>
        </select>

        <label>Security Question</label>
        <select name="security_question" class="form-select" required>
          <option value="">Select a security question</option>
          <option value="What is your favorite color?">What is your favorite color?</option>
          <option value="What is your favorite food?">What is your favorite food?</option>
          <option value="What is your pet’s name?">What is your pet’s name?</option>
          <option value="What city were you born in?">What city were you born in?</option>
        </select>

        <label>Your Answer</label>
        <input type="text" name="security_answer" class="form-control" placeholder="Your answer" required>

        <button class="btn-primary">Sign Up</button>
      </form>

      <p class="tiny">
        Already have an account? <a href="/jobportalsystem/auth/login.php">Log in</a>
      </p>
    </div>
  </div>

  <script>
    const pw = document.getElementById('pw');
    const t  = document.getElementById('pwToggle');
    t.addEventListener('click', () => {
      pw.type = pw.type === 'password' ? 'text' : 'password';
      t.textContent = pw.type === 'password' ? '👁️' : '🙈';
    });
  </script>
</body>
</html>
