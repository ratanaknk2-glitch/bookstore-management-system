<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$user = requireLogin();
$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Auto-generate low stock / membership expiry notifications on the fly (simple polling model)
        $lowStock = $pdo->query('SELECT title, quantity FROM view_low_stock')->fetchAll();
        $expiring = $pdo->query(
            "SELECT full_name, membership_expiry FROM customers
             WHERE membership_expiry IS NOT NULL AND membership_expiry <= (CURDATE() + INTERVAL 7 DAY)"
        )->fetchAll();

        $items = [];
        foreach ($lowStock as $b) {
            $items[] = ['type' => 'Low Stock', 'message' => "\"{$b['title']}\" is low on stock ({$b['quantity']} left)."];
        }
        foreach ($expiring as $c) {
            $items[] = ['type' => 'Membership Expiry', 'message' => "{$c['full_name']}'s membership expires on {$c['membership_expiry']}."];
        }

        $stored = $pdo->prepare('SELECT * FROM notifications WHERE user_id = :uid OR user_id IS NULL ORDER BY created_at DESC LIMIT 30');
        $stored->execute(['uid' => $user['user_id']]);

        jsonResponse(true, ['live' => $items, 'stored' => $stored->fetchAll()]);
        break;

    case 'PUT':
        // mark as read
        requireCsrfForWrites();
        $input = sanitizeInput(getJsonInput());
        $id = (int) ($input['notification_id'] ?? 0);
        if (!$id) jsonError('notification_id is required.');
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE notification_id = :id')->execute(['id' => $id]);
        jsonResponse(true, null, 'Marked as read.');
        break;

    default:
        jsonError('Method not allowed', 405);
}
