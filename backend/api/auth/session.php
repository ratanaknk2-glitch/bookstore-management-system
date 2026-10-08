<?php
/** Lets the frontend check "am I logged in" on page load and get a fresh CSRF token */
require_once __DIR__ . '/../../includes/bootstrap.php';

if (empty($_SESSION['user_id'])) {
    jsonResponse(false, null, 'Not authenticated', 401);
}
$user = requireLogin();

// Profile image isn't stored in the session (only set at login), so it's fetched
// fresh here - this keeps the sidebar avatar in sync right after an upload/change.
$pdo = getDBConnection();
$stmt = $pdo->prepare('SELECT profile_image FROM users WHERE user_id = :id');
$stmt->execute(['id' => $user['user_id']]);
$profileImage = $stmt->fetchColumn();

jsonResponse(true, [
    'user_id'       => $user['user_id'],
    'username'      => $user['username'],
    'full_name'     => $_SESSION['full_name'],
    'role'          => $user['role'],
    'profile_image' => $profileImage ?: null,
    'csrf_token'    => generateCsrfToken(),
]);
