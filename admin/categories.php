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

function admin_csrf_token_cat() {
    if (empty($_SESSION['csrf_admin_cats'])) {
        $_SESSION['csrf_admin_cats'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_admin_cats'];
}

function admin_verify_csrf_cat() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $ok = isset($_POST['csrf']) && hash_equals($_SESSION['csrf_admin_cats'] ?? '', $_POST['csrf']);
        if (!$ok) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }
    }
}

$msg = '';
$err = '';

/* ---------- handle POST actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_verify_csrf_cat();

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name  = trim($_POST['name'] ?? '');
        $slug  = trim($_POST['slug'] ?? '');
        $order = (int)($_POST['display_order'] ?? 0);

        if ($name === '' || $slug === '') {
            $err = 'Name and slug are required.';
        } else {
            $stmt = $conn->prepare("INSERT INTO service_categories (name, slug, display_order) VALUES (?,?,?)");
            $stmt->bind_param('ssi', $name, $slug, $order);
            try {
                $stmt->execute();
                $msg = 'Category added.';
            } catch (mysqli_sql_exception $e) {
                $err = 'Could not add category (maybe slug already exists).';
            }
        }

    } elseif ($action === 'update') {
        $id    = (int)($_POST['id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $slug  = trim($_POST['slug'] ?? '');
        $order = (int)($_POST['display_order'] ?? 0);

        if ($id <= 0) {
            $err = 'Invalid category ID.';
        } elseif ($name === '' || $slug === '') {
            $err = 'Name and slug are required.';
        } else {
            $stmt = $conn->prepare("UPDATE service_categories SET name=?, slug=?, display_order=? WHERE id=? LIMIT 1");
            $stmt->bind_param('ssii', $name, $slug, $order, $id);
            try {
                $stmt->execute();
                $msg = 'Category updated.';
            } catch (mysqli_sql_exception $e) {
                $err = 'Could not update category (maybe slug already exists).';
            }
        }

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $err = 'Invalid category ID.';
        } else {
            $stmt = $conn->prepare("DELETE FROM service_categories WHERE id=? LIMIT 1");
            $stmt->bind_param('i', $id);
            try {
                $stmt->execute();
                if ($stmt->affected_rows > 0) $msg = 'Category deleted.';
                else $err = 'Category not deleted.';
            } catch (mysqli_sql_exception $e) {
                $err = 'Cannot delete category (maybe used by workers).';
            }
        }
    }
}

/* ---------- fetch categories ---------- */
$cats = [];
$res = $conn->query("SELECT id, name, slug, display_order FROM service_categories ORDER BY display_order, id");
while ($row = $res->fetch_assoc()) {
    $cats[] = $row;
}

$base = '/jobportalsystem';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin – Service Categories</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    *{box-sizing:border-box;margin:0;padding:0;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}
    body{min-height:100vh;background:#020617;color:#e5e7eb;padding:1.5rem;}
    .layout{max-width:1100px;margin:0 auto;}
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

    .grid{display:grid;grid-template-columns:minmax(0,2fr) minmax(0,1.2fr);gap:1rem;align-items:flex-start;}
    table{width:100%;border-collapse:collapse;font-size:.82rem;background:#020617;border-radius:.75rem;overflow:hidden;}
    th,td{padding:.45rem .4rem;border-bottom:1px solid rgba(31,41,55,.9);text-align:left;}
    th{font-size:.75rem;text-transform:uppercase;letter-spacing:.08em;color:#9ca3af;background:#020617;}
    tr:hover td{background:#020617;}
    .btn-mini{
      font-size:.7rem;border-radius:999px;border:1px solid rgba(148,163,184,.6);
      padding:.15rem .55rem;background:#020617;color:#e5e7eb;cursor:pointer;
    }
    .btn-mini.danger{border-color:rgba(248,113,113,.7);color:#fecaca;}
    .form-card{
      background:#020617;border-radius:1rem;border:1px solid rgba(148,163,184,.4);
      padding:1rem;
    }
    .form-card h2{font-size:1rem;margin-bottom:.5rem;}
    .field{margin-bottom:.6rem;}
    .field label{display:block;font-size:.8rem;margin-bottom:.25rem;color:#e5e7eb;}
    .field input{
      width:100%;padding:.4rem .45rem;border-radius:.4rem;border:1px solid #4b5563;
      background:#020617;color:#e5e7eb;font-size:.85rem;
    }
    .field input:focus{outline:none;border-color:#38bdf8;box-shadow:0 0 0 1px #38bdf8;}
    .btn-primary{
      padding:.4rem .9rem;border-radius:.6rem;border:none;
      background:linear-gradient(135deg,#38bdf8,#22c55e);color:#020617;
      font-size:.85rem;font-weight:600;cursor:pointer;margin-top:.3rem;
    }
  </style>
</head>
<body>
  <div class="layout">
    <header>
      <div>
        <h1>Service Categories</h1>
        <p style="font-size:.85rem;color:#9ca3af;">Create, update, or remove service categories for workers.</p>
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

    <div class="grid">
      <div>
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Slug</th>
              <th>Order</th>
              <th>Delete</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($cats)): ?>
            <tr><td colspan="5">No categories found.</td></tr>
          <?php else: ?>
            <?php foreach ($cats as $c): ?>
              <tr>
                <td>#<?= (int)$c['id'] ?></td>
                <td>
                  <form method="post">
                    <input type="hidden" name="csrf" value="<?= e(admin_csrf_token_cat()) ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <input type="text" name="name" value="<?= e($c['name']) ?>" style="width:100%;margin-bottom:.2rem;">
                    <input type="text" name="slug" value="<?= e($c['slug']) ?>" style="width:100%;margin-bottom:.2rem;font-size:.78rem;">
                    <input type="number" name="display_order" value="<?= (int)$c['display_order'] ?>" style="width:60px;margin-bottom:.25rem;">
                    <button type="submit" class="btn-mini">Save</button>
                  </form>
                </td>
                <td><?= e($c['slug']) ?></td>
                <td><?= (int)$c['display_order'] ?></td>
                <td>
                  <form method="post" onsubmit="return confirm('Delete this category?');">
                    <input type="hidden" name="csrf" value="<?= e(admin_csrf_token_cat()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button type="submit" class="btn-mini danger">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div>
        <div class="form-card">
          <h2>Add New Category</h2>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= e(admin_csrf_token_cat()) ?>">
            <input type="hidden" name="action" value="add">

            <div class="field">
              <label for="name">Name</label>
              <input id="name" name="name" required placeholder="e.g., Plumbing">
            </div>

            <div class="field">
              <label for="slug">Slug</label>
              <input id="slug" name="slug" required placeholder="e.g., plumbing">
            </div>

            <div class="field">
              <label for="order">Display Order</label>
              <input id="order" type="number" name="display_order" value="0">
            </div>

            <button type="submit" class="btn-primary">Add Category</button>
          </form>
          <p style="font-size:.78rem;color:#9ca3af;margin-top:.5rem;">
            Slug should be unique and lowercase, used in URLs and filters.
          </p>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
