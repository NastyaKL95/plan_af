<section>
    <h1 class="iro-page-title mb-3"><?= !empty($series) ? 'Редактировать серию' : 'Новая серия' ?></h1>
    <p class="text-muted"><a href="/admin/series" class="text-decoration-none">← К списку</a></p>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $e): ?>
                    <li><?= h((string) $e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php $s = $series ?? null; ?>

    <form method="post" enctype="multipart/form-data" class="iro-panel p-4 iro-form--w40r">
        <?= iro_csrf_field_html() ?>
        <?php if ($s): ?>
            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
        <?php endif; ?>
        <div class="mb-3">
            <label class="form-label">Название</label>
            <input type="text" name="title" class="form-control" required value="<?= h((string) ($s['title'] ?? ($_POST['title'] ?? ''))) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Slug (необязательно)</label>
            <input type="text" name="slug" class="form-control" value="<?= h((string) ($s['slug'] ?? ($_POST['slug'] ?? ''))) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Описание</label>
            <textarea name="description" class="form-control" rows="5"><?= h((string) ($s['description'] ?? ($_POST['description'] ?? ''))) ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Порядок сортировки</label>
            <input type="number" name="sort_order" class="form-control" value="<?= h((string) ($s['sort_order'] ?? ($_POST['sort_order'] ?? '0'))) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Обложка (изображение)</label>
            <input type="file" name="cover" class="form-control" accept="image/*">
            <?php if ($s && !empty($s['cover_image_path'])): ?>
                <p class="small text-muted mt-2 mb-0">Текущая: <a href="<?= h((string) $s['cover_image_path']) ?>" target="_blank" rel="noopener">открыть</a></p>
            <?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary rounded-pill px-4">Сохранить</button>
    </form>
</section>
