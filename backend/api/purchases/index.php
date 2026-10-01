<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$user = requireRole(['Admin', 'Manager']);
$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    case 'GET':
        if (!empty($_GET['id'])) {
            $id = (int) $_GET['id'];
            $stmt = $pdo->prepare('SELECT po.*, s.supplier_name FROM purchase_orders po
                JOIN suppliers s ON s.supplier_id = po.supplier_id WHERE po.purchase_id = :id');
            $stmt->execute(['id' => $id]);
            $po = $stmt->fetch();
            if (!$po) jsonError('Purchase order not found.', 404);
            $items = $pdo->prepare('SELECT poi.*, b.title, b.isbn FROM purchase_order_items poi
                JOIN books b ON b.book_id = poi.book_id WHERE poi.purchase_id = :id');
            $items->execute(['id' => $id]);
            $po['items'] = $items->fetchAll();
            jsonResponse(true, $po);
        }

$stmt = $pdo->query('SELECT po.*, s.supplier_name FROM purchase_orders po
            JOIN suppliers s ON s.supplier_id = po.supplier_id ORDER BY po.created_at DESC');
        $orders = $stmt->fetchAll();

        if ($orders) {
            $ids = array_column($orders, 'purchase_id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $itemsStmt = $pdo->prepare(
                "SELECT poi.*, b.title, b.isbn FROM purchase_order_items poi
                 JOIN books b ON b.book_id = poi.book_id
                 WHERE poi.purchase_id IN ($placeholders)"
            );
            $itemsStmt->execute($ids);
            $itemsByPO = [];
            foreach ($itemsStmt->fetchAll() as $item) {
                $itemsByPO[$item['purchase_id']][] = $item;
            }
            foreach ($orders as &$po) {
                $po['items'] = $itemsByPO[$po['purchase_id']] ?? [];
            }
            unset($po);
        }

        jsonResponse(true, $orders);
        break;

    case 'POST':
        requireCsrfForWrites();
        $input = sanitizeInput(getJsonInput());
        $supplierId = (int) ($input['supplier_id'] ?? 0);
        $items = $input['items'] ?? [];
        if (!$supplierId || empty($items)) jsonError('supplier_id and items are required.');

        try {
            $pdo->beginTransaction();
            $total = 0;
            foreach ($items as $it) {
                $total += (float) $it['unit_cost'] * (int) $it['quantity'];
            }
            $poNumber = 'PO-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $stmt = $pdo->prepare(
                'INSERT INTO purchase_orders (po_number, supplier_id, order_date, expected_date, status, total_amount, created_by)
                 VALUES (:po,:sup,CURDATE(),:exp,"Pending",:total,:uid)'
            );
            $stmt->execute([
                'po' => $poNumber, 'sup' => $supplierId,
                'exp' => $input['expected_date'] ?? null, 'total' => $total, 'uid' => $user['user_id'],
            ]);
            $purchaseId = $pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                'INSERT INTO purchase_order_items (purchase_id, book_id, quantity, unit_cost, subtotal) VALUES (:pid,:bid,:q,:c,:s)'
            );
            foreach ($items as $it) {
                $sub = (float) $it['unit_cost'] * (int) $it['quantity'];
                $itemStmt->execute([
                    'pid' => $purchaseId, 'bid' => (int) $it['book_id'],
                    'q' => (int) $it['quantity'], 'c' => (float) $it['unit_cost'], 's' => $sub,
                ]);
            }
            $pdo->commit();
            jsonResponse(true, ['purchase_id' => $purchaseId, 'po_number' => $poNumber], 'Purchase order created.', 201);
        } catch (Throwable $e) {
            $pdo->rollBack();
            jsonError('Failed to create purchase order: ' . $e->getMessage(), 400);
        }
        break;

    case 'PUT':
        requireCsrfForWrites();
        $input = sanitizeInput(getJsonInput());
        $id = (int) ($input['purchase_id'] ?? 0);
        $action = $input['action'] ?? '';
        if (!$id || !in_array($action, ['receive', 'cancel'], true)) {
            jsonError('purchase_id and a valid action (receive|cancel) are required.');
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT status FROM purchase_orders WHERE purchase_id = :id FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $po = $stmt->fetch();
            if (!$po) throw new RuntimeException('Purchase order not found.');
            if ($po['status'] !== 'Pending') throw new RuntimeException('Only pending orders can be updated.');

            if ($action === 'receive') {
                $items = $pdo->prepare('SELECT book_id, quantity FROM purchase_order_items WHERE purchase_id = :id');
                $items->execute(['id' => $id]);
                $stockStmt = $pdo->prepare('UPDATE books SET quantity = quantity + :q, status="Available" WHERE book_id = :id');
                $invStmt = $pdo->prepare(
                    'INSERT INTO inventory_transactions (book_id, type, quantity, reference, performed_by)
                     VALUES (:bid, "Stock In", :q, :ref, :uid)'
                );
                foreach ($items->fetchAll() as $it) {
                    $stockStmt->execute(['q' => $it['quantity'], 'id' => $it['book_id']]);
                    $invStmt->execute(['bid' => $it['book_id'], 'q' => $it['quantity'], 'ref' => "PO#$id", 'uid' => $user['user_id']]);
                }
                $pdo->prepare('UPDATE purchase_orders SET status="Received" WHERE purchase_id = :id')->execute(['id' => $id]);
                $pdo->commit();
                jsonResponse(true, null, 'Purchase order received. Stock updated.');
            } else {
                $pdo->prepare('UPDATE purchase_orders SET status="Cancelled" WHERE purchase_id = :id')->execute(['id' => $id]);
                $pdo->commit();
                jsonResponse(true, null, 'Purchase order cancelled.');
            }
        } catch (Throwable $e) {
            $pdo->rollBack();
            jsonError($e->getMessage(), 400);
        }
        break;

    default:
        jsonError('Method not allowed', 405);
}
