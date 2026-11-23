<section>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h1 class="iro-page-title mb-0">Серии конкурсов</h1>
        <a href="/admin/series/create" class="btn btn-primary rounded-pill">Добавить серию</a>
    </div>

    <div class="iro-panel p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                <tr>
                    <th>Название</th>
                    <th>Slug</th>
                    <th>Порядок</th>
                    <th class="text-end">Действия</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="4" class="text-muted">Серий пока нет</td></tr>
                <?php else: ?>
                    <?php foreach ($items as $row): ?>
                        <tr>
                            <td class="fw-semibold"><?= h((string) $row['title']) ?></td>
                            <td><code class="small"><?= h((string) $row['slug']) ?></code></td>
                            <td><?= (int) $row['sort_order'] ?></td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                    <a href="/series/<?= rawurlencode((string) $row['slug']) ?>/" class="btn btn-sm btn-outline-secondary rounded-pill" target="_blank" rel="noopener">На сайте</a>
                                    <a href="/admin/series/edit?id=<?= (int) $row['id'] ?>" class="btn btn-sm btn-primary rounded-pill">Изменить</a>
                                    <form method="post" action="/admin/series/delete" class="d-inline" onsubmit="return confirm('Удалить серию? Конкурсы останутся без привязки к серии.');">
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
