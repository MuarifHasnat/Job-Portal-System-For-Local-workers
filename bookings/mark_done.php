<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

$user_id   = $_SESSION['user']['id'];
$user_role = $_SESSION['user']['role'];

if ($user_role !== 'worker') {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /jobportalsystem/bookings/my_bookings.php');
    exit;
}

$booking_id = isset($_POST['booking_id']) ? (int) $_POST['booking_id'] : 0;

if ($booking_id <= 0) {
    header('Location: /jobportalsystem/bookings/my_bookings.php?error=invalid_booking');
    exit;
}

// Verify booking belongs to this worker and is confirmed
$sql = "SELECT id, worker_id, status FROM bookings WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $booking_id);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();

if (!$booking || (int)$booking['worker_id'] !== (int)$user_id) {
    header('Location: /jobportalsystem/bookings/my_bookings.php?error=not_found_or_forbidden');
    exit;
}

if ($booking['status'] !== 'confirmed') {
    header('Location: /jobportalsystem/bookings/my_bookings.php?error=invalid_status');
    exit;
}

// Update status to 'done'
$update = $conn->prepare("UPDATE bookings SET status = 'done' WHERE id = ?");
$update->bind_param('i', $booking_id);
$update->execute();

header('Location: /jobportalsystem/bookings/my_bookings.php?done=1');
exit;
