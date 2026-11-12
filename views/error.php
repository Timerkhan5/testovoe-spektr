<?php
$status = http_response_code();
$message = match ($status) {
    403 => 'Доступ запрещён',
    404 => 'Страница не найдена',
    405 => 'Действие недоступно',
    default => 'Сервис временно недоступен',
};
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $message ?> — Спектр</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <main class="login-main shell">
        <section class="form-card">
            <div class="form-head"><h1><?= $message ?></h1></div>
            <a class="button outline" href="/">На главную <span aria-hidden="true">↗</span></a>
        </section>
    </main>
</body>
</html>
