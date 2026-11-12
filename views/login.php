<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход — Спектр</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="login">
    <header class="site-header shell">
        <a class="brand" href="/"><span class="brand-icon" aria-hidden="true">✳</span> СПЕКТР</a>
        <a class="quiet-link" href="/">Форма заявки <span aria-hidden="true">↗</span></a>
    </header>
    <main class="login-main shell">
        <section class="form-card" aria-labelledby="login-title">
            <div class="form-head">
                <h1 id="login-title">Панель заявок</h1>
                <p>Войдите, чтобы управлять доставкой в Битрикс24.</p>
            </div>
            <?php if (isset($message)): ?><p class="notice notice-error" role="alert"><?= e($message) ?></p><?php endif; ?>
            <form method="post" action="/admin/login">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                <div class="field">
                    <label for="username">Логин</label>
                    <input id="username" name="username" autocomplete="username" required maxlength="150">
                </div>
                <div class="field">
                    <label for="password">Пароль</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required maxlength="256">
                </div>
                <button class="button primary submit">Войти <span aria-hidden="true">↗</span></button>
            </form>
        </section>
    </main>
</body>
</html>
