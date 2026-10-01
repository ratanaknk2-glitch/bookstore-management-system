<?php
/**
 * Global application configuration.
 */

// --- Error display: OFF in production, logged instead ---
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// --- Session security hardening ---
ini_set('session.cookie_httponly', '1');   // JS cannot read the session cookie
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Strict');
// Enable the line below automatically once you deploy behind HTTPS:
// ini_set('session.cookie_secure', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- App constants ---
define('APP_NAME', 'Book Store Management System');
define('APP_ENV', 'development'); // change to "production" when deployed
define('SESSION_TIMEOUT', 30 * 60); // 30 minutes of inactivity = auto logout
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_MINUTES', 15);
define('UPLOAD_DIR', __DIR__ . '/../uploads/book_covers/');
define('UPLOAD_URL_PATH', '/backend/uploads/book_covers/');
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

// --- CORS: allow only your own frontend origin ---
// During local dev with XAMPP/WAMP both frontend and backend usually share the
// same origin (http://localhost/bookstore-management-system/...), so CORS is
// often not even needed. Adjust ALLOWED_ORIGIN if you host frontend separately.
define('ALLOWED_ORIGIN', 'http://localhost');

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/security.php';
