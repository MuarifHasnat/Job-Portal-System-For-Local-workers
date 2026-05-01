<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/functions.php';

require_login();

$booking_id = (int)($_GET['booking_id'] ?? 0);
if ($booking_id <= 0) exit('❌ Invalid booking ID.');

$worker_id = $_SESSION['user']['id'];
$role      = $_SESSION['user']['role'];

if ($role !== 'worker') exit('❌ Unauthorized. Workers only.');

// Fetch booking
$stmt = $conn->prepare("SELECT id, user_id AS customer_id, worker_id FROM bookings WHERE id = ?");
$stmt->bind_param('i', $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking || $booking['worker_id'] != $worker_id) exit('❌ Unauthorized booking.');

// Update status
$stmt = $conn->prepare("UPDATE bookings SET status='cancelled' WHERE id=?");
$stmt->bind_param('i', $booking_id);
$stmt->execute();
$stmt->close();

// Notify customer
$title = "Booking Cancelled";
$body  = "The worker has cancelled your booking request.";
$stmt  = $conn->prepare("INSERT INTO notifications (user_id, booking_id, type, title, body, is_read, created_at)
                         VALUES (?, ?, 'booking', ?, ?, 0, NOW())");
$stmt->bind_param('iiss', $booking['customer_id'], $booking_id, $title, $body);
$stmt->execute();

// Notify worker
$worker_msg = "You cancelled the booking successfully.";
$stmt = $conn->prepare("INSERT INTO notifications (user_id, booking_id, type, title, body, is_read, created_at)
                        VALUES (?, ?, 'booking', ?, ?, 0, NOW())");
$stmt->bind_param('iiss', $worker_id, $booking_id, $title, $worker_msg);
$stmt->execute();

header("Location: /jobportalsystem/bookings/my_bookings.php?cancelled=1");
exit();
?>
