<nav aria-label="Навигация" class="mb-3">
    <a href="/news" class="iro-link small">← Все новости и объявления</a>
</nav>

<article class="iro-panel">
    <header class="mb-3">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
            <h1 class="iro-page-title mb-0"><?= h((string) $announcement['title']) ?></h1>
            <?php if (!empty($announcement['is_pinned'])): ?>
                <span class="badge text-bg-secondary rounded-pill">Закреплено</span>
            <?php endif; ?>
        </div>
        <p class="text-muted small mb-0">Опубликовано <?= h(iro_format_datetime_long_ru((string) $announcement['published_at'])) ?></p>
    </header>
    <div class="iro-announcement-body">
        <?php
        $body = (string) ($announcement['body'] ?? '');
        $allowedTags = '<p><br><br/><strong><b><em><i><u><ul><ol><li><a><h2><h3><h4><blockquote><div><span><table><thead><tbody><tr><th><td>';
        if (strip_tags($body) !== $body) {
            echo strip_tags($body, $allowedTags);
        } else {
            echo nl2br(h($body));
        }
        ?>
    </div>
</article>
