<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

$user_id   = $_SESSION['user']['id'];
$user_role = $_SESSION['user']['role'];
$base      = '/jobportalsystem';

// ⚠️ Removed: auto mark-as-read. Unread will persist until user clicks the button.

// Fetch notifications (newest first)
$stmt = $conn->prepare("
    SELECT id, booking_id, type, title, body, is_read, created_at 
    FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$notifications = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Notifications - Job Portal System</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root {
      --page-bg: radial-gradient(circle at top, #e0f2fe 0%, #bae6fd 50%, #93c5fd 100%);
      --dark-bg: radial-gradient(circle at top, #0f172a 0%, #0f172a 50%, #0f172a 100%);
      --accent: #0ea5e9;
      --accent-dark: #0369a1;
      --card-bg: #ffffff;
    }
    body {
      margin: 0;
      font-family: "Inter", system-ui, sans-serif;
      background: var(--page-bg);
      color: #0f172a;
      min-height: 100vh;
      transition: background .3s ease, color .3s ease;
    }

    header {
      position: sticky;
      top: 0;
      background: rgba(255,255,255,0.4);
      backdrop-filter: blur(10px);
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: .7rem 1.2rem;
      box-shadow: 0 2px 10px rgba(15,23,42,0.05);
      z-index: 10;
      gap: .75rem;
    }
    header h1 {
      font-size: 1.1rem;
      font-weight: 700;
      margin: 0;
      color: #0f172a;
    }
    .header-right {
      display: flex;
      align-items: center;
      gap: .5rem;
    }
    .back-btn, .mark-btn {
      border-radius: 999px;
      padding: 6px 12px;
      text-decoration: none;
      font-size: .8rem;
      font-weight: 600;
      transition: background .2s ease, opacity .2s ease;
      border: none;
      cursor: pointer;
    }
    .back-btn {
      background: #e2e8f0; color: #0f172a;
    }
    .back-btn:hover { background: #cbd5e1; }
    .mark-btn {
      background: linear-gradient(90deg, var(--accent), var(--accent-dark));
      color: #fff;
      box-shadow: 0 6px 14px rgba(14,165,233,0.25);
    }
    .mark-btn:disabled { opacity: .6; cursor: not-allowed; }

    .container {
      max-width: 800px;
      margin: 40px auto 80px;
      padding: 0 1rem;
    }

    .notif-card {
      background: var(--card-bg);
      border-radius: 18px;
      box-shadow: 0 10px 25px rgba(15,23,42,0.08);
      padding: 16px 20px 16px 64px; /* left space for serial badge */
      margin-bottom: 14px;
      transition: transform .2s ease, box-shadow .2s ease;
      position: relative;
    }
    .notif-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 14px 32px rgba(15,23,42,0.12);
    }
    .notif-card.unread {
      border-left: 5px solid var(--accent);
      background: #f0f9ff;
    }

    /* serial badge */
    .serial-badge {
      position: absolute;
      left: 16px;
      top: 16px;
      width: 36px;
      height: 36px;
      border-radius: 999px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: .9rem;
      color: #fff;
      background: linear-gradient(135deg, var(--accent), var(--accent-dark));
      box-shadow: 0 6px 14px rgba(14,165,233,0.35);
    }

    .notif-title { font-weight: 700; font-size: 1rem; margin-bottom: 4px; }
    .notif-body { font-size: .9rem; color: #334155; margin-bottom: 8px; }
    .notif-time { display: block; font-size: .75rem; color: #64748b; }

    .notif-link {
      display: inline-block;
      margin-top: 4px;
      font-size: .8rem;
      font-weight: 600;
      color: var(--accent);
      text-decoration: none;
    }
    .notif-link:hover { text-decoration: underline; }

    .no-notif {
      text-align: center;
      color: #64748b;
      margin-top: 50px;
      font-size: .9rem;
    }

    /* dark mode */
    body.dark { background: var(--dark-bg); color: #e2e8f0; }
    body.dark header { background: rgba(15,23,42,0.45); }
    body.dark h1 { color: #e2e8f0; }
    body.dark .back-btn { background: rgba(15,23,42,0.35); color: #e2e8f0; border: 1px solid rgba(148,163,184,0.25); }
    body.dark .mark-btn { box-shadow: none; }
    body.dark .notif-card { background: rgba(15,23,42,0.6); color: #e2e8f0; border: 1px solid rgba(148,163,184,0.2); }
    body.dark .notif-body { color: #cbd5f5; }
    body.dark .notif-time { color: #94a3b8; }
    body.dark .notif-link { color: #38bdf8; }
    body.dark .notif-card.unread { border-left: 5px solid #38bdf8; background: rgba(56,189,248,0.08); }
  </style>
</head>
<body>
  <header>
    <h1>🔔 Notifications</h1>
    <div class="header-right">
<a href="<?= $base ?>/index.php" class="back-btn">← Back</a>
    </div>
  </header>

  <div class="container">
    <?php if ($notifications->num_rows > 0): ?>
      <?php $serial = 1; $has_unread = false; ?>
      <?php while ($n = $notifications->fetch_assoc()): ?>
        <?php if (!$n['is_read']) $has_unread = true; ?>
        <div class="notif-card <?= $n['is_read'] ? '' : 'unread' ?>" data-notif-id="<?= (int)$n['id'] ?>">
          <div class="serial-badge"><?= $serial++ ?></div>
          <div class="notif-title"><?= htmlspecialchars($n['title']) ?></div>
          <div class="notif-body"><?= htmlspecialchars($n['body']) ?></div>
          <small class="notif-time"><?= date('F j, Y, g:i a', strtotime($n['created_at'])) ?></small>

          <?php if (!empty($n['booking_id'])): ?>
            <?php if ($user_role === 'customer'): ?>
              <a href="<?= $base ?>/bookings/my_bookings.php#booking-<?= $n['booking_id'] ?>" class="notif-link">View Booking</a>
            <?php else: ?>
              <a href="<?= $base ?>/bookings/my_bookings.php#booking-<?= $n['booking_id'] ?>" class="notif-link">View Assigned Booking</a>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      <?php endwhile; ?>

      <script>
        // Enable/disable the mark-all button depending on unread presence
        (function(){
          const btn = document.getElementById('markAllBtn');
          const hasUnread = document.querySelector('.notif-card.unread') !== null;
          if (!hasUnread) btn.disabled = true;

          btn.addEventListener('click', async () => {
            btn.disabled = true;
            try {
              // Uses your existing endpoint that marks all as read
              const res = await fetch('<?= $base ?>/notifications/mark_read.php', { method: 'POST' });
              if (!res.ok) throw new Error('Request failed');

              // Update UI: remove unread styling
              document.querySelectorAll('.notif-card.unread').forEach(el => el.classList.remove('unread'));
            } catch (e) {
              console.error(e);
              btn.disabled = false; // allow retry on error
              alert('Failed to mark as read. Please try again.');
            }
          });
        })();

        // Sync dark mode with index (if you store it)
        if (localStorage.getItem('darkMode') === 'on') document.body.classList.add('dark');
      </script>

    <?php else: ?>
      <p class="no-notif">You have no notifications yet.</p>
      <script>
        // Sync dark mode with index
        if (localStorage.getItem('darkMode') === 'on') document.body.classList.add('dark');
        // No notifications: disable the button
        document.getElementById('markAllBtn').disabled = true;
      </script>
    <?php endif; ?>
  </div>
</body>
</html>
