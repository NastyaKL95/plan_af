<?php
$path = $GLOBALS['iro_request_path'] ?? router_uri_path($GLOBALS['config'] ?? null);
$navHome = ($path === '/' || $path === '');
$navNews = str_starts_with($path, '/news');
$navSeries = str_starts_with($path, '/series');
$navContestsDropdown =
    str_starts_with($path, '/contests')
    || str_starts_with($path, '/contest')
    || str_starts_with($path, '/my-contests')
    || str_starts_with($path, '/expert/')
    || str_starts_with($path, '/submission')
    || str_starts_with($path, '/award/')
    || str_starts_with($path, '/moderator/')
    || str_starts_with($path, '/admin/contest');
$navExpert = str_starts_with($path, '/expert/') || str_starts_with($path, '/submission') || str_starts_with($path, '/award/');
$navAdminContests = str_starts_with($path, '/admin/contest');
$navAdminPanel = str_starts_with($path, '/admin');
$navGuestContestsCatalog = str_starts_with($path, '/contest');
$uid = Auth::userId();
$profileUrl = ($user && !empty($user['id'])) ? '/profile/view?profile_id=' . (int) $user['id'] : '/profile';
$notifUnread = (int) ($notif_unread_count ?? 0);
$notifPreview = is_array($notif_preview ?? null) ? $notif_preview : [];
$notifMore = (int) ($notif_preview_more_unread ?? 0);
$showContestMgmtNav = !empty($isAdmin) || !empty($is_contest_moderator);
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
    (function(){try{if(localStorage.getItem('iro_cookie_consent')==='1')document.documentElement.classList.add('iro-cookie-consent-done');}catch(e){}})();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;500;600;700;800&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title><?= h(($title ?? 'Страница') . ' — Конкурсы') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="icon" href="/assets/img/iro-brand.png" type="image/png" sizes="any">
    <?php if (!empty($extra_head)): ?>
        <?= $extra_head ?>
    <?php endif; ?>
</head>
<body class="d-flex flex-column min-vh-100 <?= h($body_class ?? '') ?>">
<a class="iro-skip-link" href="#iro-main-content">К содержимому</a>
<header class="iro-header">
    <div class="container">
        <div class="iro-header-inner">
            <button class="btn btn-link iro-header-menu-toggle d-lg-none border-0 shadow-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#iroNavOffcanvas" aria-controls="iroNavOffcanvas" aria-label="Открыть меню">
                <i class="bi bi-list fs-3" aria-hidden="true"></i>
            </button>
            <nav class="iro-header-nav d-none d-lg-block flex-grow-1" aria-label="Основное меню">
                <ul class="iro-nav">
                    <li class="nav-item">
                        <a class="nav-link<?= $navHome ? ' active' : '' ?>" href="/"<?= $navHome ? ' aria-current="page"' : '' ?>>Главная</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?= $navNews ? ' active' : '' ?>" href="/news"<?= $navNews ? ' aria-current="page"' : '' ?>>Новости</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?= $navSeries ? ' active' : '' ?>" href="/series/"<?= $navSeries ? ' aria-current="page"' : '' ?>>Серии</a>
                    </li>
                    <?php if ($uid === null): ?>
                        <li class="nav-item">
                            <a class="nav-link<?= $navGuestContestsCatalog ? ' active' : '' ?>" href="/contests"<?= $navGuestContestsCatalog ? ' aria-current="page"' : '' ?>>Конкурсы</a>
                        </li>
                    <?php endif; ?>
                    <?php if ($uid !== null): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle<?= $navContestsDropdown ? ' active' : '' ?>" href="#" id="contestDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Конкурсы</a>
                            <ul class="dropdown-menu" aria-labelledby="contestDropdown">
                                <li><a class="dropdown-item<?= $path === '/my-contests' || $path === '/my-contests/' ? ' active' : '' ?>" href="/my-contests"<?= $path === '/my-contests' || $path === '/my-contests/' ? ' aria-current="true"' : '' ?>>Мои конкурсы</a></li>
                                <li><a class="dropdown-item<?= str_starts_with($path, '/contests') && $path !== '/my-contests' && $path !== '/my-contests/' ? ' active' : '' ?>" href="/contests">Каталог конкурсов</a></li>
                                <?php if (!empty($isAdmin)): ?>
                                    <li><a class="dropdown-item<?= str_contains($path, '/admin/contests/create') ? ' active' : '' ?>" href="/admin/contests/create">Создать конкурс</a></li>
                                <?php endif; ?>
                                <?php if (!empty($is_expert)): ?>
                                    <li><a class="dropdown-item<?= $navExpert ? ' active' : '' ?>" href="/expert/contests"<?= str_starts_with($path, '/expert/contests') ? ' aria-current="true"' : '' ?>>Оценка работ</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>
                    <?php if ($showContestMgmtNav && empty($isAdmin)): ?>
                        <li class="nav-item">
                            <a class="nav-link<?= $navAdminContests ? ' active' : '' ?>" href="/admin/contests">Управление конкурсами</a>
                        </li>
                    <?php endif; ?>
                    <?php if (!empty($isAdmin)): ?>
                        <li class="nav-item">
                            <a class="nav-link<?= ($navAdminPanel && !str_starts_with($path, '/admin/contest') && !str_starts_with($path, '/admin/user')) ? ' active' : '' ?>" href="/admin/">Админ-панель</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <div class="iro-auth ms-auto ms-lg-0 flex-shrink-0">
                <?php if ($uid !== null): ?>
                    <div class="dropdown iro-notif-dropdown">
                        <button class="btn btn-link iro-notif-bell position-relative p-1 text-decoration-none text-body" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Уведомления">
                            <i class="bi bi-bell fs-5"></i>
                            <?php if ($notifUnread > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger iro-notif-badge"><?= $notifUnread > 99 ? '99+' : (int) $notifUnread ?></span>
                            <?php endif; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end iro-notif-menu shadow border-0">
                            <?php if ($notifPreview): ?>
                                <?php foreach ($notifPreview as $n): ?>
                                    <li>
                                        <a class="dropdown-item small py-2<?= empty($n['is_read']) ? ' fw-semibold' : '' ?>" href="/notifications/<?= (int) $n['id'] ?>/go/"><?= h(mb_strlen((string) $n['title']) > 64 ? mb_substr((string) $n['title'], 0, 64) . '…' : (string) $n['title']) ?></a>
                                    </li>
                                <?php endforeach; ?>
                                <?php if ($notifMore > 0): ?>
                                    <li><a class="dropdown-item small py-2 text-muted" href="/notifications"><?= $notifMore === 1 ? 'Ещё 1 непрочитанное…' : 'Ещё ' . (int) $notifMore . ' непрочитанных…' ?></a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider my-1"></li>
                            <?php else: ?>
                                <li><span class="dropdown-item-text small text-muted py-2">Нет уведомлений</span></li>
                                <li><hr class="dropdown-divider my-1"></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item small py-2" href="/notifications">Все уведомления</a></li>
                        </ul>
                    </div>
                    <a class="btn-iro-ghost" href="<?= h($profileUrl) ?>">Профиль</a>
                    <form action="/logout" method="post" class="d-inline">
                        <?= iro_csrf_field_html() ?>
                        <button type="submit" class="btn-iro-ghost border-0">Выход</button>
                    </form>
                <?php else: ?>
                    <a class="btn-iro-ghost" href="/login">Вход</a>
                    <a class="btn-iro-primary" href="/signup">Регистрация</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<div class="offcanvas offcanvas-start iro-nav-offcanvas" tabindex="-1" id="iroNavOffcanvas" aria-labelledby="iroNavOffcanvasLabel">
    <div class="offcanvas-header border-bottom">
        <h2 class="offcanvas-title h5 mb-0" id="iroNavOffcanvasLabel">Меню</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Закрыть"></button>
    </div>
    <div class="offcanvas-body">
        <nav class="nav flex-column gap-1" aria-label="Основное меню, мобильная версия">
            <a class="nav-link<?= $navHome ? ' active' : '' ?>" href="/" data-bs-dismiss="offcanvas"<?= $navHome ? ' aria-current="page"' : '' ?>>Главная</a>
            <a class="nav-link<?= $navNews ? ' active' : '' ?>" href="/news" data-bs-dismiss="offcanvas"<?= $navNews ? ' aria-current="page"' : '' ?>>Новости</a>
            <a class="nav-link<?= $navSeries ? ' active' : '' ?>" href="/series/" data-bs-dismiss="offcanvas"<?= $navSeries ? ' aria-current="page"' : '' ?>>Серии</a>
            <?php if ($uid === null): ?>
                <a class="nav-link<?= $navGuestContestsCatalog ? ' active' : '' ?>" href="/contests" data-bs-dismiss="offcanvas"<?= $navGuestContestsCatalog ? ' aria-current="page"' : '' ?>>Конкурсы</a>
            <?php else: ?>
                <span class="small text-muted text-uppercase fw-bold px-2 pt-2 pb-1">Конкурсы</span>
                <a class="nav-link ps-3<?= $path === '/my-contests' || $path === '/my-contests/' ? ' active' : '' ?>" href="/my-contests" data-bs-dismiss="offcanvas">Мои конкурсы</a>
                <a class="nav-link ps-3" href="/contests" data-bs-dismiss="offcanvas">Каталог конкурсов</a>
                <?php if (!empty($isAdmin)): ?>
                    <a class="nav-link ps-3" href="/admin/contests/create" data-bs-dismiss="offcanvas">Создать конкурс</a>
                <?php endif; ?>
                <?php if (!empty($is_expert)): ?>
                    <a class="nav-link ps-3<?= $navExpert ? ' active' : '' ?>" href="/expert/contests" data-bs-dismiss="offcanvas">Оценка работ</a>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($showContestMgmtNav && empty($isAdmin)): ?>
                <a class="nav-link<?= $navAdminContests ? ' active' : '' ?>" href="/admin/contests" data-bs-dismiss="offcanvas">Управление конкурсами</a>
            <?php endif; ?>
            <?php if (!empty($isAdmin)): ?>
                <a class="nav-link<?= ($navAdminPanel && !str_starts_with($path, '/admin/contest') && !str_starts_with($path, '/admin/user')) ? ' active' : '' ?>" href="/admin/" data-bs-dismiss="offcanvas">Админ-панель</a>
            <?php endif; ?>
        </nav>
    </div>
</div>

<?php
$iroBodyClass = (string) ($body_class ?? '');
$iroMainContainerPad = str_contains($iroBodyClass, 'iro-page-home') ? 'pt-0 pb-0 mb-0' : 'py-4 mb-5';
?>
<main id="iro-main-content" class="flex-grow-1" tabindex="-1">
    <div class="container iro-container <?= h($iroMainContainerPad) ?>">
        <?php foreach (($flash ?? []) as $item): ?>
            <?php
            $flashType = (string) ($item['type'] ?? 'info');
            $flashAutoClose = !in_array($flashType, ['danger'], true);
            ?>
            <div class="alert alert-<?= h($flashType) ?> alert-dismissible fade show<?= $flashAutoClose ? ' iro-alert-auto' : '' ?> iro-alert-flash" role="alert">
                <?= h((string) ($item['message'] ?? '')) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
            </div>
        <?php endforeach; ?>
        <?php require env_path('views/partials/breadcrumbs.php'); ?>
        <?php require $viewFile; ?>
    </div>
</main>

<footer class="iro-footer">
    <div class="container">
        <div class="iro-footer-subscribe">
            <div class="row align-items-center g-3">
                <div class="col-md-7">
                    <strong class="d-block text-white mb-1">Будьте в курсе конкурсов</strong>
                    <span class="small text-white-50">Следите за объявлениями и сроками на сайте института.</span>
                </div>
                <div class="col-md-5">
                    <form class="d-flex gap-2" action="/" method="get">
                        <input type="text" name="q" class="form-control form-control-sm" placeholder="Поиск по названию" value="<?= h((string) ($_GET['q'] ?? '')) ?>">
                        <button type="submit" class="btn btn-light btn-sm rounded-pill px-3 fw-semibold">Найти</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="row py-3">
            <div class="col-md-6">
                <p class="fw-bold text-white mb-3">ГУ ДПО «Институт развития образования Забайкальского края»</p>
                <ul class="nav flex-column gap-1">
                    <li><a href="/">Главная</a></li>
                    <li><a href="/news">Новости и объявления</a></li>
                    <li><a href="/contests">Конкурсы</a></li>
                </ul>
            </div>
            <div class="col-md-6 text-md-end mt-4 mt-md-0">
                <h6 class="text-white mb-2">Мы на связи</h6>
                <p class="small mb-2 text-white-50">Вопросы по конкурсам — звоните или пишите.</p>
                <p class="mb-1"><strong class="text-white">Телефон:</strong> <a href="tel:+73022415429">8 (3022) 41-54-29</a></p>
                <p class="mb-1"><strong class="text-white">Email:</strong> <a href="mailto:cit@iro75.ru">cit@iro75.ru</a></p>
                <p class="mb-1"><strong class="text-white">Сайт института:</strong> <a href="http://www.irozk.ru/" target="_blank" rel="noopener noreferrer">irozk.ru</a></p>
                <p class="mb-0"><strong class="text-white">ВКонтакте:</strong> <a href="https://vk.com/gudpoirozk" target="_blank" rel="noopener noreferrer">Группа ВК</a></p>
            </div>
        </div>
        <div class="text-center pt-3 border-top border-secondary border-opacity-25">
            <p class="mb-2 small text-white-50">
                <a href="/privacy" class="text-white-50">Политика конфиденциальности</a>
                <span class="text-white-50 mx-1">·</span>
                <a href="/cookies" class="text-white-50">Политика cookie</a>
                <span class="text-white-50 mx-1">·</span>
                <button type="button" class="btn btn-link btn-sm text-white-50 p-0 align-baseline text-decoration-underline border-0" data-iro-cookie-reset>Настройки cookie</button>
            </p>
            <p class="mb-0 small text-white-50">© ЦПТО, <?= h((string) date('Y')) ?></p>
        </div>
    </div>
</footer>

<div id="iro-cookie-banner" class="iro-cookie-banner" role="dialog" aria-modal="false" aria-labelledby="iro-cookie-banner-title" aria-describedby="iro-cookie-banner-desc">
    <div class="container py-3">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-3">
            <div class="flex-grow-1 small" id="iro-cookie-banner-desc">
                <strong id="iro-cookie-banner-title" class="d-block text-white mb-1">Файлы cookie</strong>
                <span class="text-white-50">Мы используем cookie для входа, сессии и безопасности. Продолжая пользоваться сайтом после нажатия «Принять», вы соглашаетесь с <a href="/cookies" class="text-white text-decoration-underline">условиями использования cookie</a>.</span>
            </div>
            <div class="d-flex flex-shrink-0 flex-wrap gap-2">
                <a class="btn btn-sm btn-outline-light" href="/cookies">Подробнее</a>
                <button type="button" class="btn btn-sm btn-light fw-semibold" id="iro-cookie-accept">Принять</button>
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/cookie-consent.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.iro-alert-auto').forEach(function (el) {
        setTimeout(function () {
            try {
                var inst = bootstrap.Alert.getOrCreateInstance(el);
                inst.close();
            } catch (e) {}
        }, 12000);
    });
});
</script>
</body>
</html>
