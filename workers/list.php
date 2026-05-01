<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// Get category name
$cat_name = 'All Workers';
if ($category_id > 0) {
    $stmt = $conn->prepare("SELECT name FROM service_categories WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $category_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) $cat_name = $row['name'];
    $stmt->close();
}

/**
 * Fetch workers with:
 * - fallback photo (worker_profiles.profile_photo OR users.profile_photo)
 * - latest address
 * - aggregated reviews: avg_rating, total_reviews (reviews.reviewee_id = worker user id)
 */
if ($category_id > 0) {
    $wstmt = $conn->prepare("
        SELECT
            u.id AS user_id,
            u.name,
            wp.headline,
            COALESCE(wp.profile_photo, u.profile_photo) AS photo,
            a.area, a.city, a.district,
            COALESCE(rv.avg_rating, 0)      AS avg_rating,
            COALESCE(rv.total_reviews, 0)   AS total_reviews
        FROM worker_profiles wp
        JOIN users u ON u.id = wp.user_id
        LEFT JOIN (
            SELECT r.reviewee_id,
                   AVG(r.rating)  AS avg_rating,
                   COUNT(*)       AS total_reviews
            FROM reviews r
            GROUP BY r.reviewee_id
        ) rv ON rv.reviewee_id = u.id
        LEFT JOIN addresses a
          ON a.user_id = u.id
         AND a.id = (SELECT MAX(id) FROM addresses WHERE user_id = u.id)
        WHERE wp.primary_category_id = ?
        ORDER BY u.name ASC
    ");
    $wstmt->bind_param('i', $category_id);
} else {
    $wstmt = $conn->prepare("
        SELECT
            u.id AS user_id,
            u.name,
            wp.headline,
            COALESCE(wp.profile_photo, u.profile_photo) AS photo,
            a.area, a.city, a.district,
            COALESCE(rv.avg_rating, 0)      AS avg_rating,
            COALESCE(rv.total_reviews, 0)   AS total_reviews
        FROM worker_profiles wp
        JOIN users u ON u.id = wp.user_id
        LEFT JOIN (
            SELECT r.reviewee_id,
                   AVG(r.rating)  AS avg_rating,
                   COUNT(*)       AS total_reviews
            FROM reviews r
            GROUP BY r.reviewee_id
        ) rv ON rv.reviewee_id = u.id
        LEFT JOIN addresses a
          ON a.user_id = u.id
         AND a.id = (SELECT MAX(id) FROM addresses WHERE user_id = u.id)
        ORDER BY u.name ASC
    ");
}
$wstmt->execute();
$workers = $wstmt->get_result();

// Fetch available areas for filter dropdown
if ($category_id > 0) {
    $areas_stmt = $conn->prepare("
        SELECT DISTINCT a.area
        FROM worker_profiles wp
        JOIN users u ON u.id = wp.user_id
        LEFT JOIN addresses a ON a.user_id = u.id
        WHERE wp.primary_category_id = ?
          AND a.area IS NOT NULL AND a.area <> ''
        ORDER BY a.area ASC
    ");
    $areas_stmt->bind_param('i', $category_id);
    $areas_stmt->execute();
    $areas_res = $areas_stmt->get_result();
} else {
    $areas_res = $conn->query("
        SELECT DISTINCT area
        FROM addresses
        WHERE area IS NOT NULL AND area <> ''
        ORDER BY area ASC
    ");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($cat_name) ?> Workers | Job Portal System</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root {
      --page-bg: radial-gradient(circle at top, #e0f2fe 0%, #bae6fd 50%, #93c5fd 100%);
      --accent: #0ea5e9;
      --accent-dark: #0369a1;
      --text: #0f172a;
      --muted: #64748b;
      --card-bg: rgba(255,255,255,0.95);
    }
    * { box-sizing: border-box; }
    body { margin: 0; background: var(--page-bg); font-family: "Inter", system-ui, sans-serif; min-height: 100vh; }

    .topbar { position: sticky; top: 0; background: rgba(255,255,255,0.55); backdrop-filter: blur(12px);
              display: flex; justify-content: space-between; align-items: center; padding: .6rem 1.3rem;
              box-shadow: 0 8px 25px rgba(15,23,42,0.03); z-index: 20; }
    .top-left { display: flex; align-items: center; gap: .45rem; font-weight: 600; color: var(--text); }
    .back-link { background: var(--accent); color: #fff; text-decoration: none; border-radius: 999px; padding: 5px 12px; font-size: .75rem; font-weight: 600; }
    .back-link:hover { background: var(--accent-dark); }

    .page-wrap { max-width: 1080px; margin: 30px auto 50px; padding: 0 1.2rem; }
    .subtitle { margin-top: .3rem; color: var(--muted); font-size: .8rem; }

    .filter-bar { display: flex; gap: .8rem; margin: 1.4rem 0 1.2rem; flex-wrap: wrap; align-items: center; }
    .search-input { background: #fff; border: 1px solid rgba(148,163,184,0.25); border-radius: 999px; padding: 7px 14px 8px; flex: 1 1 250px; outline: none; font-size: .8rem; }
    .search-input::placeholder { color: #94a3b8; }
    .filter-select { background: #fff; border: 1px solid rgba(148,163,184,0.4); border-radius: 999px; padding: 6px 14px; font-size: .78rem; outline: none; min-width: 150px; }

    .workers-list { display: flex; flex-direction: column; gap: .65rem; }
    .worker-row { background: var(--card-bg); border: 1px solid rgba(148,163,184,0.15); border-radius: 16px; padding: 12px 14px;
                  display: flex; align-items: center; gap: .85rem; transition: transform .12s ease, box-shadow .12s ease;
                  box-shadow: 0 10px 25px rgba(15,23,42,0.02); }
    .worker-row:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(15,23,42,0.05); }
    .worker-photo { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; background: #e2e8f0; flex: 0 0 48px; }
    .worker-meta { flex: 1; min-width: 0; }
    .worker-name { margin: 0; font-size: .92rem; color: var(--text); font-weight: 600; }
    .worker-headline { margin: 2px 0 0; font-size: .7rem; color: #6b7280; }
    .loc-tag { display: inline-block; background: rgba(14,165,233,.12); color: #0369a1; border-radius: 999px; padding: 2px 10px; font-size: .65rem; margin-top: 5px; }
    .rating { font-size: .75rem; color: #0ea5e9; margin-top: 4px; }
    .view-link { color: var(--accent); text-decoration: none; font-size: .72rem; font-weight: 600; }
    .empty { margin-top: 1.5rem; color: #6b7280; font-size: .8rem; text-align: center; }
  </style>
</head>
<body>
  <header class="topbar">
    <div class="top-left">👷 <?= htmlspecialchars($cat_name) ?> Workers</div>
    <a href="/jobportalsystem/workers/categories.php" class="back-link">← Back</a>
  </header>

  <div class="page-wrap">
    <p class="subtitle">Search and filter workers under this category.</p>

    <div class="filter-bar">
      <input type="text" id="searchBox" class="search-input" placeholder="Search by name or headline...">
      <select id="areaFilter" class="filter-select">
        <option value="">All areas</option>
        <?php while ($ar = $areas_res->fetch_assoc()): ?>
          <option value="<?= htmlspecialchars(strtolower($ar['area'])) ?>"><?= htmlspecialchars($ar['area']) ?></option>
        <?php endwhile; ?>
      </select>
    </div>

    <div id="workersList" class="workers-list">
      <?php if ($workers->num_rows > 0): ?>
        <?php while ($wk = $workers->fetch_assoc()): ?>
          <?php
            $photo         = $wk['photo'] ?? '';
            $area          = $wk['area'] ?? '';
            $city          = $wk['city'] ?? '';
            $district      = $wk['district'] ?? '';
            $avg_rating    = $wk['avg_rating'] ? round((float)$wk['avg_rating'], 1) : 0;
            $total_reviews = (int)$wk['total_reviews'];
          ?>
          <div class="worker-row"
               data-name="<?= htmlspecialchars(strtolower($wk['name'])) ?>"
               data-headline="<?= htmlspecialchars(strtolower($wk['headline'] ?? '')) ?>"
               data-area="<?= htmlspecialchars(strtolower($area)) ?>">
            <?php if (!empty($photo)): ?>
              <img class="worker-photo" src="/jobportalsystem/uploads/<?= htmlspecialchars($photo) ?>" alt="Worker Photo">
            <?php else: ?>
              <div class="worker-photo" style="display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:.7rem;">?</div>
            <?php endif; ?>

            <div class="worker-meta">
              <p class="worker-name"><?= htmlspecialchars($wk['name']) ?></p>
              <p class="worker-headline"><?= htmlspecialchars($wk['headline'] ?? '') ?></p>
              <?php if ($area || $city || $district): ?>
                <span class="loc-tag"><?= htmlspecialchars($area ?: $city ?: $district) ?></span>
              <?php endif; ?>
              <?php if ($total_reviews > 0): ?>
                <p class="rating">⭐ <?= $avg_rating ?>/5 (<?= $total_reviews ?> reviews)</p>
              <?php endif; ?>
            </div>

            <a class="view-link" href="/jobportalsystem/workers/detail.php?worker_id=<?= $wk['user_id'] ?>">View</a>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <p class="empty">No workers found in this category.</p>
      <?php endif; ?>
    </div>
  </div>

  <script>
    const searchBox = document.getElementById('searchBox');
    const areaFilter = document.getElementById('areaFilter');
    const rows = document.querySelectorAll('.worker-row');

    function filterWorkers() {
      const q = (searchBox.value || '').toLowerCase();
      const area = (areaFilter.value || '').toLowerCase();

      rows.forEach(row => {
        const name = row.dataset.name || '';
        const headline = row.dataset.headline || '';
        const wArea = row.dataset.area || '';
        const matchText = !q || name.includes(q) || headline.includes(q);
        const matchArea = !area || wArea === area;
        row.style.display = (matchText && matchArea) ? 'flex' : 'none';
      });
    }

    searchBox.addEventListener('input', filterWorkers);
    areaFilter.addEventListener('change', filterWorkers);
  </script>
</body>
</html>
