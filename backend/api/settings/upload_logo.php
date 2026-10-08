<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
requireRole(['Admin']);
requireCsrfForWrites();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
    jsonError('No valid file uploaded.');
}

$file = $_FILES['logo'];
$maxLogoSize = 1024 * 1024; // 1MB is plenty for a logo

if ($file['size'] > $maxLogoSize) {
    jsonError('Logo file too large. Max size is 1MB.');
}

// Real MIME type check (not just extension)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowedLogoTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
if (!in_array($mime, $allowedLogoTypes, true)) {
    jsonError('Invalid file type. Only JPG, PNG, WEBP, or SVG are allowed.');
}

// For raster images, verify it's genuinely a valid image (blocks disguised/polyglot files).
// SVG is XML text, not a raster image, so getimagesize() doesn't apply - it's sanitized instead.
if ($mime !== 'image/svg+xml') {
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
} else {
    // Basic SVG sanitization: reject any file containing <script> or event handlers
    // (SVGs can otherwise be used to smuggle executable JS - XSS risk).
    $svgContent = file_get_contents($file['tmp_name']);
    if (preg_match('/<script|on\w+\s*=|javascript:/i', $svgContent)) {
        jsonError('SVG file contains disallowed script content.');
    }
    $ext = 'svg';
}

$logoDir = __DIR__ . '/../../uploads/logo/';
if (!is_dir($logoDir)) {
    mkdir($logoDir, 0755, true);
}

// Random filename - never trust the original filename
$safeName = 'logo_' . bin2hex(random_bytes(12)) . '.' . $ext;
$destination = $logoDir . $safeName;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    jsonError('Failed to save the uploaded file.', 500);
}

// Remove the previous logo file (keep uploads folder clean)
$pdo = getDBConnection();
$old = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='store_logo'")->fetchColumn();
if ($old) {
    $oldPath = $logoDir . basename($old);
    if (is_file($oldPath)) {
        @unlink($oldPath);
    }
}

$pdo->prepare("UPDATE settings SET setting_value = :v WHERE setting_key = 'store_logo'")
    ->execute(['v' => $safeName]);

jsonResponse(true, ['logo_file' => $safeName], 'Logo updated successfully.');
