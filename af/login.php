<?php
/**
 * Обработка формы «Войти»: POST с полями login, password, remember.
 */
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/login_rate_limit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
if (!audit_fund_verify_csrf(audit_fund_post_string('_csrf') ?: null)) {
    audit_fund_redirect_with_flash('index.php', ['login_error' => 'csrf']);
}

$login = trim(audit_fund_post_string('login'));
$password = audit_fund_post_string('password');
$remember = audit_fund_post_string('remember') === '1';

if (audit_fund_login_rate_is_locked($login)) {
    audit_fund_redirect_with_flash('index.php', ['login_error' => 'locked']);
}

$user = audit_fund_try_login($login, $password);
if ($user === null) {
    audit_fund_login_rate_register_failure($login);
    audit_fund_redirect_with_flash('index.php', ['login_error' => '1']);
}

audit_fund_login_rate_clear_success($login);
audit_fund_establish_session($user);

try {
    $pdo = audit_fund_pdo();
    if ($remember) {
        audit_fund_issue_remember_me($pdo, $user['id']);
    } else {
        audit_fund_clear_remember_tokens_for_user($pdo, $user['id']);
    }
} catch (Throwable $e) {
    audit_fund_log_exception($e, 'login remember_me');
    // вход по сессии уже выполнен; remember недоступен без БД
}

header('Location: schedule.php');
exit;
