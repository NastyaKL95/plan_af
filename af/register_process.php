<?php
/**
 * Обработка формы регистрации: display_name, username, email, password, password_confirm.
 */
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/register_rate_limit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}
if (!audit_fund_registration_open()) {
    audit_fund_redirect_with_flash('register.php', ['register_error' => 'registration_closed']);
}
if (!audit_fund_verify_csrf(audit_fund_post_string('_csrf') ?: null)) {
    audit_fund_redirect_with_flash('register.php', ['register_error' => 'csrf']);
}

if (audit_fund_register_rate_is_locked()) {
    audit_fund_redirect_with_flash('register.php', ['register_error' => 'rate_limited']);
}

$displayName = audit_fund_post_string('display_name');
$username = audit_fund_post_string('username');
$email = audit_fund_post_string('email');
$password = audit_fund_post_string('password');
$passwordConfirm = audit_fund_post_string('password_confirm');

$validationError = audit_fund_register_validate($displayName, $username, $email, $password, $passwordConfirm);
if ($validationError !== null) {
    audit_fund_redirect_with_flash('register.php', ['register_error' => $validationError]);
}

$registerError = audit_fund_register_user($displayName, $username, $email, $password);
if ($registerError !== null) {
    audit_fund_register_rate_register_failure();
    audit_fund_redirect_with_flash('register.php', ['register_error' => $registerError]);
}

audit_fund_register_rate_clear_success();
audit_fund_redirect_with_flash('index.php', ['registered' => 1]);
