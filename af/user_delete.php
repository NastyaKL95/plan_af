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
    audit_fund_redirect_with_flash('users.php', ['delete_err' => 'csrf']);
}

$userId = audit_fund_post_int('user_id');
if ($userId <= 0) {
    audit_fund_redirect_with_flash('users.php', ['delete_err' => 'invalid']);
}

$sessionUserId = isset($_SESSION['audit_fund_user_id']) ? (int) $_SESSION['audit_fund_user_id'] : 0;
if ($sessionUserId === $userId) {
    audit_fund_redirect_with_flash('users.php', ['delete_err' => 'self']);
}

try {
    $pdo = audit_fund_pdo();
    audit_fund_users_ensure_role_column($pdo);
} catch (Throwable $e) {
    audit_fund_log_exception($e, 'user_delete pdo');
    audit_fund_redirect_with_flash('users.php', ['delete_err' => 'db']);
}

$stmt = $pdo->prepare('SELECT id, username, display_name, email, role FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row === false) {
    audit_fund_redirect_with_flash('users.php', ['delete_err' => 'not_found']);
}

$role = audit_fund_normalize_role((string) ($row['role'] ?? 'user'));
if ($role === 'admin') {
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND id <> ?");
    $cntStmt->execute([$userId]);
    if ((int) $cntStmt->fetchColumn() < 1) {
        audit_fund_redirect_with_flash('users.php', ['delete_err' => 'last_admin']);
    }
}

$targetLabel = audit_fund_display_login([
    'display_name' => isset($row['display_name']) ? (string) $row['display_name'] : '',
    'username' => (string) ($row['username'] ?? ''),
    'email' => isset($row['email']) && $row['email'] !== null ? (string) $row['email'] : null,
]);

try {
    $del = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $del->execute([$userId]);
} catch (PDOException $e) {
    audit_fund_log_exception($e, 'user_delete');
    $code = (int) ($e->errorInfo[1] ?? 0);
    if ($code === 1451) {
        audit_fund_redirect_with_flash('users.php', ['delete_err' => 'linked']);
    }
    audit_fund_redirect_with_flash('users.php', ['delete_err' => 'db']);
}

if ($del->rowCount() === 0) {
    audit_fund_redirect_with_flash('users.php', ['delete_err' => 'not_found']);
}

audit_fund_log_action('delete', 'user_account', (string) $userId, [
    'target_username' => $targetLabel,
    'role' => $role,
]);

audit_fund_redirect_with_flash('users.php', ['delete_ok' => 1]);
