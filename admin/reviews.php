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
function admin_csrf_reviews() {
    if (empty($_SESSION['csrf_admin_reviews'])) {
        $_SESSION['csrf_admin_reviews'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_admin_reviews'];
}
function admin_verify_csrf_reviews() {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $ok = isset($_POST['csrf']) && hash_equals($_SESSION['csrf_admin_reviews'] ?? '', $_POST['csrf']);
        if (!$ok) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }
    }
}

$msg = '';
$err = '';

/* ---------- delete review ---------- */
if ($_SERVER['REQUEST_METHOD']==='POST') {
    admin_verify_csrf_reviews();
    $action = $_POST['action'] ?? '';
    if ($action === 'delete_review') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $err = 'Invalid review ID.';
        } else {
            $stmt = $conn->prepare("DELETE FROM reviews WHERE id=? LIMIT 1");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            if ($stmt->affected_rows > 0) $msg = 'Review deleted.';
        }
    }
}

/* ---------- fetch reviews ---------- */
$reviews = [];
$sql = "SELECT r.id, r.rating, r.comment, r.created_at,
               ru.name AS reviewer_name,
               eu.name AS reviewee_name
        FROM reviews r
        JOIN users ru ON r.reviewer_id = ru.id
        JOIN users eu ON r.reviewee_id = eu.id
        ORDER BY r.created_at DESC";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) { $reviews[] = $row; }

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin – Manage Reviews</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    *{box-sizing:border-box;margin:0;padding:0;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}
    body{min-height:100vh;background:#020617;color:#e5e7eb;padding:1.5rem;}
    .layout{max-width:1100px;margin:0 auto;}
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
    .rating{
      display:inline-flex;align-items:center;gap:.15rem;font-size:.8rem;
      padding:.15rem .45rem;border-radius:999px;background:rgba(245,158,11,.15);color:#fed7aa;
    }
    .btn-mini{
      font-size:.72rem;border-radius:.7rem;border:1px solid rgba(248,113,113,.7);
      padding:.2rem .6rem;background:#020617;color:#fecaca;cursor:pointer;
    }
  </style>
</head>
<body>
  <div class="layout">
    <header>
      <div>
        <h1>Manage Reviews</h1>
        <p style="font-size:.85rem;color:#9ca3af;">View and remove inappropriate or fake reviews.</p>
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

    <?php if (empty($reviews)): ?>
      <p>No reviews found.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Reviewer → Reviewee</th>
            <th>Rating</th>
            <th>Comment</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($reviews as $r): ?>
          <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td>
              <strong><?= e($r['reviewer_name']) ?></strong><br>
              <span style="font-size:.8rem;color:#9ca3af;">→ <?= e($r['reviewee_name']) ?></span>
            </td>
            <td><span class="rating">★ <?= (int)$r['rating'] ?>/5</span></td>
            <td><?= nl2br(e($r['comment'])) ?></td>
            <td><?= e($r['created_at']) ?></td>
            <td>
              <form method="post" onsubmit="return confirm('Delete this review?');">
                <input type="hidden" name="csrf" value="<?= e(admin_csrf_reviews()) ?>">
                <input type="hidden" name="action" value="delete_review">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button type="submit" class="btn-mini">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</body>
</html>
