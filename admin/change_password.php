<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

$me   = $_SESSION['user'] ?? null;
$role = $me['role'] ?? '';

if ($role !== 'admin') {
    http_response_code(403);
    exit('Forbidden: Admins only.');
}

function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
$base = '/jobportalsystem';

/* ---------- CSRF ---------- */
function admin_csrf_pwd() {
    if (empty($_SESSION['csrf_admin_pwd'])) {
        $_SESSION['csrf_admin_pwd'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_admin_pwd'];
}
function admin_verify_csrf_pwd() {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $ok = isset($_POST['csrf']) && hash_equals($_SESSION['csrf_admin_pwd'] ?? '', $_POST['csrf']);
        if (!$ok) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }
    }
}

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    admin_verify_csrf_pwd();

    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $adminId = (int)($me['id'] ?? 0);

    if ($current==='' || $new==='' || $confirm==='') {
        $err = 'All fields are required.';
    } elseif ($new !== $confirm) {
        $err = 'New password and confirm password do not match.';
    } elseif (strlen($new) < 6) {
        $err = 'New password must be at least 6 characters.';
    } else {
        // fetch current hash
        $stmt = $conn->prepare("SELECT password_user FROM users WHERE id=? AND role='admin' LIMIT 1");
        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $hash = $stmt->get_result()->fetch_column();

        if (!$hash || !password_verify($current, $hash)) {
            $err = 'Current password is incorrect.';
        } else {
            $newHash = password_hash($new, PASSWORD_BCRYPT);
            $stmt2 = $conn->prepare("UPDATE users SET password_user=? WHERE id=? LIMIT 1");
            $stmt2->bind_param('si', $newHash, $adminId);
            $stmt2->execute();
            if ($stmt2->affected_rows >= 0) {
                $msg = 'Password updated successfully.';
            } else {
                $err = 'Failed to update password.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin – Change Password</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    *{box-sizing:border-box;margin:0;padding:0;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}
    body{min-height:100vh;background:#020617;color:#e5e7eb;padding:1.5rem;}
    .layout{max-width:600px;margin:0 auto;}
    header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;gap:1rem;}
    h1{font-size:1.4rem;}
    nav{display:flex;gap:.5rem;flex-wrap:wrap;}
    nav a,nav form button{
      font-size:.8rem;border-radius:999px;padding:.35rem .85rem;
      border:1px solid rgba(148,163,184,.5);background:#020617;color:#e5e7eb;
      text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;cursor:pointer;
    }
    nav a:hover,nav form button:hover{background:#0f172a;}
    nav form{margin:0;}
    nav form button{border-color:rgba(248,113,113,.7);color:#fecaca;}

    .card{
      background:#020617;border-radius:1rem;border:1px solid rgba(148,163,184,.4);
      padding:1.2rem 1.4rem;box-shadow:0 18px 45px rgba(15,23,42,.8);
    }
    label{display:block;font-size:.85rem;margin-bottom:.25rem;}
    input[type="password"]{
      width:100%;border-radius:.5rem;border:1px solid #4b5563;background:#020617;color:#e5e7eb;
      padding:.45rem .6rem;font-size:.9rem;margin-bottom:.7rem;
    }
    input:focus{outline:none;border-color:#38bdf8;box-shadow:0 0 0 1px #38bdf8;}
    .btn{
      border:none;border-radius:.7rem;padding:.5rem 1rem;font-size:.9rem;font-weight:600;
      background:linear-gradient(135deg,#38bdf8,#22c55e);color:#020617;cursor:pointer;
    }
    .flash{margin-bottom:.8rem;font-size:.85rem;padding:.5rem .7rem;border-radius:.5rem;}
    .flash.ok{background:rgba(22,163,74,.1);border:1px solid rgba(22,163,74,.6);color:#bbf7d0;}
    .flash.err{background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.5);color:#fecaca;}
    .hint{font-size:.8rem;color:#9ca3af;margin-top:.25rem;}
  </style>
</head>
<body>
  <div class="layout">
    <header>
      <div>
        <h1>Change Admin Password</h1>
        <p class="hint">Update your own password securely.</p>
      </div>
      <nav>
        <a href="<?= e($base) ?>/admin/dashboard.php">⬅ Admin Dashboard</a>
        <form action="<?= e($base) ?>/auth/logout.php" method="post">
          <button type="submit">🚪 Logout</button>
        </form>
      </nav>
    </header>

    <div class="card">
      <?php if ($msg): ?><div class="flash ok"><?= e($msg) ?></div><?php endif; ?>
      <?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>

      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(admin_csrf_pwd()) ?>">

        <label for="current_password">Current Password</label>
        <input type="password" id="current_password" name="current_password" required>

        <label for="new_password">New Password</label>
        <input type="password" id="new_password" name="new_password" required>

        <label for="confirm_password">Confirm New Password</label>
        <input type="password" id="confirm_password" name="confirm_password" required>

        <button type="submit" class="btn">Update Password</button>
      </form>
    </div>
  </div>
</body>
</html>
