<?php

declare(strict_types=1);

$configPath = dirname(__DIR__) . '/app/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo 'Create app/config.php from app/config.example.php';
    exit;
}

$config = require $configPath;
require dirname(__DIR__) . '/app/session.php';
iro_session_start_from_config($config);
$GLOBALS['config'] = $config;

require dirname(__DIR__) . '/app/Database.php';
require dirname(__DIR__) . '/app/helpers.php';
require dirname(__DIR__) . '/app/logger.php';
require dirname(__DIR__) . '/app/security_http.php';
require dirname(__DIR__) . '/app/rate_limit_login.php';
iro_security_headers_send($config);
iro_register_shutdown_log_fatal();
require dirname(__DIR__) . '/app/notifications.php';
require dirname(__DIR__) . '/app/mailer.php';
require dirname(__DIR__) . '/app/Auth.php';
require dirname(__DIR__) . '/app/oauth_yandex.php';
require dirname(__DIR__) . '/app/contest_awards.php';
require dirname(__DIR__) . '/app/contest_archive.php';
require dirname(__DIR__) . '/app/participant_archive.php';
require dirname(__DIR__) . '/app/csrf.php';
require dirname(__DIR__) . '/app/http/web_helpers.php';

$pdo = Database::connection($config);
$GLOBALS['iro_request_path'] = router_uri_path($config);
$uri = $GLOBALS['iro_request_path'];
iro_csrf_verify_post();
$userId = Auth::userId();
$user = Auth::user($pdo);
$flash = flash_all();
$isAdmin = $userId !== null ? has_role($pdo, $userId, 'admin') : false;
$isExpert = $userId !== null && user_has_expert_portal_nav($pdo, $userId);
$isContestModerator = false;
$notifUnread = 0;
$notifPreview = [];
$notifPreviewMoreUnread = 0;
if ($userId !== null) {
    iro_purge_stale_notifications($pdo, $userId);
    $cmStmt = $pdo->prepare('SELECT 1 FROM contest_moderators WHERE user_id = :uid LIMIT 1');
    $cmStmt->execute(['uid' => $userId]);
    $isContestModerator = (bool) $cmStmt->fetchColumn();
    $ucStmt = $pdo->prepare('SELECT COUNT(*) FROM in_app_notifications WHERE user_id = :uid AND is_read = 0');
    $ucStmt->execute(['uid' => $userId]);
    $notifUnread = (int) $ucStmt->fetchColumn();
    $prStmt = $pdo->prepare(
        'SELECT id, title, is_read FROM in_app_notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 5'
    );
    $prStmt->execute(['uid' => $userId]);
    $notifPreview = $prStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $unreadInPreview = 0;
    foreach ($notifPreview as $nrow) {
        if (empty($nrow['is_read'])) {
            $unreadInPreview++;
        }
    }
    $notifPreviewMoreUnread = max(0, $notifUnread - $unreadInPreview);
}
$GLOBALS['base_view_data'] = [
    'user' => $user,
    'flash' => $flash,
    'isAdmin' => $isAdmin,
    'can_manage_contests_global' => can_manage_contests_global($pdo, $userId),
    'is_expert' => $isExpert,
    'is_contest_moderator' => $isContestModerator,
    'notif_unread_count' => $notifUnread,
    'notif_preview' => $notifPreview,
    'notif_preview_more_unread' => $notifPreviewMoreUnread,
];

require dirname(__DIR__) . '/app/http/route_aliases.php';
$uri = iro_legacy_route_aliases($uri);

require dirname(__DIR__) . '/app/http/routes.php';
