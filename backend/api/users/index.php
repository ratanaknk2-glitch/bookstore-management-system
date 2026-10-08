<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$admin = requireRole(['Admin']);
$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    case 'GET':
        if (!empty($_GET['roles'])) {
            // helper: list available roles for the form dropdown
            jsonResponse(true, $pdo->query('SELECT role_id, role_name FROM roles ORDER BY role_id')->fetchAll());
        }
        $stmt = $pdo->query(
            'SELECT u.user_id, u.username, u.email, u.full_name, u.phone, u.status, u.last_login, r.role_name, r.role_id
             FROM users u JOIN roles r ON r.role_id = u.role_id
             ORDER BY u.full_name'
        );
        jsonResponse(true, $stmt->fetchAll());
        break;

    case 'POST':
        requireCsrfForWrites();
        $input = sanitizeInput(getJsonInput());
        $username = $input['username'] ?? '';
        $email = $input['email'] ?? '';
        $fullName = $input['full_name'] ?? '';
        $password = $input['password'] ?? '';
        $roleId = (int) ($input['role_id'] ?? 0);

        if ($username === '' || $fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonError('Username, full name, and a valid email are required.');
        }
        if (!in_array($roleId, [1, 2, 3], true)) {
            jsonError('A valid role is required.');
        }
        if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            jsonError('Password must be at least 8 characters and include an uppercase letter and a number.');
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (username, email, password_hash, full_name, phone, role_id, status)
                 VALUES (:u,:e,:p,:f,:ph,:r,"active")'
            );
            $stmt->execute([
                'u' => $username, 'e' => $email,
                'p' => password_hash($password, PASSWORD_BCRYPT),
                'f' => $fullName, 'ph' => $input['phone'] ?? null, 'r' => $roleId,
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') jsonError('That username or email is already taken.', 409);
            throw $e;
        }
        jsonResponse(true, ['user_id' => $pdo->lastInsertId()], 'User account created.', 201);
        break;

    case 'PUT':
        requireCsrfForWrites();
        $input = sanitizeInput(getJsonInput());
        $id = (int) ($input['user_id'] ?? 0);
        if (!$id) jsonError('user_id is required.');

        $fullName = $input['full_name'] ?? '';
        $email = $input['email'] ?? '';
        $roleId = (int) ($input['role_id'] ?? 0);
        $status = in_array($input['status'] ?? '', ['active', 'inactive'], true) ? $input['status'] : 'active';

        if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($roleId, [1, 2, 3], true)) {
            jsonError('Valid full name, email, and role are required.');
        }
        if ($id === $admin['user_id'] && $status === 'inactive') {
            jsonError('You cannot deactivate your own account.');
        }

        $stmt = $pdo->prepare(
            'UPDATE users SET full_name=:f, email=:e, phone=:ph, role_id=:r, status=:s WHERE user_id=:id'
        );
        $stmt->execute([
            'f' => $fullName, 'e' => $email, 'ph' => $input['phone'] ?? null,
            'r' => $roleId, 's' => $status, 'id' => $id,
        ]);

        // Optional password reset by an admin
        if (!empty($input['new_password'])) {
            $newPass = $input['new_password'];
            if (strlen($newPass) < 8 || !preg_match('/[A-Z]/', $newPass) || !preg_match('/[0-9]/', $newPass)) {
                jsonError('New password must be at least 8 characters and include an uppercase letter and a number.');
            }
            $pdo->prepare('UPDATE users SET password_hash=:h, failed_login_attempts=0, locked_until=NULL WHERE user_id=:id')
                ->execute(['h' => password_hash($newPass, PASSWORD_BCRYPT), 'id' => $id]);
        }

        jsonResponse(true, null, 'User updated.');
        break;

    case 'DELETE':
        requireCsrfForWrites();
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) jsonError('id is required.');
        if ($id === $admin['user_id']) jsonError('You cannot delete your own account.');
        $pdo->prepare('DELETE FROM users WHERE user_id = :id')->execute(['id' => $id]);
        jsonResponse(true, null, 'User deleted.');
        break;

    default:
        jsonError('Method not allowed', 405);
}
