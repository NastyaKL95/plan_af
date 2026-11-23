<section class="iro-panel mx-auto iro-panel--w520">
    <h1 class="iro-page-title mb-3">Вход</h1>
    <?php if (!empty($external_auth_login_note)): ?>
        <p class="small text-muted mb-3"><?= h((string) $external_auth_login_note) ?></p>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= h((string) $error) ?></div>
    <?php endif; ?>

    <?php if (!empty($yandex_oauth_enabled)): ?>
        <div class="d-grid gap-2 mb-3">
            <a class="btn btn-outline-dark" href="/auth/yandex">Войти через Яндекс</a>
        </div>
        <p class="text-center text-muted small mb-3">или по email и паролю</p>
    <?php endif; ?>

    <form method="post" action="/login" class="d-grid gap-3">
        <?= iro_csrf_field_html() ?>
        <div>
            <label for="email" class="form-label"><?= h((string) ($login_username_label ?? 'Email')) ?></label>
            <input id="email" name="email" type="<?= !empty($login_username_input_text) ? 'text' : 'email' ?>" class="form-control" required autocomplete="username" inputmode="<?= !empty($login_username_input_text) ? 'text' : 'email' ?>">
        </div>
        <div>
            <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-1">
                <label for="password" class="form-label mb-0">Пароль</label>
                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" data-iro-password-toggle="password" aria-pressed="false" aria-label="Показать или скрыть пароль">
                    <span class="iro-pw-toggle-text">Показать</span>
                </button>
            </div>
            <input id="password" name="password" type="password" class="form-control" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-primary">Войти</button>
    </form>
    <p class="small text-muted mt-3 mb-0">
        <?php if (!empty($password_reset_available)): ?>
            <a href="/forgot-password">Забыли пароль?</a>
        <?php else: ?>
            Забыли пароль? Напишите на <a href="mailto:cit@iro75.ru">cit@iro75.ru</a>.
        <?php endif; ?>
        · <a href="/signup">Регистрация</a>
    </p>
</section>
