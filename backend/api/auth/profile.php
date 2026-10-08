<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$user = requireLogin();
$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT u.user_id, u.username, u.email, u.full_name, u.phone, u.profile_image,
                u.last_login, r.role_name
         FROM users u JOIN roles r ON r.role_id = u.role_id WHERE u.user_id = :id'
    );
    $stmt->execute(['id' => $user['user_id']]);
    jsonResponse(true, $stmt->fetch());
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    requireCsrfForWrites();
    $input = sanitizeInput(getJsonInput());
    $fullName = $input['full_name'] ?? '';
    $email = $input['email'] ?? '';
    $phone = $input['phone'] ?? '';

    if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError('Valid full name and email are required.');
    }

    $stmt = $pdo->prepare('UPDATE users SET full_name=:n, email=:e, phone=:p WHERE user_id=:id');
    $stmt->execute(['n' => $fullName, 'e' => $email, 'p' => $phone, 'id' => $user['user_id']]);
    jsonResponse(true, null, 'Profile updated successfully.');
}

jsonError('Method not allowed', 405);
