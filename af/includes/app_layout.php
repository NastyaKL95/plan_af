<?php
declare(strict_types=1);

/**
 * Имя пользователя и роль для шапки (уже экранированы для HTML).
 * Вызывать после audit_fund_require_auth().
 *
 * @return array{displayName:string,roleLabel:string,isAdmin:bool,isStaff:bool}
 */
function audit_fund_app_layout_vars(): array
{
    $currentLogin = isset($_SESSION['audit_fund_login']) ? (string) $_SESSION['audit_fund_login'] : 'Пользователь';

    return [
        'displayName' => htmlspecialchars($currentLogin, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        'roleLabel' => htmlspecialchars(audit_fund_role_label(audit_fund_current_role()), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        'isAdmin' => audit_fund_is_admin(),
        'isStaff' => audit_fund_is_staff(),
    ];
}
