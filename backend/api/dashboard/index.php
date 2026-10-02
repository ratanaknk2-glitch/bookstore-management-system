<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
requireLogin();
$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Method not allowed', 405);

$stats = [];

$stats['total_books']      = (int) $pdo->query('SELECT COUNT(*) FROM books')->fetchColumn();
$stats['total_categories'] = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
$stats['total_customers']  = (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
$stats['total_suppliers']  = (int) $pdo->query('SELECT COUNT(*) FROM suppliers')->fetchColumn();
$stats['available_stock']  = (int) $pdo->query("SELECT COALESCE(SUM(quantity),0) FROM books WHERE status='Available'")->fetchColumn();
$stats['low_stock_count']  = (int) $pdo->query('SELECT COUNT(*) FROM view_low_stock')->fetchColumn();

$stats['today_sales'] = (float) $pdo->query(
    "SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE DATE(created_at) = CURDATE() AND status='Completed'"
)->fetchColumn();

$stats['monthly_revenue'] = (float) $pdo->query(
    "SELECT COALESCE(SUM(total_amount),0) FROM sales
     WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE()) AND status='Completed'"
)->fetchColumn();

$stats['best_selling_books'] = $pdo->query(
    'SELECT title, total_sold FROM view_best_sellers LIMIT 5'
)->fetchAll();

$stats['low_stock_items'] = $pdo->query(
    'SELECT book_id, title, isbn, quantity, reorder_level FROM view_low_stock LIMIT 10'
)->fetchAll();

// Sales chart data - last 7 days
$stmt = $pdo->query(
    "SELECT DATE(created_at) AS sale_date, COALESCE(SUM(total_amount),0) AS total
     FROM sales
     WHERE created_at >= (CURDATE() - INTERVAL 6 DAY) AND status='Completed'
     GROUP BY DATE(created_at)
     ORDER BY sale_date"
);
$rows = $stmt->fetchAll();
$byDate = [];
foreach ($rows as $r) $byDate[$r['sale_date']] = (float) $r['total'];

$chart = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $chart[] = ['date' => $d, 'total' => $byDate[$d] ?? 0];
}
$stats['sales_chart'] = $chart;

jsonResponse(true, $stats);
