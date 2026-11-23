<?php
/**
 * Мастер установки на хостинг (аналогично первому запуску WordPress).
 * Доступен, пока нет файла config/database.local.php.
 */
declare(strict_types=1);

session_start();

$root = dirname(__DIR__);
require_once $root . '/includes/install_lib.php';
require_once $root . '/includes/http_input.php';

if (!audit_fund_install_wizard_enabled()) {
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="favicon.ico" sizes="any">
  <link rel="icon" href="favicon.svg" type="image/svg+xml">
  <title>Установщик отключён</title>
  <link rel="stylesheet" href="../styles.css">
</head>
<body>
  <div class="page">
    <main class="card card--wide" role="main">
      <header class="card__header">
        <p class="card__badge">Аудиторный фонд</p>
        <h1 class="card__title">Мастер установки отключён</h1>
        <p class="card__subtitle">Чтобы снова включить веб-установку, в файле <code>includes/install_lib.php</code> задайте <code>audit_fund_install_wizard_enabled()</code> → <code>true</code>. Конфиг БД можно создать вручную: <code>config/database.local.php</code> (см. пример в репозитории).</p>
      </header>
      <p class="form__hint"><a class="form__link" href="../index.php">На страницу входа</a></p>
    </main>
  </div>
</body>
</html>
    <?php
    exit;
}

try {
    audit_fund_install_ensure_storage_dirs($root);

if (is_readable($root . '/config/database.local.php')) {
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="favicon.ico" sizes="any">
  <link rel="icon" href="favicon.svg" type="image/svg+xml">
  <title>Установка завершена</title>
  <link rel="stylesheet" href="../styles.css">
</head>
<body>
  <div class="page">
    <main class="card card--wide" role="main">
      <header class="card__header">
        <p class="card__badge">Аудиторный фонд</p>
        <h1 class="card__title">Уже установлено</h1>
        <p class="card__subtitle">Файл <code>config/database.local.php</code> уже существует. Для повторной установки удалите его вручную (как правило, это не нужно).</p>
      </header>
      <p class="form__hint"><a class="form__link" href="../index.php">Перейти ко входу</a></p>
    </main>
  </div>
</body>
</html>
    <?php
    exit;
}

if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(16));
}
$csrf = (string) $_SESSION['install_csrf'];

$errors = [];
$installScript = audit_fund_install_script_url_path();

// Шаг из POST (скрытое поле), иначе из GET — иначе при POST без ?step=2 снова показывается шаг 1.
$postedStep = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ps = audit_fund_post_int('install_step');
    if ($ps >= 1 && $ps <= 3) {
        $postedStep = $ps;
    }
}
$getStep = audit_fund_get_int('step');
$step = $postedStep ?? ($getStep >= 1 && $getStep <= 3 ? $getStep : 1);
if ($step < 1 || $step > 3) {
    $step = 1;
}

$reqErrors = audit_fund_install_requirements_errors();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = audit_fund_post_string('_csrf');
    if ($csrf === '' || $token === '' || !hash_equals($csrf, $token)) {
        $errors[] = 'Сессия установки устарела. Обновите страницу.';
    } else {
        $action = audit_fund_post_string('action');

        if ($action === 'step2') {
            $host = trim(audit_fund_post_string('db_host'));
            $port = audit_fund_post_int('db_port');
            if ($port < 1 || $port > 65535) {
                $port = 3306;
            }
            $database = trim(audit_fund_post_string('db_name'));
            $user = trim(audit_fund_post_string('db_user'));
            $password = audit_fund_post_string('db_password');

            $dbErr = audit_fund_install_validate_db_name($database);
            if ($dbErr !== null) {
                $errors[] = $dbErr;
            }
            if ($host === '') {
                $errors[] = 'Укажите хост MySQL.';
            }
            if ($user === '') {
                $errors[] = 'Укажите пользователя MySQL.';
            }

            if ($errors === []) {
                $dbCfg = [
                    'host' => $host,
                    'port' => $port,
                    'user' => $user,
                    'password' => $password,
                    'database' => $database,
                    'charset' => 'utf8mb4',
                ];
                $try = audit_fund_install_try_connection($dbCfg);
                if (!$try['ok']) {
                    $errors[] = 'Не удалось подключиться к MySQL: ' . ($try['error'] ?? '');
                } else {
                    $_SESSION['install_db'] = $dbCfg;
                    header('Location: ' . $installScript . '?step=3', true, 302);
                    exit;
                }
            }
            $step = 2;
        }

        if ($action === 'step3') {
            $dbCfg = isset($_SESSION['install_db']) && is_array($_SESSION['install_db'])
                ? $_SESSION['install_db']
                : null;
            if ($dbCfg === null || !isset($dbCfg['host'], $dbCfg['database'])) {
                $errors[] = 'Сначала заполните параметры базы данных (шаг 2).';
                $step = 2;
            } else {
                $errorsBeforeAdmin = count($errors);
                $adminUser = trim(audit_fund_post_string('admin_username'));
                $adminName = trim(audit_fund_post_string('admin_display_name'));
                $adminEmail = trim(audit_fund_post_string('admin_email'));
                $adminPass = audit_fund_post_string('admin_password');
                $adminPass2 = audit_fund_post_string('admin_password2');
                $withDemo = audit_fund_post_string('demo_data') === '1';
                $regOpen = audit_fund_post_string('registration_open') === '1';
                $httpsOnly = audit_fund_post_string('https_only') === '1';

                if (strlen($adminUser) < 3 || strlen($adminUser) > 64) {
                    $errors[] = 'Логин администратора: от 3 до 64 символов.';
                } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $adminUser)) {
                    $errors[] = 'Логин: только латиница, цифры, точка, дефис и подчёркивание.';
                }
                if ($adminName === '') {
                    $errors[] = 'Укажите отображаемое имя администратора.';
                } else {
                    $nameLen = function_exists('mb_strlen') ? mb_strlen($adminName, 'UTF-8') : strlen($adminName);
                    if ($nameLen > 191) {
                        $errors[] = 'Имя не длиннее 191 символа.';
                    } elseif (!preg_match('/^[\p{L}\p{N}\s\'.-]+$/u', $adminName)) {
                        $errors[] = 'Имя содержит недопустимые символы.';
                    }
                }
                if ($adminEmail !== '' && (strlen($adminEmail) > 191 || filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false)) {
                    $errors[] = 'Некорректный e-mail или оставьте поле пустым.';
                }
                if (strlen($adminPass) < 8) {
                    $errors[] = 'Пароль администратора не короче 8 символов.';
                } elseif (!hash_equals($adminPass, $adminPass2)) {
                    $errors[] = 'Пароли не совпадают.';
                }

                if (count($errors) === $errorsBeforeAdmin) {
                    $schema = audit_fund_install_run_schema($dbCfg, $withDemo);
                    if (!$schema['ok']) {
                        $errors[] = 'Ошибка при создании таблиц: ' . (string) ($schema['error'] ?? '');
                    } else {
                        $admin = audit_fund_install_create_admin(
                            $dbCfg,
                            $adminUser,
                            $adminName,
                            $adminEmail,
                            $adminPass
                        );
                        if (!$admin['ok']) {
                            $errors[] = 'База создана, но не удалось добавить администратора: ' . (string) ($admin['error'] ?? '');
                            $step = 3;
                        } else {
                            $writeErr = audit_fund_install_write_config([
                                'host' => (string) $dbCfg['host'],
                                'port' => (int) $dbCfg['port'],
                                'database' => (string) $dbCfg['database'],
                                'user' => (string) $dbCfg['user'],
                                'password' => (string) $dbCfg['password'],
                                'charset' => 'utf8mb4',
                                'runtime_migrations' => false,
                                'registration_open' => $regOpen,
                                'session_cookie_lifetime' => 0,
                                'session_cookie_secure' => $httpsOnly ? true : null,
                                'session_cookie_samesite' => 'Lax',
                            ]);
                            if ($writeErr !== null) {
                                $errors[] = $writeErr . ' Войдите в phpMyAdmin и удалите созданную базу или таблицы, затем исправьте права на config/.';
                                $step = 3;
                            } else {
                                unset($_SESSION['install_db'], $_SESSION['install_csrf']);
                                $installDir = str_replace('\\', '/', dirname($installScript));
                                $siteBase = dirname($installDir);
                                if ($siteBase === '/' || $siteBase === '\\' || $siteBase === '' || $siteBase === '.') {
                                    $loginUrl = '/index.php?installed=1';
                                } else {
                                    $loginUrl = rtrim($siteBase, '/') . '/index.php?installed=1';
                                }
                                header('Location: ' . $loginUrl, true, 302);
                                exit;
                            }
                        }
                    }
                }
                $step = 3;
            }
        }
    }
}

if ($step === 3 && empty($_SESSION['install_db'])) {
    header('Location: ' . $installScript . '?step=2', true, 302);
    exit;
}

$pageTitle = 'Установка — Аудиторный фонд';
$installDirForLink = str_replace('\\', '/', dirname($installScript));
$siteBaseForLink = dirname($installDirForLink);
if ($siteBaseForLink === '/' || $siteBaseForLink === '' || $siteBaseForLink === '.') {
    $homeLink = '/index.php';
} else {
    $homeLink = rtrim($siteBaseForLink, '/') . '/index.php';
}
} catch (Throwable $e) {
    audit_fund_install_render_throwable_page($e);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <link rel="icon" href="favicon.ico" sizes="any">
  <link rel="icon" href="favicon.svg" type="image/svg+xml">
  <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></title>
  <link rel="stylesheet" href="../styles.css">
</head>
<body>
  <a class="skip-link" href="#main-content">К содержимому</a>
  <div class="page">
    <main class="card card--wide" role="main" id="main-content">
      <header class="card__header">
        <p class="card__badge">Аудиторный фонд</p>
        <h1 class="card__title">Установка</h1>
        <p class="card__subtitle">
          Шаг <?php echo (int) $step; ?> из 3. Подключение к MySQL, создание таблиц и учётной записи администратора.
        </p>
      </header>

      <?php if ($step > 1 && $reqErrors !== []): ?>
        <div class="form__error" role="alert">
          <p>Окружение не готово. Вернитесь на шаг 1 или устраните пункты ниже.</p>
          <ul class="form__hint" style="margin: 0 0 1rem 1.25rem;">
            <?php foreach ($reqErrors as $e): ?>
              <li><?php echo htmlspecialchars($e, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <ol class="form__hint" style="margin: 0 0 1rem 1.25rem;">
        <li>Проверка окружения</li>
        <li>Параметры базы данных</li>
        <li>Администратор и завершение</li>
      </ol>

      <?php if ($errors !== []): ?>
        <div class="form__error" role="alert">
          <?php foreach ($errors as $msg): ?>
            <p><?php echo htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($step === 1): ?>
        <?php if ($reqErrors !== []): ?>
          <p class="form__error" role="alert">Исправьте перечисленное и обновите страницу.</p>
          <ul class="form__hint">
            <?php foreach ($reqErrors as $e): ?>
              <li><?php echo htmlspecialchars($e, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="form__success" role="status">Окружение подходит для работы.</p>
          <p class="form__hint">Понадобятся данные MySQL из панели хостинга. У пользователя БД должны быть права CREATE DATABASE (для шага создания базы из скрипта) либо база уже создана вручную.</p>
        <?php endif; ?>
        <p class="card__footer">
          <?php if ($reqErrors === []): ?>
            <a class="btn btn--primary" href="<?php echo htmlspecialchars($installScript, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>?step=2">Далее</a>
          <?php else: ?>
            <button class="btn btn--ghost" type="button" disabled>Далее</button>
          <?php endif; ?>
        </p>
      <?php elseif ($step === 2): ?>
        <form class="form" method="post" action="<?php echo htmlspecialchars($installScript, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" novalidate>
          <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
          <input type="hidden" name="install_step" value="2">
          <input type="hidden" name="action" value="step2">
          <div class="form__field">
            <label class="form__label" for="db-host">Сервер MySQL</label>
            <input class="form__input" type="text" id="db-host" name="db_host" required value="<?php $v = trim(audit_fund_post_string('db_host')); echo htmlspecialchars($v !== '' ? $v : 'localhost', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" placeholder="localhost">
          </div>
          <div class="form__field">
            <label class="form__label" for="db-port">Порт</label>
            <input class="form__input" type="number" id="db-port" name="db_port" min="1" max="65535" value="<?php $p = audit_fund_post_string('db_port'); echo htmlspecialchars($p !== '' ? $p : '3306', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
          </div>
          <div class="form__field">
            <label class="form__label" for="db-name">Имя базы данных</label>
            <input class="form__input" type="text" id="db-name" name="db_name" required value="<?php $n = trim(audit_fund_post_string('db_name')); echo htmlspecialchars($n !== '' ? $n : 'audit_fund', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" placeholder="audit_fund" pattern="[a-zA-Z0-9_]+" maxlength="64">
            <p class="form__hint">Латиница, цифры и подчёркивание. Если базы нет, она будет создана (нужны права CREATE).</p>
          </div>
          <div class="form__field">
            <label class="form__label" for="db-user">Пользователь MySQL</label>
            <input class="form__input" type="text" id="db-user" name="db_user" required value="<?php echo htmlspecialchars(trim(audit_fund_post_string('db_user')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" autocomplete="username">
          </div>
          <div class="form__field">
            <label class="form__label" for="db-password">Пароль MySQL</label>
            <input class="form__input" type="password" id="db-password" name="db_password" value="" autocomplete="current-password">
          </div>
          <div class="modal__actions">
            <a class="btn btn--ghost" href="<?php echo htmlspecialchars($installScript, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>?step=1">Назад</a>
            <button class="btn btn--primary" type="submit"<?php echo $reqErrors !== [] ? ' disabled' : ''; ?>>Проверить и продолжить</button>
          </div>
          <?php if ($reqErrors !== []): ?>
            <p class="form__hint">Сначала устраните проблемы окружения (см. выше).</p>
          <?php endif; ?>
        </form>
      <?php else: ?>
        <form class="form" method="post" action="<?php echo htmlspecialchars($installScript, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" novalidate>
          <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
          <input type="hidden" name="install_step" value="3">
          <input type="hidden" name="action" value="step3">
          <p class="form__hint">База: <strong><?php echo htmlspecialchars((string) ($_SESSION['install_db']['database'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong></p>

          <div class="form__field">
            <label class="form__label" for="admin-username">Логин администратора</label>
            <input class="form__input" type="text" id="admin-username" name="admin_username" required maxlength="64" value="<?php echo htmlspecialchars(trim(audit_fund_post_string('admin_username')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" autocomplete="username">
          </div>
          <div class="form__field">
            <label class="form__label" for="admin-display-name">Имя (как в системе)</label>
            <input class="form__input" type="text" id="admin-display-name" name="admin_display_name" required maxlength="191" value="<?php echo htmlspecialchars(trim(audit_fund_post_string('admin_display_name')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" autocomplete="name">
          </div>
          <div class="form__field">
            <label class="form__label" for="admin-email">E-mail <span class="form__optional">необязательно</span></label>
            <input class="form__input" type="email" id="admin-email" name="admin_email" value="<?php echo htmlspecialchars(trim(audit_fund_post_string('admin_email')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
          </div>
          <div class="form__field">
            <label class="form__label" for="admin-password">Пароль администратора</label>
            <input class="form__input" type="password" id="admin-password" name="admin_password" required minlength="8" autocomplete="new-password">
          </div>
          <div class="form__field">
            <label class="form__label" for="admin-password2">Пароль ещё раз</label>
            <input class="form__input" type="password" id="admin-password2" name="admin_password2" required minlength="8" autocomplete="new-password">
          </div>

          <div class="form__field">
            <label class="form__label">
              <input type="checkbox" name="demo_data" value="1"<?php echo audit_fund_post_string('demo_data') === '1' ? ' checked' : ''; ?>>
              Загрузить демо-данные (корпуса, аудитории, примеры расписания и заявок)
            </label>
          </div>
          <div class="form__field">
            <label class="form__label">
              <input type="checkbox" name="registration_open" value="1"<?php echo audit_fund_post_string('registration_open') === '1' ? ' checked' : ''; ?>>
              Разрешить открытую регистрацию новых пользователей
            </label>
          </div>
          <div class="form__field">
            <label class="form__label">
              <input type="checkbox" name="https_only" value="1"<?php echo audit_fund_post_string('https_only') === '1' ? ' checked' : ''; ?>>
              Сайт работает только по HTTPS (безопасные cookie сессии)
            </label>
            <p class="form__hint">Снимите галочку, если пока используете HTTP без SSL.</p>
          </div>

          <div class="modal__actions">
            <a class="btn btn--ghost" href="<?php echo htmlspecialchars($installScript, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>?step=2">Назад</a>
            <button class="btn btn--primary" type="submit"<?php echo $reqErrors !== [] ? ' disabled' : ''; ?>>Установить</button>
          </div>
          <?php if ($reqErrors !== []): ?>
            <p class="form__hint">Сначала устраните проблемы окружения (см. выше).</p>
          <?php endif; ?>
        </form>
      <?php endif; ?>

      <footer class="card__footer">
        <p class="form__hint">После установки удалите или защитите каталог <code>install/</code>. Подробности — <code>install/README.txt</code>.</p>
        <p><a class="form__link" href="<?php echo htmlspecialchars($homeLink, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">На страницу входа</a> (станет доступна после завершения установки)</p>
      </footer>
    </main>
  </div>
</body>
</html>
