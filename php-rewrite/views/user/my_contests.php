<section class="iro-panel">
    <h1 class="iro-page-title mb-3">Мои конкурсы</h1>
    <?php if (empty($contests)): ?>
        <div class="text-center py-4 px-2">
            <i class="bi bi-trophy d-block fs-1 text-muted opacity-50 mb-3" aria-hidden="true"></i>
            <p class="text-muted mb-2">Вы ещё не зачислены ни в один конкурс.</p>
            <p class="small text-muted mb-3">Чтобы участвовать, откройте конкурс в <a href="/contests">каталоге</a> или с главной и нажмите «Стать участником» (согласие и документы к заявке).</p>
            <a href="/contests" class="btn btn-primary rounded-pill">Перейти к конкурсам</a>
        </div>
    <?php else: ?>
        <div class="list-group">
            <?php foreach ($contests as $contest): ?>
                <a href="/contest?id=<?= (int) $contest['id'] ?>" class="list-group-item list-group-item-action">
                    <div class="d-flex justify-content-between gap-2">
                        <div>
                            <strong><?= h((string) $contest['title']) ?></strong>
                            <div class="small text-muted"><?= h((string) $contest['start_date']) ?> - <?= h((string) $contest['end_date']) ?></div>
                            <?php if (isset($contest['award_place']) && $contest['award_place'] !== null && $contest['award_place'] !== ''): ?>
                                <div class="small mt-1">
                                    <span class="text-body"><?= (int) $contest['award_place'] ?>-е место</span>
                                    <?php if (!empty($contest['award_description'])): ?>
                                        <span class="text-muted"> — <?= h((string) $contest['award_description']) ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php $st = (int) $contest['status']; ?>
                        <span class="badge <?= h(contest_status_badge_class($st)) ?> align-self-center"><?= h(contest_status_label($st)) ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
