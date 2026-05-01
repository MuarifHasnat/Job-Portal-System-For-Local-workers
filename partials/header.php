<?php
require_once __DIR__.'/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$base = '/jobportalsystem';

// Fetch unread notifications count (if logged in)
$unread_count = 0;
if (!empty($_SESSION['user'])) {
  $stmt = $conn->prepare("SELECT COUNT(*) AS unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
  $stmt->bind_param('i', $_SESSION['user']['id']);
  $stmt->execute();
  $result = $stmt->get_result();
  $unread_count = $result->fetch_assoc()['unread_count'];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Job Portal System For Local Workers</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <style>
    :root {
      --page-bg: radial-gradient(circle at top, #e0f2fe 0%, #bae6fd 50%, #93c5fd 100%);
      --dark-bg: radial-gradient(circle at top, #0f172a 0%, #0f172a 50%, #0f172a 100%);
    }
    body {
      background: var(--page-bg);
      transition: background .3s ease, color .3s ease;
    }
    body.dark {
      background: var(--dark-bg);
      color: #e2e8f0;
    }
    .notification-bell {
      position: relative;
      display: inline-block;
      margin-left: 10px;
    }
    .notification-bell .badge {
      font-size: 0.7rem;
      padding: 0.25em 0.5em;
      border-radius: 50%;
    }
    .dark-toggle {
      border: none;
      background: transparent;
      color: white;
      font-size: 1.2rem;
      margin-right: 10px;
      cursor: pointer;
    }
    body.dark .navbar {
      background-color: #0f172a !important;
    }
  </style>
  <script>
    // Apply saved dark mode early (before paint)
    (function() {
      if (localStorage.getItem('darkMode') === 'on') {
        document.documentElement.classList.add('dark-preload');
        document.addEventListener('DOMContentLoaded', () => {
          document.body.classList.add('dark');
        });
      }
    })();
  </script>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container">
    <a class="navbar-brand" href="<?= $base ?>/index.php">Job Portal System For Local Workers</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto"></ul>
      <ul class="navbar-nav">
        <?php if (!empty($_SESSION['user'])): ?>
          <li class="nav-item d-flex align-items-center">
            <span class="navbar-text me-3">Hi, <?= htmlspecialchars($_SESSION['user']['name']) ?></span>

            <!-- 🔔 Notifications -->
            <a href="<?= $base ?>/notifications/notifications.php" class="notification-bell text-white me-3" title="Notifications">
              <i class="bi bi-bell" style="font-size: 1.3rem;"></i>
              <?php if ($unread_count > 0): ?>
                <span class="badge bg-danger position-absolute top-0 start-100 translate-middle">
                  <?= $unread_count ?>
                </span>
              <?php endif; ?>
            </a>

            <!-- 🌙 Dark mode toggle -->
            <button id="darkToggle" class="dark-toggle" title="Toggle dark mode">🌙</button>

            <!-- 🚪 Logout -->
            <a class="btn btn-outline-light btn-sm" href="<?= $base ?>/auth/logout.php">Logout</a>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="btn btn-primary btn-sm" href="<?= $base ?>/auth/landing.php">Login / Sign up</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<div class="container py-4">

<script>
  // Global Dark Mode Sync
  document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('darkToggle');
    if (!btn) return;
    if (localStorage.getItem('darkMode') === 'on') {
      document.body.classList.add('dark');
    }
    btn.addEventListener('click', function() {
      document.body.classList.toggle('dark');
      localStorage.setItem('darkMode',
        document.body.classList.contains('dark') ? 'on' : 'off'
      );
    });
  });
</script>
