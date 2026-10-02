<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
requireLogin();
$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $search = trim($_GET['search'] ?? '');
        if ($search !== '') {
            $stmt = $pdo->prepare('SELECT * FROM authors WHERE author_name LIKE :s ORDER BY author_name');
            $stmt->execute(['s' => "%$search%"]);
        } else {
            $stmt = $pdo->query('SELECT * FROM authors ORDER BY author_name');
        }
        jsonResponse(true, $stmt->fetchAll());
        break;

    case 'POST':
        requireRole(['Admin', 'Manager']);
        requireCsrfForWrites();
        $input = sanitizeInput(getJsonInput());
        if (empty($input['author_name'])) jsonError('Author name is required.');
        $stmt = $pdo->prepare('INSERT INTO authors (author_name, biography, nationality) VALUES (:n, :b, :nat)');
        $stmt->execute(['n' => $input['author_name'], 'b' => $input['biography'] ?? null, 'nat' => $input['nationality'] ?? null]);
        jsonResponse(true, ['author_id' => $pdo->lastInsertId()], 'Author added.', 201);
        break;

    case 'PUT':
        requireRole(['Admin', 'Manager']);
        requireCsrfForWrites();
        $input = sanitizeInput(getJsonInput());
        $id = (int) ($input['author_id'] ?? 0);
        if (!$id || empty($input['author_name'])) jsonError('author_id and author_name are required.');
        $stmt = $pdo->prepare('UPDATE authors SET author_name=:n, biography=:b, nationality=:nat WHERE author_id=:id');
        $stmt->execute(['n' => $input['author_name'], 'b' => $input['biography'] ?? null, 'nat' => $input['nationality'] ?? null, 'id' => $id]);
        jsonResponse(true, null, 'Author updated.');
        break;

    case 'DELETE':
        requireRole(['Admin', 'Manager']);
        requireCsrfForWrites();
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) jsonError('id is required.');
        $stmt = $pdo->prepare('DELETE FROM authors WHERE author_id = :id');
        $stmt->execute(['id' => $id]);
        jsonResponse(true, null, 'Author deleted.');
        break;

    default:
        jsonError('Method not allowed', 405);
}
