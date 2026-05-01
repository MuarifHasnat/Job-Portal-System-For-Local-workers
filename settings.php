<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

$user_id = $_SESSION['user']['id'];
$baseUrl = '/jobportalsystem';
$message = "";

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Get current password hash
    $stmt = $conn->prepare("SELECT password_user FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result && password_verify($current, $result['password_user'])) {
        if ($new === $confirm && strlen($new) >= 6) {
            $newHash = password_hash($new, PASSWORD_BCRYPT);
            $update = $conn->prepare("UPDATE users SET password_user = ? WHERE id = ?");
            $update->bind_param('si', $newHash, $user_id);
            $update->execute();
            $message = "<div class='alert success'>✅ Password changed successfully!</div>";
        } else {
            $message = "<div class='alert error'>❌ Passwords don’t match or too short (min 6 chars).</div>";
        }
    } else {
        $message = "<div class='alert error'>❌ Current password is incorrect.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Settings — Job Portal System</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: radial-gradient(circle at top, #e0f2fe 0%, #bae6fd 50%, #93c5fd 100%);
      --card: #ffffff;
      --accent: #3b82f6;
      --text: #0f172a;
    }
    body {
      background: var(--bg);
      font-family: 'Inter', sans-serif;
      margin: 0;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    header {
      width: 100%;
      backdrop-filter: blur(12px);
      background: rgba(255,255,255,0.4);
      box-shadow: 0 2px 10px rgba(15,23,42,0.05);
      padding: 0.8rem 1.2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 10;
    }
    header h1 {
      font-size: 1rem;
      font-weight: 700;
      color: #0f172a;
    }
    .back-btn {
      background: #e2e8f0;
      border-radius: 999px;
      padding: 6px 14px;
      text-decoration: none;
      color: #0f172a;
      font-size: .8rem;
      font-weight: 600;
    }
    main {
      margin-top: 100px;
      background: var(--card);
      border-radius: 16px;
      box-shadow: 0 10px 30px rgba(15,23,42,0.1);
      padding: 30px;
      width: 90%;
      max-width: 480px;
    }
    h2 {
      text-align: center;
      margin-bottom: 20px;
      font-size: 1.4rem;
      color: var(--text);
    }
    label {
      display: block;
      font-weight: 600;
      margin-bottom: 6px;
    }
    input[type=password] {
      width: 100%;
      padding: 10px;
      border-radius: 10px;
      border: 1px solid #d1d5db;
      margin-bottom: 16px;
      font-size: 0.9rem;
    }
    .btn {
      display: block;
      width: 100%;
      background: var(--accent);
      color: white;
      border: none;
      border-radius: 999px;
      padding: 10px;
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
    }
    .btn:hover { background: #2563eb; }
    .alert {
      text-align: center;
      margin-bottom: 15px;
      padding: 10px;
      border-radius: 10px;
      font-size: 0.9rem;
    }
    .alert.success {
      background: #dcfce7;
      color: #166534;
    }
    .alert.error {
      background: #fee2e2;
      color: #991b1b;
    }
    footer {
      margin-top: auto;
      text-align: center;
      padding: 20px;
      color: #64748b;
      font-size: .85rem;
    }
  </style>
</head>
<body>
  <header>
    <h1>⚙️ Settings</h1>
    <a href="<?= $baseUrl ?>/index.php" class="back-btn">← Back to Dashboard</a>
  </header>

  <main>
    <h2>Change Password</h2>
    <?= $message ?>

    <form method="POST">
      <label for="current_password">Current Password</label>
      <input type="password" name="current_password" id="current_password" required>

      <label for="new_password">New Password</label>
      <input type="password" name="new_password" id="new_password" required>

      <label for="confirm_password">Confirm New Password</label>
      <input type="password" name="confirm_password" id="confirm_password" required>

      <button type="submit" class="btn">Update Password</button>
    </form>
  </main>

  <footer>
    © <?= date('Y') ?> Job Portal System — Secure Your Account
  </footer>
</body>
</html>
