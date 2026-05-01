<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/functions.php';
require_login();

header('Content-Type: application/json');

$user_id = $_SESSION['user']['id'];

// Fetch latest 5 notifications
$stmt = $conn->prepare("
  SELECT id, title, body, is_read, created_at
  FROM notifications
  WHERE user_id = ?
  ORDER BY created_at DESC
  LIMIT 5
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();

$data = [
  'unread_count'  => 0,
  'notifications' => []
];

while ($row = $res->fetch_assoc()) {
  if (!$row['is_read']) {
    $data['unread_count']++;
  }

  $data['notifications'][] = [
    'id'      => (int)$row['id'],
    'title'   => $row['title'],
    'body'    => $row['body'],
    'time'    => date('M d, g:i a', strtotime($row['created_at'])),
    'is_read' => (bool)$row['is_read']
  ];
}

echo json_encode($data);
