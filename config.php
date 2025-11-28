<?php
// Общая конфигурация приложения
session_start();

$auth_token = getenv('AUTH_TOKEN') ?: null;
if (!$auth_token) {
    $_SESSION['auth'] = true;
} else {
    $incomingToken = $_GET['token'] ?? ($_POST['token'] ?? null);
    if ($incomingToken && hash_equals($auth_token, $incomingToken)) {
        $_SESSION['auth'] = true;
    } else {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if ($header && preg_match('/Bearer\s+(.*)/i', $header, $matches)) {
            $incomingToken = trim($matches[1]);
        } elseif (isset($_SERVER['HTTP_X_AUTH_TOKEN'])) {
            $incomingToken = trim((string)$_SERVER['HTTP_X_AUTH_TOKEN']);
        }

        if ($incomingToken && hash_equals($auth_token, $incomingToken)) {
            $_SESSION['auth'] = true;
        }
    }
}

function require_auth(bool $asJson = true): void
{
    global $auth_token;

    if (!empty($_SESSION['auth'])) {
        return;
    }

    http_response_code(401);
    if ($asJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Unauthorized']);
    } else {
        echo 'Unauthorized';
    }
    exit;
}

function get_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function validate_csrf(bool $asJson = true): void
{
    $expected = get_csrf_token();
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');

    if ($expected && $provided && hash_equals($expected, (string)$provided)) {
        return;
    }

    http_response_code(403);
    if ($asJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'CSRF validation failed']);
    } else {
        echo 'CSRF validation failed';
    }
    exit;
}

// Настройки базы данных для Timeweb
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_name = getenv('DB_NAME') ?: 'cc226439_bd';
$db_user = getenv('DB_USER') ?: 'cc226439_bd';
$db_pass = getenv('DB_PASS') ?: '12345';
$db_port = getenv('DB_PORT') ?: '3306';

$dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    die('Не удалось подключиться к базе данных: ' . htmlspecialchars($e->getMessage()));
}

date_default_timezone_set('Europe/Moscow');
