<nav aria-label="Навигация" class="mb-3">
    <a href="/series/" class="iro-link small">← Все серии</a>
</nav>

<header class="mb-4">
    <h1 class="iro-page-title"><?= h((string) ($series['title'] ?? 'Серия')) ?></h1>
    <?php if (trim((string) ($series['description'] ?? '')) !== ''): ?>
        <p class="iro-lead mb-0"><?= h((string) $series['description']) ?></p>
    <?php endif; ?>
</header>

<?php
$coverPath = trim((string) ($series['cover_image_path'] ?? ''));
$today = $today ?? date('Y-m-d');
?>

<?php if ($coverPath !== ''): ?>
    <div class="iro-panel p-0 overflow-hidden mb-4">
        <img src="<?= h($coverPath) ?>" alt="" class="img-fluid w-100 iro-series-cover-hero">
    </div>
<?php endif; ?>

<?php if (!empty($contests)): ?>
    <h2 class="iro-section-title h4 mb-3">Конкурсы серии</h2>
    <div class="row g-4">
        <?php foreach ($contests as $contest): ?>
            <?php $ph = contest_calendar_phase($contest, $today); ?>
            <div class="col-md-6 col-xl-4">
                <div class="iro-card-shell">
                    <a href="/contest/<?= (int) $contest['id'] ?>/" class="iro-card-contest-link">
                        <div class="iro-card-contest-media">
                            <?php if (!empty($contest['image_path'])): ?>
                                <img src="<?= h((string) $contest['image_path']) ?>" alt="" class="iro-card-contest-media-img" loading="lazy">
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
        <p class="iro-help-text mb-0">В этой серии пока нет опубликованных конкурсов.</p>
    </div>
<?php endif; ?>
