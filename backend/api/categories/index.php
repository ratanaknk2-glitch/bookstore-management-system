<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
requireLogin();
$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $search = trim($_GET['search'] ?? '');
        if ($search !== '') {
            $stmt = $pdo->prepare('SELECT * FROM categories WHERE category_name LIKE :s ORDER BY category_name');
            $stmt->execute(['s' => "%$search%"]);
        } else {
            $stmt = $pdo->query('SELECT * FROM categories ORDER BY category_name');
        }
        jsonResponse(true, $stmt->fetchAll());
        break;

    case 'POST':
        requireRole(['Admin', 'Manager']);
        requireCsrfForWrites();
        $input = sanitizeInput(getJsonInput());
        if (empty($input['category_name'])) jsonError('Category name is required.');
        try {
            $stmt = $pdo->prepare('INSERT INTO categories (category_name, description) VALUES (:n, :d)');
            $stmt->execute(['n' => $input['category_name'], 'd' => $input['description'] ?? null]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') jsonError('Category already exists.', 409);
            throw $e;
        }
        jsonResponse(true, ['category_id' => $pdo->lastInsertId()], 'Category added.', 201);
        break;

    case 'PUT':
        requireRole(['Admin', 'Manager']);
        requireCsrfForWrites();
        $input = sanitizeInput(getJsonInput());
        $id = (int) ($input['category_id'] ?? 0);
        if (!$id || empty($input['category_name'])) jsonError('category_id and category_name are required.');
        $stmt = $pdo->prepare('UPDATE categories SET category_name=:n, description=:d WHERE category_id=:id');
        $stmt->execute(['n' => $input['category_name'], 'd' => $input['description'] ?? null, 'id' => $id]);
        jsonResponse(true, null, 'Category updated.');
        break;

    case 'DELETE':
        requireRole(['Admin', 'Manager']);
        requireCsrfForWrites();
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) jsonError('id is required.');
        $stmt = $pdo->prepare('DELETE FROM categories WHERE category_id = :id');
        $stmt->execute(['id' => $id]);
        jsonResponse(true, null, 'Category deleted.');
        break;

    default:
        jsonError('Method not allowed', 405);
}
