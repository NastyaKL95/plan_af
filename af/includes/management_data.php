<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

function audit_fund_room_color_normalize(string $colorHex): ?string
{
    $c = strtoupper(trim($colorHex));
    if ($c === '') {
        return '#3B82F6';
    }
    if (!preg_match('/^#[0-9A-F]{6}$/', $c)) {
        return null;
    }

    return $c;
}

function audit_fund_rooms_ensure_color_column(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    if (!audit_fund_runtime_migrations_enabled()) {
        $checked = true;

        return;
    }

    $existsStmt = $pdo->query("SHOW COLUMNS FROM rooms LIKE 'color_hex'");
    $exists = $existsStmt->fetch(PDO::FETCH_ASSOC) !== false;
    if (!$exists) {
        $pdo->exec("ALTER TABLE rooms ADD COLUMN color_hex CHAR(7) NOT NULL DEFAULT '#3B82F6' AFTER room_number");
    }

    $checked = true;
}

/**
 * @return array{ok:bool,rows:list<array<string,mixed>>,stats:array{total:int,active:int,maint:int,buildings:int}|null,error:string|null}
 */
function audit_fund_management_rooms_data(int $page = 1, int $perPage = 50): array
{
    $empty = [
        'ok' => false,
        'rows' => [],
        'stats' => null,
        'error' => null,
    ];

    try {
        $pdo = audit_fund_pdo();
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'management_data');
        $empty['error'] = 'Не удалось подключиться к базе данных.';

        return $empty;
    }

    try {
        audit_fund_rooms_ensure_color_column($pdo);
        $perPage = max(5, min(200, $perPage));
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        // Всего — все строки rooms; «активные» = не maint (как бейдж и логика заявок); корпуса — справочник buildings.
        $cntStmt = $pdo->query(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(operational_status = "maint"), 0) AS maint_cnt,
                    (SELECT COUNT(*) FROM buildings) AS buildings_cnt
             FROM rooms'
        );
        $cntRow = $cntStmt->fetch(PDO::FETCH_ASSOC);
        $total = (int) ($cntRow['total'] ?? 0);
        $maint = (int) ($cntRow['maint_cnt'] ?? 0);
        $active = $total - $maint;
        $buildings = (int) ($cntRow['buildings_cnt'] ?? 0);

        $stmt = $pdo->prepare(
            'SELECT r.id, r.room_number, r.color_hex, r.building_id, r.room_type_id, b.name AS building_name, r.floor, r.capacity,
                    rt.name AS room_type_name, r.operational_status
             FROM rooms r
             INNER JOIN buildings b ON b.id = r.building_id
             INNER JOIN room_types rt ON rt.id = r.room_type_id
             ORDER BY b.sort_order, b.name, r.floor, r.room_number
             LIMIT ' . $perPage . ' OFFSET ' . $offset
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'ok' => true,
            'rows' => $rows,
            'stats' => [
                'total' => $total,
                'active' => $active,
                'maint' => $maint,
                'buildings' => $buildings,
            ],
            'error' => null,
        ];
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'management_data');
        $empty['error'] = 'Ошибка чтения данных. Убедитесь, что применены скрипты sql/03_domain.sql и sql/04_seed_domain.sql.';

        return $empty;
    }
}

/**
 * Эксплуатационный статус в БД: в заявках недоступна только maint; остальное — в работе.
 *
 * @return array{class:string,label:string}
 */
function audit_fund_room_status_badge(string $operationalStatus): array
{
    $s = strtolower(trim($operationalStatus));
    if ($s === 'maint') {
        return ['class' => 'badge badge--maint', 'label' => 'На обслуживании'];
    }

    return ['class' => 'badge badge--free', 'label' => 'Активна'];
}

/**
 * Справочники для формы «Добавить аудиторию».
 *
 * @return array{buildings:list<array{id:int|string,name:string}>,room_types:list<array{id:int|string,name:string}>,error:string|null}
 */
function audit_fund_room_form_references(): array
{
    $out = ['buildings' => [], 'room_types' => [], 'error' => null];

    try {
        $pdo = audit_fund_pdo();
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'management_data');
        $out['error'] = 'Нет подключения к базе данных.';

        return $out;
    }

    try {
        $out['buildings'] = $pdo->query(
            'SELECT id, name FROM buildings ORDER BY sort_order, name'
        )->fetchAll(PDO::FETCH_ASSOC);
        $out['room_types'] = $pdo->query(
            'SELECT id, name FROM room_types ORDER BY sort_order, name'
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'management_data');
        $out['error'] = 'Не удалось загрузить справочники.';
    }

    return $out;
}

/**
 * Добавление аудитории. null — успех, иначе код ошибки.
 */
function audit_fund_room_insert(
    string $roomNumber,
    int $buildingId,
    int $roomTypeId,
    string $colorHex,
    int $floor,
    int $capacity,
    string $operationalStatus
): ?string {
    $roomNumber = trim($roomNumber);
    if ($roomNumber === '' || strlen($roomNumber) > 32) {
        return 'invalid';
    }
    if ($floor < 0 || $floor > 32767) {
        return 'invalid';
    }
    if ($capacity < 0 || $capacity > 65535) {
        return 'invalid';
    }
    if (!in_array($operationalStatus, ['active', 'maint'], true)) {
        return 'invalid';
    }
    $color = audit_fund_room_color_normalize($colorHex);
    if ($color === null) {
        return 'invalid';
    }

    try {
        $pdo = audit_fund_pdo();
        audit_fund_rooms_ensure_color_column($pdo);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'management_data');
        return 'db';
    }

    $chk = $pdo->prepare('SELECT 1 FROM buildings WHERE id = ? LIMIT 1');
    $chk->execute([$buildingId]);
    if ($chk->fetchColumn() === false) {
        return 'invalid';
    }

    $chk = $pdo->prepare('SELECT 1 FROM room_types WHERE id = ? LIMIT 1');
    $chk->execute([$roomTypeId]);
    if ($chk->fetchColumn() === false) {
        return 'invalid';
    }

    try {
        $ins = $pdo->prepare(
            'INSERT INTO rooms (building_id, room_type_id, room_number, color_hex, floor, capacity, operational_status)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([$buildingId, $roomTypeId, $roomNumber, $color, $floor, $capacity, $operationalStatus]);
    } catch (PDOException $e) {
        if ((int) $e->errorInfo[1] === 1062) {
            return 'duplicate';
        }

        return 'db';
    }

    return null;
}

/**
 * Удаление аудитории по id. null — успех, иначе код ошибки.
 */
function audit_fund_room_delete(int $roomId): ?string
{
    if ($roomId <= 0) {
        return 'invalid';
    }

    try {
        $pdo = audit_fund_pdo();
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'management_data');
        return 'db';
    }

    try {
        $del = $pdo->prepare('DELETE FROM rooms WHERE id = ? LIMIT 1');
        $del->execute([$roomId]);
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) === 1451) {
            return 'linked';
        }

        return 'db';
    }

    if ($del->rowCount() === 0) {
        return 'not_found';
    }

    return null;
}

/**
 * Обновление аудитории по id. null — успех, иначе код ошибки.
 */
function audit_fund_room_update(
    int $roomId,
    string $roomNumber,
    int $buildingId,
    int $roomTypeId,
    string $colorHex,
    int $floor,
    int $capacity,
    string $operationalStatus
): ?string {
    if ($roomId <= 0) {
        return 'invalid';
    }

    $roomNumber = trim($roomNumber);
    if ($roomNumber === '' || strlen($roomNumber) > 32) {
        return 'invalid';
    }
    if ($floor < 0 || $floor > 32767) {
        return 'invalid';
    }
    if ($capacity < 0 || $capacity > 65535) {
        return 'invalid';
    }
    if (!in_array($operationalStatus, ['active', 'maint'], true)) {
        return 'invalid';
    }
    $color = audit_fund_room_color_normalize($colorHex);
    if ($color === null) {
        return 'invalid';
    }

    try {
        $pdo = audit_fund_pdo();
        audit_fund_rooms_ensure_color_column($pdo);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'management_data');
        return 'db';
    }

    $chk = $pdo->prepare('SELECT 1 FROM rooms WHERE id = ? LIMIT 1');
    $chk->execute([$roomId]);
    if ($chk->fetchColumn() === false) {
        return 'not_found';
    }

    $chk = $pdo->prepare('SELECT 1 FROM buildings WHERE id = ? LIMIT 1');
    $chk->execute([$buildingId]);
    if ($chk->fetchColumn() === false) {
        return 'invalid';
    }

    $chk = $pdo->prepare('SELECT 1 FROM room_types WHERE id = ? LIMIT 1');
    $chk->execute([$roomTypeId]);
    if ($chk->fetchColumn() === false) {
        return 'invalid';
    }

    try {
        $upd = $pdo->prepare(
            'UPDATE rooms
             SET building_id = ?, room_type_id = ?, room_number = ?, color_hex = ?, floor = ?, capacity = ?, operational_status = ?
             WHERE id = ?
             LIMIT 1'
        );
        $upd->execute([$buildingId, $roomTypeId, $roomNumber, $color, $floor, $capacity, $operationalStatus, $roomId]);
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            return 'duplicate';
        }

        return 'db';
    }

    return null;
}
