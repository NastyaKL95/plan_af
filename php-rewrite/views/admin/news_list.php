<section>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h1 class="iro-page-title mb-0">Новости и объявления</h1>
        <a href="/admin/news/create" class="btn btn-primary rounded-pill">Добавить</a>
    </div>

    <div class="iro-panel p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                <tr>
                    <th>Заголовок</th>
                    <th>Статус</th>
                    <th>Дата</th>
                    <th class="text-end">Действия</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="4" class="text-muted">Записей пока нет</td></tr>
                <?php else: ?>
                    <?php foreach ($items as $row): ?>
                        <tr>
                            <td class="fw-semibold"><?= h((string) $row['title']) ?></td>
                            <td>
                                <?php if ((int) $row['is_published']): ?>
                                    <span class="badge text-bg-success">Опубликовано</span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">Черновик</span>
                                <?php endif; ?>
                                <?php if ((int) $row['is_pinned']): ?>
                                    <span class="badge text-bg-info">Закреплено</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= h((string) $row['published_at']) ?></td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                    <a href="/news/<?= (int) $row['id'] ?>/" class="btn btn-sm btn-outline-secondary rounded-pill" target="_blank" rel="noopener">На сайте</a>
                                    <a href="/admin/news/edit?id=<?= (int) $row['id'] ?>" class="btn btn-sm btn-primary rounded-pill">Изменить</a>
                                    <form method="post" action="/admin/news/delete" class="d-inline" onsubmit="return confirm('Удалить эту новость?');">
                                        <?= iro_csrf_field_html() ?>
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill">Удалить</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
