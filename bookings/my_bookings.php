<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

$user_id   = $_SESSION['user']['id'];
$user_role = $_SESSION['user']['role']; // 'customer' | 'worker'

// Role-based SQL
if ($user_role === 'customer') {
    // Customer: show bookings they made
    $sql = "
        SELECT b.id,
               w.name AS other_name,
               b.scheduled_at,
               b.created_at,
               b.status
        FROM bookings b
        JOIN users w ON b.worker_id = w.id
        WHERE b.user_id = ?
        ORDER BY b.created_at DESC
    ";
} elseif ($user_role === 'worker') {
    // Worker: show bookings assigned to them
    $sql = "
        SELECT b.id,
               u.name AS other_name,
               b.scheduled_at,
               b.created_at,
               b.status
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        WHERE b.worker_id = ?
        ORDER BY b.created_at DESC
    ";
} else {
    echo "Invalid user role!";
    exit;
}

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$bookings = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>My Bookings | Job Portal System</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root {
      --bg: radial-gradient(circle at top, #e0f2fe 0%, #bae6fd 50%, #93c5fd 100%);
      --card: #fff;
      --accent: #0ea5e9;
      --accent-dark: #0369a1;
      --danger: #ef4444;
      --success: #22c55e;
      --muted: #64748b;
    }

    body {
      margin: 0;
      font-family: "Inter", system-ui, -apple-system, sans-serif;
      background: var(--bg);
      min-height: 100vh;
    }

    .topbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: rgba(255,255,255,0.55);
      backdrop-filter: blur(10px);
      padding: 0.7rem 1.2rem;
      box-shadow: 0 2px 10px rgba(15,23,42,0.05);
    }

    .back-link {
      text-decoration: none;
      background: var(--accent);
      color: white;
      padding: 6px 14px;
      border-radius: 999px;
      font-weight: 600;
      font-size: 0.8rem;
    }

    .back-link:hover {
      background: var(--accent-dark);
    }

    h2 {
      margin: 20px auto 10px;
      max-width: 1000px;
      padding: 0 1rem;
      font-size: 1.5rem;
      color: #0f172a;
    }

    .table-wrap {
      max-width: 1000px;
      margin: 0 auto 40px;
      padding: 0 1rem 1rem;
      background: rgba(255,255,255,0.85);
      border-radius: 16px;
      box-shadow: 0 14px 35px rgba(15,23,42,0.08);
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.87rem;
    }

    thead {
      background: rgba(148,163,184,0.15);
    }

    th, td {
      padding: 10px 8px;
      text-align: left;
      border-bottom: 1px solid rgba(148,163,184,0.18);
    }

    th:first-child, td:first-child {
      width: 50px;
      text-align: center;
    }

    tr:last-child td {
      border-bottom: none;
    }

    .status-pill {
      display: inline-block;
      padding: 3px 10px;
      border-radius: 999px;
      font-size: 0.7rem;
      font-weight: 600;
    }

    .status-pending   { background: rgba(234,179,8,.14);  color: #92400e; }
    .status-confirmed { background: rgba(34,197,94,.18);  color: #166534; }
    .status-done      { background: rgba(59,130,246,.18); color: #1d4ed8; }
    .status-completed { background: rgba(22,163,74,.18);  color: #166534; }
    .status-cancelled { background: rgba(239,68,68,.15);  color: #b91c1c; }

    .btn-sm {
      display: inline-block;
      font-size: 0.72rem;
      padding: 4px 10px;
      border-radius: 8px;
      text-decoration: none;
      margin-right: 4px;
      font-weight: 500;
    }

    .btn-accept { background: #22c55e; color: #fff; }
    .btn-cancel { background: #ef4444; color: #fff; }
    .btn-accept:hover { background: #16a34a; }
    .btn-cancel:hover { background: #dc2626; }

    .btn-primary {
      display: inline-block;
      font-size: 0.8rem;
      padding: 6px 12px;
      border-radius: 999px;
      border: none;
      background: var(--accent);
      color: #fff;
      cursor: pointer;
      font-weight: 600;
    }

    .btn-primary:hover {
      background: var(--accent-dark);
    }

    .empty {
      padding: 18px 0;
      text-align: center;
      color: var(--muted);
    }

    .notice {
      max-width: 1000px;
      margin: 15px auto;
      padding: 8px 12px;
      border-radius: 8px;
      font-size: 0.85rem;
    }

    .notice.success { background: #dcfce7; color: #166534; }
    .notice.info    { background: #dbeafe; color: #1e3a8a; }
    .notice.error   { background: #fee2e2; color: #991b1b; }

    footer {
      text-align: center;
      font-size: 0.8rem;
      color: #64748b;
      margin-bottom: 20px;
    }
  </style>
</head>
<body>

  <div class="topbar">
    <a href="/jobportalsystem/index.php" class="back-link">← Back to Dashboard</a>
    <span style="font-size:.8rem;color:#334155;">Logged in as <strong><?=htmlspecialchars($user_role)?></strong></span>
  </div>

  <h2>My Bookings</h2>

  <?php if (!empty($_GET['success'])): ?>
    <div class="notice success">✅ Your booking request was submitted successfully.</div>
  <?php elseif (!empty($_GET['confirmed'])): ?>
    <div class="notice info">✅ Booking confirmed successfully.</div>
  <?php elseif (!empty($_GET['cancelled'])): ?>
    <div class="notice error">❌ Booking cancelled successfully.</div>
  <?php elseif (!empty($_GET['done'])): ?>
    <div class="notice info">✅ Job marked as completed by worker.</div>
  <?php elseif (!empty($_GET['completed'])): ?>
    <div class="notice success">✅ Job completion confirmed by customer.</div>
  <?php endif; ?>

  <div class="table-wrap">
    <?php if ($bookings->num_rows > 0): ?>
      <table>
        <thead>
          <tr>
            <th>SL</th>
            <th><?= $user_role === 'customer' ? 'Worker' : 'Customer' ?></th>
            <th>Date / Time</th>
            <th>Status</th>
            <?php if ($user_role === 'worker' || $user_role === 'customer'): ?>
              <th>Actions</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php $sl = 1; while ($b = $bookings->fetch_assoc()): ?>
            <?php
              // Choose display time
              $when = '';
              if (!empty($b['scheduled_at'])) {
                  $when = date('F j, Y, g:i a', strtotime($b['scheduled_at']));
              } elseif (!empty($b['created_at'])) {
                  $when = 'Requested: ' . date('F j, Y, g:i a', strtotime($b['created_at']));
              } else {
                  $when = 'Not scheduled';
              }

              $status = strtolower($b['status']);
              $status_class = 'status-pill ';
              if ($status === 'pending')      $status_class .= 'status-pending';
              elseif ($status === 'confirmed') $status_class .= 'status-confirmed';
              elseif ($status === 'done')      $status_class .= 'status-done';
              elseif ($status === 'completed') $status_class .= 'status-completed';
              else                             $status_class .= 'status-cancelled';
            ?>
            <tr id="booking-<?= $b['id'] ?>">
              <td><?= $sl++ ?></td>
              <td><?= htmlspecialchars($b['other_name'] ?? 'Unknown') ?></td>
              <td><?= $when ?></td>
              <td><span class="<?= $status_class ?>"><?= ucfirst($status) ?></span></td>

              <?php if ($user_role === 'worker'): ?>
                <td>
                  <?php if ($status === 'pending'): ?>
                    <a class="btn-sm btn-accept" href="/jobportalsystem/bookings/confirm.php?booking_id=<?= $b['id'] ?>">Accept</a>
                    <a class="btn-sm btn-cancel" href="/jobportalsystem/bookings/cancel.php?booking_id=<?= $b['id'] ?>">Cancel</a>
                  <?php elseif ($status === 'confirmed'): ?>
                    <form method="POST" action="/jobportalsystem/bookings/mark_done.php" style="display:inline;">
                      <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                      <button type="submit" class="btn-primary">Mark as Completed</button>
                    </form>
                  <?php else: ?>
                    <span style="font-size:.7rem;color:#94a3b8;">No actions</span>
                  <?php endif; ?>
                </td>

              <?php elseif ($user_role === 'customer'): ?>
                <td>
                  <?php if ($status === 'done'): ?>
                    <form method="POST" action="/jobportalsystem/bookings/confirm_completion.php" style="display:inline;">
                      <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                      <button type="submit" class="btn-primary">Confirm Job Completion</button>
                    </form>
                  <?php else: ?>
                    <span style="font-size:.7rem;color:#94a3b8;">No actions</span>
                  <?php endif; ?>
                </td>
              <?php endif; ?>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="empty">You have no bookings yet.</p>
    <?php endif; ?>
  </div>

  <footer>© <?= date('Y') ?> Job Portal System For Local Workers — Empowering Local Workforce</footer>
</body>
</html>
