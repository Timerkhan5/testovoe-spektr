<?php

declare(strict_types=1);

const DELIVERY_LABELS = [
    'saved' => 'Сохранена',
    'processing' => 'В обработке',
    'retry' => 'Ожидает повтора',
    'sent' => 'Отправлена',
];
const PRIORITY_LABELS = ['low' => 'Низкий', 'normal' => 'Обычный', 'high' => 'Высокий'];

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function validCsrf(): bool
{
    return isset($_SESSION['csrf'], $_POST['csrf']) && is_string($_POST['csrf'])
        && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}

function adminError(int $status): never
{
    http_response_code($status);
    require dirname(__DIR__) . '/views/error.php';
    exit;
}

function renderAdmin(string $view, array $data = []): never
{
    extract($data, EXTR_SKIP);
    require dirname(__DIR__) . '/views/' . $view . '.php';
    exit;
}

function handleAdmin(string $method, string $path): never
{
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('spektr_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/admin',
        'secure' => !localEnvironment(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();

    if (($_SESSION['active_at'] ?? 0) < time() - 1800 && isset($_SESSION['active_at'])) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));

    if ($path === '/admin/login') {
        if (isset($_SESSION['active_at'])) {
            redirect('/admin/requests');
        }
        if ($method === 'GET') {
            renderAdmin('login');
        }
        if ($method !== 'POST') {
            header('Allow: GET, POST');
            adminError(405);
        }
        if (!validCsrf()) {
            adminError(403);
        }
        $store = new Store(database());
        if (!$store->allow('login', 5, 600)) {
            http_response_code(429);
            header('Retry-After: ' . (600 - time() % 600));
            renderAdmin('login', ['message' => 'Слишком много попыток. Попробуйте снова через несколько минут.']);
        }
        $username = $_POST['username'] ?? null;
        $password = $_POST['password'] ?? null;
        $hash = config('ADMIN_PASSWORD_HASH');
        $admin = config('ADMIN_USER', 'admin');
        if ($hash === '' || !is_string($username) || !is_string($password)
            || strlen($username) > 150 || strlen($password) > 256
            || !password_verify($password, $hash) || !hash_equals($admin, $username)) {
            http_response_code(401);
            renderAdmin('login', ['message' => 'Неверный логин или пароль.']);
        }
        session_regenerate_id(true);
        $_SESSION = ['active_at' => time(), 'csrf' => bin2hex(random_bytes(32))];
        redirect('/admin/requests');
    }

    if (!isset($_SESSION['active_at'])) {
        if ($method === 'GET') {
            redirect('/admin/login');
        }
        adminError(403);
    }
    $_SESSION['active_at'] = time();
    if ($method === 'POST' && !validCsrf()) {
        adminError(403);
    }
    if ($path === '/admin/logout' && $method === 'POST') {
        $_SESSION = [];
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => '/admin',
            'secure' => !localEnvironment(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_destroy();
        redirect('/admin/login');
    }
    if ($path === '/admin' && $method === 'GET') {
        redirect('/admin/requests');
    }
    if ($path === '/admin/requests' && $method === 'GET') {
        $store = new Store(database());
        $filters = [];
        foreach (['delivery_status' => DELIVERY_LABELS, 'priority' => PRIORITY_LABELS] as $key => $options) {
            $value = $_GET[$key] ?? null;
            if (is_string($value) && isset($options[$value])) {
                $filters[$key] = $value;
            }
        }
        $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]) ?: 1;
        [$rows, $hasNext] = $store->requests($filters, $page);
        renderAdmin('requests', compact('rows', 'hasNext', 'filters', 'page') + ['counts' => $store->counts()]);
    }
    if (preg_match('~\A/admin/requests/([1-9][0-9]{0,19})(/retry)?\z~', $path, $match)) {
        $store = new Store(database());
        $id = $match[1];
        $request = $store->find($id);
        if (!$request) {
            adminError(404);
        }
        if (!isset($match[2]) && $method === 'GET') {
            $flash = $_SESSION['flash'][$id] ?? null;
            unset($_SESSION['flash'][$id]);
            renderAdmin('request', compact('request', 'flash') + ['attempts' => $store->attempts($id)]);
        }
        if (isset($match[2]) && $method === 'POST') {
            session_write_close();
            ignore_user_abort(true);
            (new Delivery($store))->attempt($id);
            $current = $store->find($id);
            session_start();
            if (isset($_SESSION['active_at'])) {
                $_SESSION['flash'][$id] = match ($current['delivery_status'] ?? null) {
                    'sent' => 'Лид подтверждён в Битрикс24. ID ' . $current['crm_lead_id'] . '.',
                    'processing' => 'Отправка уже выполняется.',
                    'retry' => 'Доставка ожидает повтора. Подробности в истории попыток.',
                    default => 'Статус заявки изменился.',
                };
            }
            redirect('/admin/requests/' . $id);
        }
        header('Allow: ' . (isset($match[2]) ? 'POST' : 'GET'));
        adminError(405);
    }
    adminError(404);
}
