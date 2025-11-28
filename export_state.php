<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/storage.php';

$backupDir = getenv('BACKUP_DIR') ?: __DIR__ . '/backups';
$limit = (int)(getenv('BACKUP_LIMIT') ?: 5);

try {
    $path = export_state_backup($pdo, $backupDir, $limit);
    fwrite(STDOUT, "Backup created: {$path}\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Backup failed: " . $e->getMessage() . "\n");
    exit(1);
}
