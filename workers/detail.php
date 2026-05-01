<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

$user_id   = $_SESSION['user']['id'];
$user_role = $_SESSION['user']['role'];

$worker_id = isset($_GET['worker_id']) ? (int)$_GET['worker_id'] : 0;
if (!$worker_id) {
    echo "Worker ID missing!";
    exit;
}

/* Fetch worker details */
$stmt = $conn->prepare("
    SELECT
        u.id AS user_id,
        u.name,
        u.email,
        u.phone,
        u.profile_photo AS user_photo,
        w.headline,
        w.bio,
        w.years_experience,
        w.hourly_rate,
        w.profile_photo AS worker_photo,
        c.name AS category
    FROM worker_profiles w
    JOIN users u ON u.id = w.user_id
    LEFT JOIN service_categories c ON c.id = w.primary_category_id
    WHERE u.id = ?
    LIMIT 1
");
$stmt->bind_param('i', $worker_id);
$stmt->execute();
$worker = $stmt->get_result()->fetch_assoc();
if (!$worker) {
    $worker = null;
}

/* Can current user review? (only customers with at least one confirmed booking with this worker) */
$can_review = false;
if ($user_role === 'customer') {
    $chk = $conn->prepare("
        SELECT COUNT(*) AS c
        FROM bookings
        WHERE user_id = ? AND worker_id = ? AND status = 'confirmed'
    ");
    $chk->bind_param('ii', $user_id, $worker_id);
    $chk->execute();
    $can_review = ((int)$chk->get_result()->fetch_assoc()['c']) > 0;
}

/* Handle review submission */
$success_msg = $error_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['__action']) && $_POST['__action'] === 'add_review') {
    if (!$can_review) {
        $error_msg = "You can only review after a confirmed booking.";
    } else {
        $rating  = (int)($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        if ($rating < 1 || $rating > 5) {
            $error_msg = "Rating must be between 1 and 5.";
        } else {
            $ins = $conn->prepare("
                INSERT INTO reviews (reviewer_id, reviewee_id, rating, comment, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $ins->bind_param('iiis', $user_id, $worker_id, $rating, $comment);
            try {
                if ($ins->execute()) {
                    $success_msg = "✅ Review submitted successfully.";
                }
            } catch (mysqli_sql_exception $e) {
                if (strpos($e->getMessage(), 'uq_review_pair') !== false) {
                    $error_msg = "⚠️ You’ve already submitted a review for this worker.";
                } else {
                    $error_msg = "❌ Failed to submit review. Please try again later.";
                }
            }
        }
    }
}

/* Aggregate rating for this worker */
$agg = $conn->prepare("
    SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total_reviews
    FROM reviews
    WHERE reviewee_id = ?
");
$agg->bind_param('i', $worker_id);
$agg->execute();
$aggRow = $agg->get_result()->fetch_assoc();
$avg_rating    = $aggRow && $aggRow['avg_rating'] !== null ? (float)$aggRow['avg_rating'] : 0.0;
$total_reviews = $aggRow ? (int)$aggRow['total_reviews'] : 0;

/* Fetch all reviews for this worker */
$rstmt = $conn->prepare("
    SELECT r.rating, r.comment, r.created_at, u.name AS reviewer_name
    FROM reviews r
    JOIN users u ON u.id = r.reviewer_id
    WHERE r.reviewee_id = ?
    ORDER BY r.created_at DESC
");
$rstmt->bind_param('i', $worker_id);
$rstmt->execute();
$reviews = $rstmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title><?= htmlspecialchars($worker['name'] ?? 'Worker') ?> - Worker Profile</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --page-bg: radial-gradient(circle at top, #e0f2fe 0%, #bae6fd 50%, #93c5fd 100%);
  --card: #fff;
  --text: #0f172a;
  --muted: #64748b;
  --accent: #0ea5e9;
  --accent-dark: #0369a1;
}
body {
  margin: 0;
  font-family: 'Inter', sans-serif;
  background: var(--page-bg);
  min-height: 100vh;
  color: var(--text);
}
/* header */
.topbar {
  position: sticky;
  top: 0;
  background: rgba(255,255,255,0.55);
  backdrop-filter: blur(12px);
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: .6rem 1.3rem;
  box-shadow: 0 8px 25px rgba(15,23,42,0.05);
  z-index: 10;
}
.topbar-left {
  display:flex;
  align-items:center;
  gap:.5rem;
  font-weight:700;
  color: var(--text);
}
.back-btn {
  background:#e2e8f0;
  color:#0f172a;
  border-radius:999px;
  padding:6px 14px;
  font-size:.8rem;
  font-weight:500;
  text-decoration:none;
}
.back-btn:hover { background:#cbd5e1; }
.page-wrap { max-width: 880px; margin: 35px auto 70px; padding: 0 1rem; }
.profile-card {
  background: var(--card);
  border-radius: 20px;
  box-shadow: 0 12px 30px rgba(15,23,42,0.08);
  padding: 25px 25px 28px;
  text-align:center;
}
.profile-photo {
  width:180px; height:180px; border-radius:50%; object-fit:cover;
  border:3px solid #e0f2fe; margin-bottom: 12px;
}
.placeholder {
  width:130px; height:130px; border-radius:50%; background:#e5e7eb;
  display:flex; align-items:center; justify-content:center; color:#64748b; margin: 0 auto 12px;
}
h3 { margin:0; font-size:1.6rem; font-weight:700; }
.headline { color:var(--accent-dark); font-size:1rem; font-weight:600; margin-top:6px; }
.bio { color:var(--muted); font-size:.9rem; margin:10px auto; line-height:1.5; max-width:90%; }
.info { font-size:.9rem; color:#374151; margin-top:6px; }
.info strong { color: var(--accent-dark); }
.meta-rating {
  margin-top: 8px; font-weight: 700; color:#0b4a6f;
}
hr { border:none; border-top:1px solid #e5e7eb; margin:22px 0; }
/* booking */
.booking-box {
  background: var(--card);
  border-radius: 20px;
  box-shadow: 0 12px 30px rgba(15,23,42,0.06);
  padding: 20px 25px 24px; margin-top: 22px; text-align:left;
}
.booking-box h4 { margin-top:0; color: var(--text); font-size:1.08rem; font-weight:700; }
.form-label { font-weight:600; font-size:.85rem; }
textarea {
  width:100%; border:1px solid #d1d5db; border-radius:12px; padding:.55rem .7rem;
  font-size:.9rem; resize:none; min-height:90px; margin-top:5px;
}
.btn-primary {
  background: var(--accent); color: #fff; border:none; border-radius:999px;
  padding:.6rem 1.5rem; font-weight:600; cursor:pointer; transition:background .25s ease;
}
.btn-primary:hover { background: var(--accent-dark); }
.notice { text-align:center; color:#64748b; margin-top:18px; font-size:.85rem; }
/* reviews */
.reviews-card {
  background: var(--card);
  border-radius: 20px;
  box-shadow: 0 12px 30px rgba(15,23,42,0.06);
  padding: 18px 20px;
  margin-top: 22px;
}
.review-item {
  background:#f8fafc; border:1px solid #e5e7eb; border-radius:12px; padding:10px 12px; margin-bottom:10px;
}
.badge {
  display:inline-block; font-size:.75rem; padding:2px 10px; border-radius:999px; background:#e0f2fe; color:#0369a1; font-weight:600;
}
.alert-ok { background:#dcfce7; color:#166534; padding:8px 12px; border-radius:10px; margin:10px 0; }
.alert-err { background:#fee2e2; color:#991b1b; padding:8px 12px; border-radius:10px; margin:10px 0; }
</style>
</head>
<body>

<header class="topbar">
  <div class="topbar-left">💼 Worker Details</div>
  <a href="/jobportalsystem/workers/categories.php" class="back-btn">← Back</a>
</header>

<div class="page-wrap">
<?php if (!$worker): ?>
  <div class="profile-card">
    <h3>Worker not found</h3>
    <p class="bio">This worker profile does not exist.</p>
  </div>
<?php else: ?>
  <div class="profile-card">
    <?php
      $photo = $worker['worker_photo'] ?: $worker['user_photo'] ?: '';
      if ($photo):
    ?>
      <img src="/jobportalsystem/uploads/<?= htmlspecialchars($photo) ?>" alt="Profile Photo" class="profile-photo">
    <?php else: ?>
      <div class="placeholder">No Photo</div>
    <?php endif; ?>

    <h3><?= htmlspecialchars($worker['name']) ?></h3>
    <p class="headline"><?= htmlspecialchars($worker['headline'] ?? 'No headline') ?></p>
    <p class="bio"><?= htmlspecialchars($worker['bio'] ?? 'No bio available.') ?></p>

    <p class="info">
      <strong>Category:</strong>
      <?= htmlspecialchars($worker['category'] ?? 'Uncategorized') ?>
    </p>

    <p class="info">
      <strong>Email:</strong>
      <?= !empty($worker['email']) ? htmlspecialchars($worker['email']) : 'Not set' ?>
    </p>

    <p class="info">
      <strong>Phone:</strong>
      <?= !empty($worker['phone']) ? htmlspecialchars($worker['phone']) : 'Not set' ?>
    </p>

    <p class="info">
      <strong>Experience:</strong>
      <?php
        $yrs = (int)($worker['years_experience'] ?? 0);
        if ($yrs > 0) {
          echo $yrs . ' year' . ($yrs > 1 ? 's' : '');
        } else {
          echo 'Not set';
        }
      ?>
    </p>

    <p class="info">
      <strong>Hourly Rate:</strong>
      <?= $worker['hourly_rate'] !== null
           ? '৳' . number_format((float)$worker['hourly_rate'], 2) . '/hr'
           : 'Not set' ?>
    </p>

    <p class="meta-rating">
      <?php if ($total_reviews > 0): ?>
        ⭐ <?= number_format($avg_rating, 1) ?>/5 (<?= $total_reviews ?> reviews)
      <?php else: ?>
        ⭐ No reviews yet
      <?php endif; ?>
    </p>
  </div>

  <?php if (!empty($success_msg)): ?>
    <div class="alert-ok"><?= htmlspecialchars($success_msg) ?></div>
  <?php elseif (!empty($error_msg)): ?>
    <div class="alert-err"><?= htmlspecialchars($error_msg) ?></div>
  <?php endif; ?>

  <?php if ($user_role === 'customer'): ?>
    <div class="booking-box">
      <h4>Book this Worker</h4>
      <form method="POST" action="/jobportalsystem/bookings/book_worker.php">
        <input type="hidden" name="worker_id" value="<?= htmlspecialchars($worker_id) ?>">
        <label class="form-label">Note (optional)</label>
        <textarea name="note" placeholder="Provide any additional details..."></textarea>
        <br>
        <button type="submit" class="btn-primary">Book Worker</button>
      </form>
    </div>

    <div class="reviews-card">
      <h4 style="margin:0 0 10px 0;">Leave a Review</h4>
      <?php if ($can_review): ?>
        <form method="POST">
          <input type="hidden" name="__action" value="add_review">
          <label class="form-label">Rating (1–5)</label>
          <input type="number" name="rating" min="1" max="5" required
                 style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:10px;">
          <label class="form-label" style="margin-top:10px;">Comment</label>
          <textarea name="comment" rows="3" placeholder="Share your experience..."></textarea>
          <br>
          <button type="submit" class="btn-primary" style="margin-top:8px;">Submit Review</button>
        </form>
      <?php else: ?>
        <div class="notice">⚠️ You can only leave a review after a confirmed booking with this worker.</div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <p class="notice">You are logged in as a worker. Only customers can book and review workers.</p>
  <?php endif; ?>

  <div class="reviews-card">
    <h4 style="margin-top:0;">Customer Reviews</h4>
    <?php if ($reviews->num_rows > 0): ?>
      <?php while ($r = $reviews->fetch_assoc()): ?>
        <div class="review-item">
          <span class="badge">⭐ <?= (int)$r['rating'] ?>/5</span>
          <strong style="margin-left:8px;"><?= htmlspecialchars($r['reviewer_name']) ?></strong>
          <div style="font-size:.85rem;color:#475569;margin-top:6px;">
            <?= nl2br(htmlspecialchars($r['comment'] ?: '')) ?>
          </div>
          <div style="font-size:.75rem;color:#64748b;margin-top:6px;">
            <?= date('F j, Y, g:i a', strtotime($r['created_at'])) ?>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <p class="notice" style="margin:0;">No reviews yet.</p>
    <?php endif; ?>
  </div>
<?php endif; ?>
</div>

</body>
</html>
