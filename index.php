
<?php
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/lib/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

$displayName = $_SESSION['user_name'] ?? $_SESSION['name'] ?? $_SESSION['email'] ?? 'there';
$userPhoto   = $_SESSION['user']['profile_photo'] ?? '';
$base        = '/jobportalsystem';

// counts
$workers_count = (int)$conn->query("SELECT COUNT(*) AS c FROM worker_profiles")->fetch_assoc()['c'];
$cats_count    = (int)$conn->query("SELECT COUNT(*) AS c FROM service_categories")->fetch_assoc()['c'];

// greeting
$hour  = (int)date('H');
$greet = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Job Portal System For Local Workers</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --page-bg: radial-gradient(circle at top, #e0f2fe 0%, #bae6fd 50%, #93c5fd 100%);
      --accent: #0ea5e9;
      --accent-dark: #0369a1;
    }
    body {
      margin: 0;
      font-family: "Inter", system-ui, sans-serif;
      background: var(--page-bg);
      min-height: 100vh;
    }

    /* topbar */
    .topbar {
      position: fixed;
      top: 0; left: 0; right: 0;
      backdrop-filter: blur(12px);
      background: rgba(255, 255, 255, 0.45);
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: .5rem 1.2rem;
      z-index: 1000;
      box-shadow: 0 2px 10px rgba(15,23,42,0.05);
    }
    .topbar-left {
      display: flex;
      align-items: center;
      gap: .45rem;
      font-weight: 700;
      color: #0f172a;
    }
    .topbar-left small {
      font-weight: 400;
      font-size: .7rem;
      color: #64748b;
    }
    .topbar-right {
      display: flex;
      align-items: center;
      gap: .7rem;
      position: relative;
    }

    /* avatar, buttons */
    .nav-avatar {
      width: 30px; height: 30px;
      border-radius: 999px;
      object-fit: cover;
      background: #e2e8f0;
    }
    .logout-btn {
      background:#f97316;
      color:#fff;
      padding:4px 12px;
      border-radius:999px;
      font-size:.75rem;
      text-decoration:none;
      font-weight:600;
      transition:background .2s;
    }
    .logout-btn:hover { background:#fb923c; }

    /* 🔔 Notification styles */
    .notif-wrapper { position: relative; }
    .notif-btn {
  background: transparent;
  border: none;
  font-size: 1.2rem;
  cursor: pointer;
  position: relative;
}

/* red circle with number */
.notif-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  min-width: 16px;
  height: 16px;
  padding: 0 4px;
  border-radius: 999px;
  background: #ef4444;
  color: #fff;
  font-size: 9px;
  font-weight: 700;
  display: none;              /* hidden by default */
  align-items: center;
  justify-content: center;
  box-shadow: 0 0 0 2px #fff; /* white ring around badge */
}

    .notif-panel {
      position: absolute;
      top: 36px;
      right: 0;
      width: 260px;
      background: rgba(255,255,255,0.95);
      border-radius: 14px;
      box-shadow: 0 8px 25px rgba(15,23,42,0.15);
      display: none;
      overflow: hidden;
      backdrop-filter: blur(10px);
      z-index: 999;
    }
    .notif-panel h4 {
      margin: 0;
      font-size: 0.85rem;
      padding: 10px 14px;
      border-bottom: 1px solid rgba(148,163,184,0.2);
      color: #0f172a;
    }
    .notif-panel ul {
      list-style: none;
      margin: 0;
      padding: 0;
      max-height: 200px;
      overflow-y: auto;
    }
    .notif-panel li {
      padding: 8px 14px;
      border-bottom: 1px solid rgba(148,163,184,0.1);
    }
    .notif-panel li p {
      margin: 0;
      font-size: .85rem;
      color: #0f172a;
    }
    .notif-panel li small {
      font-size: .7rem;
      color: #64748b;
    }
    .notif-panel a {
      display: block;
      text-align: center;
      padding: 8px;
      text-decoration: none;
      font-size: .75rem;
      font-weight: 600;
      color: var(--accent);
    }

    /* hero */
    .hero {
      max-width: 980px;
      margin: 85px auto 0;
      padding: 0 1rem;
      text-align: center;
    }
    .hero h2 { font-size: 2.15rem; font-weight: 700; color: #0f172a; margin-bottom: .4rem; }
    .hero p { color: #5f6b7a; margin-bottom: 1.3rem; font-size: 1rem; }

    /* stats */
    .stats-bar {
      display: flex;
      gap: 14px;
      justify-content: center;
      margin-bottom: 1.6rem;
      flex-wrap: wrap;
    }
    .stat-pill {
      background: rgba(255,255,255,0.75);
      border: 1px solid rgba(255,255,255,0.45);
      border-radius: 999px;
      padding: 6px 16px 7px;
      font-size: .8rem;
      display: flex;
      gap: 6px;
      align-items: center;
      box-shadow: 0 8px 25px rgba(15,23,42,0.07);
    }

    /* tiles */
    .tiles { display: flex; justify-content: center; gap: 1.8rem; flex-wrap: wrap; margin-top: 1rem; margin-bottom: 2rem; }
    .tile-link { text-decoration: none; }
    .tile-card {
      width: 255px; height: 245px;
      background: #fff;
      border-radius: 58px;
      box-shadow: 0 18px 35px rgba(15,23,42,0.09);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-end;
      padding-bottom: 16px;
      transition: transform .25s ease, box-shadow .25s ease;
    }
    .tile-card:hover { transform: translateY(-6px); box-shadow: 0 20px 50px rgba(15,23,42,0.14); }
    .tile-circle {
      width: 220px; height: 190px;
      background: linear-gradient(160deg, #0ea5e9 0%, #0369a1 100%);
      border-radius: 60px;
      margin-top: -36px;
      display: grid;
      place-items: center;
    }
    .tile-icon-svg { width: 38px; height: 38px; stroke: #fff; }
    .tile-label { font-size: .9rem; font-weight: 600; color: #0f172a; margin-top: 12px; }

    footer { text-align: center; color: #64748b; font-size: .8rem; margin: 10px 0 20px; }
  </style>
</head>
<body>
  <header class="topbar">
    <div class="topbar-left">
      💼 <span>Job Portal System For Local Workers</span>
      <small><?= htmlspecialchars($greet) ?>, <?= htmlspecialchars($displayName) ?></small>
    </div>
    <div class="topbar-right">
      <!-- 🔔 Notification bell -->
      <div class="notif-wrapper">
  <button id="notifBtn" class="notif-btn" title="Notifications">
    🔔
    <span id="notifBadge" class="notif-badge"></span>
  </button>
  <div id="notifPanel" class="notif-panel">
    <h4>Notifications</h4>
    <ul id="notifList"><li><p>Loading...</p></li></ul>
    <a href="<?= $base ?>/notifications/notifications.php">View all</a>
  </div>
</div>


      <?php if (!empty($userPhoto)): ?>
        <img src="<?= $base ?>/uploads/<?= htmlspecialchars($userPhoto) ?>" class="nav-avatar" alt="avatar">
      <?php endif; ?>

      <a class="logout-btn" href="<?= $base ?>/auth/logout.php">Logout</a>
    </div>
  </header>

  <section class="hero">
    <h2>Find, manage and book local workers faster.</h2>
    <p>Browse categories, view worker details, and confirm bookings in one place.</p>

    <div class="stats-bar">
      <div class="stat-pill">👷 <strong><?= $workers_count ?></strong> workers</div>
      <div class="stat-pill">📂 <strong><?= $cats_count ?></strong> categories</div>
      <div class="stat-pill">⚡ Fast booking</div>
    </div>

    <div class="tiles">
      <a class="tile-link" href="<?= $base ?>/user/profile.php">
        <div class="tile-card"><div class="tile-circle">
          <svg class="tile-icon-svg" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="9" r="3.2" stroke-width="1.4"/>
            <path d="M6 19c1.4-2.7 3.5-4 6-4s4.6 1.3 6 4" stroke-width="1.4" stroke-linecap="round"/>
          </svg></div><div class="tile-label">Profile</div></div>
      </a>

      <a class="tile-link" href="<?= $base ?>/workers/categories.php">
        <div class="tile-card"><div class="tile-circle">
          <svg class="tile-icon-svg" viewBox="0 0 24 24" fill="none">
            <path d="M6 9h12M6 12.5h12M6 16h12" stroke-width="1.6" stroke-linecap="round"/>
          </svg></div><div class="tile-label">Categories</div></div>
      </a>

      <a class="tile-link" href="<?= $base ?>/settings.php">
        <div class="tile-card"><div class="tile-circle">
          <svg class="tile-icon-svg" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="2.5" stroke-width="1.2"/>
            <path d="M12 4.5v1.7M12 18v1.7M5.5 12H3.9M20.1 12h-1.6M7.2 7.2 6.2 6.2M17.8 17.8l-1-1M7.2 16.7l-1 1M17.8 6.2l-1 1" stroke-width="1.2" stroke-linecap="round"/>
          </svg></div><div class="tile-label">Settings</div></div>
      </a>
    </div>
  </section>

  <footer>© <?= date('Y') ?> Job Portal System For Local Workers — Empowering Local Workforce</footer>

<script>
  // 🔔 Notifications
  const notifBtn   = document.getElementById('notifBtn');
  const notifPanel = document.getElementById('notifPanel');
  const notifList  = document.getElementById('notifList');
  const notifBadge = document.getElementById('notifBadge');

  function setBadge(count) {
    if (!notifBadge) return;
    if (count > 0) {
      notifBadge.textContent = count > 9 ? '9+' : count;
      notifBadge.style.display = 'flex';
    } else {
      notifBadge.style.display = 'none';
    }
  }

  notifBtn.addEventListener('click', e => {
    e.stopPropagation();
    const open = notifPanel.style.display === 'block';
    notifPanel.style.display = open ? 'none' : 'block';
    if (!open) loadNotifications(true); // open -> load + mark read
  });

  document.addEventListener('click', e => {
    if (!notifBtn.contains(e.target) && !notifPanel.contains(e.target)) {
      notifPanel.style.display = 'none';
    }
  });

  function loadNotifications(markRead = false) {
    fetch('<?= $base ?>/notifications/fetch_latest.php')
      .then(res => res.json())
      .then(data => {
        notifList.innerHTML = '';

        // update badge from unread_count
        setBadge(data.unread_count || 0);

        if (!data.notifications.length) {
          notifList.innerHTML = '<li><p>No notifications</p></li>';
          return;
        }

        data.notifications.forEach(n => {
          const li = document.createElement('li');
          li.innerHTML = `
            <p><strong>${n.title}</strong></p>
            <p style="font-size:.8rem;color:#475569;">${n.body}</p>
            <small>${n.time}</small>
          `;
          notifList.appendChild(li);
        });

        if (markRead && data.unread_count > 0) {
          markNotificationsRead();
        }
      })
      .catch(err => {
        console.error('Notification fetch error:', err);
        notifList.innerHTML = '<li><p>Failed to load notifications.</p></li>';
      });
  }

  function markNotificationsRead() {
    fetch('<?= $base ?>/notifications/mark_read.php', { method: 'POST' })
      .then(() => setBadge(0))
      .catch(err => console.error(err));
  }

  // initial badge load
  loadNotifications(false);

  // refresh every 30s (badge only – no auto mark as read)
  setInterval(() => loadNotifications(false), 30000);
</script>

</body>
</html>
