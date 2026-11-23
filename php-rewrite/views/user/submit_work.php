<section class="iro-panel iro-panel-narrow mx-auto">
    <h1 class="iro-page-title mb-3">Подача работы</h1>
    <p class="text-muted">Конкурс: <strong><?= h((string) $contest['title']) ?></strong></p>
    <p class="text-muted">Этап: <strong><?= h((string) $stage['title']) ?></strong></p>
    <p class="text-muted">Дедлайн подачи: <strong><?= h((string) $effectiveDeadline) ?></strong></p>

    <form method="post" enctype="multipart/form-data" class="d-grid gap-3">
        <?= iro_csrf_field_html() ?>
        <div>
            <label class="form-label" for="title">Название работы</label>
            <input class="form-control" id="title" name="title" type="text" required>
        </div>
        <div>
            <label class="form-label" for="work_link">Ссылка на работу (опционально)</label>
            <input class="form-control" id="work_link" name="work_link" type="url" placeholder="https://...">
        </div>
        <div>
            <label class="form-label" for="file">Файл работы</label>
            <input class="form-control" id="file" name="file" type="file" required>
        </div>
        <?php if (!empty($stageAttachmentFields)): ?>
            <div class="border rounded p-3">
                <h2 class="h6 mb-2">Дополнительные вложения этапа</h2>
                <?php foreach ($stageAttachmentFields as $field): ?>
                    <div class="mb-3">
                        <label class="form-label" for="stage_attachment_<?= (int) $field['id'] ?>">
                            <?= h((string) $field['title']) ?>
                        </label>
                        <?php if ((string) ($field['requirements'] ?? '') !== ''): ?>
                            <div class="small text-muted mb-1"><?= h((string) $field['requirements']) ?></div>
                        <?php endif; ?>
                        <input
                            class="form-control"
                            id="stage_attachment_<?= (int) $field['id'] ?>"
                            name="stage_attachment_<?= (int) $field['id'] ?>"
                            type="file"
                        >
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" type="submit">Отправить работу</button>
            <a class="btn btn-outline-secondary" href="/contest?id=<?= (int) $contest['id'] ?>">Назад</a>
        </div>
    </form>
</section>
