<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
requireLogin();
$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $search = trim($_GET['search'] ?? '');
        if ($search !== '') {
            $stmt = $pdo->prepare('SELECT * FROM publishers WHERE publisher_name LIKE :s ORDER BY publisher_name');
            $stmt->execute(['s' => "%$search%"]);
        } else {
            $stmt = $pdo->query('SELECT * FROM publishers ORDER BY publisher_name');
        }
        jsonResponse(true, $stmt->fetchAll());
        break;

    case 'POST':
        requireRole(['Admin', 'Manager']);
        requireCsrfForWrites();
        $input = sanitizeInput(getJsonInput());
        if (empty($input['publisher_name'])) jsonError('Publisher name is required.');
        if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) jsonError('Invalid email.');
        $stmt = $pdo->prepare('INSERT INTO publishers (publisher_name, phone, email, address) VALUES (:n,:p,:e,:a)');
        $stmt->execute(['n' => $input['publisher_name'], 'p' => $input['phone'] ?? null, 'e' => $input['email'] ?? null, 'a' => $input['address'] ?? null]);
        jsonResponse(true, ['publisher_id' => $pdo->lastInsertId()], 'Publisher added.', 201);
        break;

    case 'PUT':
        requireRole(['Admin', 'Manager']);
        requireCsrfForWrites();
        $input = sanitizeInput(getJsonInput());
        $id = (int) ($input['publisher_id'] ?? 0);
        if (!$id || empty($input['publisher_name'])) jsonError('publisher_id and publisher_name are required.');
        $stmt = $pdo->prepare('UPDATE publishers SET publisher_name=:n, phone=:p, email=:e, address=:a WHERE publisher_id=:id');
        $stmt->execute(['n' => $input['publisher_name'], 'p' => $input['phone'] ?? null, 'e' => $input['email'] ?? null, 'a' => $input['address'] ?? null, 'id' => $id]);
        jsonResponse(true, null, 'Publisher updated.');
        break;

    case 'DELETE':
        requireRole(['Admin', 'Manager']);
        requireCsrfForWrites();
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) jsonError('id is required.');
        $stmt = $pdo->prepare('DELETE FROM publishers WHERE publisher_id = :id');
        $stmt->execute(['id' => $id]);
        jsonResponse(true, null, 'Publisher deleted.');
        break;

    default:
        jsonError('Method not allowed', 405);
}
