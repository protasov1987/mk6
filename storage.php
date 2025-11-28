<?php
require_once __DIR__ . '/helpers.php';

function fetch_state(PDO $pdo): array
{
    $stmt = $pdo->prepare('SELECT data FROM app_state WHERE id = 1');
    $stmt->execute();
    $row = $stmt->fetchColumn();
    if ($row === false) {
        $default = build_default_data();
        save_state($pdo, $default);
        return $default;
    }
    $decoded = json_decode($row, true);
    if (
        !is_array($decoded)
        || !array_key_exists('cards', $decoded)
        || !array_key_exists('ops', $decoded)
        || !array_key_exists('centers', $decoded)
    ) {
        $decoded = build_default_data();
        save_state($pdo, $decoded);
    }
    return $decoded;
}

function save_state(PDO $pdo, array $state): void
{
    $json = json_encode($state, JSON_UNESCAPED_UNICODE);
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $stmt = $pdo->prepare('INSERT INTO app_state (id, data, updated_at) VALUES (1, :data, CURRENT_TIMESTAMP) ON CONFLICT(id) DO UPDATE SET data = excluded.data, updated_at = CURRENT_TIMESTAMP');
    } else {
        $stmt = $pdo->prepare('INSERT INTO app_state (id, data) VALUES (1, :data) ON DUPLICATE KEY UPDATE data = VALUES(data), updated_at = CURRENT_TIMESTAMP');
    }
    $stmt->execute(['data' => $json]);
}
