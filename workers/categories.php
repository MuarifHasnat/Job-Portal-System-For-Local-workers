<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

// get all categories
$cats = $conn->query("SELECT id, name FROM service_categories ORDER BY display_order ASC, name ASC");

// quick counts (for small pills under title – optional)
$workers_count = 0;
$resW = $conn->query("SELECT COUNT(*) AS c FROM worker_profiles");
if ($resW) {
    $workers_count = (int)$resW->fetch_assoc()['c'];
}
$cats_count = 0;
$resC = $conn->query("SELECT COUNT(*) AS c FROM service_categories");
if ($resC) {
    $cats_count = (int)$resC->fetch_assoc()['c'];
}

$displayName = $_SESSION['user']['name'] ?? ($_SESSION['user']['email'] ?? 'there');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Categories &amp; Workers</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --page-bg: radial-gradient(circle at top, #e0f2fe 0%, #bae6fd 50%, #93c5fd 100%);
      --card: #ffffff;
      --accent: #0ea5e9;
      --accent-soft: rgba(14,165,233,0.12);
      --text: #0f172a;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      background: var(--page-bg);
      font-family: "Inter", system-ui, sans-serif;
      color: var(--text);
      min-height: 100vh;
    }

    /* top bar same vibe as index */
    .topbar {
      background: rgba(255,255,255,0.45);
      backdrop-filter: blur(12px);
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: .5rem 1.2rem;
      box-shadow: 0 4px 16px rgba(15,23,42,0.05);
      position: sticky;
      top: 0;
      z-index: 20;
    }
    .top-left {
      display: flex;
      gap: .5rem;
      align-items: center;
      font-weight: 700;
    }
    .top-left small {
      font-weight: 400;
      font-size: .7rem;
      color: #475569;
    }
    .back-btn {
      text-decoration: none;
      background: #e2e8f0;
      border-radius: 999px;
      padding: 5px 14px;
      font-size: .75rem;
      color: #0f172a;
      font-weight: 500;
    }
    .back-btn:hover {
      background: #cbd5f5;
    }

    .page-wrap {
      max-width: 1100px;
      margin: 28px auto 50px;
      padding: 0 1.2rem 2rem;
    }

    .page-header {
      display: flex;
      flex-direction: column;
      gap: .4rem;
      margin-bottom: 1.4rem;
    }
    .page-title {
      font-size: 1.5rem;
      font-weight: 700;
    }
    .page-sub {
      color: #64748b;
      font-size: .9rem;
    }

    /* stat pills under title (like home) */
    .pill-row {
      display: flex;
      gap: .6rem;
      flex-wrap: wrap;
      margin-top: .4rem;
    }
    .pill {
      background: rgba(255,255,255,.5);
      border: 1px solid rgba(255,255,255,.35);
      border-radius: 999px;
      padding: 3px 14px 5px;
      font-size: .72rem;
      display: flex;
      gap: .35rem;
      align-items: center;
    }

    .cat-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 1.1rem;
      margin-top: 1.2rem;
    }
    .cat-card {
      background: var(--card);
      border-radius: 18px;
      padding: 1rem 1.15rem 1rem;
      box-shadow: 0 14px 32px rgba(15,23,42,0.05);
      border: 1px solid rgba(148,163,184,0.12);
      transition: transform .14s ease, box-shadow .14s ease;
      display: flex;
      flex-direction: column;
      gap: .5rem;
    }
    .cat-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 16px 36px rgba(15,23,42,0.12);
    }

    .cat-top {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: .5rem;
    }
    .cat-name {
      font-weight: 600;
      font-size: 1.02rem;
    }
    .badge {
      background: #0ea5e9;
      color: #fff;
      border-radius: 999px;
      font-size: .65rem;
      padding: 3px 10px;
      font-weight: 500;
    }
    .cat-desc {
      font-size: .75rem;
      color: #94a3b8;
    }
    .cat-footer {
      margin-top: .5rem;
    }
    .view-btn {
      text-decoration: none;
      background: rgba(14,165,233,0.12);
      border: 1px solid rgba(14,165,233,0.4);
      border-radius: 999px;
      padding: 5px 12px;
      color: #0f172a;
      font-size: .7rem;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: .25rem;
    }
    .view-btn:hover {
      background: rgba(14,165,233,0.22);
    }

    footer {
      text-align: center;
      color: #94a3b8;
      font-size: .7rem;
      margin-top: 40px;
    }
  </style>
</head>
<body>

  <header class="topbar">
    <div class="top-left">
      💼 <span>Job Portal System For Local Workers</span>
      <small>Hi, <?= htmlspecialchars($displayName) ?></small>
    </div>
    <a href="/jobportalsystem/index.php" class="back-btn">← Back to dashboard</a>
  </header>

  <div class="page-wrap">
    <div class="page-header">
      <div class="page-title">Categories &amp; Workers</div>
      <div class="page-sub">Select a category to view workers under it.</div>
      <div class="pill-row">
        <div class="pill">👷 <?= $workers_count ?> workers</div>
        <div class="pill">📂 <?= $cats_count ?> categories</div>
      </div>
    </div>

    <div class="cat-grid">
      <?php while ($cat = $cats->fetch_assoc()): ?>
        <?php
          $cid = (int)$cat['id'];
          $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM worker_profiles WHERE primary_category_id = ?");
          $stmt->bind_param('i', $cid);
          $stmt->execute();
          $row = $stmt->get_result()->fetch_assoc();
          $wcount = (int)$row['c'];
        ?>
        <div class="cat-card">
          <div class="cat-top">
            <div class="cat-name"><?= htmlspecialchars($cat['name']) ?></div>
            <span class="badge"><?= $wcount ?> worker<?= $wcount == 1 ? '' : 's' ?></span>
          </div>
          <p class="cat-desc">View all registered workers under this category.</p>
          <div class="cat-footer">
            <a class="view-btn" href="list.php?category_id=<?= $cid ?>">
              View workers →
            </a>
          </div>
        </div>
      <?php endwhile; ?>
    </div>

    <footer>
      © <?= date('Y') ?> Job Portal System For Local Workers
    </footer>
  </div>

</body>
</html>
