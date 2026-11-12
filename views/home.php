<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Оставьте заявку, чтобы обсудить проект с командой «Спектр».">
    <title>Обсудим ваш проект — Спектр</title>
    <link rel="stylesheet" href="/assets/style.css">
    <script src="/assets/form.js" defer></script>
</head>
<body class="home">
    <header class="site-header shell">
        <a class="brand" href="/" aria-label="Спектр, главная"><span class="brand-icon" aria-hidden="true">✳</span> СПЕКТР</a>
        <a class="quiet-link" href="/admin/requests">Панель заявок <span aria-hidden="true">↗</span></a>
    </header>

    <main class="shell home-layout">
        <section class="intro" aria-labelledby="intro-title">
            <h1 id="intro-title">Обсудим<br>ваш проект<span>.</span></h1>
            <p>Расскажите, что вы хотите сделать. Мы свяжемся с вами, чтобы обсудить детали и следующие шаги.</p>
            <div class="hero-image" aria-hidden="true">
                <img src="/assets/architecture.webp" alt="" width="1200" height="640">
                <span class="image-corner">↗</span>
            </div>
        </section>

        <section class="form-card" aria-labelledby="form-title">
            <form id="request-form" method="post" action="/api/requests" novalidate>
                <div class="form-head">
                    <h2 id="form-title">Оставить заявку</h2>
                    <p>Укажите контакты, чтобы мы могли ответить.</p>
                </div>
                <div class="field">
                    <label for="name">Имя <span aria-hidden="true">*</span></label>
                    <input id="name" name="name" autocomplete="name" placeholder="Как к вам обращаться" required minlength="2" maxlength="150" aria-describedby="name-error">
                    <span id="name-error" class="field-error" hidden></span>
                </div>
                <div class="field-pair">
                    <div class="field">
                        <label for="phone">Телефон <span aria-hidden="true">*</span></label>
                        <input id="phone" name="phone" type="tel" autocomplete="tel" placeholder="+7 (___) ___-__-__" required maxlength="30" aria-describedby="phone-error">
                        <span id="phone-error" class="field-error" hidden></span>
                    </div>
                    <div class="field">
                        <label for="email">Email <span aria-hidden="true">*</span></label>
                        <input id="email" name="email" type="email" autocomplete="email" placeholder="name@example.ru" required maxlength="254" aria-describedby="email-error">
                        <span id="email-error" class="field-error" hidden></span>
                    </div>
                </div>
                <div class="field">
                    <label for="comment">Комментарий <small>Необязательно</small></label>
                    <textarea id="comment" name="comment" rows="3" maxlength="5000" placeholder="Коротко опишите задачу или задайте вопрос" aria-describedby="comment-error"></textarea>
                    <span id="comment-error" class="field-error" hidden></span>
                </div>
                <p id="form-error" class="notice notice-error" role="alert" hidden></p>
                <button class="button primary submit" id="send-request" type="submit">
                    <span>Отправить заявку</span><span aria-hidden="true">↗</span>
                </button>
                <noscript><p class="notice notice-error">Включите JavaScript, чтобы отправить заявку.</p></noscript>
            </form>
            <div id="form-success" class="success" tabindex="-1" hidden>
                <span class="success-icon" aria-hidden="true">✓</span>
                <h2>Заявка принята</h2>
                <p>Мы получили ваши контакты. Команда свяжется с вами, чтобы обсудить детали.</p>
                <button class="button outline" id="new-request" type="button">Оставить ещё заявку <span aria-hidden="true">↗</span></button>
            </div>
        </section>
    </main>

    <footer class="site-footer shell">© СПЕКТР</footer>
</body>
</html>
