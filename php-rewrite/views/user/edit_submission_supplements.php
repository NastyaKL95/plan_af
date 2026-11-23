<section class="iro-panel mx-auto iro-panel--w820">
    <h1 class="iro-page-title mb-3">Дополнительные вложения</h1>
    <p class="small text-muted mb-3">Файлы сразу доступны экспертам и организаторам для просмотра, отдельная проверка не требуется.</p>
    <p class="text-muted mb-1"><strong>Работа:</strong> <?= h((string) $submission['title']) ?></p>
    <p class="text-muted mb-1"><strong>Этап:</strong> <?= h((string) $submission['stage_title']) ?></p>
    <p class="text-muted mb-3"><strong>Дедлайн редактирования:</strong> <?= h((string) $effectiveDeadline) ?></p>

    <?php if (empty($fields)): ?>
        <p class="text-muted mb-0">Для этапа не настроены дополнительные вложения.</p>
    <?php else: ?>
        <form method="post" enctype="multipart/form-data" class="d-grid gap-3">
            <?= iro_csrf_field_html() ?>
            <?php foreach ($fields as $field): ?>
                <?php $fid = (int) $field['id']; ?>
                <div class="border rounded p-3">
                    <label class="form-label" for="stage_attachment_<?= $fid ?>"><?= h((string) $field['title']) ?></label>
                    <?php if ((string) ($field['requirements'] ?? '') !== ''): ?>
                        <div class="small text-muted mb-1"><?= h((string) $field['requirements']) ?></div>
                    <?php endif; ?>
                    <?php if (isset($existingAttachments[$fid])): ?>
                        <div class="small mb-2">
                            Текущее вложение:
                            <a target="_blank" href="<?= h((string) $existingAttachments[$fid]) ?>">открыть</a>
                        </div>
                    <?php endif; ?>
                    <input class="form-control" id="stage_attachment_<?= $fid ?>" name="stage_attachment_<?= $fid ?>" type="file">
                </div>
            <?php endforeach; ?>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Сохранить вложения</button>
                <a class="btn btn-outline-secondary" href="/contest?id=<?= (int) $submission['contest_id'] ?>">Назад</a>
            </div>
        </form>
    <?php endif; ?>
</section>
