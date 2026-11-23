<section class="iro-panel iro-panel-narrow mx-auto">
    <h1 class="iro-page-title mb-3">Профиль</h1>
    <?php if (!empty($profile['id'])): ?>
        <p class="small text-muted mb-3">
            <a href="/profile/<?= (int) $profile['id'] ?>/">Как видят другие</a>
        </p>
    <?php endif; ?>
    <form method="post" action="/profile" class="row g-3">
        <?= iro_csrf_field_html() ?>
        <div class="col-md-4">
            <label for="last_name" class="form-label">Фамилия</label>
            <input id="last_name" name="last_name" class="form-control" type="text" value="<?= h((string) ($profile['last_name'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
            <label for="first_name" class="form-label">Имя</label>
            <input id="first_name" name="first_name" class="form-control" type="text" value="<?= h((string) ($profile['first_name'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
            <label for="middle_name" class="form-label">Отчество</label>
            <input id="middle_name" name="middle_name" class="form-control" type="text" value="<?= h((string) ($profile['middle_name'] ?? '')) ?>">
        </div>
        <div class="col-md-6">
            <label for="region" class="form-label">Регион</label>
            <input id="region" name="region" class="form-control" type="text" value="<?= h((string) ($profile['region'] ?? '')) ?>">
        </div>
        <div class="col-md-6">
            <label for="municipality" class="form-label">Муниципалитет</label>
            <input id="municipality" name="municipality" class="form-control" type="text" value="<?= h((string) ($profile['municipality'] ?? '')) ?>">
        </div>
        <div class="col-12">
            <label for="school" class="form-label">Учебное заведение</label>
            <input id="school" name="school" class="form-control" type="text" value="<?= h((string) ($profile['school'] ?? '')) ?>">
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">Сохранить</button>
        </div>
    </form>
</section>
