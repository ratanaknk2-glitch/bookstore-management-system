<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

rateLimit('login', 10, 60); // max 10 attempts per minute per IP

$input = sanitizeInput(getJsonInput());
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

if ($username === '' || $password === '') {
    jsonError('Username and password are required.');
}

$pdo = getDBConnection();
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

$stmt = $pdo->prepare(
    'SELECT u.user_id, u.username, u.password_hash, u.full_name, u.status,
            u.failed_login_attempts, u.locked_until, r.role_name
     FROM users u JOIN roles r ON r.role_id = u.role_id
     WHERE u.username = :username LIMIT 1'
);
$stmt->execute(['username' => $username]);
$user = $stmt->fetch();

$logStmt = $pdo->prepare(
    'INSERT INTO login_logs (user_id, username_attempt, ip_address, user_agent, status)
     VALUES (:uid, :uname, :ip, :ua, :status)'
);

if (!$user) {
    $logStmt->execute(['uid' => null, 'uname' => $username, 'ip' => $ip, 'ua' => $ua, 'status' => 'failed']);
    jsonError('Invalid username or password.', 401);
}

// Account lockout check
if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
    $minutesLeft = ceil((strtotime($user['locked_until']) - time()) / 60);
    jsonError("Account temporarily locked. Try again in {$minutesLeft} minute(s).", 423);
}

if ($user['status'] !== 'active') {
    jsonError('This account has been deactivated. Contact an administrator.', 403);
}

if (!password_verify($password, $user['password_hash'])) {
    $attempts = $user['failed_login_attempts'] + 1;
    $lockedUntil = null;
    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
        $lockedUntil = date('Y-m-d H:i:s', time() + LOCKOUT_MINUTES * 60);
        $attempts = 0;
    }
    $upd = $pdo->prepare('UPDATE users SET failed_login_attempts = :a, locked_until = :l WHERE user_id = :id');
    $upd->execute(['a' => $attempts, 'l' => $lockedUntil, 'id' => $user['user_id']]);

    $logStmt->execute(['uid' => $user['user_id'], 'uname' => $username, 'ip' => $ip, 'ua' => $ua, 'status' => 'failed']);
    jsonError('Invalid username or password.', 401);
}

// Success: reset attempts, rotate session ID (prevents session fixation)
$pdo->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE user_id = :id')
    ->execute(['id' => $user['user_id']]);

session_regenerate_id(true);
$_SESSION['user_id']       = $user['user_id'];
$_SESSION['username']      = $user['username'];
$_SESSION['role']          = $user['role_name'];
$_SESSION['full_name']     = $user['full_name'];
$_SESSION['last_activity'] = time();

$logStmt->execute(['uid' => $user['user_id'], 'uname' => $username, 'ip' => $ip, 'ua' => $ua, 'status' => 'success']);

jsonResponse(true, [
    'user_id'    => $user['user_id'],
    'username'   => $user['username'],
    'full_name'  => $user['full_name'],
    'role'       => $user['role_name'],
    'csrf_token' => generateCsrfToken(),
], 'Login successful.');
