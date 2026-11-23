<section class="iro-panel">
    <h1 class="iro-page-title mb-3">Дополнительные файлы к работам</h1>
    <p class="text-muted mb-0">Конкурс: <strong><?= h((string) $contest['title']) ?></strong></p>
    <p class="small text-muted mt-2 mb-4">Файлы к этапам не проходят отдельную модерацию — список для просмотра организаторами.</p>

    <?php if (empty($items)): ?>
        <p class="text-muted mb-0">Пока нет работ с загруженными дополнительными файлами по этому конкурсу.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                <tr>
                    <th>Участник</th>
                    <th>Этап / работа</th>
                    <th>Доп. файлы</th>
                    <th class="text-end">Оценка</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <?php
                    $sid = (int) ($item['submission_id'] ?? $item['id'] ?? 0);
                    $email = (string) ($item['participant_email'] ?? $item['email'] ?? '');
                    $stageTitle = (string) ($item['stage_title'] ?? '');
                    $workTitle = (string) ($item['work_title'] ?? $item['title'] ?? '');
                    ?>
                    <tr>
                        <td><?= h($email) ?></td>
                        <td>
                            <div><strong><?= h($stageTitle) ?></strong></div>
                            <div><?= h($workTitle) ?></div>
                        </td>
                        <td>
                            <?php if (empty($item['attachments'])): ?>
                                <span class="text-muted small">—</span>
                            <?php else: ?>
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($item['attachments'] as $att): ?>
                                        <li class="small">
                                            <?= h((string) ($att['field_title'] ?? '')) ?>:
                                            <a href="<?= h((string) ($att['file_path'] ?? '#')) ?>" target="_blank" rel="noopener">открыть</a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="/expert/evaluate?submission_id=<?= $sid ?>">Открыть оценку</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
