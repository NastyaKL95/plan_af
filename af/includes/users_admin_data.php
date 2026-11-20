<?php
declare(strict_types=1);

/**
 * @return list<array<string,mixed>>
 */
function audit_fund_users_admin_list(PDO $pdo): array
{
    audit_fund_users_ensure_role_column($pdo);
    $stmt = $pdo->query(
        'SELECT id, username, display_name, email, role, is_active FROM users ORDER BY is_active DESC, username ASC'
    );
    if ($stmt === false) {
        return [];
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return is_array($rows) ? $rows : [];
}
