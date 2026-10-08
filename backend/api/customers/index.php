<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
requireLogin();
$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Purchase history for one customer
        if (!empty($_GET['history_for'])) {
            $cid = (int) $_GET['history_for'];
            $stmt = $pdo->prepare(
                'SELECT s.sale_id, s.invoice_number, s.total_amount, s.status, s.created_at
                 FROM sales s WHERE s.customer_id = :cid ORDER BY s.created_at DESC'
            );
            $stmt->execute(['cid' => $cid]);
            jsonResponse(true, $stmt->fetchAll());
        }

        $search = trim($_GET['search'] ?? '');
        $sql = 'SELECT c.*, m.level_name FROM customers c
                LEFT JOIN membership_levels m ON m.membership_id = c.membership_id';
        $params = [];
        if ($search !== '') {
            $sql .= ' WHERE c.full_name LIKE :s OR c.phone LIKE :s2 OR c.email LIKE :s3';
            $params = ['s' => "%$search%", 's2' => "%$search%", 's3' => "%$search%"];
        }
        $sql .= ' ORDER BY c.full_name';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        jsonResponse(true, $stmt->fetchAll());
        break;

    case 'POST':
        requireCsrfForWrites();
        $input = sanitizeInput(getJsonInput());
        if (empty($input['full_name'])) jsonError('Full name is required.');
        if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) jsonError('Invalid email.');
        $stmt = $pdo->prepare(
            'INSERT INTO customers (full_name, phone, email, address, membership_id)
             VALUES (:n,:p,:e,:a,:m)'
        );
        $stmt->execute([
            'n' => $input['full_name'], 'p' => $input['phone'] ?? null,
            'e' => $input['email'] ?? null, 'a' => $input['address'] ?? null,
            'm' => $input['membership_id'] ?? 1,
        ]);
        jsonResponse(true, ['customer_id' => $pdo->lastInsertId()], 'Customer registered.', 201);
        break;

    case 'PUT':
        requireCsrfForWrites();
        $input = sanitizeInput(getJsonInput());
        $id = (int) ($input['customer_id'] ?? 0);
        if (!$id || empty($input['full_name'])) jsonError('customer_id and full_name are required.');
        $stmt = $pdo->prepare(
            'UPDATE customers SET full_name=:n, phone=:p, email=:e, address=:a, membership_id=:m WHERE customer_id=:id'
        );
        $stmt->execute([
            'n' => $input['full_name'], 'p' => $input['phone'] ?? null,
            'e' => $input['email'] ?? null, 'a' => $input['address'] ?? null,
            'm' => $input['membership_id'] ?? 1, 'id' => $id,
        ]);
        jsonResponse(true, null, 'Customer updated.');
        break;

    case 'DELETE':
        requireRole(['Admin', 'Manager']);
        requireCsrfForWrites();
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) jsonError('id is required.');
        $stmt = $pdo->prepare('DELETE FROM customers WHERE customer_id = :id');
        $stmt->execute(['id' => $id]);
        jsonResponse(true, null, 'Customer deleted.');
        break;

    default:
        jsonError('Method not allowed', 405);
}
