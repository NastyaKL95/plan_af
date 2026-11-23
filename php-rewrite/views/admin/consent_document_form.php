<section>
    <h1 class="iro-page-title mb-3"><?= !empty($doc) ? 'Редактировать шаблон' : 'Новый шаблон согласия' ?></h1>
    <p class="text-muted"><a href="/admin/consent-documents" class="text-decoration-none">← К списку</a></p>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $e): ?>
                    <li><?= h((string) $e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php
    $d = $doc ?? null;
    $activeChecked = is_post() ? isset($_POST['is_active']) : (!$d || !empty($d['is_active']));
    ?>

    <form method="post" enctype="multipart/form-data" class="iro-panel p-4 iro-form--w36r">
        <?= iro_csrf_field_html() ?>
        <?php if ($d): ?>
            <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
        <?php endif; ?>
        <div class="mb-3">
            <label class="form-label">Название</label>
            <input type="text" name="title" class="form-control" required value="<?= h((string) ($d['title'] ?? ($_POST['title'] ?? ''))) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Файл<?= $d ? ' (оставьте пустым, чтобы не менять)' : '' ?></label>
            <input type="file" name="file" class="form-control" <?= $d ? '' : 'required' ?>>
            <?php if ($d && !empty($d['file_path'])): ?>
                <p class="small text-muted mt-2 mb-0">Сейчас: <a href="<?= h((string) $d['file_path']) ?>" target="_blank" rel="noopener"><?= h(basename((string) $d['file_path'])) ?></a></p>
            <?php endif; ?>
        </div>
        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="is_active" id="act" value="1" <?= $activeChecked ? 'checked' : '' ?>>
            <label class="form-check-label" for="act">Активен (показывать участникам)</label>
        </div>
        <button type="submit" class="btn btn-primary rounded-pill px-4">Сохранить</button>
    </form>
</section>
