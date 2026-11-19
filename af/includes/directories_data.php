<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

/**
 * @return array{rows:list<array<string,mixed>>,error:string|null}
 */
function audit_fund_directories_buildings_data(): array
{
    $out = ['rows' => [], 'error' => null];

    try {
        $pdo = audit_fund_pdo();
        $stmt = $pdo->query(
            'SELECT b.id, b.code, b.name, b.address, COUNT(r.id) AS rooms_count
             FROM buildings b
             LEFT JOIN rooms r ON r.building_id = b.id
             GROUP BY b.id, b.code, b.name, b.address, b.sort_order
             ORDER BY b.sort_order, b.name'
        );
        $out['rows'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'directories_data');
        $out['error'] = 'Не удалось загрузить список корпусов.';
    }

    return $out;
}

/**
 * Добавление корпуса: код в БД генерируется автоматически (BLD-{id}).
 *
 * @return array{error:?string,id:?int,code:?string}
 */
function audit_fund_building_insert(string $name, string $address): array
{
    $bad = static function (?string $err): array {
        return ['error' => $err, 'id' => null, 'code' => null];
    };

    $name = trim($name);
    $address = trim($address);

    if ($name === '' || mb_strlen($name, 'UTF-8') > 255) {
        return $bad('invalid');
    }
    if (mb_strlen($address, 'UTF-8') > 500) {
        return $bad('invalid');
    }

    try {
        $pdo = audit_fund_pdo();
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'directories_data');

        return $bad('db');
    }

    try {
        $pdo->beginTransaction();
        $nextSortOrder = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM buildings')->fetchColumn();
        $tempCode = 'TMP' . bin2hex(random_bytes(8));
        $ins = $pdo->prepare(
            'INSERT INTO buildings (code, name, address, sort_order)
             VALUES (?, ?, ?, ?)'
        );
        $ins->execute([$tempCode, $name, $address !== '' ? $address : null, $nextSortOrder]);
        $id = (int) $pdo->lastInsertId();
        $code = 'BLD-' . $id;

        $dupChk = $pdo->prepare('SELECT 1 FROM buildings WHERE code = ? AND id <> ? LIMIT 1');
        for ($i = 0; $i < 6; $i++) {
            $dupChk->execute([$code, $id]);
            if ($dupChk->fetchColumn() === false) {
                break;
            }
            $code = 'BLD-' . $id . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
        }
        if (mb_strlen($code, 'UTF-8') > 32) {
            $pdo->rollBack();

            return $bad('db');
        }

        $upd = $pdo->prepare('UPDATE buildings SET code = ? WHERE id = ? LIMIT 1');
        $upd->execute([$code, $id]);
        $pdo->commit();

        return ['error' => null, 'id' => $id, 'code' => $code];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            return $bad('duplicate');
        }

        audit_fund_log_exception($e, 'directories_data');

        return $bad('db');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        audit_fund_log_exception($e, 'directories_data');

        return $bad('db');
    }
}

/**
 * Обновление корпуса (код в БД не меняется).
 */
function audit_fund_building_update(int $buildingId, string $name, string $address): ?string
{
    if ($buildingId <= 0) {
        return 'invalid';
    }

    $name = trim($name);
    $address = trim($address);

    if ($name === '' || mb_strlen($name, 'UTF-8') > 255) {
        return 'invalid';
    }
    if (mb_strlen($address, 'UTF-8') > 500) {
        return 'invalid';
    }

    try {
        $pdo = audit_fund_pdo();
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'directories_data');
        return 'db';
    }

    $chk = $pdo->prepare('SELECT 1 FROM buildings WHERE id = ? LIMIT 1');
    $chk->execute([$buildingId]);
    if ($chk->fetchColumn() === false) {
        return 'not_found';
    }

    try {
        $upd = $pdo->prepare(
            'UPDATE buildings
             SET name = ?, address = ?
             WHERE id = ?
             LIMIT 1'
        );
        $upd->execute([$name, $address !== '' ? $address : null, $buildingId]);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'directories_data');

        return 'db';
    }

    return null;
}

/**
 * Удаление корпуса. null — успех, иначе код ошибки.
 */
function audit_fund_building_delete(int $buildingId): ?string
{
    if ($buildingId <= 0) {
        return 'invalid';
    }

    try {
        $pdo = audit_fund_pdo();
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'directories_data');
        return 'db';
    }

    try {
        $del = $pdo->prepare('DELETE FROM buildings WHERE id = ? LIMIT 1');
        $del->execute([$buildingId]);
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
 * @return array{rows:list<array<string,mixed>>,error:string|null}
 */
function audit_fund_directories_room_types_data(): array
{
    $out = ['rows' => [], 'error' => null];

    try {
        $pdo = audit_fund_pdo();
        $stmt = $pdo->query(
            'SELECT rt.id, rt.code, rt.name, rt.description, COUNT(r.id) AS rooms_count
             FROM room_types rt
             LEFT JOIN rooms r ON r.room_type_id = rt.id
             GROUP BY rt.id, rt.code, rt.name, rt.description, rt.sort_order
             ORDER BY rt.sort_order, rt.name'
        );
        $out['rows'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'directories_data');
        $out['error'] = 'Не удалось загрузить типы аудиторий.';
    }

    return $out;
}

/**
 * Добавление типа аудитории: код в БД генерируется автоматически (RT-{id}).
 *
 * @return array{error:?string,id:?int,code:?string}
 */
function audit_fund_room_type_insert(string $name, string $description): array
{
    $bad = static function (?string $err): array {
        return ['error' => $err, 'id' => null, 'code' => null];
    };

    $name = trim($name);
    $description = trim($description);

    if ($name === '' || mb_strlen($name, 'UTF-8') > 128) {
        return $bad('invalid');
    }
    if (mb_strlen($description, 'UTF-8') > 500) {
        return $bad('invalid');
    }

    try {
        $pdo = audit_fund_pdo();
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'directories_data');

        return $bad('db');
    }

    try {
        $pdo->beginTransaction();
        $nextSortOrder = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM room_types')->fetchColumn();
        $tempCode = 'TMP' . bin2hex(random_bytes(8));
        $ins = $pdo->prepare(
            'INSERT INTO room_types (code, name, description, sort_order)
             VALUES (?, ?, ?, ?)'
        );
        $ins->execute([$tempCode, $name, $description !== '' ? $description : null, $nextSortOrder]);
        $id = (int) $pdo->lastInsertId();
        $code = 'RT-' . $id;

        $dupChk = $pdo->prepare('SELECT 1 FROM room_types WHERE code = ? AND id <> ? LIMIT 1');
        for ($i = 0; $i < 6; $i++) {
            $dupChk->execute([$code, $id]);
            if ($dupChk->fetchColumn() === false) {
                break;
            }
            $code = 'RT-' . $id . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
        }
        if (mb_strlen($code, 'UTF-8') > 32) {
            $pdo->rollBack();

            return $bad('db');
        }

        $upd = $pdo->prepare('UPDATE room_types SET code = ? WHERE id = ? LIMIT 1');
        $upd->execute([$code, $id]);
        $pdo->commit();

        return ['error' => null, 'id' => $id, 'code' => $code];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            return $bad('duplicate');
        }

        audit_fund_log_exception($e, 'directories_data');

        return $bad('db');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        audit_fund_log_exception($e, 'directories_data');

        return $bad('db');
    }
}

/**
 * Обновление типа аудитории (код в БД не меняется).
 */
function audit_fund_room_type_update(int $roomTypeId, string $name, string $description): ?string
{
    if ($roomTypeId <= 0) {
        return 'invalid';
    }

    $name = trim($name);
    $description = trim($description);

    if ($name === '' || mb_strlen($name, 'UTF-8') > 128) {
        return 'invalid';
    }
    if (mb_strlen($description, 'UTF-8') > 500) {
        return 'invalid';
    }

    try {
        $pdo = audit_fund_pdo();
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'directories_data');
        return 'db';
    }

    $chk = $pdo->prepare('SELECT 1 FROM room_types WHERE id = ? LIMIT 1');
    $chk->execute([$roomTypeId]);
    if ($chk->fetchColumn() === false) {
        return 'not_found';
    }

    try {
        $upd = $pdo->prepare(
            'UPDATE room_types
             SET name = ?, description = ?
             WHERE id = ?
             LIMIT 1'
        );
        $upd->execute([$name, $description !== '' ? $description : null, $roomTypeId]);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'directories_data');

        return 'db';
    }

    return null;
}

/**
 * Удаление типа аудитории. null — успех, иначе код ошибки.
 */
function audit_fund_room_type_delete(int $roomTypeId): ?string
{
    if ($roomTypeId <= 0) {
        return 'invalid';
    }

    try {
        $pdo = audit_fund_pdo();
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'directories_data');
        return 'db';
    }

    try {
        $del = $pdo->prepare('DELETE FROM room_types WHERE id = ? LIMIT 1');
        $del->execute([$roomTypeId]);
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
