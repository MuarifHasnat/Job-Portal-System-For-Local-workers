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
function admin_csrf_workers() {
    if (empty($_SESSION['csrf_admin_workers'])) {
        $_SESSION['csrf_admin_workers'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_admin_workers'];
}
function admin_verify_csrf_workers() {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $ok = isset($_POST['csrf']) && hash_equals($_SESSION['csrf_admin_workers'] ?? '', $_POST['csrf']);
        if (!$ok) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }
    }
}

$msg = '';
$err = '';

/* ---------- update worker profile ---------- */
if ($_SERVER['REQUEST_METHOD']==='POST') {
    admin_verify_csrf_workers();

    $action = $_POST['action'] ?? '';
    if ($action === 'update_worker') {
        $uid      = (int)($_POST['user_id'] ?? 0);
        $headline = trim($_POST['headline'] ?? '');
        $bio      = trim($_POST['bio'] ?? '');
        $exp      = (int)($_POST['years_experience'] ?? 0);
        $rate     = (float)($_POST['hourly_rate'] ?? 0);
        $catId    = (int)($_POST['primary_category_id'] ?? 0);

        if ($uid <= 0) {
            $err = 'Invalid worker ID.';
        } else {
            $stmt = $conn->prepare(
                "UPDATE worker_profiles 
                 SET headline=?, bio=?, years_experience=?, hourly_rate=?, primary_category_id=?, updated_at=NOW()
                 WHERE user_id=? LIMIT 1"
            );
            $stmt->bind_param('ssiddi', $headline, $bio, $exp, $rate, $catId, $uid);
            $stmt->execute();
            if ($stmt->affected_rows >= 0) $msg = 'Worker profile updated.';
        }
    }
}

/* ---------- fetch categories ---------- */
$categories = [];
$resCat = $conn->query("SELECT id, name FROM service_categories ORDER BY name");
while ($row = $resCat->fetch_assoc()) { $categories[] = $row; }

/* ---------- fetch workers ---------- */
$workers = [];
$sql = "SELECT u.id, u.name, u.email, u.phone,
               wp.headline, wp.bio, wp.years_experience, wp.hourly_rate,
               sc.name AS category_name, wp.primary_category_id
        FROM users u
        LEFT JOIN worker_profiles wp ON wp.user_id = u.id
        LEFT JOIN service_categories sc ON wp.primary_category_id = sc.id
        WHERE u.role = 'worker'
        ORDER BY u.created_at DESC";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) { $workers[] = $row; }

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin – Manage Workers</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    *{box-sizing:border-box;margin:0;padding:0;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}
    body{min-height:100vh;background:#020617;color:#e5e7eb;padding:1.5rem;}
    .layout{max-width:1200px;margin:0 auto;}
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

    .flash{margin-bottom:.8rem;font-size:.85rem;padding:.5rem .7rem;border-radius:.5rem;}
    .flash.ok{background:rgba(22,163,74,.1);border:1px solid rgba(22,163,74,.6);color:#bbf7d0;}
    .flash.err{background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.5);color:#fecaca;}

    table{width:100%;border-collapse:collapse;font-size:.8rem;background:#020617;border-radius:.75rem;overflow:hidden;}
    th,td{padding:.45rem .4rem;border-bottom:1px solid rgba(31,41,55,.9);text-align:left;vertical-align:top;}
    th{font-size:.75rem;text-transform:uppercase;letter-spacing:.08em;color:#9ca3af;background:#020617;}
    tr:hover td{background:#020617;}

    textarea{
      width:100%;min-height:60px;
      border-radius:.4rem;border:1px solid #4b5563;background:#020617;color:#e5e7eb;
      padding:.3rem .4rem;font-size:.8rem;resize:vertical;
    }
    input[type="text"],input[type="number"]{
      width:100%;border-radius:.4rem;border:1px solid #4b5563;background:#020617;color:#e5e7eb;
      padding:.3rem .4rem;font-size:.8rem;margin-bottom:.25rem;
    }
    select{
      width:100%;border-radius:.4rem;border:1px solid #4b5563;background:#020617;color:#e5e7eb;
      padding:.3rem .4rem;font-size:.8rem;margin-bottom:.25rem;
    }
    input:focus,textarea:focus,select:focus{
      outline:none;border-color:#38bdf8;box-shadow:0 0 0 1px #38bdf8;
    }
    .btn-mini{
      font-size:.75rem;border-radius:.6rem;border:1px solid rgba(148,163,184,.6);
      padding:.25rem .7rem;background:#020617;color:#e5e7eb;cursor:pointer;margin-top:.2rem;
    }
  </style>
</head>
<body>
  <div class="layout">
    <header>
      <div>
        <h1>Manage Workers</h1>
        <p style="font-size:.85rem;color:#9ca3af;">View and adjust worker profiles, categories, experience, and rates.</p>
      </div>
      <nav>
        <a href="<?= e($base) ?>/admin/dashboard.php">⬅ Admin Dashboard</a>
        <a href="<?= e($base) ?>/index.php">🏠 Main Site</a>
        <form action="<?= e($base) ?>/auth/logout.php" method="post">
          <button type="submit">🚪 Logout</button>
        </form>
      </nav>
    </header>

    <?php if ($msg): ?><div class="flash ok"><?= e($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>

    <?php if (empty($workers)): ?>
      <p>No workers found.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Worker</th>
            <th>Profile</th>
            <th>Category &amp; Rate</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($workers as $w): ?>
          <tr>
            <td style="width:22%;">
              <strong><?= e($w['name']) ?></strong><br>
              <span style="font-size:.8rem;color:#9ca3af;"><?= e($w['email']) ?></span><br>
              <span style="font-size:.8rem;color:#9ca3af;"><?= e($w['phone']) ?></span>
            </td>
            <td style="width:38%;">
              <form method="post">
                <input type="hidden" name="csrf" value="<?= e(admin_csrf_workers()) ?>">
                <input type="hidden" name="action" value="update_worker">
                <input type="hidden" name="user_id" value="<?= (int)$w['id'] ?>">

                <label style="font-size:.75rem;">Headline</label>
                <input type="text" name="headline" value="<?= e($w['headline']) ?>" placeholder="e.g., Expert Electrician">

                <label style="font-size:.75rem;">Bio</label>
                <textarea name="bio" placeholder="Short description about this worker"><?= e($w['bio']) ?></textarea>
            </td>
            <td style="width:40%;">
                <label style="font-size:.75rem;">Primary Category</label>
                <select name="primary_category_id">
                  <option value="0">-- None --</option>
                  <?php foreach ($categories as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= ($w['primary_category_id']==$c['id'] ? 'selected' : '') ?>>
                      <?= e($c['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>

                <label style="font-size:.75rem;">Years of Experience</label>
                <input type="number" name="years_experience" value="<?= (int)$w['years_experience'] ?>" min="0" max="80">

                <label style="font-size:.75rem;">Hourly Rate</label>
                <input type="number" step="0.01" name="hourly_rate" value="<?= e($w['hourly_rate']) ?>" min="0">

                <button type="submit" class="btn-mini">Save Changes</button>
              </form>
              <p style="font-size:.75rem;color:#9ca3af;margin-top:.3rem;">
                Current category: <?= e($w['category_name'] ?? 'None') ?>
              </p>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</body>
</html>
