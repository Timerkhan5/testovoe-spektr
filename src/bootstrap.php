<?php

declare(strict_types=1);

ini_set('display_errors', '0');
date_default_timezone_set('UTC');

function config(string $key, string $default = ''): string
{
    static $settings;
    if ($settings === null) {
        $path = dirname(__DIR__) . '/.env';
        $settings = is_file($path) ? parse_ini_file($path, false, INI_SCANNER_RAW) : [];
        if ($settings === false) {
            throw new RuntimeException('Invalid local configuration');
        }
    }
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }
    $value = $settings[$key] ?? $default;
    if (strlen($value) >= 2 && $value[0] === "'" && str_ends_with($value, "'")) {
        return substr($value, 1, -1);
    }
    return $value;
}

function localEnvironment(): bool
{
    return config('APP_ENV') === 'local';
}

function database(): PDO
{
    static $connection;
    if ($connection === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            config('DB_HOST', '127.0.0.1'),
            config('DB_PORT', '3306'),
            config('DB_NAME', 'spektr')
        );
        $connection = new PDO($dsn, config('DB_USER'), config('DB_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
        ]);
    }
    return $connection;
}

function jsonResponse(int $status, array $body): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/Store.php';
require_once __DIR__ . '/integrations.php';
require_once __DIR__ . '/Delivery.php';
