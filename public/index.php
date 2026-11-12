<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (PHP_SAPI === 'cli-server' && $method === 'GET'
    && is_string($path) && preg_match('~\A/assets/[a-z0-9-]+\.(css|js|webp)\z~', $path)
    && is_file(__DIR__ . $path)) {
    return false;
}

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
header('Cache-Control: no-store');
header('Content-Type: text/html; charset=utf-8');

try {
    if ($path === '/' && $method === 'GET') {
        require dirname(__DIR__) . '/views/home.php';
        exit;
    }
    if ($path === '/health' && $method === 'GET') {
        foreach (['leads_requests', 'delivery_attempts', 'rate_limits'] as $table) {
            database()->query("SELECT 1 FROM $table LIMIT 1");
        }
        jsonResponse(200, ['status' => 'ok']);
    }
    if ($path === '/api/requests') {
        if ($method !== 'POST') {
            header('Allow: POST');
            jsonResponse(405, ['message' => 'Используйте POST.']);
        }
        require dirname(__DIR__) . '/src/submit.php';
        submitRequest();
    }
    if (is_string($path) && ($path === '/admin' || str_starts_with($path, '/admin/'))) {
        require dirname(__DIR__) . '/src/admin.php';
        handleAdmin($method, $path);
    }
    http_response_code(404);
    require dirname(__DIR__) . '/views/error.php';
} catch (Throwable $error) {
    error_log('SPEKTR application failure: ' . get_class($error));
    if ($path === '/health') {
        jsonResponse(503, ['status' => 'unavailable']);
    }
    if ($path === '/api/requests') {
        jsonResponse(503, ['message' => 'Сервис временно недоступен. Попробуйте отправить заявку ещё раз.']);
    }
    http_response_code(503);
    require dirname(__DIR__) . '/views/error.php';
}
