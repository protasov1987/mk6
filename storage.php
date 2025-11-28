<?php
require_once __DIR__ . '/helpers.php';

function ensure_state_table(PDO $pdo): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $pdo->exec('CREATE TABLE IF NOT EXISTS app_state (id INTEGER PRIMARY KEY, data TEXT NOT NULL, version INTEGER NOT NULL DEFAULT 1)');
        ensure_version_column($pdo, $driver);
        return;
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_state (id TINYINT UNSIGNED PRIMARY KEY, data LONGTEXT NOT NULL, version INT UNSIGNED NOT NULL DEFAULT 1)");
    ensure_version_column($pdo, $driver);
}

function ensure_version_column(PDO $pdo, string $driver): void
{
    if ($driver === 'sqlite') {
        $columns = $pdo->query("PRAGMA table_info(app_state)")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            if (($column['name'] ?? '') === 'version') {
                return;
            }
        }
        $pdo->exec('ALTER TABLE app_state ADD COLUMN version INTEGER NOT NULL DEFAULT 1');
        return;
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM app_state LIKE 'version'");
    if ($stmt->fetch()) {
        return;
    }
    $pdo->exec('ALTER TABLE app_state ADD COLUMN version INT UNSIGNED NOT NULL DEFAULT 1');
}

function fetch_state(PDO $pdo): array
{
    ensure_state_table($pdo);
    $stmt = $pdo->prepare('SELECT data, version FROM app_state WHERE id = 1');
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        $default = build_default_data();
        $default['version'] = 1;
        save_state($pdo, $default, 1);
        return $default;
    }

    $decoded = json_decode($row['data'], true);
    if (!is_array($decoded)) {
        $decoded = build_default_data();
    }

    $version = isset($row['version']) ? (int)$row['version'] : 1;
    if ($version <= 0) {
        $version = 1;
        save_state($pdo, $decoded, $version);
    }

    $decoded['version'] = $version;
    return $decoded;
}

function save_state(PDO $pdo, array $state, ?int $version = null): int
{
    $payload = $state;
    unset($payload['version']);
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    ensure_state_table($pdo);
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $version = $version ?? 1;
    if ($driver === 'sqlite') {
        $stmt = $pdo->prepare('INSERT INTO app_state (id, data, version) VALUES (1, :data, :version) ON CONFLICT(id) DO UPDATE SET data = excluded.data, version = excluded.version');
    } else {
        $stmt = $pdo->prepare('INSERT INTO app_state (id, data, version) VALUES (1, :data, :version) ON DUPLICATE KEY UPDATE data = VALUES(data), version = VALUES(version)');
    }
    $stmt->execute(['data' => $json, 'version' => $version]);

    return $version;
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
