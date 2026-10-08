<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$user = requireLogin();
$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    case 'GET':
        if (!empty($_GET['id'])) {
            $id = (int) $_GET['id'];
            $stmt = $pdo->prepare('SELECT s.*, c.full_name AS customer_name, u.full_name AS cashier_name
                FROM sales s
                LEFT JOIN customers c ON c.customer_id = s.customer_id
                JOIN users u ON u.user_id = s.cashier_id
                WHERE s.sale_id = :id');
            $stmt->execute(['id' => $id]);
            $sale = $stmt->fetch();
            if (!$sale) jsonError('Sale not found.', 404);

            $items = $pdo->prepare('SELECT si.*, b.title, b.isbn FROM sale_items si
                JOIN books b ON b.book_id = si.book_id WHERE si.sale_id = :id');
            $items->execute(['id' => $id]);
            $sale['items'] = $items->fetchAll();
            jsonResponse(true, $sale);
        }

        // List sales (with optional date filter)
        $from = $_GET['from'] ?? date('Y-m-d');
        $to = $_GET['to'] ?? date('Y-m-d');
        $stmt = $pdo->prepare(
            'SELECT s.sale_id, s.invoice_number, s.total_amount, s.status, s.payment_method, s.created_at,
                    c.full_name AS customer_name, u.full_name AS cashier_name
             FROM sales s
             LEFT JOIN customers c ON c.customer_id = s.customer_id
             JOIN users u ON u.user_id = s.cashier_id
             WHERE DATE(s.created_at) BETWEEN :f AND :t
             ORDER BY s.created_at DESC'
        );
        $stmt->execute(['f' => $from, 't' => $to]);
        jsonResponse(true, $stmt->fetchAll());
        break;

    case 'POST':
        requireCsrfForWrites();
        $input = sanitizeInput(getJsonInput());
        $cart = $input['items'] ?? [];
        if (empty($cart) || !is_array($cart)) jsonError('Cart is empty.');

        $customerId = !empty($input['customer_id']) ? (int) $input['customer_id'] : null;
        $discountCode = trim($input['discount_code'] ?? '');
        $paymentMethod = in_array($input['payment_method'] ?? '', ['Cash','Card','Mobile'], true)
            ? $input['payment_method'] : 'Cash';
        $amountPaid = (float) ($input['amount_paid'] ?? 0);

        try {
            $pdo->beginTransaction();

            // Tax rate from settings
            $taxStmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='tax_percent'");
            $taxPercent = (float) $taxStmt->fetchColumn();

            $subtotal = 0;
            $lineItems = [];

            foreach ($cart as $line) {
                $bookId = (int) ($line['book_id'] ?? 0);
                $qty = (int) ($line['quantity'] ?? 0);
                if (!$bookId || $qty <= 0) throw new RuntimeException('Invalid cart item.');

                // Lock the row to prevent overselling under concurrent requests
                $stmt = $pdo->prepare('SELECT title, selling_price, quantity FROM books WHERE book_id = :id FOR UPDATE');
                $stmt->execute(['id' => $bookId]);
                $book = $stmt->fetch();
                if (!$book) throw new RuntimeException("Book ID $bookId not found.");
                if ($book['quantity'] < $qty) throw new RuntimeException("Insufficient stock for \"{$book['title']}\".");

                $lineSubtotal = $book['selling_price'] * $qty;
                $subtotal += $lineSubtotal;
                $lineItems[] = [
                    'book_id' => $bookId, 'quantity' => $qty,
                    'unit_price' => $book['selling_price'], 'subtotal' => $lineSubtotal,
                ];
            }

            // Discount
            $discountAmount = 0;
            $discountId = null;
            if ($discountCode !== '') {
                $dStmt = $pdo->prepare(
                    "SELECT * FROM discounts WHERE code = :c AND status='active'
                     AND (start_date IS NULL OR start_date <= CURDATE())
                     AND (end_date IS NULL OR end_date >= CURDATE())
                     AND (usage_limit IS NULL OR used_count < usage_limit)"
                );
                $dStmt->execute(['c' => $discountCode]);
                $discount = $dStmt->fetch();
                if ($discount) {
                    $discountId = $discount['discount_id'];
                    $discountAmount = $discount['type'] === 'percentage'
                        ? round($subtotal * ($discount['value'] / 100), 2)
                        : min((float) $discount['value'], $subtotal);
                    $pdo->prepare('UPDATE discounts SET used_count = used_count + 1 WHERE discount_id = :id')
                        ->execute(['id' => $discountId]);
                }
            }

            $taxable = $subtotal - $discountAmount;
            $taxAmount = round($taxable * ($taxPercent / 100), 2);
            $total = round($taxable + $taxAmount, 2);
            $changeDue = max(0, round($amountPaid - $total, 2));

            $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

            $saleStmt = $pdo->prepare(
                'INSERT INTO sales (invoice_number, customer_id, cashier_id, discount_id, subtotal,
                    discount_amount, tax_percent, tax_amount, total_amount, payment_method, amount_paid, change_due, status)
                 VALUES (:inv,:cust,:cashier,:disc,:sub,:discamt,:taxp,:taxamt,:total,:pm,:paid,:change,"Completed")'
            );
            $saleStmt->execute([
                'inv' => $invoiceNumber, 'cust' => $customerId, 'cashier' => $user['user_id'],
                'disc' => $discountId, 'sub' => $subtotal, 'discamt' => $discountAmount,
                'taxp' => $taxPercent, 'taxamt' => $taxAmount, 'total' => $total,
                'pm' => $paymentMethod, 'paid' => $amountPaid, 'change' => $changeDue,
            ]);
            $saleId = $pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                'INSERT INTO sale_items (sale_id, book_id, quantity, unit_price, subtotal) VALUES (:sid,:bid,:q,:p,:s)'
            );
            $stockStmt = $pdo->prepare('UPDATE books SET quantity = quantity - :q WHERE book_id = :id');
            $invStmt = $pdo->prepare(
                'INSERT INTO inventory_transactions (book_id, type, quantity, reference, performed_by)
                 VALUES (:bid, "Stock Out", :q, :ref, :uid)'
            );

            foreach ($lineItems as $li) {
                $itemStmt->execute(['sid' => $saleId, 'bid' => $li['book_id'], 'q' => $li['quantity'], 'p' => $li['unit_price'], 's' => $li['subtotal']]);
                $stockStmt->execute(['q' => $li['quantity'], 'id' => $li['book_id']]);
                $invStmt->execute(['bid' => $li['book_id'], 'q' => $li['quantity'], 'ref' => $invoiceNumber, 'uid' => $user['user_id']]);
            }

            // Auto flag out-of-stock books
            $pdo->exec("UPDATE books SET status='Out of Stock' WHERE quantity <= 0 AND status='Available'");

            $pdo->commit();

            jsonResponse(true, [
                'sale_id' => $saleId, 'invoice_number' => $invoiceNumber,
                'subtotal' => $subtotal, 'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount, 'total_amount' => $total, 'change_due' => $changeDue,
            ], 'Sale completed successfully.', 201);

        } catch (Throwable $e) {
            $pdo->rollBack();
            jsonError($e->getMessage() ?: 'Transaction failed.', 400);
        }
        break;

    case 'PUT':
        // Cancel a sale (restores stock)
        requireRole(['Admin', 'Manager']);
        requireCsrfForWrites();
        $input = sanitizeInput(getJsonInput());
        $saleId = (int) ($input['sale_id'] ?? 0);
        $action = $input['action'] ?? '';
        if (!$saleId || $action !== 'cancel') jsonError('sale_id and action=cancel are required.');

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT status FROM sales WHERE sale_id = :id FOR UPDATE');
            $stmt->execute(['id' => $saleId]);
            $sale = $stmt->fetch();
            if (!$sale) throw new RuntimeException('Sale not found.');
            if ($sale['status'] !== 'Completed') throw new RuntimeException('Only completed sales can be cancelled.');

            $items = $pdo->prepare('SELECT book_id, quantity FROM sale_items WHERE sale_id = :id');
            $items->execute(['id' => $saleId]);
            $restoreStmt = $pdo->prepare('UPDATE books SET quantity = quantity + :q, status="Available" WHERE book_id = :id');
            foreach ($items->fetchAll() as $it) {
                $restoreStmt->execute(['q' => $it['quantity'], 'id' => $it['book_id']]);
            }

            $pdo->prepare('UPDATE sales SET status="Cancelled" WHERE sale_id = :id')->execute(['id' => $saleId]);
            $pdo->commit();
            jsonResponse(true, null, 'Sale cancelled and stock restored.');
        } catch (Throwable $e) {
            $pdo->rollBack();
            jsonError($e->getMessage(), 400);
        }
        break;

    default:
        jsonError('Method not allowed', 405);
}
