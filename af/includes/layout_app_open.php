<?php
declare(strict_types=1);

/**
 * Начало HTML-оболочки приложения (шапка и боковое меню).
 * Перед подключением задайте:
 * — $auditFundPageTitle (полный текст тега title),
 * — $auditFundNav: schedule | management | requests | reports | directories | users | audit_log | profile (отчёты, users и audit_log только у staff/admin по роли)
 * — $displayName, $roleLabel (экранированные строки из audit_fund_app_layout_vars()).
 */
$auditFundNav = isset($auditFundNav) ? (string) $auditFundNav : '';
$navItems = [
    ['id' => 'schedule', 'href' => 'schedule.php', 'label' => 'Главная'],
    ['id' => 'management', 'href' => 'management.php', 'label' => 'Обзор фонда'],
    ['id' => 'requests', 'href' => 'requests.php', 'label' => 'Заявки'],
];
if (function_exists('audit_fund_is_staff') && audit_fund_is_staff()) {
    $navItems[] = ['id' => 'reports', 'href' => 'reports.php', 'label' => 'Отчёты'];
}
$navItems[] = ['id' => 'directories', 'href' => 'directories.php', 'label' => 'Справочники'];
if (function_exists('audit_fund_is_admin') && audit_fund_is_admin()) {
    $navItems[] = ['id' => 'users', 'href' => 'users.php', 'label' => 'Пользователи'];
    $navItems[] = ['id' => 'audit_log', 'href' => 'audit_log.php', 'label' => 'Аудит-лог'];
}
$navItems[] = ['id' => 'profile', 'href' => 'profile.php', 'label' => 'Профиль'];
$pageTitle = isset($auditFundPageTitle) ? (string) $auditFundPageTitle : 'Аудиторный фонд';
require_once __DIR__ . '/sidebar_nav_icons.php';
require_once __DIR__ . '/requests_data.php';
$auditFundPendingBellCount = 0;
if (function_exists('audit_fund_is_logged_in') && audit_fund_is_logged_in()) {
    if (function_exists('audit_fund_is_staff') && audit_fund_is_staff()) {
        $auditFundPendingBellCount = audit_fund_requests_pending_active_count(null);
    } else {
        $auditFundBellUid = isset($_SESSION['audit_fund_user_id']) ? (int) $_SESSION['audit_fund_user_id'] : 0;
        $auditFundPendingBellCount = $auditFundBellUid > 0 ? audit_fund_requests_pending_active_count($auditFundBellUid) : 0;
    }
}
$auditFundPendingBellBadge = $auditFundPendingBellCount > 99 ? '99+' : (string) $auditFundPendingBellCount;
?>
<!DOCTYPE html>
<html lang="ru" class="app--sidebar-collapsed">
<head>
  <meta charset="UTF-8">
  <script>
    try {
      if (localStorage.getItem('audit-fund-sidebar-expanded') === '1') {
        document.documentElement.classList.remove('app--sidebar-collapsed');
      }
    } catch (e) {}
  </script>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <?php require __DIR__ . '/theme_head.php'; ?>
  <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></title>
  <link rel="stylesheet" href="styles.css">
</head>
<body class="app">
  <a class="skip-link" href="#main-content">К содержимому</a>
  <header class="app-header animate-on-load">
    <div class="app-header__left">
      <button type="button" class="mobile-nav-toggle" id="app-mobile-nav-toggle" aria-expanded="false" aria-controls="app-sidebar" aria-label="Открыть меню разделов">
        <span class="mobile-nav-toggle__bars" aria-hidden="true"></span>
      </button>
      <span class="app-header__mark" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" focusable="false">
          <g stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
          </g>
        </svg>
      </span>
      <a class="app-header__badge" href="schedule.php">Аудиторный фонд</a>
    </div>
    <div class="app-header__actions">
      <?php require __DIR__ . '/theme_toggle.php'; ?>
      <?php if ($auditFundPendingBellCount > 0): ?>
      <a class="app-header__bell" href="requests.php" aria-label="Заявки на рассмотрении, <?php echo (int) $auditFundPendingBellCount; ?>" title="Заявки на рассмотрении (<?php echo (int) $auditFundPendingBellCount; ?>)">
        <span class="app-header__bell-icon" aria-hidden="true">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" focusable="false">
            <path d="M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M13.73 21a2 2 0 01-3.46 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </span>
        <span class="app-header__bell-count"><?php echo htmlspecialchars($auditFundPendingBellBadge, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
      </a>
      <?php endif; ?>
      <span class="app-header__user" title="Текущий пользователь"><?php echo $displayName; ?> · <?php echo $roleLabel; ?></span>
      <form class="app-header__logout-form" method="post" action="logout.php">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
        <button type="submit" class="btn btn--ghost">Выйти</button>
      </form>
    </div>
  </header>

  <div class="app-body">
    <div class="mobile-nav-backdrop" id="app-mobile-nav-backdrop" aria-hidden="true"></div>
    <aside class="sidebar animate-on-load animate-on-load--d1" id="app-sidebar" aria-label="Разделы">
      <div class="sidebar__toolbar">
        <button type="button" class="sidebar__rail-toggle" id="app-sidebar-rail-toggle" aria-expanded="false" aria-controls="app-sidebar-nav" aria-label="Развернуть боковое меню" title="Развернуть меню с подписями">
          <span class="sidebar__rail-toggle-icon sidebar__rail-toggle-icon--collapse" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18.75 19.5 11.25 12l7.5-7.5M4.5 5.25v13.5"/></svg>
          </span>
          <span class="sidebar__rail-toggle-icon sidebar__rail-toggle-icon--expand" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5.25 4.5 12.75 12l-7.5 7.5M19.5 5.25v13.5"/></svg>
          </span>
        </button>
      </div>
      <nav class="sidebar__nav" id="app-sidebar-nav">
        <?php foreach ($navItems as $item): ?>
        <?php
          $isActive = $auditFundNav === $item['id'];
          $class = 'sidebar__link' . ($isActive ? ' sidebar__link--active' : '');
          $navIcon = audit_fund_sidebar_nav_icon((string) $item['id']);
        ?>
        <a class="<?php echo htmlspecialchars($class, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($item['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"<?php echo $isActive ? ' aria-current="page"' : ''; ?>><span class="sidebar__link-icon"><?php echo $navIcon; ?></span><span class="sidebar__link-label"><?php echo htmlspecialchars($item['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span></a>
        <?php endforeach; ?>
      </nav>
    </aside>
