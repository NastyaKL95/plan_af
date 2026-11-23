<section>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h1 class="iro-page-title mb-0">Шаблоны согласий</h1>
        <a href="/admin/consent-documents/create" class="btn btn-primary rounded-pill">Добавить</a>
    </div>
    <p class="text-muted small">Файлы-образцы, на которые ориентируются участники при загрузке согласия.</p>

    <div class="iro-panel p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                <tr>
                    <th>Название</th>
                    <th>Файл</th>
                    <th>Статус</th>
                    <th class="text-end">Действия</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="4" class="text-muted">Документов нет</td></tr>
                <?php else: ?>
                    <?php foreach ($items as $row): ?>
                        <tr>
                            <td class="fw-semibold"><?= h((string) $row['title']) ?></td>
                            <td><a href="<?= h((string) $row['file_path']) ?>" target="_blank" rel="noopener">Скачать</a></td>
                            <td>
                                <?php if ((int) $row['is_active']): ?>
                                    <span class="badge text-bg-success">Активен</span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">Выключен</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                    <a href="/admin/consent-documents/edit?id=<?= (int) $row['id'] ?>" class="btn btn-sm btn-primary rounded-pill">Изменить</a>
                                    <form method="post" action="/admin/consent-documents/delete" class="d-inline" onsubmit="return confirm('Удалить шаблон?');">
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
