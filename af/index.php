<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/sso.php';
require_once __DIR__ . '/includes/yandex_oauth.php';

audit_fund_session_start();

if (audit_fund_is_logged_in()) {
    header('Location: schedule.php');
    exit;
}
if (audit_fund_sso_should_autostart()) {
    audit_fund_sso_begin_peer_check();
}

$flashIndex = audit_fund_flash_take('index.php');
$loginErrFlash = isset($flashIndex['login_error']) ? (string) $flashIndex['login_error'] : '';
$loginErrParam = audit_fund_get_string('error');
$showError = ($loginErrParam === '1') || $loginErrFlash === '1';
$showLockedError = ($loginErrParam === 'locked') || $loginErrFlash === 'locked';
$showCsrfError = ($loginErrParam === 'csrf') || $loginErrFlash === 'csrf';
$showAccountDisabled = audit_fund_get_string('account_disabled') === '1';
$showRegistered = (audit_fund_get_string('registered') === '1') || !empty($flashIndex['registered']);
$showInstalled = audit_fund_get_string('installed') === '1';
$registrationOpen = audit_fund_registration_open();
$authNotice = audit_fund_auth_notice($loginErrParam);
if ($authNotice === '' && $loginErrFlash !== '' && $loginErrFlash !== '1') {
    $authNotice = audit_fund_auth_notice($loginErrFlash);
}
$showYandex = audit_fund_yandex_button_enabled();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <?php require __DIR__ . '/includes/theme_head.php'; ?>
  <title>Вход — Учёт аудиторного фонда</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <a class="skip-link" href="#main-content">К содержимому</a>
  <div class="page">
    <?php require __DIR__ . '/includes/theme_toggle.php'; ?>
    <main class="card" role="main" id="main-content">
      <header class="card__header animate-on-load">
        <p class="card__badge">Аудиторный фонд</p>
        <h1 class="card__title">Вход в систему</h1>
        <p class="card__subtitle">
          Учёт помещений и расписания. Введите данные своей учётной записи или войдите через Яндекс — та же сессия откроет и Вектор.
        </p>
      </header>

      <form class="form animate-on-load" action="login.php" method="post" novalidate>
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
        <?php if ($showInstalled): ?>
          <p class="form__success" role="status">Установка завершена. Войдите под учётной записью администратора. Каталог <code>install/</code> на сервере лучше удалить или закрыть от доступа из сети.</p>
        <?php endif; ?>
        <?php if ($showRegistered): ?>
          <p class="form__success" role="status">Регистрация прошла успешно. Войдите с выбранным логином и паролем.</p>
        <?php endif; ?>
        <?php if ($showCsrfError): ?>
          <p class="form__error" role="alert">Сессия устарела. Обновите страницу и попробуйте снова.</p>
        <?php endif; ?>
        <?php if ($showLockedError): ?>
          <p class="form__error" role="alert">Слишком много неудачных попыток входа. Подождите около 15 минут и попробуйте снова.</p>
        <?php endif; ?>
        <?php if ($showAccountDisabled): ?>
          <p class="form__error" role="alert">Учётная запись отключена. Для доступа обратитесь к администратору.</p>
        <?php endif; ?>
        <?php if ($showError): ?>
          <p class="form__error" role="alert">Неверный логин или пароль.</p>
        <?php endif; ?>
        <?php if ($authNotice !== ''): ?>
          <p class="form__error" role="alert"><?php echo htmlspecialchars($authNotice, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
        <?php endif; ?>

        <div class="form__field">
          <label class="form__label" for="login">Логин или e-mail</label>
          <input
            class="form__input"
            type="text"
            id="login"
            name="login"
            autocomplete="username"
            placeholder="name@organization.ru"
            required
          >
        </div>

        <div class="form__field">
          <label class="form__label" for="password">Пароль</label>
          <input
            class="form__input"
            type="password"
            id="password"
            name="password"
            autocomplete="current-password"
            placeholder="••••••••"
            required
          >
        </div>

        <div class="form__row">
          <label class="form__checkbox">
            <input type="checkbox" name="remember" value="1">
            <span>Запомнить меня</span>
          </label>
          <span class="form__hint form__hint--row">Пароль восстанавливает администратор.</span>
        </div>

        <button class="form__submit" type="submit">Войти</button>
        <?php if ($showYandex): ?>
          <p class="form__divider"><span>или</span></p>
          <a class="form__yandex" href="yandex_start.php">Продолжить через Яндекс</a>
        <?php endif; ?>
      </form>

      <footer class="card__footer animate-on-load">
        <p class="card__footer-note"><a class="form__link" href="schedule_public.php">Расписание без входа</a> — только просмотр занятости.</p>
        <?php if (audit_fund_sso_ready()): ?>
        <p class="card__footer-note"><a class="form__link" href="sso_start.php">Уже вошли в Вектор</a> — открыть эту сессию здесь. Локальный пароль без общей сессии: <a class="form__link" href="index.php?sso=skip">войти отдельно</a>.</p>
        <?php endif; ?>
        <?php if ($registrationOpen): ?>
        <p>Нет аккаунта? <a class="form__link" href="register.php">Зарегистрироваться</a></p>
        <?php else: ?>
        <p class="card__footer-note">Новые учётные записи создаёт администратор.</p>
        <?php endif; ?>
        <p class="card__footer-note">Нужна помощь — обратитесь к администратору организации.</p>
      </footer>
    </main>
  </div>

  <script src="script.js?v=6"></script>
<?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>

