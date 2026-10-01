<?php
/**
 * Security helpers used across the whole backend.
 */

// ---------------------------------------------------------------
// Security headers (sent on every API request)
// ---------------------------------------------------------------
function sendSecurityHeaders(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'");
    header('Content-Type: application/json; charset=utf-8');

    // CORS - restrict to known origin only
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === ALLOWED_ORIGIN || strpos($origin, 'localhost') !== false) {
        header('Access-Control-Allow-Origin: ' . $origin);
    }
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// ---------------------------------------------------------------
// Standard JSON responses
// ---------------------------------------------------------------
function jsonResponse(bool $success, $data = null, string $message = '', int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data,
    ]);
    exit;
}

function jsonError(string $message, int $httpCode = 400): void
{
    jsonResponse(false, null, $message, $httpCode);
}

// ---------------------------------------------------------------
// Input handling
// ---------------------------------------------------------------
function getJsonInput(): array
{
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/** Recursively trim + strip tags from all string inputs (defense in depth vs XSS) */
function sanitizeInput($value)
{
    if (is_array($value)) {
        return array_map('sanitizeInput', $value);
    }
    if (is_string($value)) {
        return trim(strip_tags($value));
    }
    return $value;
}

/** Escape output for safe HTML rendering when needed server-side */
function escapeOutput(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// ---------------------------------------------------------------
// CSRF protection (token issued per session, required on all writes)
// ---------------------------------------------------------------
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(): void
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sent)) {
        jsonError('Invalid or missing CSRF token.', 403);
    }
}

// ---------------------------------------------------------------
// Simple IP-based rate limiting (protects login & write endpoints from abuse)
// Uses a file-based counter, no extra DB writes needed.
// ---------------------------------------------------------------
function rateLimit(string $bucket, int $maxRequests = 30, int $windowSeconds = 60): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $dir = sys_get_temp_dir() . '/bookstore_ratelimit';
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    $file = $dir . '/' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $bucket . '_' . $ip) . '.json';

    $now = time();
    $data = ['count' => 0, 'start' => $now];
    if (file_exists($file)) {
        $content = json_decode(file_get_contents($file), true);
        if (is_array($content)) {
            $data = $content;
        }
    }

    if ($now - $data['start'] > $windowSeconds) {
        $data = ['count' => 0, 'start' => $now];
    }

    $data['count']++;
    file_put_contents($file, json_encode($data), LOCK_EX);

    if ($data['count'] > $maxRequests) {
        jsonError('Too many requests. Please slow down and try again shortly.', 429);
    }
}

// ---------------------------------------------------------------
// Session / Auth guards
// ---------------------------------------------------------------
function requireLogin(): array
{
    if (empty($_SESSION['user_id'])) {
        jsonError('Unauthorized. Please log in.', 401);
    }

    // Idle session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        jsonError('Session expired. Please log in again.', 401);
    }
    $_SESSION['last_activity'] = time();

    // Defensive check: a valid session must have ALL of these set together (they're
    // always written atomically at login). If any are missing, this is a corrupted
    // or partial session (e.g. left over from a database reset during testing) -
    // force a clean logout instead of returning nulls that silently break the UI.
    if (empty($_SESSION['username']) || empty($_SESSION['role']) || empty($_SESSION['full_name'])) {
        session_unset();
        session_destroy();
        jsonError('Your session is out of date. Please log in again.', 401);
    }

    return [
        'user_id'  => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role'     => $_SESSION['role'],
    ];
}

/** Restrict endpoint to specific roles, e.g. requireRole(['Admin','Manager']) */
function requireRole(array $allowedRoles): array
{
    $user = requireLogin();
    if (!in_array($user['role'], $allowedRoles, true)) {
        jsonError('Forbidden. You do not have permission to perform this action.', 403);
    }
    return $user;
}

/** Require a valid CSRF token for state-changing requests (POST/PUT/DELETE) */
function requireCsrfForWrites(): void
{
    $method = $_SERVER['REQUEST_METHOD'];
    if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        verifyCsrfToken();
    }
}