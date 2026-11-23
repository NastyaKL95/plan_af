<section class="iro-panel mx-auto iro-panel--w640">
    <h1 class="iro-page-title mb-3">Регистрация</h1>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= h((string) $error) ?></div>
    <?php endif; ?>
    <?php if (!empty($yandex_oauth_enabled)): ?>
        <div class="d-grid gap-2 mb-3">
            <a class="btn btn-outline-dark" href="/auth/yandex">Войти через Яндекс</a>
        </div>
        <p class="text-center text-muted small mb-3">или создайте аккаунт по email</p>
    <?php endif; ?>
    <form method="post" action="/signup" class="row g-3">
        <?= iro_csrf_field_html() ?>
        <div class="col-md-4">
            <label for="last_name" class="form-label">Фамилия</label>
            <input id="last_name" name="last_name" class="form-control" type="text" autocomplete="family-name">
        </div>
        <div class="col-md-4">
            <label for="first_name" class="form-label">Имя</label>
            <input id="first_name" name="first_name" class="form-control" type="text" autocomplete="given-name">
        </div>
        <div class="col-md-4">
            <label for="middle_name" class="form-label">Отчество</label>
            <input id="middle_name" name="middle_name" class="form-control" type="text" autocomplete="additional-name">
        </div>
        <div class="col-12">
            <label for="email" class="form-label">Email</label>
            <input id="email" name="email" class="form-control" type="email" required autocomplete="email">
        </div>
        <div class="col-12">
            <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-1">
                <label for="password" class="form-label mb-0">Пароль</label>
                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" data-iro-password-toggle="password" aria-pressed="false" aria-label="Показать или скрыть пароль">
                    <span class="iro-pw-toggle-text">Показать</span>
                </button>
            </div>
            <input id="password" name="password" class="form-control" type="password" minlength="8" required autocomplete="new-password">
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">Создать аккаунт</button>
        </div>
    </form>
</section>
