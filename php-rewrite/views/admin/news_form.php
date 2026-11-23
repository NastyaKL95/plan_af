<section>
    <h1 class="iro-page-title mb-3"><?= !empty($news) ? 'Редактировать новость' : 'Новая новость' ?></h1>
    <p class="text-muted"><a href="/admin/news" class="text-decoration-none">← К списку</a></p>

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
    $n = $news ?? null;
    $pubVal = (string) ($_POST['published_at'] ?? '');
    if ($pubVal === '' && $n && !empty($n['published_at'])) {
        $pubVal = date('Y-m-d\TH:i', strtotime((string) $n['published_at']));
    }
    ?>

    <?php
    $pubChecked = is_post() ? isset($_POST['is_published']) : ($n && !empty($n['is_published']));
    $pinChecked = is_post() ? isset($_POST['is_pinned']) : ($n && !empty($n['is_pinned']));
    ?>
    <form method="post" class="iro-panel p-4 iro-form--w48r">
        <?= iro_csrf_field_html() ?>
        <?php if ($n): ?>
            <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
        <?php endif; ?>
        <div class="mb-3">
            <label class="form-label">Заголовок</label>
            <input type="text" name="title" class="form-control" required value="<?= h((string) ($n['title'] ?? ($_POST['title'] ?? ''))) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Slug (необязательно, иначе из заголовка)</label>
            <input type="text" name="slug" class="form-control" value="<?= h((string) ($n['slug'] ?? ($_POST['slug'] ?? ''))) ?>" placeholder="латиница-или-транслит">
        </div>
        <div class="mb-3">
            <label class="form-label">Краткое описание</label>
            <input type="text" name="summary" class="form-control" maxlength="500" value="<?= h((string) ($n['summary'] ?? ($_POST['summary'] ?? ''))) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Текст</label>
            <textarea name="body" class="form-control" rows="12" required><?= h((string) ($n['body'] ?? ($_POST['body'] ?? ''))) ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Дата публикации</label>
            <input type="datetime-local" name="published_at" class="form-control" value="<?= h($pubVal) ?>">
        </div>
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="is_published" id="np" value="1" <?= $pubChecked ? 'checked' : '' ?>>
            <label class="form-check-label" for="np">Опубликовано</label>
        </div>
        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="is_pinned" id="pin" value="1" <?= $pinChecked ? 'checked' : '' ?>>
            <label class="form-check-label" for="pin">Закрепить на главной</label>
        </div>
        <button type="submit" class="btn btn-primary rounded-pill px-4">Сохранить</button>
    </form>
</section>
