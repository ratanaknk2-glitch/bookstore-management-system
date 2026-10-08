<?php
/**
 * Upload or remove the CURRENT user's own profile photo.
 * Any logged-in role may call this (unlike the store logo, which is Admin-only) -
 * it only ever touches the calling user's own users.profile_image row.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
$user = requireLogin();
requireCsrfForWrites();

$avatarDir = __DIR__ . '/../../uploads/avatars/';
if (!is_dir($avatarDir)) {
    mkdir($avatarDir, 0755, true);
}

$pdo = getDBConnection();

function deleteOldAvatar(string $avatarDir, ?string $filename): void
{
    if ($filename) {
        $oldPath = $avatarDir . basename($filename);
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        jsonError('No valid file uploaded.');
    }

    $file = $_FILES['avatar'];
    $maxAvatarSize = 2 * 1024 * 1024; // 2MB is plenty for a profile photo

    if ($file['size'] > $maxAvatarSize) {
        jsonError('Image too large. Max size is 2MB.');
    }

    // Real MIME type check (not just extension/original filename)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    // No SVG here (unlike the store logo) - avatars can be uploaded by any role,
    // so we keep the accepted types to raster formats only to shrink attack surface.
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowedTypes, true)) {
        jsonError('Invalid file type. Only JPG, PNG, or WEBP are allowed.');
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        jsonError('File is not a valid image.');
    }
    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => 'jpg',
    };

    // Random filename - never trust the original filename
    $safeName = 'avatar_' . $user['user_id'] . '_' . bin2hex(random_bytes(12)) . '.' . $ext;
    $destination = $avatarDir . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        jsonError('Failed to save the uploaded file.', 500);
    }

    $old = $pdo->prepare('SELECT profile_image FROM users WHERE user_id = :id');
    $old->execute(['id' => $user['user_id']]);
    deleteOldAvatar($avatarDir, $old->fetchColumn() ?: null);

    $pdo->prepare('UPDATE users SET profile_image = :img WHERE user_id = :id')
        ->execute(['img' => $safeName, 'id' => $user['user_id']]);

    jsonResponse(true, ['profile_image' => $safeName], 'Profile photo updated successfully.');
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $old = $pdo->prepare('SELECT profile_image FROM users WHERE user_id = :id');
    $old->execute(['id' => $user['user_id']]);
    deleteOldAvatar($avatarDir, $old->fetchColumn() ?: null);

    $pdo->prepare('UPDATE users SET profile_image = NULL WHERE user_id = :id')
        ->execute(['id' => $user['user_id']]);

    jsonResponse(true, null, 'Profile photo removed.');
}

jsonError('Method not allowed', 405);
