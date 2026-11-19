<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/flash_messages.php';

audit_fund_session_start();

if (audit_fund_is_logged_in()) {
    header('Location: schedule.php');
    exit;
}

$registrationOpen = audit_fund_registration_open();
$flashReg = audit_fund_flash_take('register.php');
$regErrGet = audit_fund_get_string('error');
$error = $regErrGet !== ''
    ? $regErrGet
    : (isset($flashReg['register_error']) ? (string) $flashReg['register_error'] : '');
$errorText = audit_fund_flash_text($error, audit_fund_flash_messages_register(), '');
if ($error !== '' && $errorText === '') {
    $errorText = 'Не удалось зарегистрироваться. Попробуйте ещё раз.';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <?php require __DIR__ . '/includes/theme_head.php'; ?>
  <title>Регистрация — Учёт аудиторного фонда</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <a class="skip-link" href="#main-content">К содержимому</a>
  <div class="page">
    <?php require __DIR__ . '/includes/theme_toggle.php'; ?>
    <main class="card card--wide" role="main" id="main-content">
      <header class="card__header animate-on-load">
        <p class="card__badge">Аудиторный фонд</p>
        <h1 class="card__title">Регистрация</h1>
        <p class="card__subtitle">
          <?php echo $registrationOpen
            ? 'Создайте учётную запись для входа в систему учёта помещений и расписания.'
            : 'Открытая регистрация выключена — учётные записи выдаёт администратор.'; ?>
        </p>
      </header>

      <?php if (!$registrationOpen): ?>
        <?php if ($errorText !== ''): ?>
          <p class="form__error" role="alert"><?php echo htmlspecialchars($errorText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
        <?php endif; ?>
        <p class="form__success" role="status">Создание учётных записей через эту страницу недоступно. Воспользуйтесь входом или обратитесь к администратору.</p>
      <?php else: ?>
      <form class="form animate-on-load" action="register_process.php" method="post" novalidate>
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
        <?php if ($errorText !== ''): ?>
          <p class="form__error" role="alert"><?php echo htmlspecialchars($errorText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
        <?php endif; ?>

        <div class="form__field">
          <label class="form__label" for="reg-display-name">Имя</label>
          <input
            class="form__input"
            type="text"
            id="reg-display-name"
            name="display_name"
            autocomplete="name"
            maxlength="191"
            placeholder="Иванов Иван Иванович"
            required
          >
          <p class="form__hint">Как вас показывать в системе (заявки, журнал). До 191 символа: буквы, цифры, пробел, <code>'</code> <code>.</code> <code>-</code></p>
        </div>

        <div class="form__field">
          <label class="form__label" for="reg-username">Логин</label>
          <input
            class="form__input"
            type="text"
            id="reg-username"
            name="username"
            autocomplete="username"
            maxlength="64"
            placeholder="ivanov"
            required
          >
          <p class="form__hint">3–64 символа: латиница, цифры, <code>.</code> <code>-</code> <code>_</code></p>
        </div>

        <div class="form__field">
          <label class="form__label" for="reg-email">E-mail <span class="form__optional">необязательно</span></label>
          <input
            class="form__input"
            type="email"
            id="reg-email"
            name="email"
            autocomplete="email"
            maxlength="255"
            placeholder="name@organization.ru"
          >
        </div>

        <div class="form__field">
          <label class="form__label" for="reg-password">Пароль</label>
          <input
            class="form__input"
            type="password"
            id="reg-password"
            name="password"
            autocomplete="new-password"
            minlength="8"
            placeholder="••••••••"
            required
          >
        </div>

        <div class="form__field">
          <label class="form__label" for="reg-password2">Пароль ещё раз</label>
          <input
            class="form__input"
            type="password"
            id="reg-password2"
            name="password_confirm"
            autocomplete="new-password"
            minlength="8"
            placeholder="••••••••"
            required
          >
        </div>

        <button class="form__submit" type="submit">Зарегистрироваться</button>
      </form>
      <?php endif; ?>

      <footer class="card__footer animate-on-load">
        <?php if ($registrationOpen): ?>
        <p>Уже есть аккаунт? <a class="form__link" href="index.php">Войти</a></p>
        <?php else: ?>
        <p><a class="form__link" href="index.php">На страницу входа</a></p>
        <?php endif; ?>
      </footer>
    </main>
  </div>

  <script src="script.js?v=6"></script>
<?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>
