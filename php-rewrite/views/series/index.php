<h1 class="iro-page-title mb-3">Серии конкурсов</h1>
<p class="iro-lead mb-4">Откройте серию, чтобы посмотреть конкурсы.</p>

<?php if (!empty($series)): ?>
    <div class="row g-4">
        <?php foreach ($series as $s): ?>
            <?php
            $slug = (string) $s['slug'];
            $cover = trim((string) ($s['cover_image_path'] ?? ''));
            $desc = trim((string) ($s['description'] ?? ''));
            $descShort = $desc !== '' ? mb_strimwidth($desc, 0, 160, '…') : '';
            ?>
            <div class="col-md-6 col-xl-4">
                <div class="iro-card-shell h-100">
                    <a href="/series/<?= rawurlencode($slug) ?>/" class="iro-card-contest-link d-flex flex-column h-100">
                        <div class="iro-card-contest-media flex-shrink-0">
                            <?php if ($cover !== ''): ?>
                                <img class="iro-card-contest-cover-img" src="<?= h($cover) ?>" alt="" loading="lazy">
                            <?php endif; ?>
                        </div>
                        <div class="iro-card-contest-body flex-grow-1 d-flex flex-column">
                            <h2 class="iro-card-title h5"><?= h((string) $s['title']) ?></h2>
                            <?php if ($descShort !== ''): ?>
                                <p class="iro-card-desc small mb-3 flex-grow-1"><?= h($descShort) ?></p>
                            <?php else: ?>
                                <p class="iro-card-desc small text-muted mb-3 flex-grow-1">Перейти к списку конкурсов серии</p>
                            <?php endif; ?>
                            <span class="iro-link small mt-auto">Открыть серию</span>
                        </div>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="iro-panel text-center py-5">
        <i class="bi bi-inbox display-4 d-block mb-3 text-muted opacity-50"></i>
        <p class="iro-help-text mb-0">Серий пока нет. Проверьте позже.</p>
    </div>
<?php endif; ?>
