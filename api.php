<?php
require_once __DIR__ . '/storage.php';

header('Content-Type: application/json; charset=utf-8');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

require_auth();

function log_validation_issue(string $message): void
{
    $formatted = '[api validation] ' . $message;
    $logFile = __DIR__ . '/api_validation.log';
    error_log($formatted);
    file_put_contents($logFile, date('c') . ' ' . $formatted . PHP_EOL, FILE_APPEND);
}

function validate_string_field($value, string $field, int $maxLength, bool $allowEmpty = true): ?string
{
    if (!is_string($value)) {
        return "Поле {$field} должно быть строкой";
    }
    if (!$allowEmpty && $value === '') {
        return "Поле {$field} не может быть пустым";
    }
    if (mb_strlen($value, 'UTF-8') > $maxLength) {
        return "Поле {$field} превышает максимально допустимую длину {$maxLength}";
    }
    return null;
}

function validate_base64_content($value, string $field, int $maxBytes): ?string
{
    if (!is_string($value)) {
        return "Поле {$field} должно быть строкой";
    }
    $decoded = base64_decode($value, true);
    if ($decoded === false) {
        return "Поле {$field} должно быть корректной строкой base64";
    }
    if (strlen($decoded) > $maxBytes) {
        return "Поле {$field} превышает максимально допустимый размер";
    }
    return null;
}

function validate_payload(array $payload): ?string
{
    if (!array_key_exists('version', $payload)) {
        return 'Поле version обязательно для отправки состояния';
    }
    if (!is_int($payload['version']) || $payload['version'] < 1) {
        return 'Поле version должно быть положительным целым числом';
    }

    $cards = $payload['cards'] ?? [];
    $ops = $payload['ops'] ?? [];
    $centers = $payload['centers'] ?? [];

    if (!is_array($cards) || !is_array($ops) || !is_array($centers)) {
        return 'Поля cards, ops и centers должны быть массивами';
    }

    if (count($cards) > 500) {
        return 'Слишком много карт в запросе';
    }
    if (count($ops) > 500) {
        return 'Слишком много операций в запросе';
    }
    if (count($centers) > 200) {
        return 'Слишком много рабочих центров в запросе';
    }

    foreach ($centers as $idx => $center) {
        if (!is_array($center)) {
            return "Центр #{$idx} имеет неверный формат";
        }
        if (isset($center['id']) && ($error = validate_string_field($center['id'], 'centers.id', 120, false))) {
            return $error;
        }
        if (isset($center['name']) && ($error = validate_string_field($center['name'], 'centers.name', 255, false))) {
            return $error;
        }
        if (isset($center['desc']) && ($error = validate_string_field($center['desc'], 'centers.desc', 2000))) {
            return $error;
        }
    }

    foreach ($ops as $idx => $op) {
        if (!is_array($op)) {
            return "Операция #{$idx} имеет неверный формат";
        }
        if (isset($op['id']) && ($error = validate_string_field($op['id'], 'ops.id', 120, false))) {
            return $error;
        }
        if (isset($op['code']) && ($error = validate_string_field($op['code'], 'ops.code', 64, false))) {
            return $error;
        }
        if (isset($op['name']) && ($error = validate_string_field($op['name'], 'ops.name', 255, false))) {
            return $error;
        }
        if (isset($op['desc']) && ($error = validate_string_field($op['desc'], 'ops.desc', 2000))) {
            return $error;
        }
        if (isset($op['recTime']) && (!is_int($op['recTime']) || $op['recTime'] < 0)) {
            return 'Поле ops.recTime должно быть неотрицательным целым числом';
        }
    }

    foreach ($cards as $idx => $card) {
        if (!is_array($card)) {
            return "Карта #{$idx} имеет неверный формат";
        }
        if (isset($card['id']) && ($error = validate_string_field($card['id'], 'cards.id', 120, false))) {
            return $error;
        }
        if (isset($card['barcode']) && ($error = validate_string_field($card['barcode'], 'cards.barcode', 64, false))) {
            return $error;
        }
        if (isset($card['name']) && ($error = validate_string_field($card['name'], 'cards.name', 255, false))) {
            return $error;
        }
        if (isset($card['orderNo']) && ($error = validate_string_field($card['orderNo'], 'cards.orderNo', 120))) {
            return $error;
        }
        if (isset($card['desc']) && ($error = validate_string_field($card['desc'], 'cards.desc', 5000))) {
            return $error;
        }
        if (isset($card['drawing']) && ($error = validate_string_field($card['drawing'], 'cards.drawing', 255))) {
            return $error;
        }
        if (isset($card['material']) && ($error = validate_string_field($card['material'], 'cards.material', 255))) {
            return $error;
        }
        if (isset($card['status']) && ($error = validate_string_field($card['status'], 'cards.status', 50, false))) {
            return $error;
        }
        if (isset($card['archived']) && !is_bool($card['archived'])) {
            return 'Поле cards.archived должно быть булевым';
        }
        if (isset($card['quantity']) && (!is_int($card['quantity']) || $card['quantity'] < 0)) {
            return 'Поле cards.quantity должно быть неотрицательным целым числом';
        }
        if (isset($card['createdAt']) && (!is_int($card['createdAt']) || $card['createdAt'] < 0)) {
            return 'Поле cards.createdAt должно быть положительным числом';
        }

        if (isset($card['logs'])) {
            if (!is_array($card['logs'])) {
                return 'Поле cards.logs должно быть массивом';
            }
            if (count($card['logs']) > 1000) {
                return 'Слишком много логов в карте';
            }
        }

        $operations = $card['operations'] ?? [];
        if (!is_array($operations)) {
            return 'Поле cards.operations должно быть массивом';
        }
        if (count($operations) > 500) {
            return 'Слишком много операций в карте';
        }
        foreach ($operations as $opIdx => $operation) {
            if (!is_array($operation)) {
                return "Маршрутная операция #{$opIdx} имеет неверный формат";
            }
            if (isset($operation['id']) && ($error = validate_string_field($operation['id'], 'cards.operations.id', 120, false))) {
                return $error;
            }
            if (isset($operation['opId']) && ($error = validate_string_field($operation['opId'], 'cards.operations.opId', 120))) {
                return $error;
            }
            if (isset($operation['opCode']) && ($error = validate_string_field($operation['opCode'], 'cards.operations.opCode', 64))) {
                return $error;
            }
            if (isset($operation['opName']) && ($error = validate_string_field($operation['opName'], 'cards.operations.opName', 255))) {
                return $error;
            }
            if (isset($operation['centerId']) && ($error = validate_string_field($operation['centerId'], 'cards.operations.centerId', 120))) {
                return $error;
            }
            if (isset($operation['centerName']) && ($error = validate_string_field($operation['centerName'], 'cards.operations.centerName', 255))) {
                return $error;
            }
            if (isset($operation['comment']) && ($error = validate_string_field($operation['comment'], 'cards.operations.comment', 2000))) {
                return $error;
            }
        }

        $attachments = $card['attachments'] ?? [];
        if (!is_array($attachments)) {
            return 'Поле cards.attachments должно быть массивом';
        }
        if (count($attachments) > 50) {
            return 'Слишком много вложений у карты';
        }
        foreach ($attachments as $fileIdx => $file) {
            if (!is_array($file)) {
                return "Вложение #{$fileIdx} имеет неверный формат";
            }
            if (isset($file['id']) && ($error = validate_string_field($file['id'], 'cards.attachments.id', 120, false))) {
                return $error;
            }
            if (isset($file['name']) && ($error = validate_string_field($file['name'], 'cards.attachments.name', 255, false))) {
                return $error;
            }
            if (isset($file['type']) && ($error = validate_string_field($file['type'], 'cards.attachments.type', 150))) {
                return $error;
            }
            if (isset($file['size']) && (!is_int($file['size']) || $file['size'] < 0)) {
                return 'Поле cards.attachments.size должно быть неотрицательным целым числом';
            }
            if (isset($file['content']) && ($error = validate_base64_content($file['content'], 'cards.attachments.content', ATTACHMENT_MAX_BYTES))) {
                return $error;
            }
        }
    }

    return null;
}

try {
    if ($method === 'GET') {
        $state = fetch_state($pdo);
        ensure_operation_codes($state);
        echo json_encode($state, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST') {
        validate_csrf();
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            http_response_code(400);
            echo json_encode(['error' => 'Некорректный JSON']);
            exit;
        }
        $error = validate_payload($payload);
        if ($error !== null) {
            http_response_code(400);
            log_validation_issue($error);
            echo json_encode(['error' => $error]);
            exit;
        }
        $current = fetch_state($pdo);
        $incomingVersion = $payload['version'] ?? 0;
        if ($incomingVersion !== ($current['version'] ?? 0)) {
            http_response_code(409);
            echo json_encode(['error' => 'Данные устарели, перезагрузите страницу', 'expectedVersion' => $current['version'] ?? null]);
            exit;
        }
        $incoming = [
            'cards' => $payload['cards'] ?? [],
            'ops' => $payload['ops'] ?? [],
            'centers' => $payload['centers'] ?? [],
        ];
        $incoming = merge_snapshots($current, $incoming);
        ensure_operation_codes($incoming);
        $newVersion = ($current['version'] ?? 0) + 1;
        $incoming['version'] = $newVersion;
        save_state($pdo, $incoming, $newVersion);
        echo json_encode(['status' => 'ok', 'version' => $newVersion]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Метод не поддерживается']);
} catch (SnapshotConflictException $e) {
    http_response_code(409);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
