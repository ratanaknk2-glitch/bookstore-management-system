<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
requireRole(['Admin', 'Manager']);
requireCsrfForWrites();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$bookId = (int) ($_POST['book_id'] ?? 0);
if (!$bookId) jsonError('book_id is required.');

if (empty($_FILES['cover']) || $_FILES['cover']['error'] !== UPLOAD_ERR_OK) {
    jsonError('No valid file uploaded.');
}

$file = $_FILES['cover'];

// 1. Size check
if ($file['size'] > MAX_UPLOAD_SIZE) {
    jsonError('File too large. Max size is 2MB.');
}

// 2. Real MIME type check (not just extension - prevents disguised malicious files)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, ALLOWED_IMAGE_TYPES, true)) {
    jsonError('Invalid file type. Only JPG, PNG, or WEBP images are allowed.');
}

// 3. Verify it's actually a valid image (blocks polyglot files)
$imageInfo = @getimagesize($file['tmp_name']);
if ($imageInfo === false) {
    jsonError('File is not a valid image.');
}

// 4. Generate a random, safe filename - never trust the original filename
$ext = match ($mime) {
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    default => 'jpg',
};
$safeName = bin2hex(random_bytes(16)) . '.' . $ext;

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
$destination = UPLOAD_DIR . $safeName;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    jsonError('Failed to save the uploaded file.', 500);
}

$pdo = getDBConnection();
$stmt = $pdo->prepare('UPDATE books SET cover_image = :img WHERE book_id = :id');
$stmt->execute(['img' => $safeName, 'id' => $bookId]);

jsonResponse(true, ['cover_image' => UPLOAD_URL_PATH . $safeName], 'Cover uploaded successfully.');
