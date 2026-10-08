<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
requireRole(['Admin', 'Manager']);
$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (!empty($_GET['attendance_for'])) {
            $eid = (int) $_GET['attendance_for'];
            $stmt = $pdo->prepare('SELECT * FROM attendance WHERE employee_id = :id ORDER BY work_date DESC LIMIT 60');
            $stmt->execute(['id' => $eid]);
            jsonResponse(true, $stmt->fetchAll());
        }
        $stmt = $pdo->query('SELECT * FROM employees ORDER BY full_name');
        jsonResponse(true, $stmt->fetchAll());
        break;

    case 'POST':
        requireCsrfForWrites();
        $input = sanitizeInput(getJsonInput());
        if (empty($input['full_name'])) jsonError('Full name is required.');
        $stmt = $pdo->prepare(
            'INSERT INTO employees (full_name, position, phone, email, hire_date, salary, status)
             VALUES (:n,:pos,:p,:e,:h,:s,:st)'
        );
        $stmt->execute([
            'n' => $input['full_name'], 'pos' => $input['position'] ?? null,
            'p' => $input['phone'] ?? null, 'e' => $input['email'] ?? null,
            'h' => $input['hire_date'] ?? null, 's' => $input['salary'] ?? 0,
            'st' => $input['status'] ?? 'active',
        ]);
        jsonResponse(true, ['employee_id' => $pdo->lastInsertId()], 'Employee added.', 201);
        break;

    case 'PUT':
        requireCsrfForWrites();
        $input = sanitizeInput(getJsonInput());
        $id = (int) ($input['employee_id'] ?? 0);
        if (!$id || empty($input['full_name'])) jsonError('employee_id and full_name are required.');
        $stmt = $pdo->prepare(
            'UPDATE employees SET full_name=:n, position=:pos, phone=:p, email=:e, hire_date=:h, salary=:s, status=:st
             WHERE employee_id=:id'
        );
        $stmt->execute([
            'n' => $input['full_name'], 'pos' => $input['position'] ?? null,
            'p' => $input['phone'] ?? null, 'e' => $input['email'] ?? null,
            'h' => $input['hire_date'] ?? null, 's' => $input['salary'] ?? 0,
            'st' => $input['status'] ?? 'active', 'id' => $id,
        ]);
        jsonResponse(true, null, 'Employee updated.');
        break;

    case 'DELETE':
        requireCsrfForWrites();
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) jsonError('id is required.');
        $pdo->prepare('DELETE FROM employees WHERE employee_id = :id')->execute(['id' => $id]);
        jsonResponse(true, null, 'Employee deleted.');
        break;

    default:
        jsonError('Method not allowed', 405);
}
