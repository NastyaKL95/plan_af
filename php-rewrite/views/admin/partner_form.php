<section>
    <h1 class="iro-page-title mb-3"><?= !empty($partner) ? 'Редактировать партнёра' : 'Новый партнёр' ?></h1>
    <p class="text-muted"><a href="/admin/partners" class="text-decoration-none">← К списку</a></p>

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
    $p = $partner ?? null;
    $activeChecked = is_post() ? isset($_POST['is_active']) : (!$p || !empty($p['is_active']));
    $sortVal = is_post()
        ? (int) ($_POST['sort_order'] ?? 0)
        : ($p ? (int) ($p['sort_order'] ?? 0) : (int) ($default_sort_order ?? 0));
    ?>

    <form method="post" enctype="multipart/form-data" class="iro-panel p-4 iro-form--w36r">
        <?= iro_csrf_field_html() ?>
        <?php if ($p): ?>
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
        <?php endif; ?>
        <div class="mb-3">
            <label class="form-label">Название</label>
            <input type="text" name="title" class="form-control" required value="<?= h((string) ($p['title'] ?? ($_POST['title'] ?? ''))) ?>">
            <div class="form-text">Подпись для подсказки и доступности (alt).</div>
        </div>
        <div class="mb-3">
            <label class="form-label">Ссылка на сайт</label>
            <input type="url" name="website_url" class="form-control" placeholder="https://…"
                   value="<?= h((string) ($p['website_url'] ?? ($_POST['website_url'] ?? ''))) ?>">
            <div class="form-text">Необязательно: при пустом поле логотип не будет ссылкой.</div>
        </div>
        <div class="mb-3">
            <label class="form-label">Порядок сортировки</label>
            <input type="number" name="sort_order" class="form-control" style="max-width:12rem" value="<?= (int) $sortVal ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Логотип<?= $p ? ' (оставьте пустым, чтобы не менять)' : '' ?></label>
            <input type="file" name="logo" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif" <?= $p ? '' : 'required' ?>>
            <?php if ($p && !empty($p['logo_path'])): ?>
                <p class="small text-muted mt-2 mb-0">Сейчас: <a href="<?= h((string) $p['logo_path']) ?>" target="_blank" rel="noopener">открыть файл</a></p>
            <?php endif; ?>
        </div>
        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="is_active" id="pact" value="1" <?= $activeChecked ? 'checked' : '' ?>>
            <label class="form-check-label" for="pact">Показывать на главной</label>
        </div>
        <button type="submit" class="btn btn-primary rounded-pill px-4">Сохранить</button>
    </form>
</section>
