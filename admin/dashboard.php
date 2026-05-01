<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

$me = $_SESSION['user'] ?? null;
$role = $me['role'] ?? '';

if ($role !== 'admin') {
    http_response_code(403);
    exit('Forbidden: Admins only.');
}

/* ---------- small helper ----------- */
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$base = '/jobportalsystem';

/* ---------- stats queries ----------- */

// total users
$totalUsers = (int)$conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'];

// role wise
$totalWorkers   = (int)$conn->query("SELECT COUNT(*) AS c FROM users WHERE role='worker'")->fetch_assoc()['c'];
$totalCustomers = (int)$conn->query("SELECT COUNT(*) AS c FROM users WHERE role='customer'")->fetch_assoc()['c'];
$totalAdmins    = (int)$conn->query("SELECT COUNT(*) AS c FROM users WHERE role='admin'")->fetch_assoc()['c'];

// bookings
$totalBookings      = (int)$conn->query("SELECT COUNT(*) AS c FROM bookings")->fetch_assoc()['c'];
$pendingBookings    = (int)$conn->query("SELECT COUNT(*) AS c FROM bookings WHERE status='pending'")->fetch_assoc()['c'];
$confirmedBookings  = (int)$conn->query("SELECT COUNT(*) AS c FROM bookings WHERE status='confirmed'")->fetch_assoc()['c'];
$completedBookings  = (int)$conn->query("SELECT COUNT(*) AS c FROM bookings WHERE status IN ('completed','done')")->fetch_assoc()['c'];
$cancelledBookings  = (int)$conn->query("SELECT COUNT(*) AS c FROM bookings WHERE status='cancelled'")->fetch_assoc()['c'];

// reviews & categories
$totalReviews    = (int)$conn->query("SELECT COUNT(*) AS c FROM reviews")->fetch_assoc()['c'];
$totalCategories = (int)$conn->query("SELECT COUNT(*) AS c FROM service_categories")->fetch_assoc()['c'];

// latest 5 bookings (with names)
$latestBookings = [];
$sql = "SELECT b.id, b.status, b.created_at,
               cu.name AS customer_name,
               wu.name AS worker_name
        FROM bookings b
        JOIN users cu ON b.user_id   = cu.id
        JOIN users wu ON b.worker_id = wu.id
        ORDER BY b.created_at DESC
        LIMIT 5";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) { $latestBookings[] = $row; }

// latest 5 users
$latestUsers = [];
$sql2 = "SELECT id, name, email, role, created_at 
         FROM users 
         ORDER BY created_at DESC 
         LIMIT 5";
$res2 = $conn->query($sql2);
while ($row = $res2->fetch_assoc()) { $latestUsers[] = $row; }

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin Dashboard – Job Portal System</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    *{box-sizing:border-box;margin:0;padding:0;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}
    body{
      min-height:100vh;
      background:radial-gradient(circle at top,#1e293b,#020617 55%);
      color:#e5e7eb;
      padding:1.5rem;
    }
    .layout{
      max-width:1200px;
      margin:0 auto;
    }
    header{
      display:flex;
      align-items:center;
      justify-content:space-between;
      margin-bottom:1.5rem;
      gap:1rem;
    }
    .title-wrap h1{
      font-size:1.6rem;
      font-weight:700;
    }
    .title-wrap p{
      font-size:.9rem;
      color:#9ca3af;
      margin-top:.25rem;
    }
    .chip{
      padding:.25rem .7rem;
      border-radius:999px;
      border:1px solid rgba(52,211,153,.5);
      background:rgba(6,95,70,.4);
      font-size:.75rem;
      text-transform:uppercase;
      letter-spacing:.09em;
      display:inline-flex;
      align-items:center;
      gap:.3rem;
      color:#bbf7d0;
    }
    nav{
      display:flex;
      gap:.5rem;
      flex-wrap:wrap;
    }
    nav a, nav form button{
      font-size:.8rem;
      border-radius:999px;
      padding:.4rem .85rem;
      border:1px solid rgba(148,163,184,.5);
      background:rgba(15,23,42,.8);
      color:#e5e7eb;
      text-decoration:none;
      display:inline-flex;
      align-items:center;
      gap:.35rem;
      cursor:pointer;
    }
    nav a:hover, nav form button:hover{
      background:#0f172a;
    }
    nav form{margin:0;}
    nav form button{
      border-color:rgba(248,113,113,.7);
      color:#fecaca;
    }

    .grid{
      display:grid;
      grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
      gap:1rem;
      margin-bottom:1.5rem;
    }
    .card{
      background:rgba(15,23,42,.9);
      border:1px solid rgba(148,163,184,.4);
      border-radius:1rem;
      padding:1rem 1.1rem;
      box-shadow:0 18px 45px rgba(15,23,42,.8);
    }
    .card h2{
      font-size:.9rem;
      text-transform:uppercase;
      letter-spacing:.12em;
      color:#9ca3af;
      margin-bottom:.35rem;
    }
    .card .big{
      font-size:1.6rem;
      font-weight:700;
    }
    .muted{
      font-size:.78rem;
      color:#9ca3af;
      margin-top:.25rem;
    }
    .pill-row{
      display:flex;
      flex-wrap:wrap;
      gap:.4rem;
      margin-top:.65rem;
    }
    .pill{
      font-size:.7rem;
      border-radius:999px;
      padding:.15rem .6rem;
      background:rgba(15,23,42,1);
      border:1px solid rgba(148,163,184,.5);
    }
    table{
      width:100%;
      border-collapse:collapse;
      font-size:.8rem;
      margin-top:.4rem;
    }
    th,td{
      padding:.4rem .35rem;
      text-align:left;
      border-bottom:1px solid rgba(31,41,55,.9);
    }
    th{
      font-size:.75rem;
      text-transform:uppercase;
      letter-spacing:.08em;
      color:#9ca3af;
    }
    tbody tr:hover{
      background:rgba(15,23,42,.9);
    }
    .status{
      display:inline-flex;
      align-items:center;
      padding:.1rem .55rem;
      border-radius:999px;
      font-size:.7rem;
      text-transform:capitalize;
    }
    .st-pending{background:rgba(251,191,36,.15);color:#facc15;}
    .st-confirmed{background:rgba(52,211,153,.18);color:#6ee7b7;}
    .st-completed{background:rgba(59,130,246,.18);color:#bfdbfe;}
    .st-cancelled{background:rgba(248,113,113,.18);color:#fecaca;}
    .role-badge{
      padding:.1rem .6rem;
      border-radius:999px;
      font-size:.7rem;
      text-transform:capitalize;
    }
    .r-admin{background:rgba(251,113,133,.16);color:#fecaca;}
    .r-worker{background:rgba(56,189,248,.16);color:#bae6fd;}
    .r-customer{background:rgba(52,211,153,.16);color:#bbf7d0;}
    @media (max-width:640px){
      body{padding:1rem;}
      header{flex-direction:column;align-items:flex-start;}
    }
  </style>
</head>
<body>
  <div class="layout">
    <header>
      <div class="title-wrap">
        <div class="chip">Admin Dashboard</div>
        <h1>Welcome, <?= e($me['name'] ?? 'Admin') ?></h1>
        <p class="subtitle">Here you can monitor users, workers, bookings, reviews, and categories.</p>
      </div>

      <nav>
        <!-- FULL ADMIN PANEL LINKS -->
        <a href="<?= e($base) ?>/admin/users.php">👥 Users</a>
        <a href="<?= e($base) ?>/admin/categories.php">🗂 Categories</a>
        <a href="<?= e($base) ?>/admin/bookings.php">📅 Bookings</a>
        <a href="<?= e($base) ?>/admin/workers.php">🛠 Workers</a>
        <a href="<?= e($base) ?>/admin/reviews.php">⭐ Reviews</a>
        <a href="<?= e($base) ?>/admin/change_password.php">🔒 Change Password</a>

        <!-- existing links -->
        <a href="<?= e($base) ?>/index.php">🏠 Main Dashboard</a>
        <form action="<?= e($base) ?>/auth/logout.php" method="post">
          <button type="submit">🚪 Logout</button>
        </form>
      </nav>
    </header>

    <!-- Top stats -->
    <div class="grid">
      <div class="card">
        <h2>Users</h2>
        <div class="big"><?= $totalUsers ?></div>
        <div class="pill-row">
          <span class="pill">Workers: <?= $totalWorkers ?></span>
          <span class="pill">Customers: <?= $totalCustomers ?></span>
          <span class="pill">Admins: <?= $totalAdmins ?></span>
        </div>
        <p class="muted">All registered accounts in the system.</p>
      </div>

      <div class="card">
        <h2>Bookings</h2>
        <div class="big"><?= $totalBookings ?></div>
        <div class="pill-row">
          <span class="pill">Pending: <?= $pendingBookings ?></span>
          <span class="pill">Confirmed: <?= $confirmedBookings ?></span>
          <span class="pill">Completed: <?= $completedBookings ?></span>
          <span class="pill">Cancelled: <?= $cancelledBookings ?></span>
        </div>
        <p class="muted">Service requests between customers and workers.</p>
      </div>

      <div class="card">
        <h2>Reviews & Categories</h2>
        <div class="big"><?= $totalReviews ?></div>
        <div class="pill-row">
          <span class="pill">Service Categories: <?= $totalCategories ?></span>
        </div>
        <p class="muted">Quality feedback and structure of the marketplace.</p>
      </div>
    </div>

    <!-- Tables -->
    <div class="grid">
      <div class="card">
        <h2>Latest Bookings</h2>
        <?php if (empty($latestBookings)): ?>
          <p class="muted">No bookings found.</p>
        <?php else: ?>
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Customer</th>
                <th>Worker</th>
                <th>Status</th>
                <th>Created</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($latestBookings as $b): 
                $st = $b['status'];
                $cls = 'st-pending';
                if ($st === 'confirmed') $cls = 'st-confirmed';
                elseif ($st === 'cancelled') $cls = 'st-cancelled';
                elseif ($st === 'completed' || $st === 'done') $cls = 'st-completed';
              ?>
              <tr>
                <td>#<?= (int)$b['id'] ?></td>
                <td><?= e($b['customer_name']) ?></td>
                <td><?= e($b['worker_name']) ?></td>
                <td><span class="status <?= $cls ?>"><?= e($st) ?></span></td>
                <td><?= e($b['created_at']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <div class="card">
        <h2>Newest Users</h2>
        <?php if (empty($latestUsers)): ?>
          <p class="muted">No users found.</p>
        <?php else: ?>
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Role</th>
                <th>Joined</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($latestUsers as $u): 
                $r = $u['role'];
                $rc = 'r-customer';
                if ($r === 'admin') $rc = 'r-admin';
                elseif ($r === 'worker') $rc = 'r-worker';
              ?>
              <tr>
                <td><?= (int)$u['id'] ?></td>
                <td><?= e($u['name']) ?></td>
                <td><span class="role-badge <?= $rc ?>"><?= e($r) ?></span></td>
                <td><?= e($u['created_at']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
