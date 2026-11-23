<?php
/** @var string $step */
/** @var list<string> $errors */
/** @var list<array{label: string, ok: bool, required: bool, hint: string}> $checks */
/** @var bool $reqOk */
/** @var array<string, mixed> $form */
/** @var bool $existing */
/** @var ?string $configContent */
/** @var bool $configWritten */
/** @var string $base */
/** @var string $csrf */
$steps = ['check' => 'Проверка сервера', 'db' => 'База данных', 'site' => 'Сайт и администратор', 'done' => 'Готово'];
$stepKeys = array_keys($steps);
$curIdx = (int) array_search($step, $stepKeys, true);
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Установка — <?= h($steps[$step] ?? '') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" href="<?= h($base) ?>/assets/img/iro-brand.png" type="image/png">
    <style>
        body { background: #f3f5f9; }
        .install-card { max-width: 760px; }
        .install-steps .badge { font-weight: 500; }
        pre.install-config { max-height: 360px; overflow: auto; font-size: .8rem; }
    </style>
</head>
<body>
<main class="container py-5">
    <div class="install-card mx-auto">
        <div class="d-flex align-items-center gap-3 mb-4">
            <img src="<?= h($base) ?>/assets/img/iro-brand.png" alt="" width="48" height="48">
            <div>
                <h1 class="h3 mb-0">Установка сайта конкурсов</h1>
                <div class="text-muted small">Шаг <?= $curIdx + 1 ?> из <?= count($steps) ?>: <?= h($steps[$step] ?? '') ?></div>
            </div>
        </div>

        <div class="install-steps d-flex flex-wrap gap-2 mb-4">
            <?php foreach ($stepKeys as $i => $key): ?>
                <span class="badge rounded-pill <?= $i < $curIdx ? 'text-bg-success' : ($i === $curIdx ? 'text-bg-primary' : 'text-bg-light border') ?>">
                    <?= $i + 1 ?>. <?= h($steps[$key]) ?>
                </span>
            <?php endforeach; ?>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <?php foreach ($errors as $err): ?>
                    <div class="alert alert-danger"><?= h($err) ?></div>
                <?php endforeach; ?>

                <?php if ($step === 'check'): ?>
                    <p>Проверяем, что на сервере есть всё необходимое.</p>
                    <ul class="list-group mb-4">
                        <?php foreach ($checks as $c): ?>
                            <li class="list-group-item d-flex gap-3 align-items-start">
                                <?php if ($c['ok']): ?>
                                    <i class="bi bi-check-circle-fill text-success fs-5"></i>
                                <?php elseif ($c['required']): ?>
                                    <i class="bi bi-x-circle-fill text-danger fs-5"></i>
                                <?php else: ?>
                                    <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                                <?php endif; ?>
                                <div>
                                    <div><?= h($c['label']) ?><?= $c['required'] ? '' : ' <span class="text-muted small">(желательно)</span>' ?></div>
                                    <?php if (!$c['ok']): ?>
                                        <div class="small text-muted"><?= h($c['hint']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($reqOk): ?>
                        <a class="btn btn-primary rounded-pill px-4" href="<?= h($base) ?>/?install_step=db">Далее</a>
                    <?php else: ?>
                        <div class="alert alert-warning mb-3">Исправьте отмеченные красным пункты и обновите страницу.</div>
                        <a class="btn btn-outline-secondary rounded-pill px-4" href="<?= h($base) ?>/?install_step=check">Проверить снова</a>
                    <?php endif; ?>

                <?php elseif ($step === 'db'): ?>
                    <p>Укажите данные базы MySQL / MariaDB. Их выдаёт хостинг (или они заданы в OpenServer).</p>
                    <form method="post" action="<?= h($base) ?>/?install_step=db" autocomplete="off">
                        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label" for="host">Сервер базы</label>
                                <input class="form-control" id="host" name="host" value="<?= h((string) $form['host']) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="port">Порт</label>
                                <input class="form-control" id="port" name="port" type="number" value="<?= (int) $form['port'] ?>">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label" for="dbname">Имя базы</label>
                                <input class="form-control" id="dbname" name="dbname" value="<?= h((string) $form['dbname']) ?>" required pattern="[A-Za-z0-9_]+">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="create_db" name="create_db" value="1" checked>
                                    <label class="form-check-label small" for="create_db">Создать базу, если её нет (нужны права пользователя)</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="user">Пользователь</label>
                                <input class="form-control" id="user" name="user" value="<?= h((string) $form['user']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="password">Пароль</label>
                                <input class="form-control" id="password" name="password" type="password" value="">
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-4">
                            <a class="btn btn-outline-secondary rounded-pill px-4" href="<?= h($base) ?>/?install_step=check">Назад</a>
                            <button class="btn btn-primary rounded-pill px-4" type="submit">Проверить подключение</button>
                        </div>
                    </form>

                <?php elseif ($step === 'site'): ?>
                    <?php if ($existing): ?>
                        <div class="alert alert-info">
                            В этой базе уже есть таблицы сайта. Таблицы и пользователи не будут изменены —
                            установщик только создаст файл настроек <code>app/config.php</code>.
                        </div>
                    <?php else: ?>
                        <p>Подключение к базе работает. Осталось указать название сайта и создать администратора.</p>
                    <?php endif; ?>
                    <form method="post" action="<?= h($base) ?>/?install_step=site" autocomplete="off">
                        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="site_name">Название сайта</label>
                                <input class="form-control" id="site_name" name="site_name" value="<?= h((string) $form['site_name']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="origin">Адрес сайта</label>
                                <input class="form-control" id="origin" name="origin" value="<?= h((string) $form['origin']) ?>" required>
                                <div class="form-text">Для ссылок в письмах и входа через Яндекс.</div>
                            </div>
                            <?php if (!$existing): ?>
                                <div class="col-12"><hr class="my-1"><h2 class="h6 mt-2 mb-0">Администратор</h2></div>
                                <div class="col-md-6">
                                    <label class="form-label" for="last_name">Фамилия</label>
                                    <input class="form-control" id="last_name" name="last_name" value="<?= h((string) $form['last_name']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="first_name">Имя</label>
                                    <input class="form-control" id="first_name" name="first_name" value="<?= h((string) $form['first_name']) ?>">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label" for="email">Email (логин)</label>
                                    <input class="form-control" id="email" name="email" type="email" value="<?= h((string) $form['email']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="admin_password">Пароль</label>
                                    <input class="form-control" id="admin_password" name="password" type="password" minlength="8" required autocomplete="new-password">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="password_confirm">Повтор пароля</label>
                                    <input class="form-control" id="password_confirm" name="password_confirm" type="password" minlength="8" required autocomplete="new-password">
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="demo" name="demo" value="1"<?= !empty($form['demo']) ? ' checked' : '' ?>>
                                        <label class="form-check-label" for="demo">Добавить демо-конкурс с двумя этапами (черновик)</label>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex gap-2 mt-4">
                            <a class="btn btn-outline-secondary rounded-pill px-4" href="<?= h($base) ?>/?install_step=db">Назад</a>
                            <button class="btn btn-primary rounded-pill px-4" type="submit">Установить</button>
                        </div>
                    </form>

                <?php elseif ($step === 'done'): ?>
                    <?php if ($configWritten): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle-fill me-1"></i>
                            Установка завершена. Файл <code>app/config.php</code> создан, установщик отключён.
                        </div>
                        <p>Дальше можно настроить в <code>app/config.php</code> вход через Яндекс (<code>oauth.yandex</code>) и почту (<code>mail</code>) — см. README.</p>
                        <a class="btn btn-primary rounded-pill px-4" href="<?= h($base) ?>/login">Войти на сайт</a>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            База готова, но записать <code>app/config.php</code> не удалось (нет прав на каталог <code>app/</code>).
                            Создайте файл <code>app/config.php</code> с текстом ниже и откройте сайт.
                        </div>
                        <pre class="install-config bg-light border rounded p-3"><code><?= h((string) $configContent) ?></code></pre>
                        <a class="btn btn-primary rounded-pill px-4" href="<?= h($base) ?>/login">Я сохранил файл — войти</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <p class="text-center text-muted small mt-3">Установщик работает, пока на сервере нет файла <code>app/config.php</code>.</p>
    </div>
</main>
</body>
</html>
