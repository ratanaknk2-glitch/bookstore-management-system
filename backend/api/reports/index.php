<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
requireRole(['Admin', 'Manager']);
$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Method not allowed', 405);

$type = $_GET['type'] ?? '';
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');

switch ($type) {

    case 'inventory':
        $data = $pdo->query(
            'SELECT b.isbn, b.title, a.author_name, c.category_name, b.quantity, b.purchase_price,
                    b.selling_price, b.status
             FROM books b
             LEFT JOIN authors a ON a.author_id = b.author_id
             LEFT JOIN categories c ON c.category_id = b.category_id
             ORDER BY b.title'
        )->fetchAll();
        break;

    case 'sales':
        $stmt = $pdo->prepare(
            'SELECT s.invoice_number, s.created_at, c.full_name AS customer, u.full_name AS cashier,
                    s.subtotal, s.discount_amount, s.tax_amount, s.total_amount, s.payment_method, s.status
             FROM sales s
             LEFT JOIN customers c ON c.customer_id = s.customer_id
             JOIN users u ON u.user_id = s.cashier_id
             WHERE DATE(s.created_at) BETWEEN :f AND :t
             ORDER BY s.created_at DESC'
        );
        $stmt->execute(['f' => $from, 't' => $to]);
        $data = $stmt->fetchAll();
        break;

    case 'purchase':
        $stmt = $pdo->prepare(
            'SELECT po.po_number, po.order_date, s.supplier_name, po.total_amount, po.status
             FROM purchase_orders po JOIN suppliers s ON s.supplier_id = po.supplier_id
             WHERE po.order_date BETWEEN :f AND :t ORDER BY po.order_date DESC'
        );
        $stmt->execute(['f' => $from, 't' => $to]);
        $data = $stmt->fetchAll();
        break;

    case 'customer':
        $data = $pdo->query(
            'SELECT c.full_name, c.phone, c.email, m.level_name,
                    COUNT(s.sale_id) AS total_orders, COALESCE(SUM(s.total_amount),0) AS total_spent
             FROM customers c
             LEFT JOIN membership_levels m ON m.membership_id = c.membership_id
             LEFT JOIN sales s ON s.customer_id = c.customer_id AND s.status = "Completed"
             GROUP BY c.customer_id ORDER BY total_spent DESC'
        )->fetchAll();
        break;

    case 'supplier':
        $data = $pdo->query(
            'SELECT s.supplier_name, s.phone, s.email, COUNT(po.purchase_id) AS total_orders,
                    COALESCE(SUM(po.total_amount),0) AS total_purchased
             FROM suppliers s
             LEFT JOIN purchase_orders po ON po.supplier_id = s.supplier_id
             GROUP BY s.supplier_id ORDER BY total_purchased DESC'
        )->fetchAll();
        break;

    case 'author':
        $data = $pdo->query(
            'SELECT a.author_name, a.nationality, COUNT(b.book_id) AS total_books,
                    COALESCE(SUM(b.quantity),0) AS total_stock
             FROM authors a LEFT JOIN books b ON b.author_id = a.author_id
             GROUP BY a.author_id ORDER BY a.author_name'
        )->fetchAll();
        break;

    case 'revenue':
        $stmt = $pdo->prepare(
            'SELECT DATE(created_at) AS day, COALESCE(SUM(total_amount),0) AS revenue
             FROM sales WHERE DATE(created_at) BETWEEN :f AND :t AND status="Completed"
             GROUP BY DATE(created_at) ORDER BY day'
        );
        $stmt->execute(['f' => $from, 't' => $to]);
        $data = $stmt->fetchAll();
        break;

    case 'profit_loss':
        $stmt = $pdo->prepare(
            'SELECT DATE(s.created_at) AS day,
                    SUM(si.subtotal) AS revenue,
                    SUM(si.quantity * b.purchase_price) AS cost,
                    SUM(si.subtotal) - SUM(si.quantity * b.purchase_price) AS profit
             FROM sale_items si
             JOIN sales s ON s.sale_id = si.sale_id AND s.status = "Completed"
             JOIN books b ON b.book_id = si.book_id
             WHERE DATE(s.created_at) BETWEEN :f AND :t
             GROUP BY DATE(s.created_at) ORDER BY day'
        );
        $stmt->execute(['f' => $from, 't' => $to]);
        $data = $stmt->fetchAll();
        break;

    case 'low_stock':
        $data = $pdo->query('SELECT * FROM view_low_stock')->fetchAll();
        break;

    case 'best_selling':
        $data = $pdo->query('SELECT * FROM view_best_sellers LIMIT 50')->fetchAll();
        break;

    default:
        jsonError('Unknown report type. Valid types: inventory, sales, purchase, customer, supplier, author, revenue, profit_loss, low_stock, best_selling.');
}

jsonResponse(true, $data);
