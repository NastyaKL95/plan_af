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
    audit_fund_redirect_with_flash('users.php', ['block_err' => 'csrf']);
}

$userId = audit_fund_post_int('user_id');
$setActiveRaw = trim(audit_fund_post_string('set_active'));

if ($userId <= 0 || !in_array($setActiveRaw, ['0', '1'], true)) {
    audit_fund_redirect_with_flash('users.php', ['block_err' => 'invalid']);
}

$setActive = $setActiveRaw === '1';

$sessionUserId = isset($_SESSION['audit_fund_user_id']) ? (int) $_SESSION['audit_fund_user_id'] : 0;
if (!$setActive && $sessionUserId === $userId) {
    audit_fund_redirect_with_flash('users.php', ['block_err' => 'self']);
}

try {
    $pdo = audit_fund_pdo();
} catch (Throwable $e) {
    audit_fund_log_exception($e, 'user_block_update pdo');
    audit_fund_redirect_with_flash('users.php', ['block_err' => 'db']);
}

audit_fund_users_ensure_role_column($pdo);

$stmt = $pdo->prepare(
    'SELECT id, username, display_name, email, role, is_active FROM users WHERE id = ? LIMIT 1'
);
$stmt->execute([$userId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row === false) {
    audit_fund_redirect_with_flash('users.php', ['block_err' => 'not_found']);
}

$wasActive = (int) ($row['is_active'] ?? 0) === 1;
$role = audit_fund_normalize_role((string) ($row['role'] ?? 'user'));

if (!$setActive && $wasActive && $role === 'admin') {
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1 AND id <> ?");
    $cntStmt->execute([$userId]);
    if ((int) $cntStmt->fetchColumn() < 1) {
        audit_fund_redirect_with_flash('users.php', ['block_err' => 'last_admin']);
    }
}

if ($wasActive === $setActive) {
    audit_fund_redirect_with_flash('users.php', ['block_ok' => 1]);
}

$upd = $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ? LIMIT 1');
$upd->execute([$setActive ? 1 : 0, $userId]);
if ($upd->rowCount() === 0) {
    audit_fund_redirect_with_flash('users.php', ['block_err' => 'not_found']);
}

if (!$setActive) {
    audit_fund_clear_remember_tokens_for_user($pdo, $userId);
}

$targetLabel = audit_fund_display_login([
    'display_name' => isset($row['display_name']) ? (string) $row['display_name'] : '',
    'username' => (string) ($row['username'] ?? ''),
    'email' => isset($row['email']) && $row['email'] !== null ? (string) $row['email'] : null,
]);

audit_fund_log_action('update', 'user_account', (string) $userId, [
    'target_username' => $targetLabel,
    'account_access' => $setActive ? 'активна' : 'заблокирована',
]);

audit_fund_redirect_with_flash('users.php', ['block_ok' => 1]);
