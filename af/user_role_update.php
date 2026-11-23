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
    audit_fund_redirect_with_flash('users.php', ['role_err' => 'csrf']);
}

$userId = audit_fund_post_int('user_id');
$roleRaw = trim(audit_fund_post_string('role'));
$allowed = ['user', 'manager', 'admin'];
if ($userId <= 0 || !in_array($roleRaw, $allowed, true)) {
    audit_fund_redirect_with_flash('users.php', ['role_err' => 'invalid']);
}

$newRole = $roleRaw;

try {
    $pdo = audit_fund_pdo();
} catch (Throwable $e) {
    audit_fund_log_exception($e, 'user_role_update pdo');
    audit_fund_redirect_with_flash('users.php', ['role_err' => 'db']);
}

audit_fund_users_ensure_role_column($pdo);

$stmt = $pdo->prepare(
    'SELECT id, username, display_name, email, role, is_active FROM users WHERE id = ? LIMIT 1'
);
$stmt->execute([$userId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row === false) {
    audit_fund_redirect_with_flash('users.php', ['role_err' => 'not_found']);
}

$oldRole = audit_fund_normalize_role((string) ($row['role'] ?? 'user'));
$targetLabel = audit_fund_display_login([
    'display_name' => isset($row['display_name']) ? (string) $row['display_name'] : '',
    'username' => (string) ($row['username'] ?? ''),
    'email' => isset($row['email']) && $row['email'] !== null ? (string) $row['email'] : null,
]);

if ($oldRole === $newRole) {
    audit_fund_redirect_with_flash('users.php', ['role_ok' => 1]);
}

if ($oldRole === 'admin' && $newRole !== 'admin') {
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1 AND id <> ?");
    $cntStmt->execute([$userId]);
    $otherActiveAdmins = (int) $cntStmt->fetchColumn();
    if ($otherActiveAdmins < 1) {
        audit_fund_redirect_with_flash('users.php', ['role_err' => 'last_admin']);
    }
}

$upd = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
$upd->execute([$newRole, $userId]);
if ($upd->rowCount() === 0) {
    audit_fund_redirect_with_flash('users.php', ['role_err' => 'not_found']);
}

$sessionUserId = isset($_SESSION['audit_fund_user_id']) ? (int) $_SESSION['audit_fund_user_id'] : 0;
if ($sessionUserId === $userId) {
    $_SESSION['audit_fund_role'] = audit_fund_normalize_role($newRole);
}

audit_fund_log_action('update', 'user_account', (string) $userId, [
    'target_username' => $targetLabel,
    'role_from' => $oldRole,
    'role_to' => $newRole,
]);

audit_fund_redirect_with_flash('users.php', ['role_ok' => 1]);
