<section>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h1 class="iro-page-title mb-0">Архив конкурсов</h1>
        <a href="/admin/contests" class="btn btn-outline-secondary rounded-pill px-3">К активным конкурсам</a>
    </div>

    <p class="text-muted small mb-4">
        Здесь только конкурсы, перенесённые в архив: сохранены название, период проведения и файл Excel с полной статистикой на момент архивации.
        Карточка на сайте доступна только администратору.
    </p>

    <form method="get" action="/admin/contests/archived" class="mb-4 iro-filter-bar">
        <div class="input-group shadow-sm">
            <input type="text" name="q" class="form-control border-end-0" placeholder="Поиск по названию"
                   value="<?= h((string) ($q ?? '')) ?>">
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
                    <th>Архивирован</th>
                    <th>Отчёт Excel</th>
                    <th class="text-end">Действия</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($archived_contests)): ?>
                    <tr><td colspan="5" class="text-muted">В архиве пока нет конкурсов</td></tr>
                <?php else: ?>
                    <?php foreach ($archived_contests as $c): ?>
                        <?php
                        $rp = trim((string) ($c['archive_report_path'] ?? ''));
                        $archAt = trim((string) ($c['archived_at'] ?? ''));
                        ?>
                        <tr>
                            <td class="fw-semibold"><?= h((string) $c['title']) ?></td>
                            <td class="small text-muted text-nowrap"><?= h((string) ($c['start_date'] ?? '')) ?> — <?= h((string) ($c['end_date'] ?? '')) ?></td>
                            <td class="small text-muted"><?= $archAt !== '' ? h(iro_format_datetime_long_ru($archAt)) : '—' ?></td>
                            <td>
                                <?php if ($rp !== ''): ?>
                                    <a href="<?= h($rp) ?>" class="btn btn-sm btn-outline-primary rounded-pill"><i class="bi bi-file-earmark-excel"></i> <?= h(basename($rp)) ?></a>
                                <?php else: ?>
                                    <span class="text-muted small">нет файла</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="/contest?id=<?= (int) $c['id'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill">Карточка</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
