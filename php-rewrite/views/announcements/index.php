<h1 class="iro-page-title mb-3">Новости и объявления</h1>
<p class="iro-lead mb-4">Новости и объявления конкурса.</p>

<?php if (!empty($announcements)): ?>
    <div class="d-flex flex-column gap-3">
        <?php foreach ($announcements as $a): ?>
            <?php
            $teaser = iro_announcement_list_teaser((string) ($a['summary'] ?? ''), (string) ($a['body'] ?? ''));
            $teaserShort = iro_truncate_words($teaser, 40);
            ?>
            <article class="iro-panel mb-0">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
                    <h2 class="iro-subcard-title mb-0">
                        <a href="/news/<?= (int) $a['id'] ?>/" class="text-decoration-none text-body"><?= h((string) $a['title']) ?></a>
                    </h2>
                    <?php if (!empty($a['is_pinned'])): ?>
                        <span class="badge text-bg-secondary rounded-pill">Закреплено</span>
                    <?php endif; ?>
                </div>
                <p class="text-muted small mb-2"><?= h(iro_format_datetime_long_ru((string) $a['published_at'])) ?></p>
                <p class="mb-0 text-body-secondary"><?= h($teaserShort) ?></p>
                <a href="/news/<?= (int) $a['id'] ?>/" class="iro-link small d-inline-block mt-2">Читать полностью</a>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if (($pages ?? 1) > 1): ?>
        <nav class="mt-4 pt-2" aria-label="Страницы новостей">
            <ul class="pagination pagination-sm justify-content-center flex-wrap mb-0 gap-1">
                <?php if (($page ?? 1) > 1): ?>
                    <li class="page-item">
                        <a class="page-link rounded-pill px-3" href="?page=<?= (int) $page - 1 ?>">Назад</a>
                    </li>
                <?php endif; ?>
                <li class="page-item disabled">
                    <span class="page-link border-0 bg-transparent text-body-secondary"><?= (int) $page ?> / <?= (int) $pages ?></span>
                </li>
                <?php if (($page ?? 1) < ($pages ?? 1)): ?>
                    <li class="page-item">
                        <a class="page-link rounded-pill px-3" href="?page=<?= (int) $page + 1 ?>">Вперёд</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
<?php else: ?>
    <div class="iro-panel text-center py-5">
        <i class="bi bi-megaphone display-4 d-block mb-3 text-muted opacity-50"></i>
        <p class="iro-help-text mb-0">Новостей пока нет.</p>
    </div>
<?php endif; ?>
