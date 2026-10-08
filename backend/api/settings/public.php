<?php
/**
 * Public, unauthenticated endpoint - intentionally exposes ONLY the store name
 * and logo filename so the login page can render custom branding before
 * a session exists. Never add sensitive settings (tax rate, etc.) here.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

rateLimit('public_branding', 60, 60);

$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = :k");

$stmt->execute(['k' => 'store_name']);
$storeName = $stmt->fetchColumn() ?: 'Book Store MS';

$stmt->execute(['k' => 'store_logo']);
$storeLogo = $stmt->fetchColumn() ?: '';

jsonResponse(true, ['store_name' => $storeName, 'store_logo' => $storeLogo]);
