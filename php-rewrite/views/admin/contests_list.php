<section>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h1 class="iro-page-title mb-0">Конкурсы</h1>
        <div class="d-flex flex-wrap gap-2">
            <?php if (!empty($isAdmin)): ?>
                <a href="/admin/contests/create" class="btn btn-primary rounded-pill px-4">Создать конкурс</a>
                <a href="/admin/contests/create-link" class="btn btn-outline-primary rounded-pill px-4">Конкурс-ссылка</a>
                <a href="/admin/contests/archived" class="btn btn-outline-secondary rounded-pill px-4"><i class="bi bi-archive me-1"></i>Архив</a>
            <?php endif; ?>
        </div>
    </div>

    <form method="get" class="mb-4 iro-filter-bar">
        <div class="input-group shadow-sm">
            <input type="text" name="q" class="form-control border-end-0" placeholder="Поиск по названию конкурса" value="<?= h((string) ($q ?? '')) ?>">
            <button type="submit" class="btn btn-primary">Найти</button>
        </div>
    </form>

    <div class="iro-panel p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                <tr>
                    <th>Название</th>
                    <th>Период</th>
                    <th>Тип</th>
                    <th>Статус</th>
                    <th class="text-end">Действия</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($contests)): ?>
                    <tr><td colspan="5" class="text-muted">Нет конкурсов</td></tr>
                <?php else: ?>
                    <?php foreach ($contests as $c): ?>
                        <?php $isArchived = (int) ($c['is_archived'] ?? 0) === 1; ?>
                        <tr class="<?= $isArchived ? 'table-secondary' : '' ?>">
                            <td class="fw-semibold"><?= h((string) $c['title']) ?></td>
                            <td class="small text-muted text-nowrap"><?= h((string) ($c['start_date'] ?? '')) ?> — <?= h((string) ($c['end_date'] ?? '')) ?></td>
                            <td>
                                <?php if ((int) ($c['is_external'] ?? 0) === 1): ?>
                                    <span class="badge text-bg-secondary">Ссылка</span>
                                <?php else: ?>
                                    <span class="badge text-bg-light border">На сайте</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isArchived): ?>
                                    <span class="badge text-bg-dark">Архив</span>
                                    <?php $rp = trim((string) ($c['archive_report_path'] ?? '')); ?>
                                    <?php if ($rp !== ''): ?>
                                        <div class="mt-1"><a href="<?= h($rp) ?>" class="small"><?= h(basename($rp)) ?></a></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php $st = (int) $c['status']; ?>
                                    <span class="badge <?= h(contest_status_badge_class($st)) ?>"><?= h(contest_status_label($st)) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap gap-2 justify-content-end">
                                    <?php if (!$isArchived): ?>
                                        <a href="/contest?id=<?= (int) $c['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill">Открыть</a>
                                        <?php if ((int) ($c['is_external'] ?? 0) === 1): ?>
                                            <a href="/admin/contests/edit-link?id=<?= (int) $c['id'] ?>" class="btn btn-sm btn-primary rounded-pill">Изменить</a>
                                        <?php else: ?>
                                            <a href="/admin/contests/edit?id=<?= (int) $c['id'] ?>" class="btn btn-sm btn-primary rounded-pill">Изменить</a>
                                            <?php if (!empty($isAdmin)): ?>
                                                <form method="post" action="/admin/contests/archive" class="d-inline"
                                                      onsubmit="var w = prompt('В архив? Будут удалены все работы, оценки, участники, файлы и настройки этапов. Останутся только название, даты и файл Excel с полной статистикой (как при выгрузке «Статистика»). Это необратимо.\n\nДля подтверждения введите слово АРХИВ'); if (w === null) return false; this.confirm_word.value = w; return true;">
                                                    <?= iro_csrf_field_html() ?>
                                                    <input type="hidden" name="confirm_word" value="">
                                                    <input type="hidden" name="contest_id" value="<?= (int) $c['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill">В архив</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="small text-muted">Только карточка и отчёт</span>
                                    <?php endif; ?>
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

