<h1 class="iro-page-title mb-3">Конкурсы</h1>
<p class="iro-lead mb-4">Опубликованные конкурсы. Откройте карточку, чтобы перейти на страницу конкурса.</p>

<?php
$today = $today ?? date('Y-m-d');
?>

<?php if (!empty($contests)): ?>
    <div class="row g-4">
        <?php foreach ($contests as $contest): ?>
            <?php $ph = contest_calendar_phase($contest, $today); ?>
            <div class="col-md-6 col-xl-4">
                <div class="iro-card-shell">
                    <a href="/contest/<?= (int) $contest['id'] ?>/" class="iro-card-contest-link">
                        <div class="iro-card-contest-media">
                            <?php if (!empty($contest['image_path'])): ?>
                                <img src="<?= h((string) $contest['image_path']) ?>" alt="Обложка: <?= h((string) $contest['title']) ?>" class="iro-card-contest-media-img" loading="lazy">
                            <?php endif; ?>
                        </div>
                        <div class="iro-card-contest-body">
                            <div class="iro-contest-badges-row">
                                <span class="iro-badge-status <?= h($ph['badge_class']) ?>"><?= h($ph['label']) ?></span>
                                <?php if ((int) ($contest['is_external'] ?? 0) === 1): ?>
                                    <span class="iro-badge-status iro-status-external">На другом сайте</span>
                                <?php endif; ?>
                            </div>
                            <h2 class="iro-card-title"><?= h((string) $contest['title']) ?></h2>
                            <p class="iro-card-desc mb-0"><?= h(mb_strimwidth(strip_tags((string) $contest['description']), 0, 160, '…')) ?></p>
                            <div class="iro-card-meta">
                                <div class="mb-1"><i class="bi bi-calendar-event me-1"></i> Начало: <strong><?= h(iro_format_date_long_ru((string) $contest['start_date'])) ?></strong></div>
                                <div class="mb-0"><i class="bi bi-calendar-event me-1"></i> Окончание: <strong><?= h(iro_format_date_long_ru((string) $contest['end_date'])) ?></strong></div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="iro-panel text-center py-5">
        <i class="bi bi-inbox display-4 d-block mb-3 text-muted opacity-50" aria-hidden="true"></i>
        <p class="iro-help-text mb-2">Пока нет опубликованных конкурсов.</p>
        <p class="small text-muted mb-3">Загляните позже или посмотрите <a href="/news">новости и объявления</a>.</p>
        <a href="/" class="btn btn-outline-primary rounded-pill">На главную</a>
    </div>
<?php endif; ?>
