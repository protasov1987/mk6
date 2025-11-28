<?php
require_once __DIR__ . '/storage.php';

require_auth(false);

$id = $_GET['id'] ?? '';
if (!$id) {
    http_response_code(400);
    echo 'Missing file id';
    exit;
}

$data = fetch_state($pdo);
$attachment = null;
foreach ($data['cards'] ?? [] as $card) {
    foreach ($card['attachments'] ?? [] as $file) {
        if (($file['id'] ?? '') === $id) {
            $attachment = $file;
            break 2;
        }
    }
}

if (!$attachment || empty($attachment['content'])) {
    http_response_code(404);
    echo 'File not found';
    exit;
}

$payload = extract_base64_payload((string)$attachment['content']);
$binary = base64_decode($payload);
if ($binary === false) {
    http_response_code(500);
    echo 'Corrupted file';
    exit;
}
$size = strlen($binary);
if ($size > ATTACHMENT_MAX_BYTES) {
    http_response_code(400);
    echo 'File too large';
    exit;
}

try {
    $filename = sanitize_filename($attachment['name'] ?? 'file');
    $type = resolve_attachment_type($filename, $attachment['type'] ?? null);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo $e->getMessage();
    exit;
}
header('Content-Type: ' . $type);
header('Content-Length: ' . $size);
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $binary;
