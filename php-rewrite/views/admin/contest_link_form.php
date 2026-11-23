<?php
$isEdit = ($mode ?? 'create') === 'edit';
$c = $contest ?? [];
?>
<section>
    <h1 class="iro-page-title mb-2"><?= $isEdit ? 'Редактировать конкурс-ссылку' : 'Создать конкурс-ссылку' ?></h1>
    <p class="iro-lead mb-4 text-muted small">
        Анонс с датами и переходом на страницу конкурса на другом сайте. Участие и этапы на этой площадке не оформляются.
    </p>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $e): ?>
                    <li><?= h((string) $e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="iro-panel iro-stage-form-panel border-0 shadow-sm">
        <form method="post" enctype="multipart/form-data" class="iro-contest-form" action="<?= $isEdit && !empty($c['id']) ? h('/admin/contests/edit-link?id=' . (int) $c['id']) : h('/admin/contests/create-link') ?>">
            <?= iro_csrf_field_html() ?>
            <?php if ($isEdit && !empty($c['id'])): ?>
                <input type="hidden" name="contest_id" value="<?= (int) $c['id'] ?>">
            <?php endif; ?>
            <fieldset class="iro-stage-form-section mb-0">
                <legend>Параметры</legend>
            <div class="mb-3">
                <label class="form-label" for="title">Название</label>
                <input class="form-control" id="title" name="title" value="<?= h((string) ($c['title'] ?? ($_POST['title'] ?? ''))) ?>" required>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label" for="start_date">Дата начала</label>
                    <input class="form-control" type="date" id="start_date" name="start_date" value="<?= h((string) ($c['start_date'] ?? ($_POST['start_date'] ?? ''))) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="end_date">Дата окончания</label>
                    <input class="form-control" type="date" id="end_date" name="end_date" value="<?= h((string) ($c['end_date'] ?? ($_POST['end_date'] ?? ''))) ?>" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="description">Описание</label>
                <textarea class="form-control" id="description" name="description" rows="5" required><?= h((string) ($c['description'] ?? ($_POST['description'] ?? ''))) ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label" for="external_url">Ссылка на конкурс</label>
                <input class="form-control" type="url" id="external_url" name="external_url" inputmode="url" autocomplete="url"
                       placeholder="https://example.org/contest"
                       value="<?= h((string) ($c['external_url'] ?? ($_POST['external_url'] ?? ''))) ?>" required>
                <div class="form-text">Полный адрес страницы, где проходит или анонсируется конкурс.</div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="status">Статус публикации</label>
                <?php $st = (int) ($c['status'] ?? ($_POST['status'] ?? 1)); ?>
                <select class="form-select" id="status" name="status">
                    <option value="1" <?= $st === 1 ? 'selected' : '' ?>>Черновик</option>
                    <option value="2" <?= $st === 2 ? 'selected' : '' ?>>Опубликован</option>
                    <option value="3" <?= $st === 3 ? 'selected' : '' ?>>Отменён</option>
                    <option value="4" <?= $st === 4 ? 'selected' : '' ?>>Скрытый</option>
                    <option value="5" <?= $st === 5 ? 'selected' : '' ?>>Только в серии</option>
                </select>
                <div class="form-text">
                    «Только в серии» требует выбранную серию. На главной и в списке «Конкурсы» отображаются записи со статусом «Опубликован».
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="series_id">Серия конкурсов</label>
                <?php $sid = (int) ($c['series_id'] ?? ($_POST['series_id'] ?? 0)); ?>
                <select class="form-select" id="series_id" name="series_id">
                    <option value="0">— без серии —</option>
                    <?php foreach (($series ?? []) as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === $sid ? 'selected' : '' ?>>
                            <?= h((string) $s['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label" for="image">Обложка (необязательно)</label>
                <input class="form-control" type="file" id="image" name="image" accept="image/*">
                <?php if (!empty($c['image_path'])): ?>
                    <div class="small mt-2">
                        Текущая обложка: <a href="<?= h((string) $c['image_path']) ?>" target="_blank" rel="noopener">открыть</a>
                    </div>
                <?php endif; ?>
            </div>

            </fieldset>

            <div class="iro-stage-form-actions mt-2">
                <button type="submit" class="btn btn-primary rounded-pill px-4"><?= $isEdit ? 'Сохранить' : 'Создать' ?></button>
                <a href="/admin/contests" class="btn btn-outline-secondary rounded-pill px-4">Отмена</a>
            </div>
        </form>
    </div>
</section>
