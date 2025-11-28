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
