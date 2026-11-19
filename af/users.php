<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/users_admin_data.php';
require_once __DIR__ . '/includes/app_layout.php';

audit_fund_require_admin();

extract(audit_fund_app_layout_vars(), EXTR_SKIP);

$flashUsers = audit_fund_flash_take('users.php');
$roleOk = !empty($flashUsers['role_ok']);
$roleErrCode = isset($flashUsers['role_err']) ? (string) $flashUsers['role_err'] : '';
$roleErrText = '';
if ($roleErrCode === 'csrf') {
    $roleErrText = 'Сессия устарела. Обновите страницу и попробуйте снова.';
} elseif ($roleErrCode === 'invalid') {
    $roleErrText = 'Некорректные данные формы.';
} elseif ($roleErrCode === 'not_found') {
    $roleErrText = 'Пользователь не найден.';
} elseif ($roleErrCode === 'last_admin') {
    $roleErrText = 'Нельзя снять последнего активного администратора.';
} elseif ($roleErrCode === 'db') {
    $roleErrText = 'Не удалось подключиться к базе данных.';
}

$pwdOk = !empty($flashUsers['pwd_ok']);
$pwdErrCode = isset($flashUsers['pwd_err']) ? (string) $flashUsers['pwd_err'] : '';
$pwdErrText = '';
if ($pwdErrCode === 'csrf') {
    $pwdErrText = 'Сессия устарела. Обновите страницу и попробуйте снова.';
} elseif ($pwdErrCode === 'invalid') {
    $pwdErrText = 'Некорректные данные.';
} elseif ($pwdErrCode === 'not_found') {
    $pwdErrText = 'Пользователь не найден.';
} elseif ($pwdErrCode === 'short') {
    $pwdErrText = 'Пароль не короче 8 символов.';
} elseif ($pwdErrCode === 'mismatch') {
    $pwdErrText = 'Пароли не совпадают.';
} elseif ($pwdErrCode === 'db') {
    $pwdErrText = 'Не удалось сохранить пароль.';
}

$blockOk = !empty($flashUsers['block_ok']);
$blockErrCode = isset($flashUsers['block_err']) ? (string) $flashUsers['block_err'] : '';
$blockErrText = '';
if ($blockErrCode === 'csrf') {
    $blockErrText = 'Сессия устарела. Обновите страницу и попробуйте снова.';
} elseif ($blockErrCode === 'invalid') {
    $blockErrText = 'Некорректные данные.';
} elseif ($blockErrCode === 'not_found') {
    $blockErrText = 'Пользователь не найден.';
} elseif ($blockErrCode === 'self') {
    $blockErrText = 'Нельзя заблокировать свою учётную запись.';
} elseif ($blockErrCode === 'last_admin') {
    $blockErrText = 'Нельзя заблокировать единственного активного администратора.';
} elseif ($blockErrCode === 'db') {
    $blockErrText = 'Не удалось изменить статус учётной записи.';
}

$userRows = [];
$listError = null;
try {
    $pdo = audit_fund_pdo();
    $userRows = audit_fund_users_admin_list($pdo);
} catch (Throwable $e) {
    audit_fund_log_exception($e, 'users.php list');
    $listError = 'Не удалось загрузить список пользователей.';
}

$sessionUserId = isset($_SESSION['audit_fund_user_id']) ? (int) $_SESSION['audit_fund_user_id'] : 0;

$auditFundPageTitle = 'Пользователи — Аудиторный фонд';
$auditFundNav = 'users';
require __DIR__ . '/includes/layout_app_open.php';
?>
    <main class="app-main" id="main-content">
      <h1 class="page-title animate-on-load animate-on-load--d1">Пользователи</h1>
      <p class="app-intro animate-on-load animate-on-load--d1">
        Назначение ролей: <strong>менеджер</strong> имеет те же права в системе, что и администратор, кроме этой страницы — назначать менеджеров и менять роли может только администратор.
        Заблокированные учётные записи не могут войти; сессии и «запомнить меня» сбрасываются при смене пароля и при блокировке.
      </p>
      <?php if ($roleOk): ?>
        <p class="form__success app-flash animate-on-load animate-on-load--d1" role="status">Роль сохранена.</p>
      <?php endif; ?>
      <?php if ($roleErrText !== ''): ?>
        <p class="form__error app-flash animate-on-load animate-on-load--d1" role="alert"><?php echo htmlspecialchars($roleErrText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
      <?php endif; ?>
      <?php if ($pwdOk): ?>
        <p class="form__success app-flash animate-on-load animate-on-load--d1" role="status">Пароль изменён. Пользователю нужно войти заново.</p>
      <?php endif; ?>
      <?php if ($pwdErrText !== ''): ?>
        <p class="form__error app-flash animate-on-load animate-on-load--d1" role="alert"><?php echo htmlspecialchars($pwdErrText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
      <?php endif; ?>
      <?php if ($blockOk): ?>
        <p class="form__success app-flash animate-on-load animate-on-load--d1" role="status">Статус учётной записи обновлён.</p>
      <?php endif; ?>
      <?php if ($blockErrText !== ''): ?>
        <p class="form__error app-flash animate-on-load animate-on-load--d1" role="alert"><?php echo htmlspecialchars($blockErrText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
      <?php endif; ?>

      <section class="panel animate-on-load animate-on-load--d2" aria-labelledby="users-panel-title">
        <div class="panel__head">
          <h2 class="panel__title" id="users-panel-title">Учётные записи</h2>
        </div>
        <?php if ($listError !== null): ?>
          <p class="form__error"><?php echo htmlspecialchars($listError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
        <?php else: ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th scope="col">Логин</th>
                <th scope="col">Имя</th>
                <th scope="col">E-mail</th>
                <th scope="col">Статус</th>
                <th scope="col">Роль</th>
                <th scope="col">Действия</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($userRows) === 0): ?>
              <tr>
                <td colspan="6" class="data-table__message">Нет учётных записей.</td>
              </tr>
              <?php endif; ?>
              <?php foreach ($userRows as $uRow): ?>
              <?php
                $uid = (int) ($uRow['id'] ?? 0);
                $uName = (string) ($uRow['username'] ?? '');
                $uDisplay = isset($uRow['display_name']) && trim((string) $uRow['display_name']) !== ''
                  ? trim((string) $uRow['display_name'])
                  : '—';
                $uEmail = $uRow['email'] !== null ? (string) $uRow['email'] : '—';
                $uRole = audit_fund_normalize_role((string) ($uRow['role'] ?? 'user'));
                $uActive = (int) ($uRow['is_active'] ?? 0) === 1;
              ?>
              <tr>
                <td><?php echo htmlspecialchars($uName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($uDisplay, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($uEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                <td><?php echo $uActive ? 'Активна' : 'Заблокирована'; ?></td>
                <td><?php echo htmlspecialchars(audit_fund_role_label($uRole), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                <td class="data-table__cell--user-action-btn">
                  <button
                    type="button"
                    class="btn btn--primary btn--sm"
                    data-modal-open="user-admin"
                    data-user-id="<?php echo $uid; ?>"
                    data-user-login="<?php echo htmlspecialchars($uName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                    data-user-role="<?php echo htmlspecialchars($uRole, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                    data-user-active="<?php echo $uActive ? '1' : '0'; ?>"
                  >Управление…</button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </section>

      <div class="modal is-hidden" id="modal-user-admin" role="dialog" aria-modal="true" aria-labelledby="modal-user-admin-title">
        <div class="modal__overlay" data-modal-close></div>
        <div class="modal__dialog modal__dialog--user-admin" role="document">
          <div class="modal__head modal__head--split">
            <div class="modal__lead">
              <h2 class="modal__title" id="modal-user-admin-title">Учётная запись</h2>
              <p class="modal__meta" id="modal-user-admin-meta"></p>
            </div>
            <button class="modal__close" type="button" data-modal-close aria-label="Закрыть">×</button>
          </div>
          <div class="users-admin-actions" id="user-admin-actions-root">
            <div class="users-admin-actions__block">
              <span class="users-admin-actions__caption">Роль</span>
              <form class="users-admin-actions__form" method="post" action="user_role_update.php">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <input type="hidden" name="user_id" value="" id="user-admin-form-role-user-id">
                <label class="visually-hidden" for="user-admin-role">Роль пользователя</label>
                <div class="users-admin-actions__row">
                  <select class="form__input users-admin-actions__control" name="role" id="user-admin-role">
                    <option value="user">Пользователь</option>
                    <option value="manager">Менеджер</option>
                    <option value="admin">Администратор</option>
                  </select>
                  <button type="submit" class="btn btn--ghost btn--sm users-admin-actions__btn-tight">Применить</button>
                </div>
              </form>
            </div>
            <div class="users-admin-actions__block">
              <span class="users-admin-actions__caption">Пароль</span>
              <form class="users-admin-actions__form" method="post" action="user_password_admin.php" autocomplete="off">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <input type="hidden" name="user_id" value="" id="user-admin-form-password-user-id">
                <div class="users-admin-actions__password-grid">
                  <input class="form__input users-admin-actions__control" type="password" name="new_password" id="user-admin-password-new" placeholder="Новый пароль" minlength="8" autocomplete="new-password" aria-label="Новый пароль">
                  <input class="form__input users-admin-actions__control" type="password" name="new_password_confirm" id="user-admin-password-confirm" placeholder="Повтор" minlength="8" autocomplete="new-password" aria-label="Повтор пароля">
                </div>
                <button type="submit" class="btn btn--primary btn--sm users-admin-actions__btn-wide">Сменить пароль</button>
              </form>
            </div>
            <div class="users-admin-actions__block users-admin-actions__block--subtle is-hidden" id="user-admin-access-active-wrap">
              <span class="users-admin-actions__caption">Доступ</span>
              <form class="users-admin-actions__form" method="post" action="user_block_update.php" id="user-admin-form-block" onsubmit="return confirm('Заблокировать вход для этой учётной записи?');">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <input type="hidden" name="user_id" value="" id="user-admin-form-block-user-id">
                <input type="hidden" name="set_active" value="0">
                <button type="submit" class="btn btn--danger-ghost btn--sm users-admin-actions__btn-wide" id="user-admin-btn-block">Заблокировать вход</button>
              </form>
            </div>
            <div class="users-admin-actions__block users-admin-actions__block--subtle is-hidden" id="user-admin-access-inactive-wrap">
              <span class="users-admin-actions__caption">Доступ</span>
              <form class="users-admin-actions__form" method="post" action="user_block_update.php">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <input type="hidden" name="user_id" value="" id="user-admin-form-unblock-user-id">
                <input type="hidden" name="set_active" value="1">
                <button type="submit" class="btn btn--primary btn--sm users-admin-actions__btn-wide">Разблокировать вход</button>
              </form>
            </div>
          </div>
          <div class="modal__actions">
            <button type="button" class="btn btn--ghost" data-modal-close>Закрыть</button>
          </div>
        </div>
      </div>
    </main>
<?php require __DIR__ . '/includes/layout_app_post_main.php'; ?>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var sessionUserId = <?php echo (int) $sessionUserId; ?>;
      var metaEl = document.getElementById('modal-user-admin-meta');
      var roleSelect = document.getElementById('user-admin-role');
      var hidRole = document.getElementById('user-admin-form-role-user-id');
      var hidPwd = document.getElementById('user-admin-form-password-user-id');
      var hidBlock = document.getElementById('user-admin-form-block-user-id');
      var hidUnblock = document.getElementById('user-admin-form-unblock-user-id');
      var pwdNew = document.getElementById('user-admin-password-new');
      var pwdConfirm = document.getElementById('user-admin-password-confirm');
      var wrapActive = document.getElementById('user-admin-access-active-wrap');
      var wrapInactive = document.getElementById('user-admin-access-inactive-wrap');
      var blockBtn = document.getElementById('user-admin-btn-block');

      document.querySelectorAll('[data-modal-open="user-admin"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var id = btn.getAttribute('data-user-id') || '';
          var login = btn.getAttribute('data-user-login') || '';
          var role = btn.getAttribute('data-user-role') || 'user';
          var active = btn.getAttribute('data-user-active') === '1';

          if (metaEl) {
            metaEl.textContent = login ? ('Логин: ' + login) : '';
          }

          if (hidRole) { hidRole.value = id; }
          if (hidPwd) { hidPwd.value = id; }
          if (hidBlock) { hidBlock.value = id; }
          if (hidUnblock) { hidUnblock.value = id; }

          if (roleSelect) {
            roleSelect.value = role === 'manager' ? 'manager' : (role === 'admin' ? 'admin' : 'user');
          }

          if (pwdNew) { pwdNew.value = ''; }
          if (pwdConfirm) { pwdConfirm.value = ''; }
          var pwdLabel = login ? ('Новый пароль для ' + login) : 'Новый пароль';
          if (pwdNew) { pwdNew.setAttribute('aria-label', pwdLabel); }

          if (wrapActive && wrapInactive) {
            wrapActive.classList.toggle('is-hidden', !active);
            wrapInactive.classList.toggle('is-hidden', active);
          }

          if (blockBtn) {
            var uidNum = parseInt(id, 10) || 0;
            var selfBlock = uidNum > 0 && uidNum === sessionUserId;
            blockBtn.disabled = selfBlock;
            blockBtn.title = selfBlock ? 'Нельзя заблокировать себя' : '';
          }
        }, true);
      });
    });
  </script>
<?php require __DIR__ . '/includes/layout_app_finish.php'; ?>
