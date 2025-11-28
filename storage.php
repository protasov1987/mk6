<?php
require_once __DIR__ . '/helpers.php';

function ensure_state_table(PDO $pdo): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $pdo->exec('CREATE TABLE IF NOT EXISTS app_state (id INTEGER PRIMARY KEY, data TEXT NOT NULL)');
        return;
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_state (id TINYINT UNSIGNED PRIMARY KEY, data LONGTEXT NOT NULL)");
}

function fetch_state(PDO $pdo): array
{
    ensure_state_table($pdo);
    $stmt = $pdo->prepare('SELECT data FROM app_state WHERE id = 1');
    $stmt->execute();
    $row = $stmt->fetchColumn();
    if ($row === false) {
        $default = build_default_data();
        save_state($pdo, $default);
        return $default;
    }
    $decoded = json_decode($row, true);
    if (!is_array($decoded)) {
        $decoded = build_default_data();
        save_state($pdo, $decoded);
    }
    return $decoded;
}

function save_state(PDO $pdo, array $state): void
{
    $json = json_encode($state, JSON_UNESCAPED_UNICODE);
    ensure_state_table($pdo);
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $stmt = $pdo->prepare('INSERT INTO app_state (id, data) VALUES (1, :data) ON CONFLICT(id) DO UPDATE SET data = excluded.data');
    } else {
        $stmt = $pdo->prepare('INSERT INTO app_state (id, data) VALUES (1, :data) ON DUPLICATE KEY UPDATE data = VALUES(data)');
    }
    $stmt->execute(['data' => $json]);
}

function export_state_backup(PDO $pdo, string $backupDir = __DIR__ . '/backups', int $limit = 5): string
{
    if ($limit < 1) {
        $limit = 1;
    }

    if (!is_dir($backupDir)) {
        if (!mkdir($backupDir, 0777, true) && !is_dir($backupDir)) {
            throw new RuntimeException('Не удалось создать папку для бэкапов');
        }
    }

    $state = fetch_state($pdo);
    $encoded = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $compressed = gzencode($encoded, 9);
    if ($compressed === false) {
        throw new RuntimeException('Не удалось упаковать состояние');
    }

    $microtime = microtime(true);
    $timestamp = date('Ymd_His', (int)$microtime) . '_' . sprintf('%06d', (int)(($microtime - (int)$microtime) * 1000000));
    $filename = rtrim($backupDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'state_' . $timestamp . '.json.gz';
    if (file_put_contents($filename, $compressed) === false) {
        throw new RuntimeException('Не удалось сохранить бэкап: ' . $filename);
    }

    rotate_state_backups($backupDir, $limit);

    return $filename;
}

function rotate_state_backups(string $backupDir, int $limit): void
{
    $files = glob(rtrim($backupDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'state_*.json.gz');
    if ($files === false || count($files) <= $limit) {
        return;
    }

    usort($files, static function ($a, $b) {
        return filemtime($b) <=> filemtime($a);
    });

    $toDelete = array_slice($files, $limit);
    foreach ($toDelete as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}
