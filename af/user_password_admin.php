<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/audit_log.php';
require_once __DIR__ . '/includes/redirect_flash.php';

audit_fund_require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: users.php');
    exit;
}

if (!audit_fund_verify_csrf(audit_fund_post_string('_csrf') ?: null)) {
    audit_fund_redirect_with_flash('users.php', ['pwd_err' => 'csrf']);
}

$userId = audit_fund_post_int('user_id');
$password = audit_fund_post_string('new_password');
$passwordConfirm = audit_fund_post_string('new_password_confirm');

if ($userId <= 0) {
    audit_fund_redirect_with_flash('users.php', ['pwd_err' => 'invalid']);
}

if (strlen($password) < 8) {
    audit_fund_redirect_with_flash('users.php', ['pwd_err' => 'short']);
}

if (!hash_equals($password, $passwordConfirm)) {
    audit_fund_redirect_with_flash('users.php', ['pwd_err' => 'mismatch']);
}

try {
    $pdo = audit_fund_pdo();
} catch (Throwable $e) {
    audit_fund_log_exception($e, 'user_password_admin pdo');
    audit_fund_redirect_with_flash('users.php', ['pwd_err' => 'db']);
}

audit_fund_users_ensure_role_column($pdo);

$stmt = $pdo->prepare('SELECT id, username, display_name, email FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row === false) {
    audit_fund_redirect_with_flash('users.php', ['pwd_err' => 'not_found']);
}

$targetLabel = audit_fund_display_login([
    'display_name' => isset($row['display_name']) ? (string) $row['display_name'] : '',
    'username' => (string) ($row['username'] ?? ''),
    'email' => isset($row['email']) && $row['email'] !== null ? (string) $row['email'] : null,
]);

$hash = password_hash($password, PASSWORD_DEFAULT);
if ($hash === false) {
    audit_fund_redirect_with_flash('users.php', ['pwd_err' => 'db']);
}

$upd = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ? LIMIT 1');
$upd->execute([$hash, $userId]);
if ($upd->rowCount() === 0) {
    audit_fund_redirect_with_flash('users.php', ['pwd_err' => 'not_found']);
}

audit_fund_clear_remember_tokens_for_user($pdo, $userId);

audit_fund_log_action('update', 'user_account', (string) $userId, [
    'target_username' => $targetLabel,
    'password_reset_by_admin' => 'да',
]);

audit_fund_redirect_with_flash('users.php', ['pwd_ok' => 1]);
