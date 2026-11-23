<?php
$filterQs = array_filter([
    'q' => ($query ?? '') !== '' ? $query : null,
    'phase' => ($phase ?? '') !== '' ? $phase : null,
]);
$cardsQs = http_build_query($filterQs);
$listQs = http_build_query(array_merge($filterQs, ['view' => 'list']));
$viewMode = $view_mode ?? 'cards';
$pageQsBase = $viewMode === 'list' ? $listQs : $cardsQs;
$pageSuffix = $pageQsBase !== '' ? '&' . $pageQsBase : '';
$today = $today ?? date('Y-m-d');

$cardsHref = $cardsQs !== '' ? '?' . $cardsQs : '?';
$listHref = '?' . $listQs;
?>
<div class="iro-home-toolbar d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 pb-1">
    <div>
        <h2 class="iro-section-title mb-0 mt-0">Актуальные конкурсы</h2>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="btn-group btn-group-sm iro-home-view-toggle" role="group" aria-label="Вид списка конкурсов">
            <a href="<?= h($cardsHref) ?>"
               class="btn btn-outline-secondary rounded-start-pill px-3 iro-home-ajax <?= $viewMode === 'cards' ? 'active' : '' ?>">Плиткой</a>
            <a href="<?= h($listHref) ?>"
               class="btn btn-outline-secondary rounded-end-pill px-3 iro-home-ajax <?= $viewMode === 'list' ? 'active' : '' ?>">Списком</a>
        </div>
        <?php if (!empty($can_manage_contests_global)): ?>
            <a href="/admin/contests/create" class="btn btn-primary btn-sm px-3 rounded-pill">+ Новый конкурс</a>
            <a href="/admin/contests/create-link" class="btn btn-outline-primary btn-sm px-3 rounded-pill">+ Конкурс-ссылка</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($contests)): ?>
    <?php if ($viewMode === 'list'): ?>
        <div class="list-group iro-contest-compact-list">
            <?php foreach ($contests as $contest): ?>
                <?php $ph = contest_calendar_phase($contest, $today); ?>
                <a href="/contest?id=<?= (int) $contest['id'] ?>" class="list-group-item list-group-item-action iro-contest-compact-item">
                    <div class="iro-contest-compact-grid">
                        <?php if (!empty($contest['image_path'])): ?>
                            <div class="iro-contest-compact-thumb rounded-2 overflow-hidden iro-cc-thumb">
                                <img src="<?= h((string) $contest['image_path']) ?>" loading="lazy" decoding="async" alt="Обложка: <?= h((string) $contest['title']) ?>" class="iro-contest-compact-thumb-img">
                            </div>
                        <?php else: ?>
                            <div class="iro-contest-compact-thumb iro-contest-compact-thumb--empty rounded-2 d-flex align-items-center justify-content-center text-muted small iro-cc-thumb">Нет фото</div>
                        <?php endif; ?>
                        <div class="iro-cc-body">
                            <div class="iro-cc-top">
                                <span class="iro-cc-title text-body"><?= h((string) $contest['title']) ?></span>
                                <div class="iro-cc-badges iro-contest-badges-row flex-shrink-0">
                                    <span class="iro-badge-status <?= h($ph['badge_class']) ?>"><?= h($ph['label']) ?></span>
                                    <?php if ((int) ($contest['is_external'] ?? 0) === 1): ?>
                                        <span class="iro-badge-status iro-status-external">На другом сайте</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="iro-cc-desc mb-0 small text-muted"><?= h(mb_strimwidth(strip_tags((string) $contest['description']), 0, 140, '…')) ?></p>
                        </div>
                        <div class="iro-cc-dates text-muted small">
                            <div class="text-nowrap"><span class="text-uppercase iro-label-calendar-pill">Начало</span><br><strong class="fw-semibold text-body-secondary"><?= h((string) $contest['start_date']) ?></strong></div>
                            <div class="text-nowrap mt-1"><span class="text-uppercase iro-label-calendar-pill">Конец</span><br><strong class="fw-semibold text-body-secondary"><?= h((string) $contest['end_date']) ?></strong></div>
                        </div>
                        <span class="bi bi-chevron-right text-muted iro-cc-go d-none d-md-flex" aria-hidden="true"></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($contests as $contest): ?>
                <?php $ph = contest_calendar_phase($contest, $today); ?>
                <div class="col-md-6 col-xl-4">
                    <div class="iro-card-shell">
                        <a href="/contest?id=<?= (int) $contest['id'] ?>" class="iro-card-contest-link">
                            <div class="iro-card-contest-media">
                                <?php if (!empty($contest['image_path'])): ?>
                                    <img src="<?= h((string) $contest['image_path']) ?>"
                                         loading="lazy" decoding="async" alt="Обложка: <?= h((string) $contest['title']) ?>"
                                         class="iro-card-contest-media-img">
                                <?php endif; ?>
                            </div>
                            <div class="iro-card-contest-body">
                                <div class="iro-contest-badges-row">
                                    <span class="iro-badge-status <?= h($ph['badge_class']) ?>"><?= h($ph['label']) ?></span>
                                    <?php if ((int) ($contest['is_external'] ?? 0) === 1): ?>
                                        <span class="iro-badge-status iro-status-external">На другом сайте</span>
                                    <?php endif; ?>
                                </div>
                                <h3 class="iro-card-title"><?= h((string) $contest['title']) ?></h3>
                                <p class="iro-card-desc mb-0"><?= h(mb_strimwidth(strip_tags((string) $contest['description']), 0, 100, '…')) ?></p>
                                <div class="iro-card-meta">
                                    <div class="mb-1"><i class="bi bi-calendar-event me-1"></i> Начало: <strong><?= h((string) $contest['start_date']) ?></strong></div>
                                    <div class="mb-0"><i class="bi bi-calendar-event me-1"></i> Окончание: <strong><?= h((string) $contest['end_date']) ?></strong></div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (($pages ?? 1) > 1): ?>
        <nav class="mt-4 pt-2" aria-label="Страницы списка конкурсов">
            <ul class="pagination pagination-sm justify-content-center flex-wrap mb-0 align-items-center gap-1">
                <?php if (($page ?? 1) > 1): ?>
                    <li class="page-item">
                        <a class="page-link rounded-pill px-3 iro-home-ajax" rel="prev" href="?page=<?= (int) $page - 1 ?><?= h($pageSuffix) ?>">Назад</a>
                    </li>
                <?php else: ?>
                    <li class="page-item disabled"><span class="page-link rounded-pill px-3 opacity-50">Назад</span></li>
                <?php endif; ?>
                <li class="page-item disabled mx-1">
                    <span class="page-link border-0 bg-transparent text-body-secondary px-2"><?= (int) $page ?> / <?= (int) $pages ?></span>
                </li>
                <?php if (($page ?? 1) < ($pages ?? 1)): ?>
                    <li class="page-item">
                        <a class="page-link rounded-pill px-3 iro-home-ajax" rel="next" href="?page=<?= (int) $page + 1 ?><?= h($pageSuffix) ?>">Вперёд</a>
                    </li>
                <?php else: ?>
                    <li class="page-item disabled"><span class="page-link rounded-pill px-3 opacity-50">Вперёд</span></li>
                <?php endif; ?>
            </ul>
            <p class="text-center text-muted small mb-0 mt-2">Всего <?= (int) $total ?> <?= h((string) $contest_count_word) ?></p>
        </nav>
    <?php endif; ?>
<?php else: ?>
    <div class="iro-panel text-center py-5">
        <i class="bi bi-inbox display-4 d-block mb-3 text-muted opacity-50" aria-hidden="true"></i>
        <p class="iro-help-text mb-2">Ничего не найдено по текущим фильтрам и запросу.</p>
        <p class="small text-muted mb-3">Сбросьте фильтр «По датам», очистите поиск или откройте <a href="/contests">полный каталог конкурсов</a>.</p>
        <a href="/" class="btn btn-outline-primary rounded-pill">Сбросить фильтры на главной</a>
    </div>
<?php endif; ?>
