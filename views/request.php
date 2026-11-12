<?php
$title = 'Заявка #' . $request['id'];
$status = $request['delivery_status'];
$running = $status === 'processing' && strtotime($request['lease_until'] ?? '') > time();
$cooldown = $request['last_error_code'] === 'crm_create_uncertain'
    && strtotime($request['next_retry_at'] ?? '') > time();
require __DIR__ . '/admin_header.php';
?>
<main class="shell admin-main">
    <a class="quiet-link" href="/admin/requests">← К списку заявок</a>
    <div class="page-head detail-head">
        <div>
            <h1><?= e($title) ?></h1>
            <p>Поступила <?= e($request['created_at']) ?> UTC</p>
        </div>
        <?php if ($status !== 'sent' && !$running && !$cooldown): ?>
            <form method="post" action="/admin/requests/<?= e($request['id']) ?>/retry">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                <button class="button primary">Повторить отправку <span aria-hidden="true">↗</span></button>
            </form>
        <?php endif; ?>
    </div>
    <?php if ($flash): ?><p class="notice" role="status"><?= e($flash) ?></p><?php endif; ?>
    <div class="detail-grid">
        <section class="panel detail-panel" aria-labelledby="contact-heading">
            <h2 id="contact-heading">Контакты и комментарий</h2>
            <dl>
                <dt>Имя</dt><dd><?= e($request['name']) ?></dd>
                <dt>Телефон</dt><dd><?= e($request['phone']) ?></dd>
                <dt>Email</dt><dd><?= e($request['email']) ?></dd>
                <dt>Комментарий</dt><dd class="comment"><?= e($request['comment'] ?? '—') ?></dd>
            </dl>
        </section>
        <section class="panel detail-panel" aria-labelledby="delivery-heading">
            <h2 id="delivery-heading">Доставка</h2>
            <dl>
                <dt>Статус</dt><dd><span class="pill status-<?= e($status) ?>"><?= e(DELIVERY_LABELS[$status]) ?></span></dd>
                <dt>Приоритет</dt><dd><span class="pill priority-<?= e($request['priority']) ?>"><?= e(PRIORITY_LABELS[$request['priority']]) ?></span>
                    <small><?= $request['priority_source'] === 'ai' ? 'AI' : ($request['classified_at'] ? 'По умолчанию' : 'Ожидает оценки') ?></small></dd>
                <dt>Лид CRM</dt><dd><?= e($request['crm_lead_id'] ?? '—') ?></dd>
                <dt>UUID заявки</dt><dd><code><?= e($request['request_uuid']) ?></code></dd>
                <?php if ($request['last_error_message']): ?>
                    <dt>Последняя ошибка</dt><dd><?= e($request['last_error_message']) ?> <small><code><?= e($request['last_error_code']) ?></code></small></dd>
                <?php endif; ?>
                <?php if ($cooldown): ?>
                    <dt>Повтор доступен</dt><dd><?= e($request['next_retry_at']) ?> UTC</dd>
                <?php endif; ?>
            </dl>
        </section>
    </div>
    <section class="panel" aria-labelledby="history-heading">
        <div class="panel-top"><h2 id="history-heading">История попыток</h2></div>
        <?php if (!$attempts): ?>
            <div class="empty">Попыток доставки пока не было.</div>
        <?php else: ?>
            <div class="table-scroll" tabindex="0" role="region" aria-label="История попыток доставки">
                <table>
                    <thead><tr><th scope="col">Попытка</th><th scope="col">Начало, UTC</th><th scope="col">Окончание, UTC</th><th scope="col">Результат</th><th scope="col">HTTP CRM</th><th scope="col">Код ошибки</th></tr></thead>
                    <tbody>
                    <?php foreach ($attempts as $attempt): ?>
                        <tr>
                            <td><?= e($attempt['attempt_no']) ?></td>
                            <td><?= e($attempt['started_at']) ?></td>
                            <td><?= e($attempt['finished_at'] ?? '—') ?></td>
                            <td><span class="pill status-<?= e($attempt['outcome']) ?>"><?= e(DELIVERY_LABELS[$attempt['outcome']]) ?></span></td>
                            <td><?= e($attempt['http_status'] ?? '—') ?></td>
                            <td><code><?= e($attempt['error_code'] ?? '—') ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
