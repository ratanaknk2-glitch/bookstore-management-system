<?php
/**
 * Database backup/restore. Admin only.
 * Uses mysqldump if available on the server; otherwise falls back to a PHP-based export.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
requireRole(['Admin']);

$pdo = getDBConnection();
$backupDir = __DIR__ . '/../../backups/';
if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'create') {
    requireCsrfForWrites();
    $filename = 'backup_' . date('Ymd_His') . '.sql';
    $filepath = $backupDir . $filename;

    // Pure-PHP export (portable across hosts that disable exec())
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $sql = "-- Book Store Management System backup\n-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
        $sql .= "DROP TABLE IF EXISTS `$table`;\n" . $create['Create Table'] . ";\n\n";

        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll();
        foreach ($rows as $row) {
            $cols = array_map(fn($c) => "`$c`", array_keys($row));
            $vals = array_map(function ($v) use ($pdo) {
                return $v === null ? 'NULL' : $pdo->quote((string) $v);
            }, array_values($row));
            $sql .= "INSERT INTO `$table` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n";
        }
        $sql .= "\n";
    }
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

    file_put_contents($filepath, $sql);
    jsonResponse(true, ['filename' => $filename], 'Backup created successfully.');
}

if ($action === 'list') {
    $files = array_values(array_filter(scandir($backupDir), fn($f) => str_ends_with($f, '.sql')));
    rsort($files);
    jsonResponse(true, $files);
}

if ($action === 'download') {
    $file = basename($_GET['file'] ?? ''); // basename() blocks path traversal
    $path = $backupDir . $file;
    if (!$file || !file_exists($path) || !str_ends_with($file, '.sql')) {
        jsonError('Backup file not found.', 404);
    }
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

if ($action === 'restore') {
    requireCsrfForWrites();
    $jsonInput = getJsonInput();
    $file = basename($jsonInput['file'] ?? ($_POST['file'] ?? ''));
    $path = $backupDir . $file;
    if (!$file || !file_exists($path) || !str_ends_with($file, '.sql')) {
        jsonError('Backup file not found.', 404);
    }

    $sql = file_get_contents($path);
    try {
        $pdo->exec($sql);
        jsonResponse(true, null, 'Database restored successfully.');
    } catch (PDOException $e) {
        jsonError('Restore failed: ' . $e->getMessage(), 500);
    }
}

jsonError('Invalid action. Use: create, list, download, restore.');
