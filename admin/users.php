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

/* ---------- helpers ---------- */
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function admin_csrf_token() {
    if (empty($_SESSION['csrf_admin_users'])) {
        $_SESSION['csrf_admin_users'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_admin_users'];
}

function admin_verify_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $ok = isset($_POST['csrf']) && hash_equals($_SESSION['csrf_admin_users'] ?? '', $_POST['csrf']);
        if (!$ok) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }
    }
}

/* ---------- actions ---------- */
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_verify_csrf();

    $action = $_POST['action'] ?? '';
    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

    if ($userId <= 0) {
        $err = 'Invalid user ID.';
    } else {
        if ($action === 'change_role') {
            $newRole = $_POST['new_role'] ?? '';
            if (!in_array($newRole, ['admin','worker','customer'], true)) {
                $err = 'Invalid role.';
            } else {
                $stmt = $conn->prepare("UPDATE users SET role=? WHERE id=? LIMIT 1");
                $stmt->bind_param('si', $newRole, $userId);
                $stmt->execute();
                if ($stmt->affected_rows >= 0) $msg = 'Role updated.';
            }
        }
        elseif ($action === 'toggle_status') {
            $currentStatus = $_POST['current_status'] ?? 'active';
            $newStatus = $currentStatus === 'active' ? 'blocked' : 'active';
            $stmt = $conn->prepare("UPDATE users SET status=? WHERE id=? LIMIT 1");
            $stmt->bind_param('si', $newStatus, $userId);
            $stmt->execute();
            if ($stmt->affected_rows >= 0) $msg = 'Status updated.';
        }
        elseif ($action === 'delete_user') {
            // NOTE: This will cascade delete addresses, bookings, etc. because of FK constraints.
            $stmt = $conn->prepare("DELETE FROM users WHERE id=? LIMIT 1");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            if ($stmt->affected_rows > 0) $msg = 'User deleted.';
        }
    }
}

/* ---------- fetch users ---------- */
$users = [];
$res = $conn->query("SELECT id, name, email, phone, role, status, created_at FROM users ORDER BY created_at DESC");
while ($row = $res->fetch_assoc()) {
    $users[] = $row;
}

$base = '/jobportalsystem';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin – Manage Users</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    *{box-sizing:border-box;margin:0;padding:0;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}
    body{min-height:100vh;background:#020617;color:#e5e7eb;padding:1.5rem;}
    .layout{max-width:1200px;margin:0 auto;}
    header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;gap:1rem;}
    h1{font-size:1.4rem;}
    a{color:#38bdf8;text-decoration:none;}
    a:hover{text-decoration:underline;}
    nav{display:flex;gap:.5rem;flex-wrap:wrap;}
    nav a,nav form button{
      font-size:.8rem;border-radius:999px;padding:.3rem .9rem;
      border:1px solid rgba(148,163,184,.5);background:#020617;color:#e5e7eb;
      text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;cursor:pointer;
    }
    nav form{margin:0;}
    nav form button{border-color:rgba(248,113,113,.7);color:#fecaca;}

    .flash{margin-bottom:.8rem;font-size:.85rem;padding:.5rem .7rem;border-radius:.5rem;}
    .flash.ok{background:rgba(22,163,74,.1);border:1px solid rgba(22,163,74,.6);color:#bbf7d0;}
    .flash.err{background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.5);color:#fecaca;}

    table{width:100%;border-collapse:collapse;font-size:.82rem;background:#020617;border-radius:.75rem;overflow:hidden;}
    th,td{padding:.45rem .4rem;border-bottom:1px solid rgba(31,41,55,.9);text-align:left;}
    th{font-size:.75rem;text-transform:uppercase;letter-spacing:.08em;color:#9ca3af;background:#020617;}
    tr:hover td{background:#020617;}
    .role-badge,.status-badge{
      border-radius:999px;padding:.1rem .55rem;font-size:.7rem;text-transform:capitalize;
      display:inline-flex;align-items:center;
    }
    .r-admin{background:rgba(251,113,133,.16);color:#fecaca;}
    .r-worker{background:rgba(56,189,248,.16);color:#bae6fd;}
    .r-customer{background:rgba(52,211,153,.16);color:#bbf7d0;}
    .s-active{background:rgba(52,211,153,.16);color:#bbf7d0;}
    .s-blocked{background:rgba(248,113,113,.16);color:#fecaca;}
    .actions{display:flex;flex-wrap:wrap;gap:.25rem;}
    .actions form{display:inline;}
    .btn-mini{
      font-size:.7rem;border-radius:999px;border:1px solid rgba(148,163,184,.6);
      padding:.15rem .55rem;background:#020617;color:#e5e7eb;cursor:pointer;
    }
    .btn-mini.danger{border-color:rgba(248,113,113,.7);color:#fecaca;}
    .btn-mini.alt{border-color:rgba(56,189,248,.7);color:#bae6fd;}
    select{
      font-size:.75rem;border-radius:.4rem;border:1px solid #4b5563;background:#020617;color:#e5e7eb;
      padding:.15rem .35rem;
    }
    @media(max-width:768px){
      table,thead,tbody,tr,td,th{font-size:.76rem;}
    }
  </style>
</head>
<body>
  <div class="layout">
    <header>
      <div>
        <h1>Manage Users</h1>
        <p style="font-size:.85rem;color:#9ca3af;">View, change role, block/unblock, or delete users.</p>
      </div>
      <nav>
        <a href="<?= e($base) ?>/admin/dashboard.php">⬅ Admin Dashboard</a>
        <a href="<?= e($base) ?>/index.php">🏠 Main Site</a>
        <form action="<?= e($base) ?>/auth/logout.php" method="post">
          <button type="submit">🚪 Logout</button>
        </form>
      </nav>
    </header>

    <?php if ($msg): ?>
      <div class="flash ok"><?= e($msg) ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
      <div class="flash err"><?= e($err) ?></div>
    <?php endif; ?>

    <?php if (empty($users)): ?>
      <p>No users found.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Name &amp; Email</th>
            <th>Phone</th>
            <th>Role</th>
            <th>Status</th>
            <th>Joined</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td>#<?= (int)$u['id'] ?></td>
            <td>
              <?= e($u['name']) ?><br>
              <span style="font-size:.75rem;color:#9ca3af;"><?= e($u['email']) ?></span>
            </td>
            <td><?= e($u['phone']) ?></td>
            <td>
              <?php
                $rb = 'r-customer';
                if ($u['role'] === 'admin') $rb = 'r-admin';
                elseif ($u['role'] === 'worker') $rb = 'r-worker';
              ?>
              <span class="role-badge <?= $rb ?>"><?= e($u['role']) ?></span>
              <?php if ($u['id'] !== $me['id']): // cannot change own role easily ?>
              <form method="post" style="margin-top:.25rem;">
                <input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
                <input type="hidden" name="action" value="change_role">
                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <select name="new_role" onchange="this.form.submit()">
                  <option value="">Change role…</option>
                  <option value="customer">Customer</option>
                  <option value="worker">Worker</option>
                  <option value="admin">Admin</option>
                </select>
              </form>
              <?php endif; ?>
            </td>
            <td>
              <?php $sb = $u['status'] === 'active' ? 's-active' : 's-blocked'; ?>
              <span class="status-badge <?= $sb ?>"><?= e($u['status']) ?></span>
              <?php if ($u['id'] !== $me['id']): ?>
              <form method="post" style="margin-top:.25rem;">
                <input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <input type="hidden" name="current_status" value="<?= e($u['status']) ?>">
                <button type="submit" class="btn-mini alt">
                  <?= $u['status'] === 'active' ? 'Block' : 'Unblock' ?>
                </button>
              </form>
              <?php endif; ?>
            </td>
            <td><?= e($u['created_at']) ?></td>
            <td>
              <div class="actions">
                <?php if ($u['id'] !== $me['id']): ?>
                <form method="post" onsubmit="return confirm('Delete this user? This will remove related bookings, addresses, etc.');">
                  <input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete_user">
                  <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                  <button type="submit" class="btn-mini danger">Delete</button>
                </form>
                <?php else: ?>
                  <span style="font-size:.7rem;color:#9ca3af;">(You)</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</body>
</html>
