<section class="iro-panel">
    <h1 class="iro-page-title mb-3">Назначение места в итогах</h1>
    <p class="iro-lead mb-4">Выберите конкурс.</p>
    <div class="list-group">
        <?php foreach ($contests as $c): ?>
            <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="fw-semibold"><?= h((string) $c['title']) ?></span>
                <a href="/award/create/<?= (int) $c['id'] ?>/" class="btn btn-primary btn-sm rounded-pill px-3">Далее</a>
            </div>
        <?php endforeach; ?>
    </div>
</section>
