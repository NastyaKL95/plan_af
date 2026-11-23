<section class="iro-panel iro-expert-stages">
    <header class="iro-expert-stages__head mb-4">
        <h1 class="iro-page-title mb-2">Этапы конкурса</h1>
        <p class="iro-lead mb-0"><?= h((string) ($contest['title'] ?? '')) ?></p>
    </header>

    <?php if (!empty($expert_pool_only_no_stage)): ?>
        <p class="text-muted mb-0">
            Вы включены в пул экспертов этого конкурса, но пока не назначены на этап.
            Когда организатор добавит вас в список экспертов этапа, здесь появятся ссылки на работы.
        </p>
    <?php elseif (empty($stages)): ?>
        <p class="text-muted mb-0">Нет доступных этапов.</p>
    <?php else: ?>
        <ul class="iro-link-list mb-0">
            <?php foreach ($stages as $st): ?>
                <li>
                    <a href="/expert/stages/<?= (int) $st['id'] ?>/submissions/">
                        <?= h((string) $st['title']) ?>
                        <span class="text-muted small">(<?= h((string) $st['start_date']) ?> – <?= h((string) $st['end_date']) ?>)</span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

