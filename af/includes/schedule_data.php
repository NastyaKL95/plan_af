<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/requests_data.php';

function audit_fund_schedule_ensure_room_color_column(PDO $pdo): void
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

function audit_fund_schedule_ensure_maintenance_table(PDO $pdo): void
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
        'CREATE TABLE IF NOT EXISTS room_maintenance_windows (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            room_id BIGINT UNSIGNED NOT NULL,
            maint_date DATE NOT NULL,
            time_start TIME NOT NULL,
            time_end TIME NOT NULL,
            reason VARCHAR(1000) DEFAULT NULL,
            created_by_user_id BIGINT UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_maintenance_room_date (room_id, maint_date),
            KEY idx_maintenance_date (maint_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $checked = true;
}

/**
 * @return array{rows:list<array<string,mixed>>,error:string|null}
 */
function audit_fund_schedule_entries_data(?string $dateFrom = null, ?string $dateTo = null): array
{
    $out = ['rows' => [], 'error' => null];

    try {
        $pdo = audit_fund_pdo();
        audit_fund_schedule_ensure_room_color_column($pdo);
        audit_fund_schedule_ensure_maintenance_table($pdo);
        if ($dateFrom !== null && $dateTo !== null) {
            $stmt = $pdo->prepare(
                'SELECT x.id, x.room_id, x.entry_date, x.time_start, x.time_end, x.comment,
                        x.request_id, x.entry_kind,
                        r.room_number, r.color_hex, b.name AS building_name
                 FROM (
                    SELECT se.id, se.room_id, se.entry_date, se.time_start, se.time_end, se.comment
                         , NULL AS request_id, "schedule" AS entry_kind
                    FROM schedule_entries se
                    UNION ALL
                    SELECT NULL AS id, br.room_id, br.booking_date AS entry_date, br.time_start, br.time_end,
                           COALESCE(br.comment, "Заявка одобрена") AS comment,
                           br.id AS request_id, "approved_request" AS entry_kind
                    FROM booking_requests br
                    LEFT JOIN schedule_entries se2
                      ON se2.room_id = br.room_id
                     AND se2.entry_date = br.booking_date
                     AND se2.time_start = br.time_start
                     AND se2.time_end = br.time_end
                    WHERE br.status = "approved"
                      AND se2.id IS NULL
                    UNION ALL
                    SELECT mw.id, mw.room_id, mw.maint_date AS entry_date, mw.time_start, mw.time_end,
                           COALESCE(mw.reason, "Техническое обслуживание") AS comment,
                           NULL AS request_id, "maintenance" AS entry_kind
                    FROM room_maintenance_windows mw
                 ) x
                 INNER JOIN rooms r ON r.id = x.room_id
                 INNER JOIN buildings b ON b.id = r.building_id
                 WHERE x.entry_date BETWEEN ? AND ?
                 ORDER BY x.entry_date, x.time_start, b.sort_order, b.name, r.room_number'
            );
            $stmt->execute([$dateFrom, $dateTo]);
               } else {
            $stmt = $pdo->query(
                'SELECT x.id, x.room_id, x.entry_date, x.time_start, x.time_end, x.comment,
                        x.request_id, x.entry_kind,
                        r.room_number, r.color_hex, b.name AS building_name
                 FROM (
                    SELECT se.id, se.room_id, se.entry_date, se.time_start, se.time_end, se.comment
                         , NULL AS request_id, "schedule" AS entry_kind
                    FROM schedule_entries se
                    UNION ALL
                    SELECT NULL AS id, br.room_id, br.booking_date AS entry_date, br.time_start, br.time_end,
                           COALESCE(br.comment, "Заявка одобрена") AS comment,
                           br.id AS request_id, "approved_request" AS entry_kind
                    FROM booking_requests br
                    LEFT JOIN schedule_entries se2
                      ON se2.room_id = br.room_id
                     AND se2.entry_date = br.booking_date
                     AND se2.time_start = br.time_start
                     AND se2.time_end = br.time_end
                    WHERE br.status = "approved"
                      AND se2.id IS NULL
                    UNION ALL
                    SELECT mw.id, mw.room_id, mw.maint_date AS entry_date, mw.time_start, mw.time_end,
                           COALESCE(mw.reason, "Техническое обслуживание") AS comment,
                           NULL AS request_id, "maintenance" AS entry_kind
                    FROM room_maintenance_windows mw
                 ) x
                 INNER JOIN rooms r ON r.id = x.room_id
                 INNER JOIN buildings b ON b.id = r.building_id
                 ORDER BY x.entry_date, x.time_start, b.sort_order, b.name, r.room_number'
            );
        }
        $out['rows'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'schedule_data');
        $out['error'] = 'Не удалось загрузить данные расписания.';
    }

    return $out;
}

/**
 * @return array{rows:list<array<string,mixed>>,rooms:list<array<string,mixed>>,error:string|null}
 */
function audit_fund_schedule_maintenance_data(?string $dateFrom = null, ?string $dateTo = null): array
{
    $out = ['rows' => [], 'rooms' => [], 'error' => null];

    try {
        $pdo = audit_fund_pdo();
        audit_fund_schedule_ensure_maintenance_table($pdo);

        $out['rooms'] = $pdo->query(
            'SELECT r.id, r.room_number, b.name AS building_name
             FROM rooms r
             INNER JOIN buildings b ON b.id = r.building_id
             ORDER BY b.sort_order, b.name, r.floor, r.room_number'
        )->fetchAll(PDO::FETCH_ASSOC);

        if ($dateFrom !== null && $dateTo !== null) {
            $stmt = $pdo->prepare(
                'SELECT mw.id, mw.room_id, mw.maint_date, mw.time_start, mw.time_end, mw.reason,
                        r.room_number, b.name AS building_name
                 FROM room_maintenance_windows mw
                 INNER JOIN rooms r ON r.id = mw.room_id
                 INNER JOIN buildings b ON b.id = r.building_id
                 WHERE mw.maint_date BETWEEN ? AND ?
                 ORDER BY mw.maint_date, mw.time_start, b.sort_order, b.name, r.room_number'
            );
            $stmt->execute([$dateFrom, $dateTo]);
            $out['rows'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $out['rows'] = $pdo->query(
                'SELECT mw.id, mw.room_id, mw.maint_date, mw.time_start, mw.time_end, mw.reason,
                        r.room_number, b.name AS building_name
                 FROM room_maintenance_windows mw
                 INNER JOIN rooms r ON r.id = mw.room_id
                 INNER JOIN buildings b ON b.id = r.building_id
                 ORDER BY mw.maint_date, mw.time_start, b.sort_order, b.name, r.room_number'
            )->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'schedule_data');
        $out['error'] = 'Не удалось загрузить окна обслуживания.';
    }

    return $out;
}

function audit_fund_maintenance_window_insert(
    int $roomId,
    string $maintDate,
    string $timeStart,
    string $timeEnd,
    string $reason,
    int $userId
): ?string {
    if ($roomId <= 0 || $userId <= 0) {
        return 'invalid';
    }
    $maintDate = trim($maintDate);
    $timeStart = trim($timeStart);
    $timeEnd = trim($timeEnd);
    $reason = trim($reason);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $maintDate)) {
        return 'invalid';
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $timeStart) || !preg_match('/^\d{2}:\d{2}$/', $timeEnd)) {
        return 'invalid';
    }
    if ($timeStart >= $timeEnd || strlen($reason) > 1000) {
        return 'invalid';
    }
    $startTs = strtotime($maintDate . ' ' . $timeStart);
    if ($startTs === false || $startTs <= time()) {
        return 'past';
    }
    $timeStartDb = $timeStart . ':00';
    $timeEndDb = $timeEnd . ':00';

    try {
        $pdo = audit_fund_pdo();
        audit_fund_schedule_ensure_maintenance_table($pdo);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'schedule_data');
        return 'db';
    }

    try {
        $roomStmt = $pdo->prepare('SELECT 1 FROM rooms WHERE id = ? LIMIT 1');
        $roomStmt->execute([$roomId]);
        if ($roomStmt->fetchColumn() === false) {
            return 'invalid';
        }

        $lockPairs = [[$roomId, $maintDate]];
        if (!audit_fund_mysql_acquire_room_date_locks($pdo, $lockPairs)) {
            return 'db';
        }
        try {
            $scheduleConflict = $pdo->prepare(
                'SELECT 1 FROM schedule_entries WHERE room_id = ?
                   AND entry_date = ?
                   AND ? < time_end
                   AND ? > time_start
                 LIMIT 1'
            );
            $scheduleConflict->execute([$roomId, $maintDate, $timeStartDb, $timeEndDb]);
            if ($scheduleConflict->fetchColumn() !== false) {
                return 'busy';
            }

            $requestConflict = $pdo->prepare(
                'SELECT 1 FROM booking_requests
                 WHERE room_id = ?
                   AND booking_date = ?
                   AND status IN ("pending", "approved")
                   AND ? < time_end
                   AND ? > time_start
                 LIMIT 1'
            );
            $requestConflict->execute([$roomId, $maintDate, $timeStartDb, $timeEndDb]);
            if ($requestConflict->fetchColumn() !== false) {
                return 'busy';
            }

            $maintConflict = $pdo->prepare(
                'SELECT 1 FROM room_maintenance_windows
                 WHERE room_id = ?
                   AND maint_date = ?
                   AND ? < time_end
                   AND ? > time_start
                 LIMIT 1'
            );
            $maintConflict->execute([$roomId, $maintDate, $timeStartDb, $timeEndDb]);
            if ($maintConflict->fetchColumn() !== false) {
                return 'overlap';
            }

            $ins = $pdo->prepare(
                'INSERT INTO room_maintenance_windows (room_id, maint_date, time_start, time_end, reason, created_by_user_id)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $ins->execute([
                $roomId,
                $maintDate,
                $timeStartDb,
                $timeEndDb,
                $reason !== '' ? $reason : null,
                $userId,
            ]);
        } finally {
            audit_fund_mysql_release_room_date_locks($pdo, $lockPairs);
        }
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'schedule_data');
        return 'db';
    }

    return null;
}

function audit_fund_maintenance_window_delete(int $windowId): ?string
{
    if ($windowId <= 0) {
        return 'invalid';
    }
    try {
        $pdo = audit_fund_pdo();
        audit_fund_schedule_ensure_maintenance_table($pdo);
        $del = $pdo->prepare('DELETE FROM room_maintenance_windows WHERE id = ? LIMIT 1');
        $del->execute([$windowId]);
        if ($del->rowCount() === 0) {
            return 'not_found';
        }
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'schedule_data');
        return 'db';
    }

    return null;
}

function audit_fund_maintenance_window_update(
    int $windowId,
    int $roomId,
    string $maintDate,
    string $timeStart,
    string $timeEnd,
    string $reason
): ?string {
    if ($windowId <= 0 || $roomId <= 0) {
        return 'invalid';
    }
    $maintDate = trim($maintDate);
    $timeStart = trim($timeStart);
    $timeEnd = trim($timeEnd);
    $reason = trim($reason);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $maintDate)) {
        return 'invalid';
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $timeStart) || !preg_match('/^\d{2}:\d{2}$/', $timeEnd)) {
        return 'invalid';
    }
    if ($timeStart >= $timeEnd || strlen($reason) > 1000) {
        return 'invalid';
    }
    $startTs = strtotime($maintDate . ' ' . $timeStart);
    if ($startTs === false || $startTs <= time()) {
        return 'past';
    }
    $timeStartDb = $timeStart . ':00';
    $timeEndDb = $timeEnd . ':00';

    try {
        $pdo = audit_fund_pdo();
        audit_fund_schedule_ensure_maintenance_table($pdo);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'schedule_data');
        return 'db';
    }

    try {
        $existsStmt = $pdo->prepare('SELECT room_id, maint_date FROM room_maintenance_windows WHERE id = ? LIMIT 1');
        $existsStmt->execute([$windowId]);
        $exRow = $existsStmt->fetch(PDO::FETCH_ASSOC);
        if ($exRow === false) {
            return 'not_found';
        }
        $oldMaintRoomId = (int) $exRow['room_id'];
        $oldMaintDate = (string) $exRow['maint_date'];

        $roomStmt = $pdo->prepare('SELECT 1 FROM rooms WHERE id = ? LIMIT 1');
        $roomStmt->execute([$roomId]);
        if ($roomStmt->fetchColumn() === false) {
            return 'invalid';
        }

        $lockPairs = [[$roomId, $maintDate]];
        if ($oldMaintRoomId !== $roomId || $oldMaintDate !== $maintDate) {
            $lockPairs[] = [$oldMaintRoomId, $oldMaintDate];
        }
        if (!audit_fund_mysql_acquire_room_date_locks($pdo, $lockPairs)) {
            return 'db';
        }
        try {
            $scheduleConflict = $pdo->prepare(
                'SELECT 1 FROM schedule_entries
                 WHERE room_id = ?
                   AND entry_date = ?
                   AND ? < time_end
                   AND ? > time_start
                 LIMIT 1'
            );
            $scheduleConflict->execute([$roomId, $maintDate, $timeStartDb, $timeEndDb]);
            if ($scheduleConflict->fetchColumn() !== false) {
                return 'busy';
            }

            $requestConflict = $pdo->prepare(
                'SELECT 1 FROM booking_requests
                 WHERE room_id = ?
                   AND booking_date = ?
                   AND status IN ("pending", "approved")
                   AND ? < time_end
                   AND ? > time_start
                 LIMIT 1'
            );
            $requestConflict->execute([$roomId, $maintDate, $timeStartDb, $timeEndDb]);
            if ($requestConflict->fetchColumn() !== false) {
                return 'busy';
            }

            $maintConflict = $pdo->prepare(
                'SELECT 1 FROM room_maintenance_windows
                 WHERE room_id = ?
                   AND maint_date = ?
                   AND id <> ?
                   AND ? < time_end
                   AND ? > time_start
                 LIMIT 1'
            );
            $maintConflict->execute([$roomId, $maintDate, $windowId, $timeStartDb, $timeEndDb]);
            if ($maintConflict->fetchColumn() !== false) {
                return 'overlap';
            }

            $upd = $pdo->prepare(
                'UPDATE room_maintenance_windows
                 SET room_id = ?, maint_date = ?, time_start = ?, time_end = ?, reason = ?
                 WHERE id = ? LIMIT 1'
            );
            $upd->execute([
                $roomId,
                $maintDate,
                $timeStartDb,
                $timeEndDb,
                $reason !== '' ? $reason : null,
                $windowId,
            ]);
        } finally {
            audit_fund_mysql_release_room_date_locks($pdo, $lockPairs);
        }
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'schedule_data');
        return 'db';
    }

    return null;
}

/**
 * Разбор интервала для переноса: из полей формы или из текущих значений в БД.
 *
 * @return array{time_start:string,time_end:string}|string Карта полей в БД или код ошибки.
 */
function audit_fund_relocate_parse_interval(
    string $oldTimeStartDb,
    string $oldTimeEndDb,
    ?string $rawStart,
    ?string $rawEnd
) {
    $dispStart = substr($oldTimeStartDb, 0, 5);
    $dispEnd = audit_fund_request_time_end_display_hhmm($oldTimeEndDb);

    $tsN = ($rawStart !== null && trim($rawStart) !== '')
        ? audit_fund_normalize_time_hhmm(trim($rawStart))
        : audit_fund_normalize_time_hhmm($dispStart);
    $teN = ($rawEnd !== null && trim($rawEnd) !== '')
        ? audit_fund_normalize_time_hhmm(trim($rawEnd))
        : audit_fund_normalize_time_hhmm($dispEnd);

    if ($tsN === null || $teN === null) {
        return 'bad_time';
    }
    if (!audit_fund_request_validate_interval_order($tsN, $teN)) {
        return 'bad_interval';
    }

    [$tsDb, $teDb] = audit_fund_request_interval_times_for_db($tsN, $teN);

    return ['time_start' => $tsDb, 'time_end' => $teDb];
}

/**
 * Перенос записи schedule_entries: дата, аудитория и/или время. $newRoomId <= 0 — та же аудитория.
 */
function audit_fund_schedule_entry_relocate(
    int $entryId,
    string $newDate,
    int $newRoomId,
    ?string $rawTimeStart = null,
    ?string $rawTimeEnd = null
): ?string {
    if ($entryId <= 0) {
        return 'invalid';
    }
    $newDate = trim($newDate);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate)) {
        return 'invalid';
    }

    try {
        $pdo = audit_fund_pdo();
        audit_fund_schedule_ensure_maintenance_table($pdo);
        audit_fund_requests_ensure_columns($pdo);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'schedule_data');
        return 'db';
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'SELECT id, room_id, entry_date, time_start, time_end
             FROM schedule_entries
             WHERE id = ?
             LIMIT 1'
        );
        $stmt->execute([$entryId]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($entry === false) {
            $pdo->rollBack();
            return 'not_found';
        }

        $oldRoomId = (int) $entry['room_id'];
        $oldDate = (string) $entry['entry_date'];
        $oldTimeStartDb = (string) $entry['time_start'];
        $oldTimeEndDb = (string) $entry['time_end'];

        $parsed = audit_fund_relocate_parse_interval($oldTimeStartDb, $oldTimeEndDb, $rawTimeStart, $rawTimeEnd);
        if (is_string($parsed)) {
            $pdo->rollBack();
            return $parsed;
        }
        $timeStart = $parsed['time_start'];
        $timeEnd = $parsed['time_end'];

        if ($newRoomId <= 0) {
            $newRoomId = $oldRoomId;
        }

        if (
            $oldRoomId === $newRoomId
            && $oldDate === $newDate
            && $oldTimeStartDb === $timeStart
            && $oldTimeEndDb === $timeEnd
        ) {
            $pdo->rollBack();
            return 'invalid';
        }

        $roomStmt = $pdo->prepare('SELECT 1 FROM rooms WHERE id = ? LIMIT 1');
        $roomStmt->execute([$newRoomId]);
        if ($roomStmt->fetchColumn() === false) {
            $pdo->rollBack();
            return 'invalid';
        }

        if (!audit_fund_request_booking_interval_not_in_future($newDate, $timeEnd)) {
            $pdo->rollBack();
            return 'past';
        }

        $lockPairs = [[$newRoomId, $newDate]];
        if ($oldRoomId !== $newRoomId || $oldDate !== $newDate) {
            $lockPairs[] = [$oldRoomId, $oldDate];
        }
        if (!audit_fund_mysql_acquire_room_date_locks($pdo, $lockPairs)) {
            $pdo->rollBack();
            return 'db';
        }
        try {
            $scheduleConflict = $pdo->prepare(
                'SELECT 1 FROM schedule_entries
                 WHERE room_id = ?
                   AND entry_date = ?
                   AND id <> ?
                   AND ? < time_end
                   AND ? > time_start
                 LIMIT 1'
            );
            $scheduleConflict->execute([$newRoomId, $newDate, $entryId, $timeStart, $timeEnd]);
            if ($scheduleConflict->fetchColumn() !== false) {
                $pdo->rollBack();
                return 'busy';
            }

            $requestConflict = $pdo->prepare(
                'SELECT 1 FROM booking_requests
                 WHERE room_id = ?
                   AND booking_date = ?
                   AND status IN ("pending", "approved")
                   AND ? < time_end
                   AND ? > time_start
                   AND NOT (room_id = ? AND booking_date = ? AND time_start = ? AND time_end = ?)
                 LIMIT 1'
            );
            $requestConflict->execute([
                $newRoomId,
                $newDate,
                $timeStart,
                $timeEnd,
                $oldRoomId,
                $oldDate,
                $oldTimeStartDb,
                $oldTimeEndDb,
            ]);
            if ($requestConflict->fetchColumn() !== false) {
                $pdo->rollBack();
                return 'busy';
            }

            $maintConflict = $pdo->prepare(
                'SELECT 1 FROM room_maintenance_windows
                 WHERE room_id = ?
                   AND maint_date = ?
                   AND ? < time_end
                   AND ? > time_start
                 LIMIT 1'
            );
            $maintConflict->execute([$newRoomId, $newDate, $timeStart, $timeEnd]);
            if ($maintConflict->fetchColumn() !== false) {
                $pdo->rollBack();
                return 'maint';
            }

            $syncReq = $pdo->prepare(
                'UPDATE booking_requests SET room_id = ?, booking_date = ?, time_start = ?, time_end = ?
                 WHERE room_id = ? AND booking_date = ? AND time_start = ? AND time_end = ?
                   AND status = "approved"'
            );
            $syncReq->execute([
                $newRoomId,
                $newDate,
                $timeStart,
                $timeEnd,
                $oldRoomId,
                $oldDate,
                $oldTimeStartDb,
                $oldTimeEndDb,
            ]);

            $upd = $pdo->prepare(
                'UPDATE schedule_entries
                 SET room_id = ?, entry_date = ?, time_start = ?, time_end = ?
                 WHERE id = ? LIMIT 1'
            );
            $upd->execute([$newRoomId, $newDate, $timeStart, $timeEnd, $entryId]);
            $pdo->commit();
        } finally {
            audit_fund_mysql_release_room_date_locks($pdo, $lockPairs);
        }
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'schedule_data');
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return 'db';
    }

    return null;
}

function audit_fund_schedule_entry_move(int $entryId, string $newDate): ?string
{
    return audit_fund_schedule_entry_relocate($entryId, $newDate, 0, null, null);
}

/** Текст причины отклонения при снятии слота из расписания менеджером или администратором. */
function audit_fund_schedule_slot_removal_reason(): string
{
    return 'Снято из расписания.';
}

function audit_fund_schedule_entry_delete(int $entryId): ?string
{
    if ($entryId <= 0) {
        return 'invalid';
    }

    try {
        $pdo = audit_fund_pdo();
        audit_fund_schedule_ensure_maintenance_table($pdo);
        audit_fund_requests_ensure_columns($pdo);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'schedule_data');
        return 'db';
    }

    $reason = audit_fund_schedule_slot_removal_reason();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'SELECT id, room_id, entry_date, time_start, time_end
             FROM schedule_entries
             WHERE id = ?
             LIMIT 1'
        );
        $stmt->execute([$entryId]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($entry === false) {
            $pdo->rollBack();
            return 'not_found';
        }

        $roomId = (int) $entry['room_id'];
        $timeStart = (string) $entry['time_start'];
        $timeEnd = (string) $entry['time_end'];
        $entryDate = (string) $entry['entry_date'];

        $lockPairs = [[$roomId, $entryDate]];
        if (!audit_fund_mysql_acquire_room_date_locks($pdo, $lockPairs)) {
            $pdo->rollBack();
            return 'db';
        }
        try {
            $del = $pdo->prepare('DELETE FROM schedule_entries WHERE id = ? LIMIT 1');
            $del->execute([$entryId]);

            $rej = $pdo->prepare(
                'UPDATE booking_requests SET status = "rejected", rejection_reason = ?
                 WHERE room_id = ? AND booking_date = ? AND time_start = ? AND time_end = ?
                   AND status = "approved"'
            );
            $rej->execute([$reason, $roomId, $entryDate, $timeStart, $timeEnd]);

            $pdo->commit();
        } finally {
            audit_fund_mysql_release_room_date_locks($pdo, $lockPairs);
        }
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'schedule_data');
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return 'db';
    }

    return null;
}

function audit_fund_approved_request_booking_relocate(
    int $requestId,
    string $newDate,
    int $newRoomId,
    ?string $rawTimeStart = null,
    ?string $rawTimeEnd = null
): ?string {
    if ($requestId <= 0) {
        return 'invalid';
    }
    $newDate = trim($newDate);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate)) {
        return 'invalid';
    }

    try {
        $pdo = audit_fund_pdo();
        audit_fund_schedule_ensure_maintenance_table($pdo);
        audit_fund_requests_ensure_columns($pdo);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'schedule_data');
        return 'db';
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'SELECT id, room_id, booking_date, time_start, time_end, comment
             FROM booking_requests
             WHERE id = ? AND status = "approved"
             LIMIT 1'
        );
        $stmt->execute([$requestId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            $pdo->rollBack();
            return 'not_found';
        }

        $oldRoomId = (int) $row['room_id'];
        $oldDate = (string) $row['booking_date'];
        $oldTimeStartDb = (string) $row['time_start'];
        $oldTimeEndDb = (string) $row['time_end'];
        $requestCommentRaw = isset($row['comment']) ? trim((string) $row['comment']) : '';
        $scheduleCommentForInsert = $requestCommentRaw !== '' ? $requestCommentRaw : 'Заявка одобрена';

        $schedExistsStmt = $pdo->prepare(
            'SELECT 1 FROM schedule_entries
             WHERE room_id = ? AND entry_date = ? AND time_start = ? AND time_end = ?
             LIMIT 1'
        );
        $schedExistsStmt->execute([$oldRoomId, $oldDate, $oldTimeStartDb, $oldTimeEndDb]);
        $hasMatchingScheduleRow = $schedExistsStmt->fetchColumn() !== false;

        $parsed = audit_fund_relocate_parse_interval($oldTimeStartDb, $oldTimeEndDb, $rawTimeStart, $rawTimeEnd);
        if (is_string($parsed)) {
            $pdo->rollBack();
            return $parsed;
        }
        $timeStart = $parsed['time_start'];
        $timeEnd = $parsed['time_end'];

        if ($newRoomId <= 0) {
            $newRoomId = $oldRoomId;
        }

        if (
            $oldRoomId === $newRoomId
            && $oldDate === $newDate
            && $oldTimeStartDb === $timeStart
            && $oldTimeEndDb === $timeEnd
        ) {
            $pdo->rollBack();
            return 'invalid';
        }

        $roomStmt = $pdo->prepare('SELECT 1 FROM rooms WHERE id = ? LIMIT 1');
        $roomStmt->execute([$newRoomId]);
        if ($roomStmt->fetchColumn() === false) {
            $pdo->rollBack();
            return 'invalid';
        }

        if (!audit_fund_request_booking_interval_not_in_future($newDate, $timeEnd)) {
            $pdo->rollBack();
            return 'past';
        }

        $lockPairs = [[$newRoomId, $newDate]];
        if ($oldRoomId !== $newRoomId || $oldDate !== $newDate) {
            $lockPairs[] = [$oldRoomId, $oldDate];
        }
        if (!audit_fund_mysql_acquire_room_date_locks($pdo, $lockPairs)) {
            $pdo->rollBack();
            return 'db';
        }
        try {
            $scheduleConflict = $pdo->prepare(
                'SELECT 1 FROM schedule_entries
                 WHERE room_id = ?
                   AND entry_date = ?
                   AND ? < time_end
                   AND ? > time_start
                   AND NOT (room_id = ? AND entry_date = ? AND time_start = ? AND time_end = ?)
                 LIMIT 1'
            );
            $scheduleConflict->execute([
                $newRoomId,
                $newDate,
                $timeStart,
                $timeEnd,
                $oldRoomId,
                $oldDate,
                $oldTimeStartDb,
                $oldTimeEndDb,
            ]);
            if ($scheduleConflict->fetchColumn() !== false) {
                $pdo->rollBack();
                return 'busy';
            }

            $requestConflict = $pdo->prepare(
                'SELECT 1 FROM booking_requests
                 WHERE room_id = ?
                   AND booking_date = ?
                   AND status IN ("pending", "approved")
                   AND id <> ?
                   AND ? < time_end
                   AND ? > time_start
                 LIMIT 1'
            );
            $requestConflict->execute([$newRoomId, $newDate, $requestId, $timeStart, $timeEnd]);
            if ($requestConflict->fetchColumn() !== false) {
                $pdo->rollBack();
                return 'busy';
            }

            $maintConflict = $pdo->prepare(
                'SELECT 1 FROM room_maintenance_windows
                 WHERE room_id = ?
                   AND maint_date = ?
                   AND ? < time_end
                   AND ? > time_start
                 LIMIT 1'
            );
            $maintConflict->execute([$newRoomId, $newDate, $timeStart, $timeEnd]);
            if ($maintConflict->fetchColumn() !== false) {
                $pdo->rollBack();
                return 'maint';
            }

            if ($hasMatchingScheduleRow) {
                $updSched = $pdo->prepare(
                    'UPDATE schedule_entries SET room_id = ?, entry_date = ?, time_start = ?, time_end = ?
                     WHERE room_id = ? AND entry_date = ? AND time_start = ? AND time_end = ?'
                );
                $updSched->execute([
                    $newRoomId,
                    $newDate,
                    $timeStart,
                    $timeEnd,
                    $oldRoomId,
                    $oldDate,
                    $oldTimeStartDb,
                    $oldTimeEndDb,
                ]);
            } else {
                $insSched = $pdo->prepare(
                    'INSERT INTO schedule_entries (room_id, entry_date, time_start, time_end, comment)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $insSched->execute([
                    $newRoomId,
                    $newDate,
                    $timeStart,
                    $timeEnd,
                    $scheduleCommentForInsert,
                ]);
            }

            $updReq = $pdo->prepare(
                'UPDATE booking_requests SET room_id = ?, booking_date = ?, time_start = ?, time_end = ?
                 WHERE id = ? LIMIT 1'
            );
            $updReq->execute([$newRoomId, $newDate, $timeStart, $timeEnd, $requestId]);

            $pdo->commit();
        } finally {
            audit_fund_mysql_release_room_date_locks($pdo, $lockPairs);
        }
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'schedule_data');
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return 'db';
    }

    return null;
}

function audit_fund_approved_request_booking_move(int $requestId, string $newDate): ?string
{
    return audit_fund_approved_request_booking_relocate($requestId, $newDate, 0, null, null);
}

function audit_fund_approved_request_slot_remove(int $requestId): ?string
{
    if ($requestId <= 0) {
        return 'invalid';
    }

    try {
        $pdo = audit_fund_pdo();
        audit_fund_schedule_ensure_maintenance_table($pdo);
        audit_fund_requests_ensure_columns($pdo);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'schedule_data');
        return 'db';
    }

    $reason = audit_fund_schedule_slot_removal_reason();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'SELECT id, room_id, booking_date, time_start, time_end, status
             FROM booking_requests
             WHERE id = ?
             LIMIT 1'
        );
        $stmt->execute([$requestId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            $pdo->rollBack();
            return 'not_found';
        }
        if ((string) $row['status'] !== 'approved') {
            $pdo->rollBack();
            return 'not_approved';
        }

        $roomId = (int) $row['room_id'];
        $timeStart = (string) $row['time_start'];
        $timeEnd = (string) $row['time_end'];
        $bookingDate = (string) $row['booking_date'];

        $lockPairs = [[$roomId, $bookingDate]];
        if (!audit_fund_mysql_acquire_room_date_locks($pdo, $lockPairs)) {
            $pdo->rollBack();
            return 'db';
        }
        try {
            $delSched = $pdo->prepare(
                'DELETE FROM schedule_entries
                 WHERE room_id = ? AND entry_date = ? AND time_start = ? AND time_end = ?
                 LIMIT 1'
            );
            $delSched->execute([$roomId, $bookingDate, $timeStart, $timeEnd]);

            $upd = $pdo->prepare(
                'UPDATE booking_requests SET status = "rejected", rejection_reason = ?
                 WHERE id = ? AND status = "approved"
                 LIMIT 1'
            );
            $upd->execute([$reason, $requestId]);
            if ($upd->rowCount() === 0) {
                $pdo->rollBack();
                return 'not_found';
            }

            $pdo->commit();
        } finally {
            audit_fund_mysql_release_room_date_locks($pdo, $lockPairs);
        }
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'schedule_data');
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return 'db';
    }

    return null;
}
