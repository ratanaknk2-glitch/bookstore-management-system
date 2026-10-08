<?php
/**
 * Renders a printable receipt as a REAL page (not a JSON API response), so the
 * browser navigates to an actual URL - this avoids the "about:blank" address
 * bar issue that happens when a receipt is built client-side with document.write().
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
requireLogin();

// bootstrap.php's sendSecurityHeaders() sets Content-Type: application/json and a
// strict CSP by default for API endpoints - override both here since this endpoint
// renders a real HTML page with its own inline <style> and the auto-print <script>.
header('Content-Type: text/html; charset=utf-8');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline';");

$pdo = getDBConnection();
$id = (int) ($_GET['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    die('Missing sale id.');
}

$stmt = $pdo->prepare('SELECT * FROM sales WHERE sale_id = :id');
$stmt->execute(['id' => $id]);
$sale = $stmt->fetch();

if (!$sale) {
    http_response_code(404);
    die('Sale not found.');
}

$dateStr = date('m/d/Y, H:i:s', strtotime($sale['created_at']));

// Store branding for the receipt header - pulled live from Settings so it stays
// in sync automatically whenever the store info or logo is updated.
$settingsRows = $pdo->query(
    "SELECT setting_key, setting_value FROM settings
     WHERE setting_key IN ('store_name','store_logo','store_phone','store_email','store_address','receipt_footer_message')"
)->fetchAll();
$settings = [];
foreach ($settingsRows as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$storeName = $settings['store_name'] ?? 'Book Store';
$storeLogo = $settings['store_logo'] ?? '';
$storePhone = $settings['store_phone'] ?? '';
$storeEmail = $settings['store_email'] ?? '';
$storeAddress = $settings['store_address'] ?? '';
$footerMessage = $settings['receipt_footer_message'] ?? 'Thank you for your purchase!';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt <?= escapeOutput($sale['invoice_number']) ?></title>
<style>
  body{font-family:monospace;background:#e9eef2;margin:0;padding:32px 0;}
  .receipt{max-width:380px;margin:0 auto;background:#fff;padding:16px;border:1px solid #ccc;}
  .line{border-top:1px dashed #999;margin:8px 0;}
  .right{text-align:right;}
  .store-header{display:flex;align-items:flex-start;gap:10px;margin-bottom:12px;}
  .store-header img{width:48px;height:48px;object-fit:contain;flex-shrink:0;}
  .store-header .store-info{font-size:11px;line-height:1.5;}
  .store-header .store-name{font-size:14px;font-weight:bold;margin-bottom:2px;}
  @media print { body{background:#fff;padding:0;} .receipt{border:none;margin:0;max-width:none;} }
</style>
</head>
<body>
  <div class="receipt">
    <div class="store-header">
      <?php if ($storeLogo): ?>
        <img src="../../uploads/logo/<?= escapeOutput($storeLogo) ?>" alt="Logo">
      <?php endif; ?>
      <div class="store-info">
        <div class="store-name"><?= escapeOutput($storeName) ?></div>
        <?php if ($storePhone): ?><div><?= escapeOutput($storePhone) ?></div><?php endif; ?>
        <?php if ($storeEmail): ?><div><?= escapeOutput($storeEmail) ?></div><?php endif; ?>
        <?php if ($storeAddress): ?><div><?= escapeOutput($storeAddress) ?></div><?php endif; ?>
      </div>
    </div>
    <h3 style="text-align:center;">Book Store Receipt</h3>
    <p>Invoice: <?= escapeOutput($sale['invoice_number']) ?></p>
    <p>Date: <?= escapeOutput($dateStr) ?></p>
    <div class="line"></div>
    <p class="right">Subtotal: $<?= number_format((float) $sale['subtotal'], 2) ?></p>
    <p class="right">Discount: -$<?= number_format((float) $sale['discount_amount'], 2) ?></p>
    <p class="right">Tax: $<?= number_format((float) $sale['tax_amount'], 2) ?></p>
    <p class="right"><b>Total: $<?= number_format((float) $sale['total_amount'], 2) ?></b></p>
    <p class="right">Change: $<?= number_format((float) $sale['change_due'], 2) ?></p>
    <div class="line"></div>
    <p style="text-align:center;"><?= escapeOutput($footerMessage) ?></p>
  </div>
  <script>
    // Auto-close this tab once the print dialog is dismissed (printed OR cancelled),
    // so the person never has to look at the plain page underneath it.
    window.onafterprint = function () { window.close(); };
    window.print();
  </script>
</body>
</html>
