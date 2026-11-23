<section class="iro-panel iro-panel-narrow mx-auto">
    <h1 class="iro-page-title mb-3">Профиль</h1>
    <?php if (!empty($profile_is_sparse)): ?>
        <p class="alert alert-secondary small">Заполненность профиля минимальна — рекомендуем указать ФИО и контактные данные в разделе редактирования.</p>
    <?php endif; ?>
    <dl class="row mb-0 iro-def-list">
        <dt class="col-sm-4">Email</dt>
        <dd class="col-sm-8"><?= h((string) ($profile_user['email'] ?? '')) ?></dd>
        <dt class="col-sm-4">ФИО</dt>
        <dd class="col-sm-8"><?= h(user_display_name($profile_user)) ?></dd>
        <dt class="col-sm-4">Дата рождения</dt>
        <dd class="col-sm-8"><?= !empty($profile_user['date_of_birth']) ? h((string) $profile_user['date_of_birth']) : '—' ?></dd>
        <dt class="col-sm-4">Регион</dt>
        <dd class="col-sm-8"><?= h((string) ($profile_user['region'] ?? '') ?: '—') ?></dd>
        <dt class="col-sm-4">Муниципалитет</dt>
        <dd class="col-sm-8"><?= h((string) ($profile_user['municipality'] ?? '') ?: '—') ?></dd>
        <dt class="col-sm-4">Учебное заведение</dt>
        <dd class="col-sm-8"><?= h((string) ($profile_user['school'] ?? '') ?: '—') ?></dd>
    </dl>
    <?php if (!empty($is_own)): ?>
        <div class="mt-4">
            <a href="/profile" class="btn btn-primary">Редактировать профиль</a>
        </div>
    <?php endif; ?>
</section>
