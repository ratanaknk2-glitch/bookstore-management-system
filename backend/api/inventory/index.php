<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$user = requireRole(['Admin', 'Manager']);
$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // history for one book, or recent global history
        $bookId = $_GET['book_id'] ?? null;
        $sql = 'SELECT it.*, b.title, u.full_name AS performed_by_name
                FROM inventory_transactions it
                JOIN books b ON b.book_id = it.book_id
                LEFT JOIN users u ON u.user_id = it.performed_by';
        $params = [];
        if ($bookId) {
            $sql .= ' WHERE it.book_id = :bid';
            $params['bid'] = (int) $bookId;
        }
        $sql .= ' ORDER BY it.created_at DESC LIMIT 200';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        jsonResponse(true, $stmt->fetchAll());
        break;

    case 'POST':
        // Manual stock in / out / adjustment (physical stock check correction)
        requireCsrfForWrites();
        $input = sanitizeInput(getJsonInput());
        $bookId = (int) ($input['book_id'] ?? 0);
        $type = $input['type'] ?? '';
        $qty = (int) ($input['quantity'] ?? 0);
        $notes = $input['notes'] ?? null;

        if (!$bookId || !in_array($type, ['Stock In', 'Stock Out', 'Adjustment'], true) || $qty === 0) {
            jsonError('book_id, valid type, and non-zero quantity are required.');
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT quantity FROM books WHERE book_id = :id FOR UPDATE');
            $stmt->execute(['id' => $bookId]);
            $book = $stmt->fetch();
            if (!$book) throw new RuntimeException('Book not found.');

            if ($type === 'Stock In') {
                $newQty = $book['quantity'] + $qty;
            } elseif ($type === 'Stock Out') {
                if ($book['quantity'] < $qty) throw new RuntimeException('Insufficient stock.');
                $newQty = $book['quantity'] - $qty;
            } else { // Adjustment - set to exact quantity given
                $newQty = $qty;
            }

            $pdo->prepare('UPDATE books SET quantity = :q, status = IF(:q2 <= 0, "Out of Stock", "Available") WHERE book_id = :id')
                ->execute(['q' => $newQty, 'q2' => $newQty, 'id' => $bookId]);

            $pdo->prepare(
                'INSERT INTO inventory_transactions (book_id, type, quantity, reference, notes, performed_by)
                 VALUES (:bid,:type,:q,"Manual",:notes,:uid)'
            )->execute(['bid' => $bookId, 'type' => $type, 'q' => $qty, 'notes' => $notes, 'uid' => $user['user_id']]);

            $pdo->commit();
            jsonResponse(true, ['new_quantity' => $newQty], 'Inventory updated.');
        } catch (Throwable $e) {
            $pdo->rollBack();
            jsonError($e->getMessage(), 400);
        }
        break;

    default:
        jsonError('Method not allowed', 405);
}
