<section>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h1 class="iro-page-title mb-0">Партнёры на главной</h1>
        <a href="/admin/partners/create" class="btn btn-primary rounded-pill">Добавить</a>
    </div>
    <p class="text-muted small">Логотипы выводятся на главной по три в ряд. Порядок — поле «Порядок» (меньше — выше в списке).</p>

    <div class="iro-panel p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                <tr>
                    <th class="text-nowrap">Порядок</th>
                    <th>Название</th>
                    <th>Сайт</th>
                    <th>Логотип</th>
                    <th>Статус</th>
                    <th class="text-end">Действия</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="6" class="text-muted">Партнёров пока нет</td></tr>
                <?php else: ?>
                    <?php foreach ($items as $row): ?>
                        <tr>
                            <td class="text-muted"><?= (int) ($row['sort_order'] ?? 0) ?></td>
                            <td class="fw-semibold"><?= h((string) $row['title']) ?></td>
                            <td class="small">
                                <?php $u = trim((string) ($row['website_url'] ?? '')); ?>
                                <?php if ($u !== ''): ?>
                                    <a href="<?= h($u) ?>" target="_blank" rel="noopener noreferrer" class="text-break"><?= h($u) ?></a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['logo_path'])): ?>
                                    <a href="<?= h((string) $row['logo_path']) ?>" target="_blank" rel="noopener" class="d-inline-block">
                                        <img src="<?= h((string) $row['logo_path']) ?>" alt="" class="rounded border bg-white" style="max-height:40px;max-width:120px;object-fit:contain">
                                    </a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int) ($row['is_active'] ?? 0)): ?>
                                    <span class="badge text-bg-success">На сайте</span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">Скрыт</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                    <a href="/admin/partners/edit?id=<?= (int) $row['id'] ?>" class="btn btn-sm btn-primary rounded-pill">Изменить</a>
                                    <form method="post" action="/admin/partners/delete" class="d-inline" onsubmit="return confirm('Удалить партнёра и файл логотипа?');">
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
