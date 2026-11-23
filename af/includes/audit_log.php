<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

function audit_fund_log_ensure_table(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    if (!audit_fund_runtime_migrations_enabled()) {
        $checked = true;

        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS audit_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            user_display VARCHAR(191) DEFAULT NULL,
            action VARCHAR(64) NOT NULL,
            entity_type VARCHAR(64) NOT NULL,
            entity_id VARCHAR(64) DEFAULT NULL,
            details_json TEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_audit_created (created_at),
            KEY idx_audit_user (user_id, created_at),
            KEY idx_audit_entity (entity_type, entity_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $legacyUsername = $pdo->query("SHOW COLUMNS FROM audit_log LIKE 'username'");
    if ($legacyUsername !== false && $legacyUsername->fetch(PDO::FETCH_ASSOC) !== false) {
        $pdo->exec(
            'ALTER TABLE audit_log CHANGE COLUMN username user_display VARCHAR(191) NULL DEFAULT NULL'
        );
    }

    $checked = true;
}

/**
 * @param array<string,mixed> $details
 */
function audit_fund_log_action(
    string $action,
    string $entityType,
    ?string $entityId = null,
    array $details = []
): void {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $pdo = audit_fund_pdo();
        audit_fund_log_ensure_table($pdo);
        $userId = isset($_SESSION['audit_fund_user_id']) ? (int) $_SESSION['audit_fund_user_id'] : null;
        $userDisplay = isset($_SESSION['audit_fund_login']) ? (string) $_SESSION['audit_fund_login'] : null;
        $detailsJson = $details !== [] ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

        $ins = $pdo->prepare(
            'INSERT INTO audit_log (user_id, user_display, action, entity_type, entity_id, details_json)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $userId,
            $userDisplay !== '' ? $userDisplay : null,
            $action,
            $entityType,
            $entityId,
            $detailsJson !== false ? $detailsJson : null,
        ]);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'audit_log');
        // Логирование не должно ломать бизнес-операцию.
    }
}

/**
 * @return array{rows:list<array<string,mixed>>,total:int,error:string|null}
 */
function audit_fund_log_list(
    ?string $dateFrom = null,
    ?string $dateTo = null,
    ?string $userQuery = null,
    ?int $limit = null,
    int $offset = 0
): array {
    $out = ['rows' => [], 'total' => 0, 'error' => null];
    $df = $dateFrom !== null ? trim($dateFrom) : '';
    $dt = $dateTo !== null ? trim($dateTo) : '';
    if ($df !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $df)) {
        $out['error'] = 'Некорректная дата «с». Используйте формат ГГГГ-ММ-ДД.';

        return $out;
    }
    if ($dt !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dt)) {
        $out['error'] = 'Некорректная дата «по». Используйте формат ГГГГ-ММ-ДД.';

        return $out;
    }
    if ($df !== '' && $dt !== '' && $df > $dt) {
        $out['error'] = 'Дата «с» не может быть позже даты «по».';

        return $out;
    }

    try {
        $pdo = audit_fund_pdo();
        audit_fund_log_ensure_table($pdo);
        $where = [];
        $params = [];
        if ($df !== '') {
            $where[] = 'DATE(created_at) >= ?';
            $params[] = $df;
        }
        if ($dt !== '') {
            $where[] = 'DATE(created_at) <= ?';
            $params[] = $dt;
        }
        if ($userQuery !== null && trim($userQuery) !== '') {
            $where[] = 'LOWER(COALESCE(user_display, \'\')) LIKE ?';
            $params[] = '%' . mb_strtolower(trim($userQuery), 'UTF-8') . '%';
        }
        $whereSql = $where !== [] ? (' WHERE ' . implode(' AND ', $where)) : '';

        if ($limit === null) {
            $sql = 'SELECT id, user_id, user_display, action, entity_type, entity_id, details_json, created_at FROM audit_log'
                . $whereSql
                . ' ORDER BY created_at DESC, id DESC LIMIT 10000';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $out['rows'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $out['total'] = count($out['rows']);
        } else {
            $countSql = 'SELECT COUNT(*) FROM audit_log' . $whereSql;
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($params);
            $out['total'] = (int) $countStmt->fetchColumn();

            $lim = max(1, min($limit, 500));
            $off = max(0, $offset);
            // LIMIT/OFFSET нельзя надёжно биндить в нативных prepared statements MySQL — только целые литералы.
            $sql = 'SELECT id, user_id, user_display, action, entity_type, entity_id, details_json, created_at FROM audit_log'
                . $whereSql
                . ' ORDER BY created_at DESC, id DESC LIMIT ' . $lim . ' OFFSET ' . $off;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $out['rows'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'audit_log');
        $out['error'] = 'Не удалось загрузить журнал действий.';
    }

    return $out;
}

/**
 * Удаление записей журнала аудита. Только для вызова после проверки прав администратора.
 *
 * Режимы: before — DATE(created_at) &lt; $beforeDate; range — даты включительно; all — вся таблица.
 *
 * @return array{deleted:int,error:string|null}
 */
function audit_fund_log_purge(string $mode, ?string $beforeDate = null, ?string $dateFrom = null, ?string $dateTo = null): array
{
    if (!in_array($mode, ['before', 'range', 'all'], true)) {
        return ['deleted' => 0, 'error' => 'invalid'];
    }

    try {
        $pdo = audit_fund_pdo();
        audit_fund_log_ensure_table($pdo);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'audit_log');
        return ['deleted' => 0, 'error' => 'db'];
    }

    try {
        if ($mode === 'all') {
            $stmt = $pdo->query('DELETE FROM audit_log');
            $deleted = $stmt !== false ? $stmt->rowCount() : 0;
        } elseif ($mode === 'before') {
            $beforeDate = $beforeDate !== null ? trim($beforeDate) : '';
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $beforeDate)) {
                return ['deleted' => 0, 'error' => 'invalid'];
            }
            $stmt = $pdo->prepare('DELETE FROM audit_log WHERE DATE(created_at) < ?');
            $stmt->execute([$beforeDate]);
            $deleted = $stmt->rowCount();
        } else {
            $dateFrom = $dateFrom !== null ? trim($dateFrom) : '';
            $dateTo = $dateTo !== null ? trim($dateTo) : '';
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
                return ['deleted' => 0, 'error' => 'invalid'];
            }
            if ($dateFrom > $dateTo) {
                return ['deleted' => 0, 'error' => 'invalid'];
            }
            $stmt = $pdo->prepare(
                'DELETE FROM audit_log WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?'
            );
            $stmt->execute([$dateFrom, $dateTo]);
            $deleted = $stmt->rowCount();
        }
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'audit_log');
        return ['deleted' => 0, 'error' => 'db'];
    }

    return ['deleted' => $deleted, 'error' => null];
}

function audit_fund_audit_action_label(string $action): string
{
    $map = [
        'create' => 'Создание',
        'update' => 'Изменение',
        'delete' => 'Удаление',
        'move' => 'Перенос',
        'approve' => 'Одобрение',
        'reject' => 'Отклонение',
    ];

    return $map[$action] ?? $action;
}

function audit_fund_audit_entity_label(string $entity): string
{
    $map = [
        'request' => 'Заявка',
        'room' => 'Аудитория',
        'building' => 'Корпус',
        'room_type' => 'Тип аудитории',
        'maintenance_window' => 'Окно обслуживания',
        'schedule_entry' => 'Запись расписания',
        'user_account' => 'Учётная запись',
        'audit_log' => 'Журнал аудита',
    ];

    return $map[$entity] ?? $entity;
}

/**
 * @param mixed $value
 */
function audit_fund_audit_value_to_text($value): string
{
    if (is_bool($value)) {
        return $value ? 'Да' : 'Нет';
    }
    if ($value === null) {
        return '—';
    }
    if (is_array($value)) {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json !== false ? $json : '—';
    }

    return (string) $value;
}

/**
 * @return list<string>
 */
function audit_fund_audit_details_pretty(?string $json): array
{
    if (!is_string($json) || trim($json) === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded) || $decoded === []) {
        return [$json];
    }

    $keyMap = [
        'room_id' => 'ID аудитории',
        'booking_date' => 'Дата',
        'time_start' => 'Время с',
        'time_end' => 'Время до',
        'status' => 'Статус',
        'rejection_reason' => 'Причина отклонения',
        'maint_date' => 'Дата обслуживания',
        'reason' => 'Причина',
        'room_number' => 'Номер аудитории',
        'building_id' => 'ID корпуса',
        'room_type_id' => 'ID типа аудитории',
        'code' => 'Код',
        'name' => 'Название',
        'target_username' => 'Пользователь',
        'display_name' => 'Имя',
        'role_from' => 'Роль (было)',
        'role_to' => 'Роль (стало)',
        'purge_mode' => 'Режим очистки',
        'deleted_rows' => 'Удалено записей',
        'new_room_id' => 'Новая аудитория (ID)',
        'new_date' => 'Новая дата',
        'relocate_time_start' => 'Время после переноса (начало)',
        'relocate_time_end' => 'Время после переноса (окончание)',
        'context' => 'Контекст',
        'account_access' => 'Доступ к учётной записи',
        'password_reset_by_admin' => 'Пароль задан администратором',
    ];

    $lines = [];
    foreach ($decoded as $key => $value) {
        $label = $keyMap[$key] ?? $key;
        $lines[] = $label . ': ' . audit_fund_audit_value_to_text($value);
    }

    return $lines;
}

/**
 * Текст для одной ячейки Excel (детали записи).
 */
function audit_fund_audit_details_excel_cell(?string $json): string
{
    $lines = audit_fund_audit_details_pretty($json);

    return $lines === [] ? '' : implode("\n", $lines);
}
