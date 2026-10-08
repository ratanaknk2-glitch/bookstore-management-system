<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
requireLogin();
$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $stmt = $pdo->query('SELECT setting_key, setting_value FROM settings');
        $settings = [];
        foreach ($stmt->fetchAll() as $row) $settings[$row['setting_key']] = $row['setting_value'];
        jsonResponse(true, $settings);
        break;

    case 'PUT':
        requireRole(['Admin']);
        requireCsrfForWrites();
        $input = sanitizeInput(getJsonInput());
        $stmt = $pdo->prepare('UPDATE settings SET setting_value = :v WHERE setting_key = :k');
        foreach ($input as $key => $value) {
            $stmt->execute(['v' => $value, 'k' => $key]);
        }
        jsonResponse(true, null, 'Settings updated.');
        break;

    default:
        jsonError('Method not allowed', 405);
}
