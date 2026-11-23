<section class="iro-panel mx-auto iro-panel--w520">
    <h1 class="iro-page-title mb-3">Новый пароль</h1>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= h((string) $error) ?></div>
    <?php endif; ?>
    <?php if (!empty($token_valid)): ?>
    <form method="post" action="/reset-password" class="d-grid gap-3">
        <?= iro_csrf_field_html() ?>
        <input type="hidden" name="token" value="<?= h((string) $token) ?>">
        <div>
            <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-1">
                <label for="password" class="form-label mb-0">Новый пароль</label>
                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" data-iro-password-toggle="password" aria-pressed="false" aria-label="Показать или скрыть пароль">
                    <span class="iro-pw-toggle-text">Показать</span>
                </button>
            </div>
            <input id="password" name="password" type="password" class="form-control" minlength="8" required autocomplete="new-password">
        </div>
        <div>
            <label for="password_confirm" class="form-label">Повтор пароля</label>
            <input id="password_confirm" name="password_confirm" type="password" class="form-control" minlength="8" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary">Сохранить пароль</button>
    </form>
    <?php else: ?>
        <p class="mb-0"><a href="/forgot-password">Запросить новую ссылку</a> · <a href="/login">Вход</a></p>
    <?php endif; ?>
</section>
