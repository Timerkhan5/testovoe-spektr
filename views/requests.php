<?php
$title = 'Заявки';
require __DIR__ . '/admin_header.php';
?>
<main class="shell admin-main">
    <div class="page-head">
        <h1>Заявки</h1>
        <p>Статусы доставки в Битрикс24</p>
    </div>
    <section class="summary-grid" aria-label="Сводка по заявкам">
        <?php foreach (DELIVERY_LABELS as $key => $label): ?>
            <a href="?delivery_status=<?= e($key) ?>" class="metric">
                <span class="pill status-<?= e($key) ?>"><?= e($label) ?></span>
                <strong><?= e($counts[$key]) ?></strong>
            </a>
        <?php endforeach; ?>
    </section>

    <section class="panel" aria-labelledby="list-title">
        <div class="panel-top">
            <h2 id="list-title">Список заявок</h2>
            <form class="filters" method="get" action="/admin/requests">
                <label class="sr-only" for="delivery-status">Статус</label>
                <select id="delivery-status" name="delivery_status">
                    <option value="">Все статусы</option>
                    <?php foreach (DELIVERY_LABELS as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= ($filters['delivery_status'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="sr-only" for="priority">Приоритет</label>
                <select id="priority" name="priority">
                    <option value="">Все приоритеты</option>
                    <?php foreach (PRIORITY_LABELS as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= ($filters['priority'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button outline">Показать</button>
                <?php if ($filters): ?><a class="quiet-link" href="/admin/requests">Сбросить</a><?php endif; ?>
            </form>
        </div>
        <?php if (!$rows): ?>
            <div class="empty">
                <strong><?= $filters ? 'Заявки по этим фильтрам не найдены' : 'Здесь будут заявки с сайта' ?></strong>
                <p><?= $filters ? 'Измените статус или приоритет.' : 'Отправьте первую заявку через публичную форму.' ?></p>
                <?php if (!$filters): ?><a class="button outline" href="/">Открыть форму <span aria-hidden="true">↗</span></a><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-scroll" tabindex="0" role="region" aria-label="Таблица заявок">
                <table>
                    <thead><tr>
                        <th scope="col">ID / дата, UTC</th><th scope="col">Контакты</th><th scope="col">Приоритет</th>
                        <th scope="col">Доставка</th><th scope="col">Попытки</th><th scope="col">Лид CRM</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><a class="row-link" href="/admin/requests/<?= e($row['id']) ?>">#<?= e($row['id']) ?> <span aria-hidden="true">↗</span></a><small><?= e(substr($row['created_at'], 0, 16)) ?></small></td>
                            <td><strong><?= e($row['name']) ?></strong><small><?= e($row['phone']) ?></small><small><?= e($row['email']) ?></small></td>
                            <td><span class="pill priority-<?= e($row['priority']) ?>"><?= e(PRIORITY_LABELS[$row['priority']]) ?></span><small><?= $row['priority_source'] === 'ai' ? 'AI' : ($row['classified_at'] ? 'По умолчанию' : 'Ожидает оценки') ?></small></td>
                            <td><span class="pill status-<?= e($row['delivery_status']) ?>"><?= e(DELIVERY_LABELS[$row['delivery_status']]) ?></span>
                                <?php if ($row['last_error_message']): ?><small><?= e($row['last_error_message']) ?></small><?php endif; ?>
                            </td>
                            <td><?= e($row['attempt_count']) ?></td>
                            <td><?= e($row['crm_lead_id'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php if ($page > 1 || $hasNext): ?>
            <nav class="pagination" aria-label="Страницы заявок">
                <?php if ($page > 1): ?><a class="button outline" href="?<?= e(http_build_query($filters + ['page' => $page - 1])) ?>">← Назад</a><?php endif; ?>
                <span>Страница <?= e($page) ?></span>
                <?php if ($hasNext): ?><a class="button outline" href="?<?= e(http_build_query($filters + ['page' => $page + 1])) ?>">Дальше →</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
