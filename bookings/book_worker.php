<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/functions.php';

// Ensure login
require_login();

if (session_status() === PHP_SESSION_NONE) session_start();

$user_id   = $_SESSION['user']['id'];
$user_role = $_SESSION['user']['role'];

// Only customers can book workers
if ($user_role !== 'customer') {
    exit('❌ Only customers can book workers.');
}

// Collect POST data safely
$worker_id = isset($_POST['worker_id']) ? (int)$_POST['worker_id'] : 0;
$note      = trim($_POST['note'] ?? '');

// Validate input
if ($worker_id <= 0) {
    exit('❌ Worker ID missing or invalid.');
}

// Check worker exists
$check = $conn->prepare("SELECT user_id FROM worker_profiles WHERE user_id = ? LIMIT 1");
$check->bind_param('i', $worker_id);
$check->execute();
$exists = $check->get_result()->fetch_assoc();
$check->close();

if (!$exists) {
    exit('❌ The specified worker does not exist.');
}

// Begin transaction to ensure consistency
$conn->begin_transaction();

try {
    // 1️⃣ Insert booking
    $insert_booking = $conn->prepare("
        INSERT INTO bookings (user_id, worker_id, status, note, created_at)
        VALUES (?, ?, 'pending', ?, NOW())
    ");
    $insert_booking->bind_param('iis', $user_id, $worker_id, $note);
    $insert_booking->execute();
    $booking_id = $conn->insert_id;
    $insert_booking->close();

    // 2️⃣ Insert notification for worker
    $worker_msg = "You have a new booking request from " . htmlspecialchars($_SESSION['user']['name']);
    $insert_worker_notif = $conn->prepare("
        INSERT INTO notifications (user_id, booking_id, type, title, body, is_read, created_at)
        VALUES (?, ?, 'booking', 'New Booking Request', ?, 0, NOW())
    ");
    $insert_worker_notif->bind_param('iis', $worker_id, $booking_id, $worker_msg);
    $insert_worker_notif->execute();
    $insert_worker_notif->close();

    // 3️⃣ Insert notification for customer
    $cust_msg = "Your booking request has been submitted successfully and is now pending approval.";
    $insert_cust_notif = $conn->prepare("
        INSERT INTO notifications (user_id, booking_id, type, title, body, is_read, created_at)
        VALUES (?, ?, 'booking', 'Booking Request Submitted', ?, 0, NOW())
    ");
    $insert_cust_notif->bind_param('iis', $user_id, $booking_id, $cust_msg);
    $insert_cust_notif->execute();
    $insert_cust_notif->close();

    // 4️⃣ Commit transaction
    $conn->commit();

    // Redirect to My Bookings page
    header("Location: /jobportalsystem/bookings/my_bookings.php?success=1");
    exit();

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    error_log("Booking Error: " . $e->getMessage());
    exit('❌ An error occurred while processing your booking. Please try again.');
}
?>
