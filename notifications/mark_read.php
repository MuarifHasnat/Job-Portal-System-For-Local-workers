<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

header('Content-Type: application/json');

$user_id = $_SESSION['user']['id'] ?? 0;
$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();

echo json_encode(['ok' => true]);
