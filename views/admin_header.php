<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> — Спектр</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="admin">
    <header class="site-header shell">
        <a class="brand" href="/admin/requests"><span class="brand-icon" aria-hidden="true">✳</span> СПЕКТР</a>
        <nav aria-label="Панель заявок">
            <a class="quiet-link" href="/">Форма заявки <span aria-hidden="true">↗</span></a>
            <form action="/admin/logout" method="post">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                <button type="submit" class="button outline">Выйти</button>
            </form>
        </nav>
    </header>
