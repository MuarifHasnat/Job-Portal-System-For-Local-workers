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
function admin_csrf_bookings() {
    if (empty($_SESSION['csrf_admin_bookings'])) {
        $_SESSION['csrf_admin_bookings'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_admin_bookings'];
}
function admin_verify_csrf_bookings() {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $ok = isset($_POST['csrf']) && hash_equals($_SESSION['csrf_admin_bookings'] ?? '', $_POST['csrf']);
        if (!$ok) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }
    }
}

/* ---------- actions ---------- */
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    admin_verify_csrf_bookings();

    $action    = $_POST['action'] ?? '';
    $bookingId = (int)($_POST['booking_id'] ?? 0);

    if ($bookingId <= 0) {
        $err = 'Invalid booking ID.';
    } else {
        if ($action === 'change_status') {
            $newStatus = $_POST['status'] ?? '';
            $allowed = ['pending','confirmed','completed','cancelled','done'];
            if (!in_array($newStatus, $allowed, true)) {
                $err = 'Invalid status.';
            } else {
                $stmt = $conn->prepare("UPDATE bookings SET status=?, updated_at=NOW() WHERE id=? LIMIT 1");
                $stmt->bind_param('si', $newStatus, $bookingId);
                $stmt->execute();
                if ($stmt->affected_rows >= 0) $msg = 'Booking status updated.';
            }
        } elseif ($action === 'delete_booking') {
            $stmt = $conn->prepare("DELETE FROM bookings WHERE id=? LIMIT 1");
            $stmt->bind_param('i', $bookingId);
            $stmt->execute();
            if ($stmt->affected_rows > 0) $msg = 'Booking deleted.';
        }
    }
}

/* ---------- fetch bookings ---------- */
$bookings = [];
$sql = "SELECT b.id, b.status, b.created_at, b.scheduled_at,
               cu.name AS customer_name,
               wu.name AS worker_name
        FROM bookings b
        JOIN users cu ON b.user_id   = cu.id
        JOIN users wu ON b.worker_id = wu.id
        ORDER BY b.created_at DESC";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) { $bookings[] = $row; }

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin – Manage Bookings</title>
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
    th,td{padding:.45rem .4rem;border-bottom:1px solid rgba(31,41,55,.9);text-align:left;}
    th{font-size:.75rem;text-transform:uppercase;letter-spacing:.08em;color:#9ca3af;background:#020617;}
    tr:hover td{background:#020617;}

    .status-pill{
      padding:.1rem .55rem;border-radius:999px;font-size:.7rem;text-transform:capitalize;
      display:inline-flex;align-items:center;
    }
    .s-pending{background:rgba(251,191,36,.15);color:#facc15;}
    .s-confirmed{background:rgba(52,211,153,.18);color:#6ee7b7;}
    .s-completed{background:rgba(59,130,246,.18);color:#bfdbfe;}
    .s-cancelled{background:rgba(248,113,113,.18);color:#fecaca;}
    .s-done{background:rgba(59,130,246,.18);color:#bfdbfe;}

    .actions{display:flex;flex-wrap:wrap;gap:.25rem;}
    .actions form{display:inline;}
    select{
      font-size:.75rem;border-radius:.4rem;border:1px solid #4b5563;background:#020617;color:#e5e7eb;
      padding:.15rem .4rem;
    }
    .btn-mini{
      font-size:.7rem;border-radius:999px;border:1px solid rgba(148,163,184,.6);
      padding:.15rem .55rem;background:#020617;color:#e5e7eb;cursor:pointer;
    }
    .btn-mini.danger{border-color:rgba(248,113,113,.7);color:#fecaca;}
  </style>
</head>
<body>
  <div class="layout">
    <header>
      <div>
        <h1>Manage Bookings</h1>
        <p style="font-size:.85rem;color:#9ca3af;">View and control all bookings between customers and workers.</p>
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

    <?php if (empty($bookings)): ?>
      <p>No bookings found.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Customer</th>
            <th>Worker</th>
            <th>Status</th>
            <th>Scheduled</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($bookings as $b):
          $st = $b['status'];
          $cls = 's-pending';
          if ($st==='confirmed') $cls='s-confirmed';
          elseif ($st==='completed') $cls='s-completed';
          elseif ($st==='done') $cls='s-done';
          elseif ($st==='cancelled') $cls='s-cancelled';
        ?>
          <tr>
            <td>#<?= (int)$b['id'] ?></td>
            <td><?= e($b['customer_name']) ?></td>
            <td><?= e($b['worker_name']) ?></td>
            <td><span class="status-pill <?= $cls ?>"><?= e($st) ?></span></td>
            <td><?= e($b['scheduled_at'] ?? '') ?></td>
            <td><?= e($b['created_at']) ?></td>
            <td>
              <div class="actions">
                <form method="post">
                  <input type="hidden" name="csrf" value="<?= e(admin_csrf_bookings()) ?>">
                  <input type="hidden" name="action" value="change_status">
                  <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                  <select name="status" onchange="this.form.submit()">
                    <option value="">Set status…</option>
                    <option value="pending">Pending</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="completed">Completed</option>
                    <option value="done">Done</option>
                    <option value="cancelled">Cancelled</option>
                  </select>
                </form>
                <form method="post" onsubmit="return confirm('Delete this booking?');">
                  <input type="hidden" name="csrf" value="<?= e(admin_csrf_bookings()) ?>">
                  <input type="hidden" name="action" value="delete_booking">
                  <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                  <button type="submit" class="btn-mini danger">Delete</button>
                </form>
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
