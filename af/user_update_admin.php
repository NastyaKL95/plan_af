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
    audit_fund_redirect_with_flash('users.php', ['profile_err' => 'csrf']);
}

$userId = audit_fund_post_int('user_id');
$username = trim(audit_fund_post_string('username'));
$displayName = trim(audit_fund_post_string('display_name'));
$emailRaw = trim(audit_fund_post_string('email'));

if ($userId <= 0) {
    audit_fund_redirect_with_flash('users.php', ['profile_err' => 'invalid']);
}
if (strlen($username) < 3 || strlen($username) > 64) {
    audit_fund_redirect_with_flash('users.php', ['profile_err' => 'username_len']);
}
if (preg_match('/^[a-zA-Z0-9._-]+$/', $username) !== 1) {
    audit_fund_redirect_with_flash('users.php', ['profile_err' => 'username_chars']);
}

$displayDb = null;
if ($displayName !== '') {
    $nameErr = audit_fund_validate_display_name($displayName);
    if ($nameErr !== null) {
        audit_fund_redirect_with_flash('users.php', ['profile_err' => $nameErr]);
    }
    $displayDb = $displayName;
}

$emailDb = null;
if ($emailRaw !== '') {
    $emailDb = strtolower($emailRaw);
    if (strlen($emailDb) > 191 || filter_var($emailDb, FILTER_VALIDATE_EMAIL) === false) {
        audit_fund_redirect_with_flash('users.php', ['profile_err' => 'email_invalid']);
    }
}

try {
    $pdo = audit_fund_pdo();
    audit_fund_users_ensure_role_column($pdo);
} catch (Throwable $e) {
    audit_fund_log_exception($e, 'user_update_admin pdo');
    audit_fund_redirect_with_flash('users.php', ['profile_err' => 'db']);
}

$stmt = $pdo->prepare('SELECT id, username, display_name, email, role FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row === false) {
    audit_fund_redirect_with_flash('users.php', ['profile_err' => 'not_found']);
}

$takenLogin = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
$takenLogin->execute([$username, $userId]);
if ($takenLogin->fetch(PDO::FETCH_ASSOC) !== false) {
    audit_fund_redirect_with_flash('users.php', ['profile_err' => 'user_exists']);
}

if ($emailDb !== null) {
    $takenEmail = $pdo->prepare('SELECT id FROM users WHERE email IS NOT NULL AND LOWER(email) = ? AND id <> ? LIMIT 1');
    $takenEmail->execute([$emailDb, $userId]);
    if ($takenEmail->fetch(PDO::FETCH_ASSOC) !== false) {
        audit_fund_redirect_with_flash('users.php', ['profile_err' => 'email_exists']);
    }
}

try {
    $upd = $pdo->prepare('UPDATE users SET username = ?, display_name = ?, email = ? WHERE id = ?');
    $upd->execute([$username, $displayDb, $emailDb, $userId]);
} catch (PDOException $e) {
    audit_fund_log_exception($e, 'user_update_admin update');
    $code = (int) ($e->errorInfo[1] ?? 0);
    if ($code === 1062) {
        audit_fund_redirect_with_flash('users.php', ['profile_err' => 'duplicate']);
    }
    audit_fund_redirect_with_flash('users.php', ['profile_err' => 'db']);
}

$sessionUserId = isset($_SESSION['audit_fund_user_id']) ? (int) $_SESSION['audit_fund_user_id'] : 0;
if ($sessionUserId === $userId) {
    $_SESSION['audit_fund_login'] = audit_fund_display_login([
        'display_name' => (string) ($displayDb ?? ''),
        'username' => $username,
        'email' => $emailDb,
    ]);
}

audit_fund_log_action('update', 'user_account', (string) $userId, [
    'target_username' => $username,
    'username_from' => (string) ($row['username'] ?? ''),
    'display_name' => (string) ($displayDb ?? ''),
    'email' => (string) ($emailDb ?? ''),
]);

audit_fund_redirect_with_flash('users.php', ['profile_ok' => 1]);
