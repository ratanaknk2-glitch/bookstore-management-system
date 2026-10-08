<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
requireLogin();
$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $stmt = $pdo->query('SELECT * FROM discounts ORDER BY created_at DESC');
        jsonResponse(true, $stmt->fetchAll());
        break;

    case 'POST':
        requireRole(['Admin', 'Manager']);
        requireCsrfForWrites();
        $input = sanitizeInput(getJsonInput());
        if (empty($input['code']) || empty($input['name']) || empty($input['type']) || !isset($input['value'])) {
            jsonError('code, name, type and value are required.');
        }
        if (!in_array($input['type'], ['percentage', 'fixed'], true)) jsonError('Invalid discount type.');
        // Empty strings from the form (blank date pickers, blank usage limit) must become
        // real NULLs - otherwise MySQL stores "0000-00-00" which silently breaks the
        // active-date check used at checkout.
        $startDate = !empty($input['start_date']) ? $input['start_date'] : null;
        $endDate = !empty($input['end_date']) ? $input['end_date'] : null;
        $usageLimit = !empty($input['usage_limit']) ? (int) $input['usage_limit'] : null;
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO discounts (code, name, type, value, start_date, end_date, usage_limit, status)
                 VALUES (:code,:name,:type,:value,:start,:end,:limit,:status)'
            );
            $stmt->execute([
                'code' => strtoupper($input['code']), 'name' => $input['name'], 'type' => $input['type'],
                'value' => (float) $input['value'], 'start' => $startDate,
                'end' => $endDate, 'limit' => $usageLimit,
                'status' => $input['status'] ?? 'active',
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') jsonError('Discount code already exists.', 409);
            throw $e;
        }
        jsonResponse(true, ['discount_id' => $pdo->lastInsertId()], 'Discount created.', 201);
        break;

    case 'PUT':
        requireRole(['Admin', 'Manager']);
        requireCsrfForWrites();
        $input = sanitizeInput(getJsonInput());
        $id = (int) ($input['discount_id'] ?? 0);
        if (!$id) jsonError('discount_id is required.');
        // Empty strings from blank date pickers / blank usage limit must become
        // real NULLs - otherwise MySQL stores "0000-00-00", which silently breaks
        // the active-date check used at checkout.
        $startDate = !empty($input['start_date']) ? $input['start_date'] : null;
        $endDate = !empty($input['end_date']) ? $input['end_date'] : null;
        $usageLimit = !empty($input['usage_limit']) ? (int) $input['usage_limit'] : null;
        $stmt = $pdo->prepare(
            'UPDATE discounts SET name=:name, type=:type, value=:value, start_date=:start, end_date=:end,
                usage_limit=:limit, status=:status WHERE discount_id=:id'
        );
        $stmt->execute([
            'name' => $input['name'], 'type' => $input['type'], 'value' => (float) $input['value'],
            'start' => $startDate, 'end' => $endDate,
            'limit' => $usageLimit, 'status' => $input['status'] ?? 'active', 'id' => $id,
        ]);
        jsonResponse(true, null, 'Discount updated.');
        break;

    case 'DELETE':
        requireRole(['Admin', 'Manager']);
        requireCsrfForWrites();
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) jsonError('id is required.');
        $pdo->prepare('DELETE FROM discounts WHERE discount_id = :id')->execute(['id' => $id]);
        jsonResponse(true, null, 'Discount deleted.');
        break;

    default:
        jsonError('Method not allowed', 405);
}
