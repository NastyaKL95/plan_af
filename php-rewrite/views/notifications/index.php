<section class="iro-panel">
    <h1 class="iro-page-title mb-3">Уведомления</h1>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <form method="post" action="/notifications/read-all" class="m-0">
            <?= iro_csrf_field_html() ?>
            <button type="submit" class="btn btn-sm btn-outline-secondary">Отметить все прочитанными</button>
        </form>
        <?php if (!empty($notifications)): ?>
            <form method="post" action="/notifications/delete-all" class="m-0" onsubmit="return confirm('Удалить все уведомления? Это действие нельзя отменить.');">
                <?= iro_csrf_field_html() ?>
                <button type="submit" class="btn btn-sm btn-outline-danger">Удалить все</button>
            </form>
        <?php endif; ?>
    </div>
    <?php if (empty($notifications)): ?>
        <div class="text-center py-4 px-2">
            <i class="bi bi-bell-slash d-block fs-1 text-muted opacity-50 mb-3" aria-hidden="true"></i>
            <p class="text-muted mb-2">У вас пока нет уведомлений.</p>
            <p class="small text-muted mb-0">Новые сообщения появятся здесь при событиях по конкурсам. Откройте <a href="/contests">каталог конкурсов</a> или <a href="/">главную страницу</a>.</p>
        </div>
    <?php else: ?>
        <div class="list-group">
            <?php foreach ($notifications as $item): ?>
                <?php $nid = (int) $item['id']; ?>
                <div class="list-group-item iro-notif-row d-flex flex-nowrap align-items-center gap-2 gap-sm-3 px-3 py-2<?= (int) $item['is_read'] === 0 ? ' list-group-item-warning' : '' ?>">
                    <a href="/notifications/<?= $nid ?>/go/" class="list-group-item-action flex-grow-1 min-w-0 text-decoration-none text-body py-1">
                        <div class="fw-semibold"><?= h((string) $item['title']) ?></div>
                        <div class="small"><?= h((string) $item['body']) ?></div>
                        <div class="small text-muted mb-0"><?= h(iro_format_datetime_long_ru((string) $item['created_at'])) ?></div>
                    </a>
                    <form method="post" action="/notifications/delete" class="iro-notif-delete-form m-0 flex-shrink-0" onsubmit="return confirm('Удалить это уведомление?');">
                        <?= iro_csrf_field_html() ?>
                        <input type="hidden" name="id" value="<?= $nid ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger iro-notif-delete-btn rounded-circle" title="Удалить" aria-label="Удалить уведомление">
                            <i class="bi bi-trash" aria-hidden="true"></i>
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
