<?php
$u = is_array($edit_user ?? null) ? $edit_user : [];
$uid = (int) ($u['id'] ?? 0);
$checked = array_fill_keys((array) ($edit_user_role_slugs ?? []), true);
$fio = trim(
    trim((string) ($u['last_name'] ?? '')) . ' ' .
    trim((string) ($u['first_name'] ?? '')) . ' ' .
    trim((string) ($u['middle_name'] ?? ''))
);
$rp = (int) ($return_page ?? 1);
$rq = (string) ($return_q ?? '');
$backHref = '/admin/users?page=' . $rp . ($rq !== '' ? '&q=' . rawurlencode($rq) : '');
?>
<section>
    <h1 class="iro-page-title mb-3">Редактирование профиля</h1>
    <p class="text-muted">
        <a href="<?= h($backHref) ?>" class="text-decoration-none">← К списку пользователей</a>
    </p>
    <p class="text-muted small mb-4">
        <?= $fio !== '' ? h($fio) : 'Без ФИО' ?>
        <span class="text-muted">·</span>
        <span class="fw-semibold text-body"><?= h((string) ($u['email'] ?? '')) ?></span>
    </p>

    <div class="iro-panel iro-stage-form-panel border-0 shadow-sm iro-panel-narrow">
        <form method="post" action="/admin/users/edit" class="iro-contest-form mb-0">
            <?= iro_csrf_field_html() ?>
            <input type="hidden" name="user_id" value="<?= $uid ?>">
            <input type="hidden" name="return_page" value="<?= $rp ?>">
            <input type="hidden" name="return_q" value="<?= h($rq) ?>">

            <fieldset class="iro-stage-form-section">
                <legend>Роли</legend>
                <p class="small text-muted mb-3">
                    Здесь только роль администратора сайта. Участие в конкурсе — после согласия и записи в заявках;
                    модераторы и эксперты — в карточке конкурса и этапов.
                </p>
                <div class="iro-user-roles d-flex flex-wrap align-items-stretch gap-3">
                    <?php foreach (($roles ?? []) as $r): ?>
                        <?php
                        $slug = (string) $r['slug'];
                        if (!in_array($slug, admin_editable_site_role_slugs(), true)) {
                            continue;
                        }
                        $chkId = 'role-edit-' . $uid . '-' . preg_replace('/[^a-z0-9_-]/i', '_', $slug);
                        ?>
                        <div class="iro-role-check d-inline-flex align-items-center gap-2 px-3 py-2">
                            <input class="form-check-input flex-shrink-0 m-0" type="checkbox"
                                name="roles[]" value="<?= h($slug) ?>" id="<?= h($chkId) ?>"
                                <?= !empty($checked[$slug]) ? 'checked' : '' ?>>
                            <label class="form-check-label small mb-0 fw-semibold" for="<?= h($chkId) ?>"><?= h((string) $r['name']) ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <fieldset class="iro-stage-form-section">
                <legend>Доступ</legend>
                <input type="hidden" name="is_active_present" value="1">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="user-is-active-<?= $uid ?>"
                        <?= (int) ($u['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="user-is-active-<?= $uid ?>">Учётная запись активна (может входить на сайт)</label>
                </div>
                <p class="small text-muted mt-2 mb-0">При отключении все сессии пользователя завершаются при следующем запросе.</p>
            </fieldset>

            <fieldset class="iro-stage-form-section">
                <legend>Пароль</legend>
                <p class="small text-muted mb-3">Оставьте поля пустыми, если менять пароль не нужно. Минимум 8 символов.</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="new_password">Новый пароль</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" autocomplete="new-password" placeholder="Не менять — оставьте пустым">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="new_password_confirm">Подтверждение</label>
                        <input type="password" class="form-control" id="new_password_confirm" name="new_password_confirm" autocomplete="new-password" placeholder="Повтор нового пароля">
                    </div>
                </div>
            </fieldset>

            <div class="iro-stage-form-actions mt-2">
                <button type="submit" class="btn btn-primary rounded-pill px-4">Сохранить</button>
                <a href="<?= h($backHref) ?>" class="btn btn-outline-secondary rounded-pill px-4">Отмена</a>
            </div>
        </form>
    </div>
</section>
