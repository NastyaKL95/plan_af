<section class="iro-panel">
    <h1 class="iro-page-title mb-3">Кабинет эксперта</h1>
    <?php if (empty($contests)): ?>
        <p class="text-muted mb-0">Нет конкурсов, где вы назначены экспертом.</p>
    <?php else: ?>
        <div class="list-group">
            <?php foreach ($contests as $c): ?>
                <a class="list-group-item list-group-item-action" href="/expert/contests/<?= (int) $c['id'] ?>/stages/">
                    <div class="fw-semibold"><?= h((string) $c['title']) ?></div>
                    <div class="small text-muted"><?= h((string) $c['start_date']) ?> – <?= h((string) $c['end_date']) ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

