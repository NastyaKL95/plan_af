<?php if (!empty($pick_mode)): ?>
<section>
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div class="flex-grow-1 min-w-0">
            <h1 class="iro-page-title mb-2">Архив материалов участников</h1>
            <p class="iro-lead mb-0">Выберите конкурс — откроется список участников; для каждого можно скачать ZIP с согласием, файлами к заявке и всеми работами по этапам.</p>
        </div>
        <div class="iro-action-toolbar flex-shrink-0">
            <a href="/contests" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                <i class="bi bi-collection me-1" aria-hidden="true"></i>К каталогу конкурсов
            </a>
        </div>
    </div>

    <?php if (empty($manageable_contests)): ?>
        <div class="iro-panel text-center py-5 px-3 border-0 shadow-sm">
            <i class="bi bi-folder-x display-6 d-block mb-3 text-muted opacity-50" aria-hidden="true"></i>
            <p class="text-muted mb-2 fw-medium">Нет конкурсов для модерации</p>
            <p class="small text-muted mb-0">Вас не назначили модератором ни одного конкурса. Если вы администратор сайта, здесь будут все конкурсы.</p>
        </div>
    <?php else: ?>
        <div class="iro-panel p-0 overflow-hidden border-0 shadow-sm mb-4">
            <div class="p-3 border-bottom bg-light bg-opacity-75">
                <h2 class="iro-block-title mb-0"><i class="bi bi-folder2-open me-1"></i>Конкурсы</h2>
                <p class="small text-muted mb-0 mt-1">Найдено: <?= count($manageable_contests) ?></p>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th scope="col">Конкурс</th>
                        <th scope="col" class="text-center text-nowrap d-none d-md-table-cell iro-th--w7">ID</th>
                        <th scope="col" class="text-end text-nowrap iro-th--w10">Действие</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($manageable_contests as $mc): ?>
                        <tr>
                            <td class="min-w-0">
                                <a href="/moderator/participant-files?contest_id=<?= (int) $mc['id'] ?>" class="fw-semibold text-decoration-none">
                                    <?= h((string) $mc['title']) ?>
                                </a>
                                <span class="d-md-none small text-muted ms-1">· id <?= (int) $mc['id'] ?></span>
                            </td>
                            <td class="text-center small text-muted d-none d-md-table-cell"><?= (int) $mc['id'] ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary rounded-pill px-3" href="/moderator/participant-files?contest_id=<?= (int) $mc['id'] ?>">
                                    Открыть<i class="bi bi-chevron-right ms-1" aria-hidden="true"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="small text-muted mb-0">
            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
            В архив входят подписанное согласие, файлы к заявке, работы по этапам и вложения к ним.
        </p>
    <?php endif; ?>
</section>
<?php else: ?>
<section>
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div class="min-w-0">
            <h1 class="iro-page-title mb-2">Материалы участников</h1>
            <p class="text-muted small mb-0"><?= h((string) ($contest['title'] ?? '')) ?></p>
        </div>
        <div class="iro-action-toolbar flex-shrink-0">
            <a href="/moderator/participant-files" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Другой конкурс
            </a>
            <a href="/contest?id=<?= (int) ($contest['id'] ?? 0) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">К конкурсу</a>
        </div>
    </div>

    <?php if (empty($participant_rows)): ?>
        <div class="iro-panel text-center py-5 px-3 border-0 shadow-sm">
            <i class="bi bi-people display-6 d-block mb-3 text-muted opacity-50" aria-hidden="true"></i>
            <p class="text-muted mb-0">Пока нет зачисленных участников по этому конкурсу.</p>
        </div>
    <?php else: ?>
        <div class="iro-panel p-0 overflow-hidden border-0 shadow-sm mb-3">
            <div class="p-3 border-bottom bg-light bg-opacity-75">
                <h2 class="iro-block-title mb-0"><i class="bi bi-download me-1"></i>Скачать архив по участнику</h2>
                <p class="small text-muted mb-0 mt-1">Участников в списке: <?= count($participant_rows) ?></p>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th scope="col">Участник</th>
                        <th scope="col" class="d-none d-sm-table-cell">Электронная почта</th>
                        <th scope="col" class="text-end text-nowrap">Архив</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($participant_rows as $pr): ?>
                        <tr>
                            <td>
                                <?= h(user_display_name([
                                    'last_name' => $pr['last_name'] ?? '',
                                    'first_name' => $pr['first_name'] ?? '',
                                    'middle_name' => $pr['middle_name'] ?? '',
                                    'email' => $pr['email'] ?? '',
                                ])) ?>
                                <div class="d-sm-none small text-muted text-break"><?= h((string) ($pr['email'] ?? '')) ?></div>
                            </td>
                            <td class="small text-muted d-none d-sm-table-cell"><?= h((string) ($pr['email'] ?? '')) ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-primary rounded-pill px-3"
                                   href="/moderator/participant-files/download?contest_id=<?= (int) ($contest['id'] ?? 0) ?>&amp;participant_id=<?= (int) ($pr['participant_pk'] ?? 0) ?>">
                                    <i class="bi bi-download me-1" aria-hidden="true"></i>Скачать ZIP
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="small text-muted mb-0">
            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
            В архив входят подписанное согласие, файлы к заявке (если заданы поля конкурса), основные файлы работ и вложения по каждому этапу. Если участник указал только ссылку на работу, она сохраняется в текстовом файле.
        </p>
    <?php endif; ?>
</section>
<?php endif; ?>
