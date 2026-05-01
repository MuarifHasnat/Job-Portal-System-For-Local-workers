<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/functions.php';

require_login();

$user_id = $_SESSION['user']['id']; // customer
$user_role = $_SESSION['user']['role'];
if ($user_role !== 'customer') {
    echo "Only customers can rate workers.";
    exit;
}

$booking_id = $_POST['booking_id'] ?? null;
$rating     = (int)($_POST['rating'] ?? 0);

if (!$booking_id || $rating < 1 || $rating > 5) {
    echo "Invalid input.";
    exit;
}

// Get worker id for this booking
$stmt = $conn->prepare("SELECT worker_id FROM bookings WHERE id = ? AND user_id = ? AND status = 'confirmed'");
$stmt->bind_param('ii', $booking_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();
if (!$row = $res->fetch_assoc()) {
    echo "Booking not found or not eligible for rating.";
    exit;
}
$worker_id = $row['worker_id'];

// Check if already rated
$check = $conn->prepare("SELECT id FROM ratings WHERE booking_id = ?");
$check->bind_param('i', $booking_id);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    header("Location: /jobportalsystem/bookings/my_bookings.php?already_rated=1");
    exit;
}

// Insert rating
$insert = $conn->prepare("INSERT INTO ratings (booking_id, worker_id, customer_id, rating) VALUES (?, ?, ?, ?)");
$insert->bind_param('iiii', $booking_id, $worker_id, $user_id, $rating);
$insert->execute();

// Optional: notify worker
$notif = $conn->prepare("INSERT INTO notifications (user_id, booking_id, type, title, body, is_read, created_at)
                         VALUES (?, ?, 'rating', 'New Rating Received', 'A customer rated your work.', 0, NOW())");
$notif->bind_param('ii', $worker_id, $booking_id);
$notif->execute();

// Redirect
header("Location: /jobportalsystem/bookings/my_bookings.php?rated=1");
exit;
?>
