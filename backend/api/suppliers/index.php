<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
requireLogin();
$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $search = trim($_GET['search'] ?? '');
        if ($search !== '') {
            $stmt = $pdo->prepare('SELECT * FROM suppliers WHERE supplier_name LIKE :s ORDER BY supplier_name');
            $stmt->execute(['s' => "%$search%"]);
        } else {
            $stmt = $pdo->query('SELECT * FROM suppliers ORDER BY supplier_name');
        }
        jsonResponse(true, $stmt->fetchAll());
        break;

    case 'POST':
        requireRole(['Admin', 'Manager']);
        requireCsrfForWrites();
        $input = sanitizeInput(getJsonInput());
        if (empty($input['supplier_name'])) jsonError('Supplier name is required.');
        $stmt = $pdo->prepare(
            'INSERT INTO suppliers (supplier_name, contact_person, phone, email, address, status)
             VALUES (:n,:c,:p,:e,:a,:st)'
        );
        $stmt->execute([
            'n' => $input['supplier_name'], 'c' => $input['contact_person'] ?? null,
            'p' => $input['phone'] ?? null, 'e' => $input['email'] ?? null,
            'a' => $input['address'] ?? null, 'st' => $input['status'] ?? 'active',
        ]);
        jsonResponse(true, ['supplier_id' => $pdo->lastInsertId()], 'Supplier added.', 201);
        break;

    case 'PUT':
        requireRole(['Admin', 'Manager']);
        requireCsrfForWrites();
        $input = sanitizeInput(getJsonInput());
        $id = (int) ($input['supplier_id'] ?? 0);
        if (!$id || empty($input['supplier_name'])) jsonError('supplier_id and supplier_name are required.');
        $stmt = $pdo->prepare(
            'UPDATE suppliers SET supplier_name=:n, contact_person=:c, phone=:p, email=:e, address=:a, status=:st
             WHERE supplier_id=:id'
        );
        $stmt->execute([
            'n' => $input['supplier_name'], 'c' => $input['contact_person'] ?? null,
            'p' => $input['phone'] ?? null, 'e' => $input['email'] ?? null,
            'a' => $input['address'] ?? null, 'st' => $input['status'] ?? 'active', 'id' => $id,
        ]);
        jsonResponse(true, null, 'Supplier updated.');
        break;

    case 'DELETE':
        requireRole(['Admin', 'Manager']);
        requireCsrfForWrites();
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) jsonError('id is required.');
        $stmt = $pdo->prepare('DELETE FROM suppliers WHERE supplier_id = :id');
        $stmt->execute(['id' => $id]);
        jsonResponse(true, null, 'Supplier deleted.');
        break;

    default:
        jsonError('Method not allowed', 405);
}
