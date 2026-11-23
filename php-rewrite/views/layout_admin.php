<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h(($admin_page_title ?? $title ?? 'Админ-панель') . ' | Конкурсы') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;500;600;700;800&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="icon" href="/assets/img/iro-brand.png" type="image/png" sizes="any">
</head>
<body class="iro-admin-body">
<div class="iro-admin-shell d-flex min-vh-100">
    <aside class="iro-admin-sidebar flex-shrink-0 d-none d-md-flex flex-column min-vh-100">
        <div class="iro-admin-sidebar-brand px-3 py-4 border-bottom border-light border-opacity-10">
            <a href="/admin/" class="text-white text-decoration-none fw-bold d-block">Администрирование</a>
            <span class="small text-white-50">Панель управления</span>
        </div>
        <nav class="nav flex-column px-2 py-3 gap-1 flex-grow-1">
            <a class="iro-admin-nav-link <?= ($admin_sidebar_active ?? '') === 'panel' ? 'active' : '' ?>" href="/admin/">
                <i class="bi bi-speedometer2 me-2"></i> Обзор
            </a>
            <a class="iro-admin-nav-link <?= ($admin_sidebar_active ?? '') === 'contests' ? 'active' : '' ?>" href="/admin/contests">
                <i class="bi bi-trophy me-2"></i> Конкурсы
            </a>
            <?php if (!empty($isAdmin)): ?>
                <a class="iro-admin-nav-link <?= ($admin_sidebar_active ?? '') === 'contests_archived' ? 'active' : '' ?>" href="/admin/contests/archived">
                    <i class="bi bi-archive me-2"></i> Архив
                </a>
                <a class="iro-admin-nav-link <?= ($admin_sidebar_active ?? '') === 'users' ? 'active' : '' ?>" href="/admin/users">
                    <i class="bi bi-people me-2"></i> Пользователи и роли
                </a>
                <a class="iro-admin-nav-link <?= ($admin_sidebar_active ?? '') === 'news' ? 'active' : '' ?>" href="/admin/news">
                    <i class="bi bi-newspaper me-2"></i> Новости и объявления
                </a>
                <a class="iro-admin-nav-link <?= ($admin_sidebar_active ?? '') === 'series' ? 'active' : '' ?>" href="/admin/series">
                    <i class="bi bi-collection me-2"></i> Серии конкурсов
                </a>
                <a class="iro-admin-nav-link <?= ($admin_sidebar_active ?? '') === 'consent_docs' ? 'active' : '' ?>" href="/admin/consent-documents">
                    <i class="bi bi-file-earmark-text me-2"></i> Шаблоны согласий
                </a>
                <a class="iro-admin-nav-link <?= ($admin_sidebar_active ?? '') === 'partners' ? 'active' : '' ?>" href="/admin/partners">
                    <i class="bi bi-building me-2"></i> Партнёры
                </a>
                <a class="iro-admin-nav-link <?= ($admin_sidebar_active ?? '') === 'roles' ? 'active' : '' ?>" href="/admin/system-roles">
                    <i class="bi bi-shield-check me-2"></i> Системные роли
                </a>
            <?php endif; ?>
        </nav>
        <div class="mt-auto px-3 py-4 border-top border-light border-opacity-10 small">
            <a href="/" class="text-white-50 text-decoration-none"><i class="bi bi-arrow-left-short"></i> На сайт</a>
        </div>
    </aside>

    <div class="offcanvas offcanvas-start iro-admin-offcanvas d-md-none" tabindex="-1" id="adminNavOffcanvas" aria-labelledby="adminNavOffcanvasLabel">
        <div class="offcanvas-header border-bottom border-light border-opacity-25">
            <h2 class="offcanvas-title h6 text-white mb-0" id="adminNavOffcanvasLabel">Разделы</h2>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Закрыть"></button>
        </div>
        <div class="offcanvas-body p-0 d-flex flex-column">
            <nav class="nav flex-column px-2 py-3 gap-1 flex-grow-1">
                <a class="iro-admin-nav-link <?= ($admin_sidebar_active ?? '') === 'panel' ? 'active' : '' ?>" href="/admin/" data-bs-dismiss="offcanvas">
                    <i class="bi bi-speedometer2 me-2"></i> Обзор
                </a>
                <a class="iro-admin-nav-link <?= ($admin_sidebar_active ?? '') === 'contests' ? 'active' : '' ?>" href="/admin/contests" data-bs-dismiss="offcanvas">
                    <i class="bi bi-trophy me-2"></i> Конкурсы
                </a>
                <?php if (!empty($isAdmin)): ?>
                    <a class="iro-admin-nav-link <?= ($admin_sidebar_active ?? '') === 'contests_archived' ? 'active' : '' ?>" href="/admin/contests/archived" data-bs-dismiss="offcanvas">
                        <i class="bi bi-archive me-2"></i> Архив
                    </a>
                    <a class="iro-admin-nav-link <?= ($admin_sidebar_active ?? '') === 'users' ? 'active' : '' ?>" href="/admin/users" data-bs-dismiss="offcanvas">
                        <i class="bi bi-people me-2"></i> Пользователи и роли
                    </a>
                    <a class="iro-admin-nav-link <?= ($admin_sidebar_active ?? '') === 'news' ? 'active' : '' ?>" href="/admin/news" data-bs-dismiss="offcanvas">
                        <i class="bi bi-newspaper me-2"></i> Новости и объявления
                    </a>
                    <a class="iro-admin-nav-link <?= ($admin_sidebar_active ?? '') === 'series' ? 'active' : '' ?>" href="/admin/series" data-bs-dismiss="offcanvas">
                        <i class="bi bi-collection me-2"></i> Серии конкурсов
                    </a>
                    <a class="iro-admin-nav-link <?= ($admin_sidebar_active ?? '') === 'consent_docs' ? 'active' : '' ?>" href="/admin/consent-documents" data-bs-dismiss="offcanvas">
                        <i class="bi bi-file-earmark-text me-2"></i> Шаблоны согласий
                    </a>
                    <a class="iro-admin-nav-link <?= ($admin_sidebar_active ?? '') === 'partners' ? 'active' : '' ?>" href="/admin/partners" data-bs-dismiss="offcanvas">
                        <i class="bi bi-building me-2"></i> Партнёры
                    </a>
                    <a class="iro-admin-nav-link <?= ($admin_sidebar_active ?? '') === 'roles' ? 'active' : '' ?>" href="/admin/system-roles" data-bs-dismiss="offcanvas">
                        <i class="bi bi-shield-check me-2"></i> Системные роли
                    </a>
                <?php endif; ?>
            </nav>
            <div class="px-3 py-4 border-top border-light border-opacity-10 small">
                <a href="/" class="text-white-50 text-decoration-none" data-bs-dismiss="offcanvas"><i class="bi bi-arrow-left-short"></i> На сайт</a>
            </div>
        </div>
    </div>

    <div class="iro-admin-main flex-grow-1 d-flex flex-column min-vh-100 bg-light">
        <header class="iro-admin-topbar border-bottom bg-white px-3 px-md-4 py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2 min-w-0 flex-grow-1">
                <button type="button" class="btn btn-outline-secondary d-md-none flex-shrink-0" data-bs-toggle="offcanvas" data-bs-target="#adminNavOffcanvas" aria-label="Открыть меню">
                    <i class="bi bi-list fs-5" aria-hidden="true"></i>
                </button>
                <div class="text-muted small text-truncate">
                    <?php if (!empty($user['email'])): ?>
                        <?= h((string) $user['email']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="/" class="btn btn-sm btn-outline-secondary">Сайт</a>
                <form action="/logout" method="post" class="d-inline mb-0">
                    <?= iro_csrf_field_html() ?>
                    <button type="submit" class="btn btn-sm btn-outline-danger">Выйти</button>
                </form>
            </div>
        </header>
        <main class="iro-admin-content flex-grow-1 p-4">
            <?php foreach (($flash ?? []) as $item): ?>
                <?php
                $flashTypeAdm = (string) ($item['type'] ?? 'info');
                $flashAutoCloseAdm = !in_array($flashTypeAdm, ['danger'], true);
                ?>
                <div class="alert alert-<?= h($flashTypeAdm) ?> alert-dismissible fade show<?= $flashAutoCloseAdm ? ' iro-alert-auto' : '' ?> iro-alert-flash" role="alert">
                    <?= h((string) ($item['message'] ?? '')) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
                </div>
            <?php endforeach; ?>
            <?php require $admin_view_file; ?>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
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
