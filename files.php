<?php
require_once __DIR__ . '/storage.php';

$requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? bin2hex(random_bytes(8));
header('X-Request-Id: ' . $requestId);

require_auth(false);

function log_file_issue(string $message, string $requestId): void
{
    $formatted = sprintf('[files][request:%s] %s', $requestId, $message);
    $logFile = __DIR__ . '/files_errors.log';
    error_log($formatted);
    file_put_contents($logFile, date('c') . ' ' . $formatted . PHP_EOL, FILE_APPEND);
}

$id = $_GET['id'] ?? '';
if (!$id) {
    http_response_code(400);
    log_file_issue('Missing file id', $requestId);
    echo 'Missing file id. Request ID: ' . $requestId;
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
    log_file_issue('File not found: ' . $id, $requestId);
    echo 'File not found. Request ID: ' . $requestId;
    exit;
}

$payload = extract_base64_payload((string)$attachment['content']);
$binary = base64_decode($payload);
if ($binary === false) {
    http_response_code(500);
    log_file_issue('Corrupted file content: ' . $id, $requestId);
    echo 'Corrupted file. Request ID: ' . $requestId;
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
