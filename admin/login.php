<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/* ------------- if already logged in ------------- */
if (!empty($_SESSION['user'])) {
    if (($_SESSION['user']['role'] ?? '') === 'admin') {
        redirect('/jobportalsystem/admin/dashboard.php');
    } else {
        // Logged in but not admin
        redirect('/jobportalsystem/index.php');
    }
}

/* ---------------- local helpers ----------------- */
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function csrf_token(){
    if (empty($_SESSION['csrf_admin_login'])) {
        $_SESSION['csrf_admin_login'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_admin_login'];
}

function verify_csrf(){
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $ok = isset($_POST['csrf']) && hash_equals($_SESSION['csrf_admin_login'] ?? '', $_POST['csrf']);
        if (!$ok) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }
    }
}

function ratelimit($key, $max=10, $window=600){
    $now = time();
    $_SESSION['rl'][$key] = array_filter($_SESSION['rl'][$key] ?? [], fn($t) => $t > $now - $window);
    if (count($_SESSION['rl'][$key]) >= $max) {
        http_response_code(429);
        exit('Too many attempts. Try again later.');
    }
    $_SESSION['rl'][$key][] = $now;
}

/* ----------------- main logic ------------------- */
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    ratelimit('admin_login', 8, 600); // 8 tries in 10 minutes

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } else {
        // only allow admin role here
        $sql = "SELECT id, name, email, role, password_user, profile_photo 
                FROM users 
                WHERE email = ? AND role = 'admin' 
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();

        if ($row && password_verify($password, $row['password_user'])) {
            // success
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'            => (int)$row['id'],
                'name'          => $row['name'],
                'role'          => $row['role'],
                'profile_photo' => $row['profile_photo'] ?? null,
                'email'         => $row['email'] ?? null,
            ];
            redirect('/jobportalsystem/admin/dashboard.php');
        } else {
            $error = 'Invalid admin credentials.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin Login – Job Portal System</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    *{box-sizing:border-box;margin:0;padding:0;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}
    body{
      min-height:100vh;
      display:flex;
      align-items:center;
      justify-content:center;
      background:linear-gradient(135deg,#0f172a,#020617);
      color:#e5e7eb;
    }
    .card{
      background:#020617;
      border:1px solid rgba(148,163,184,.4);
      border-radius:16px;
      padding:2rem 2.5rem;
      width:100%;
      max-width:420px;
      box-shadow:0 25px 60px rgba(15,23,42,.8);
    }
    h1{
      font-size:1.5rem;
      margin-bottom:.25rem;
    }
    .subtitle{
      font-size:.9rem;
      color:#9ca3af;
      margin-bottom:1.5rem;
    }
    label{
      display:block;
      font-size:.85rem;
      margin-bottom:.45rem;
      color:#e5e7eb;
    }
    input[type="email"],
    input[type="password"]{
      width:100%;
      padding:.65rem .75rem;
      border-radius:.5rem;
      border:1px solid #4b5563;
      background:#020617;
      color:#e5e7eb;
      font-size:.9rem;
      margin-bottom:1rem;
    }
    input:focus{
      outline:none;
      border-color:#38bdf8;
      box-shadow:0 0 0 1px #38bdf8;
    }
    .btn{
      width:100%;
      border:none;
      border-radius:.75rem;
      padding:.7rem 1rem;
      font-weight:600;
      cursor:pointer;
      background:linear-gradient(135deg,#38bdf8,#22c55e);
      color:#0f172a;
      font-size:.95rem;
      margin-top:.5rem;
      transition:transform .1s ease, box-shadow .1s ease, filter .2s ease;
      box-shadow:0 10px 30px rgba(34,197,94,.4);
    }
    .btn:hover{
      filter:brightness(1.1);
      transform:translateY(-1px);
      box-shadow:0 14px 35px rgba(34,197,94,.55);
    }
    .error{
      background:rgba(248,113,113,.1);
      border:1px solid rgba(248,113,113,.4);
      color:#fecaca;
      padding:.55rem .75rem;
      border-radius:.5rem;
      font-size:.8rem;
      margin-bottom:1rem;
    }
    .muted{
      font-size:.8rem;
      color:#9ca3af;
      margin-top:1rem;
      text-align:center;
    }
    a{
      color:#38bdf8;
      text-decoration:none;
    }
    a:hover{text-decoration:underline;}
    .badge{
      display:inline-flex;
      align-items:center;
      gap:.3rem;
      background:rgba(56,189,248,.1);
      border:1px solid rgba(56,189,248,.5);
      color:#e0f2fe;
      padding:.25rem .6rem;
      border-radius:999px;
      font-size:.7rem;
      margin-bottom:1.5rem;
      text-transform:uppercase;
      letter-spacing:.08em;
    }
  </style>
</head>
<body>
  <div class="card">
    <div class="badge">Admin Area</div>
    <h1>Sign in as Admin</h1>
    <p class="subtitle">Only authorized administrators can access this panel.</p>

    <?php if ($error): ?>
      <div class="error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

      <label for="email">Admin Email</label>
      <input type="email" id="email" name="email" required placeholder="admin@example.com" value="<?= e($_POST['email'] ?? '') ?>">

      <label for="password">Password</label>
      <input type="password" id="password" name="password" required placeholder="••••••••">

      <button class="btn" type="submit">Login</button>
    </form>

    <p class="muted">
      Back to <a href="/jobportalsystem/auth/login.php">normal user login</a>.
    </p>
  </div>
</body>
</html>
