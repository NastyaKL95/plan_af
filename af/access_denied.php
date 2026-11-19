<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';

audit_fund_session_start();

if (!audit_fund_is_logged_in()) {
    header('Location: index.php');
    exit;
}

if (audit_fund_is_admin()) {
    header('Location: schedule.php');
    exit;
}

http_response_code(403);
$displayName = htmlspecialchars(
    isset($_SESSION['audit_fund_login']) ? (string) $_SESSION['audit_fund_login'] : 'Пользователь',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <?php require __DIR__ . '/includes/theme_head.php'; ?>
  <title>Доступ запрещён — Аудиторный фонд</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <a class="skip-link" href="#main-content">К содержимому</a>
  <div class="page">
    <?php require __DIR__ . '/includes/theme_toggle.php'; ?>
    <main class="card" role="main" id="main-content">
      <header class="card__header animate-on-load">
        <p class="card__badge">Аудиторный фонд</p>
        <h1 class="card__title">Недостаточно прав</h1>
        <p class="card__subtitle">
          Вы вошли как <strong><?php echo $displayName; ?></strong>. Для этой операции нужны права администратора или менеджера; раздел «Пользователи» и назначение ролей доступны только администратору.
        </p>
      </header>

      <div class="form animate-on-load">
        <p class="form__hint">
          Если вам нужны права администратора, обратитесь к ответственному за систему.
        </p>
        <p class="form__row">
          <a class="btn btn--primary" href="schedule.php">На главный экран</a>
        </p>
        <p class="card__footer-note">
          <form class="inline-logout-form" method="post" action="logout.php">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            <button type="submit" class="form__link">Выйти из учётной записи</button>
          </form>
        </p>
      </div>
    </main>
  </div>

  <script src="script.js?v=6"></script>
<?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>
