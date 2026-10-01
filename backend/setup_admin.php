<?php
/**
 * Run this ONCE in your browser after importing the database, e.g.:
 *   http://localhost/bookstore-management-system/backend/setup_admin.php
 * It sets a secure password for the "admin" account, then deletes itself.
 * If this file no longer exists, the admin password has already been set --
 * use the normal "Change Password" screen from now on.
 */
require_once __DIR__ . '/config/config.php';

$pdo = getDBConnection();
$message = '';
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $message = 'Password must be at least 8 characters, with an uppercase letter and a number.';
    } elseif ($password !== $confirm) {
        $message = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('UPDATE users SET password_hash = :h WHERE username = :u');
        $stmt->execute(['h' => $hash, 'u' => 'admin']);
        $done = true;
        // Self-delete for security - this script must not remain accessible
        @unlink(__FILE__);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Setup</title>
<style>
  body{font-family:Arial,sans-serif;background:#eaf4fb;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;}
  .box{background:#fff;border:1px solid #cfe3f2;padding:32px;width:340px;}
  h2{color:#1c6ea4;margin-top:0;}
  input{width:100%;padding:10px;margin:8px 0;border:1px solid #cfd8dc;box-sizing:border-box;}
  button{width:100%;padding:10px;background:#5aa9d6;color:#fff;border:none;cursor:pointer;}
  .msg{color:#c0392b;font-size:14px;}
  .ok{color:#1c7a4c;font-size:14px;}
</style>
</head>
<body>
<div class="box">
  <h2>Admin Account Setup</h2>
  <?php if ($done): ?>
    <p class="ok">Admin password set successfully. This setup file has been removed.
    You can now log in at the frontend with username <b>admin</b>.</p>
  <?php else: ?>
    <?php if ($message): ?><p class="msg"><?= escapeOutput($message) ?></p><?php endif; ?>
    <form method="POST">
      <input type="password" name="password" placeholder="New admin password" required>
      <input type="password" name="confirm" placeholder="Confirm password" required>
      <button type="submit">Set Password</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
