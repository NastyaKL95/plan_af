<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/app_layout.php';
require_once __DIR__ . '/includes/flash_messages.php';

audit_fund_require_auth();

extract(audit_fund_app_layout_vars(), EXTR_SKIP);

$flashProfile = audit_fund_flash_take('profile.php');
$profileOk = !empty($flashProfile['profile_ok']);
$profileErrCode = isset($flashProfile['profile_err']) ? (string) $flashProfile['profile_err'] : '';
$profileErrText = audit_fund_flash_text($profileErrCode, audit_fund_flash_messages_profile(), '');
if ($profileErrCode !== '' && $profileErrText === '') {
    $profileErrText = 'Не удалось сохранить имя. Попробуйте ещё раз.';
}

$userId = isset($_SESSION['audit_fund_user_id']) ? (int) $_SESSION['audit_fund_user_id'] : 0;
$profileUsername = '';
$profileDisplayName = '';
$profileEmail = '';
$profileLoadError = null;

try {
    $pdo = audit_fund_pdo();
    audit_fund_users_ensure_role_column($pdo);
    $stmt = $pdo->prepare(
        'SELECT username, display_name, email FROM users WHERE id = ? AND is_active = 1 LIMIT 1'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        $profileLoadError = 'Учётная запись не найдена.';
    } else {
        $profileUsername = (string) ($row['username'] ?? '');
        $profileDisplayName = isset($row['display_name']) && $row['display_name'] !== null
            ? trim((string) $row['display_name'])
            : '';
        $profileEmail = isset($row['email']) && $row['email'] !== null ? (string) $row['email'] : '';
    }
} catch (Throwable $e) {
    audit_fund_log_exception($e, 'profile.php load');
    $profileLoadError = 'Не удалось загрузить данные профиля.';
}

$auditFundPageTitle = 'Профиль — Аудиторный фонд';
$auditFundNav = 'profile';
require __DIR__ . '/includes/layout_app_open.php';
?>
    <main class="app-main" id="main-content">
      <h1 class="page-title animate-on-load animate-on-load--d1">Профиль</h1>
      <p class="app-intro animate-on-load animate-on-load--d1">
        Имя отображается в шапке, в списке заявок и в журнале аудита. Логин используется только для входа и не меняется здесь.
      </p>
      <?php if ($profileOk): ?>
        <p class="form__success app-flash animate-on-load animate-on-load--d1" role="status">Имя сохранено.</p>
      <?php endif; ?>
      <?php if ($profileErrText !== ''): ?>
        <p class="form__error app-flash animate-on-load animate-on-load--d1" role="alert"><?php echo htmlspecialchars($profileErrText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
      <?php endif; ?>

      <section class="panel animate-on-load animate-on-load--d2" aria-labelledby="profile-panel-title">
        <h2 class="panel__title panel__title--block" id="profile-panel-title">Отображаемое имя</h2>
        <?php if ($profileLoadError !== null): ?>
          <p class="form__error"><?php echo htmlspecialchars($profileLoadError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
        <?php else: ?>
        <form class="form form--compact" method="post" action="profile_update.php" novalidate>
          <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
          <div class="form__field">
            <span class="form__label">Логин</span>
            <p class="form__hint" id="profile-login-readonly"><?php echo htmlspecialchars($profileUsername, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
          </div>
          <?php if ($profileEmail !== ''): ?>
          <div class="form__field">
            <span class="form__label">E-mail</span>
            <p class="form__hint"><?php echo htmlspecialchars($profileEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
          </div>
          <?php endif; ?>
          <div class="form__field">
            <label class="form__label" for="profile-display-name">Имя</label>
            <input
              class="form__input"
              type="text"
              id="profile-display-name"
              name="display_name"
              autocomplete="name"
              maxlength="191"
              value="<?php echo htmlspecialchars($profileDisplayName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
              required
            >
            <p class="form__hint">До 191 символа: буквы, цифры, пробел, апостроф, точка и дефис.</p>
          </div>
          <button class="btn btn--primary" type="submit">Сохранить имя</button>
        </form>
        <?php endif; ?>
      </section>
    </main>
<?php require __DIR__ . '/includes/layout_app_post_main.php'; ?>
<?php require __DIR__ . '/includes/layout_app_finish.php'; ?>
