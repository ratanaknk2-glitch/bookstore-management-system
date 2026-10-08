<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$user = requireLogin();
requireCsrfForWrites();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$input = sanitizeInput(getJsonInput());
$current = $input['current_password'] ?? '';
$new = $input['new_password'] ?? '';
$confirm = $input['confirm_password'] ?? '';

if ($current === '' || $new === '' || $confirm === '') {
    jsonError('All fields are required.');
}
if ($new !== $confirm) {
    jsonError('New password and confirmation do not match.');
}
if (strlen($new) < 8 || !preg_match('/[A-Z]/', $new) || !preg_match('/[0-9]/', $new)) {
    jsonError('Password must be at least 8 characters and include an uppercase letter and a number.');
}

$pdo = getDBConnection();
$stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = :id');
$stmt->execute(['id' => $user['user_id']]);
$row = $stmt->fetch();

if (!$row || !password_verify($current, $row['password_hash'])) {
    jsonError('Current password is incorrect.', 401);
}

$newHash = password_hash($new, PASSWORD_BCRYPT);
$pdo->prepare('UPDATE users SET password_hash = :h WHERE user_id = :id')
    ->execute(['h' => $newHash, 'id' => $user['user_id']]);

jsonResponse(true, null, 'Password changed successfully.');
