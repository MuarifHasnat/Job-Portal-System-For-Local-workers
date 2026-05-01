<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

$user_id   = $_SESSION['user']['id'];
$user_role = $_SESSION['user']['role'];
$base      = '/jobportalsystem';

// Only customers can book
if ($user_role !== 'customer') {
    exit("❌ Only customers can create bookings.");
}

$worker_id = isset($_GET['worker_id']) ? (int)$_GET['worker_id'] : 0;
if ($worker_id <= 0) {
    exit("❌ Worker ID missing or invalid.");
}

// Fetch worker details
$stmt = $conn->prepare("
    SELECT u.id, u.name, w.headline, w.hourly_rate 
    FROM users u
    JOIN worker_profiles w ON u.id = w.user_id
    WHERE u.id = ? LIMIT 1
");
$stmt->bind_param('i', $worker_id);
$stmt->execute();
$worker = $stmt->get_result()->fetch_assoc();

if (!$worker) {
    exit("❌ Worker not found.");
}

// Handle booking form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $note = trim($_POST['note'] ?? '');

    $conn->begin_transaction();
    try {
        // Insert booking
        $stmt = $conn->prepare("INSERT INTO bookings (user_id, worker_id, status, note, created_at)
                                VALUES (?, ?, 'pending', ?, NOW())");
        $stmt->bind_param('iis', $user_id, $worker_id, $note);
        $stmt->execute();
        $booking_id = $conn->insert_id;
        $stmt->close();

        // Notify worker
        $msg_worker = "You have a new booking request from " . htmlspecialchars($_SESSION['user']['name']);
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, booking_id, type, title, body, is_read, created_at)
                                VALUES (?, ?, 'booking', 'New Booking Request', ?, 0, NOW())");
        $stmt->bind_param('iis', $worker_id, $booking_id, $msg_worker);
        $stmt->execute();

        // Notify customer
        $msg_customer = "Your booking request for " . htmlspecialchars($worker['name']) . " has been submitted successfully.";
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, booking_id, type, title, body, is_read, created_at)
                                VALUES (?, ?, 'booking', 'Booking Request Submitted', ?, 0, NOW())");
        $stmt->bind_param('iis', $user_id, $booking_id, $msg_customer);
        $stmt->execute();

        $conn->commit();
        header("Location: $base/bookings/my_bookings.php?success=1");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Booking creation error: " . $e->getMessage());
        exit("❌ Error creating booking.");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Book <?= htmlspecialchars($worker['name']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root {
      --page-bg: radial-gradient(circle at top, #e0f2fe 0%, #bae6fd 50%, #93c5fd 100%);
      --dark-bg: radial-gradient(circle at top, #0f172a 0%, #0f172a 50%, #0f172a 100%);
      --card-bg: #ffffff;
      --accent: #0ea5e9;
      --accent-dark: #0369a1;
    }
    body {
      background: var(--page-bg);
      font-family: "Inter", system-ui, sans-serif;
      margin: 0;
      min-height: 100vh;
      transition: background .3s ease, color .3s ease;
    }
    .container {
      max-width: 700px;
      margin: 70px auto;
      background: var(--card-bg);
      border-radius: 18px;
      padding: 26px 30px;
      box-shadow: 0 18px 40px rgba(15,23,42,0.1);
    }
    h2 {
      color: #0f172a;
      margin-bottom: 10px;
    }
    .sub {
      color: #475569;
      font-size: .9rem;
      margin-bottom: 18px;
    }
    label {
      font-weight: 600;
      font-size: .9rem;
      display: block;
      margin-bottom: 6px;
    }
    textarea, input {
      width: 100%;
      padding: .6rem .7rem;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      margin-bottom: 14px;
      font-family: inherit;
    }
    .btn-primary {
      background: var(--accent);
      border: none;
      color: white;
      font-weight: 600;
      border-radius: 999px;
      padding: .55rem 1.4rem;
      cursor: pointer;
      transition: background .2s ease;
    }
    .btn-primary:hover { background: var(--accent-dark); }
    a.back {
      display: inline-block;
      text-decoration: none;
      color: var(--accent);
      font-size: .85rem;
      margin-bottom: 1rem;
    }
    /* dark mode */
    body.dark {
      background: var(--dark-bg);
      color: #e2e8f0;
    }
    body.dark .container { background: rgba(15,23,42,0.55); color: #fff; }
    body.dark textarea, body.dark input {
      background: rgba(15,23,42,0.6);
      color: #e2e8f0;
      border: 1px solid rgba(148,163,184,0.25);
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="<?= $base ?>/workers/detail.php?worker_id=<?= $worker_id ?>" class="back">← Back to Profile</a>
    <h2>Book <?= htmlspecialchars($worker['name']) ?></h2>
    <p class="sub"><?= htmlspecialchars($worker['headline']) ?></p>
    <p><strong>Hourly Rate:</strong> ৳<?= number_format($worker['hourly_rate'], 2) ?>/hr</p>

    <form method="POST">
      <label for="note">Additional Details</label>
      <textarea name="note" id="note" rows="4" placeholder="Provide any additional details or requests"></textarea>
      <button type="submit" class="btn-primary">Confirm Booking</button>
    </form>
  </div>

  <script>
    // Sync dark mode with index
    if (localStorage.getItem('darkMode') === 'on') document.body.classList.add('dark');
  </script>
</body>
</html>
