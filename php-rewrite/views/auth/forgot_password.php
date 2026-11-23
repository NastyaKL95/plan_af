<section class="iro-panel mx-auto iro-panel--w520">
    <h1 class="iro-page-title mb-3">Восстановление пароля</h1>
    <p class="text-muted small mb-3">Укажите email учётной записи. Если она есть в системе, отправим ссылку для задания нового пароля.</p>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= h((string) $error) ?></div>
    <?php endif; ?>
    <?php if (!empty($info)): ?>
        <div class="alert alert-info"><?= h((string) $info) ?></div>
    <?php endif; ?>
    <?php if (!empty($reset_ready)): ?>
    <form method="post" action="/forgot-password" class="d-grid gap-3">
        <?= iro_csrf_field_html() ?>
        <div>
            <label for="email" class="form-label">Email</label>
            <input id="email" name="email" type="email" class="form-control" required autocomplete="email">
        </div>
        <button type="submit" class="btn btn-primary">Отправить ссылку</button>
    </form>
    <?php endif; ?>
    <p class="small text-muted mt-3 mb-0">
        <a href="/login">Вернуться ко входу</a>
        <?php if (empty($mail_enabled) && !empty($reset_ready)): ?>
            · для отправки писем нужно включить блок <code>mail</code> в config
        <?php endif; ?>
    </p>
</section>
