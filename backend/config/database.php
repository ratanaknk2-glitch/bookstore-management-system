<?php
/**
 * Database connection (PDO, prepared statements only).
 * Real credentials live in database.local.php which is GIT-IGNORED.
 * Copy database.local.example.php -> database.local.php and fill in your values.
 */

$localConfigFile = __DIR__ . '/database.local.php';

if (!file_exists($localConfigFile)) {
    http_response_code(500);
    die('Missing backend/config/database.local.php. Copy database.local.example.php and set your DB credentials.');
}

require_once $localConfigFile; // defines DB_HOST, DB_NAME, DB_USER, DB_PASS

function getDBConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // real prepared statements = SQL injection protection
            ]);
        } catch (PDOException $e) {
            error_log('DB Connection Error: ' . $e->getMessage());
            http_response_code(500);
            die(json_encode(['success' => false, 'message' => 'Database connection failed.']));
        }
    }

    return $pdo;
}
