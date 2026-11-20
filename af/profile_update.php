<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/audit_log.php';
require_once __DIR__ . '/includes/redirect_flash.php';

audit_fund_require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit;
}

if (!audit_fund_verify_csrf(audit_fund_post_string('_csrf') ?: null)) {
    audit_fund_redirect_with_flash('profile.php', ['profile_err' => 'csrf']);
}

$userId = isset($_SESSION['audit_fund_user_id']) ? (int) $_SESSION['audit_fund_user_id'] : 0;
$displayName = audit_fund_post_string('display_name');

$err = audit_fund_user_update_display_name($userId, $displayName);
if ($err !== null) {
    audit_fund_redirect_with_flash('profile.php', ['profile_err' => $err]);
}

audit_fund_log_action('update', 'user_profile', (string) $userId, [
    'display_name' => trim($displayName),
]);

audit_fund_redirect_with_flash('profile.php', ['profile_ok' => 1]);
