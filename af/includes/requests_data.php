<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/flash_messages.php';

/**
 * Нормализация времени из input[type=time]: «9:05», «09:05», «09:05:00» → «09:05».
 */
function audit_fund_normalize_time_hhmm(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $raw, $m)) {
        return null;
    }
    $h = (int) $m[1];
    $min = (int) $m[2];
    if ($h < 0 || $h > 23 || $min < 0 || $min > 59) {
        return null;
    }

    return sprintf('%02d:%02d', $h, $min);
}

/**
 * Порядок начала и конца интервала. Окончание 00:00 = до полуночи того же дня (позже любого начала того же дня).
 */
function audit_fund_request_validate_interval_order(string $timeStartN, string $timeEndN): bool
{
    if ($timeStartN === '00:00' && $timeEndN === '00:00') {
        return false;
    }
    if ($timeEndN === '00:00') {
        return $timeStartN !== '00:00';
    }

    return $timeStartN < $timeEndN;
}

/**
 * В БД для «до полуночи» храним 23:59:59, чтобы интервал был в пределах даты и проходил проверки пересечений.
 *
 * @return array{0: string, 1: string} [timeStartDb, timeEndDb] в формате H:i:s
 */
function audit_fund_request_interval_times_for_db(string $timeStartN, string $timeEndN): array
{
    $timeStartDb = $timeStartN . ':00';
    if ($timeEndN === '00:00' && $timeStartN !== '00:00') {
        return [$timeStartDb, '23:59:59'];
    }

    return [$timeStartDb, $timeEndN . ':00'];
}

/**
 * Для отображения: в БД «до полуночи» хранится как 23:59:59 — в интерфейсе показываем 00:00.
 */
function audit_fund_request_time_end_display_hhmm(string $timeFromDb): string
{
    if (strlen($timeFromDb) >= 8 && substr($timeFromDb, 0, 8) === '23:59:59') {
        return '00:00';
    }

    return substr($timeFromDb, 0, 5);
}

/**
 * Конец интервала заявки (дата + время окончания) уже наступил или сейчас — бронь недопустима.
 */
function audit_fund_request_booking_interval_not_in_future(string $dateYmd, string $timeEndHms): bool
{
    try {
        $end = new DateTimeImmutable($dateYmd . ' ' . $timeEndHms);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'requests_data');
        return false;
    }
    $now = new DateTimeImmutable('now');

    return $end > $now;
}

function audit_fund_requests_ensure_columns(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    if (!audit_fund_runtime_migrations_enabled()) {
        $checked = true;

        return;
    }

    $reasonCol = $pdo->query("SHOW COLUMNS FROM booking_requests LIKE 'rejection_reason'")->fetch(PDO::FETCH_ASSOC);
    if ($reasonCol === false) {
        $pdo->exec('ALTER TABLE booking_requests ADD COLUMN rejection_reason VARCHAR(1000) DEFAULT NULL AFTER status');
    }
    $slotRoomCol = $pdo->query("SHOW COLUMNS FROM booking_requests LIKE 'approved_slot_room_id'")->fetch(PDO::FETCH_ASSOC);
    if ($slotRoomCol === false) {
        $pdo->exec(
            'ALTER TABLE booking_requests
             ADD COLUMN approved_slot_room_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER room_id,
             ADD COLUMN approved_slot_booking_date DATE NULL DEFAULT NULL AFTER approved_slot_room_id'
        );
    }
    $slotTimeCol = $pdo->query("SHOW COLUMNS FROM booking_requests LIKE 'approved_slot_time_start'")->fetch(PDO::FETCH_ASSOC);
    if ($slotTimeCol === false) {
        $pdo->exec(
            'ALTER TABLE booking_requests
             ADD COLUMN approved_slot_time_start TIME NULL DEFAULT NULL AFTER approved_slot_booking_date,
             ADD COLUMN approved_slot_time_end TIME NULL DEFAULT NULL AFTER approved_slot_time_start'
        );
    }
    $pdo->exec(
        'UPDATE booking_requests
         SET approved_slot_room_id = room_id, approved_slot_booking_date = booking_date
         WHERE status = "approved" AND approved_slot_room_id IS NULL'
    );
    $pdo->exec(
        'UPDATE booking_requests
         SET approved_slot_time_start = time_start, approved_slot_time_end = time_end
         WHERE status = "approved" AND approved_slot_time_start IS NULL'
    );
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

function audit_fund_has_maintenance_conflict(
    PDO $pdo,
    int $roomId,
    string $date,
    string $timeStartDb,
    string $timeEndDb
): bool {
    $stmt = $pdo->prepare(
        'SELECT 1 FROM room_maintenance_windows
         WHERE room_id = ?
           AND maint_date = ?
           AND ? < time_end
           AND ? > time_start
         LIMIT 1'
    );
    $stmt->execute([$roomId, $date, $timeStartDb, $timeEndDb]);

    return $stmt->fetchColumn() !== false;
}

/**
 * @return array{rooms:list<array<string,mixed>>,error:string|null}
 */
function audit_fund_requests_rooms(): array
{
    $out = ['rooms' => [], 'error' => null];

    try {
        $pdo = audit_fund_pdo();
        $stmt = $pdo->query(
            'SELECT r.id, r.room_number, b.name AS building_name
             FROM rooms r
             INNER JOIN buildings b ON b.id = r.building_id
             WHERE r.operational_status <> "maint"
             ORDER BY b.sort_order, b.name, r.floor, r.room_number'
        );
        $out['rooms'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'requests_data');
        $out['error'] = 'Не удалось загрузить список аудиторий.';
    }

    return $out;
}

/**
 * Число заявок со статусом pending по тем же правилам «активности», что и список на requests.php
 * (интервал бронирования ещё не закончился).
 *
 * @param int|null $restrictToUserId только заявки этого пользователя; null — все (для менеджеров/админов)
 */
function audit_fund_requests_pending_active_count(?int $restrictToUserId = null): int
{
    try {
        $pdo = audit_fund_pdo();
        audit_fund_requests_ensure_columns($pdo);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'requests_data pending_count');

        return 0;
    }

    $sql = 'SELECT COUNT(*) FROM booking_requests br
             WHERE br.status = "pending"
               AND CONCAT(br.booking_date, " ", br.time_end) >= NOW()';
    $params = [];
    if ($restrictToUserId !== null && $restrictToUserId > 0) {
        $sql .= ' AND br.requested_by_user_id = ?';
        $params[] = $restrictToUserId;
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'requests_data pending_count');

        return 0;
    }
}

/**
 * Активные и будущие заявки (интервал ещё не закончился).
 *
 * @param ?int $restrictToRequesterUserId только заявки этого пользователя (для роли user); null — без ограничения (staff)
 * @return array{rows:list<array<string,mixed>>,total:int,error:string|null}
 */
function audit_fund_requests_list(int $page = 1, int $perPage = 30, ?int $restrictToRequesterUserId = null): array
{
    $out = ['rows' => [], 'total' => 0, 'error' => null];
    $perPage = max(5, min(100, $perPage));
    $page = max(1, $page);
    $offset = ($page - 1) * $perPage;

    try {
        $pdo = audit_fund_pdo();
        audit_fund_requests_ensure_columns($pdo);
        $whereSql = ' WHERE CONCAT(br.booking_date, " ", br.time_end) >= NOW()';
        $params = [];
        if ($restrictToRequesterUserId !== null) {
            if ($restrictToRequesterUserId <= 0) {
                $whereSql .= ' AND 1 = 0';
            } else {
                $whereSql .= ' AND br.requested_by_user_id = ?';
                $params[] = $restrictToRequesterUserId;
            }
        }

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM booking_requests br' . $whereSql);
        $countStmt->execute($params);
        $out['total'] = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare(
            'SELECT br.id, br.request_number, br.room_id, br.applicant_display, br.booking_date, br.time_start, br.time_end,
                    br.approved_slot_room_id, br.approved_slot_booking_date,
                    br.approved_slot_time_start, br.approved_slot_time_end,
                    br.comment,
                    br.status, br.submitted_at, br.requested_by_user_id,
                    r.room_number, b.name AS building_name,
                    COALESCE(NULLIF(TRIM(u.display_name), \'\'), u.username) AS requested_by_username,
                    br.rejection_reason
             FROM booking_requests br
             INNER JOIN rooms r ON r.id = br.room_id
             INNER JOIN buildings b ON b.id = r.building_id
             LEFT JOIN users u ON u.id = br.requested_by_user_id'
            . $whereSql
            . ' ORDER BY br.submitted_at DESC, br.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset
        );
        $stmt->execute($params);
        $out['rows'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'requests_data');
        $out['error'] = 'Не удалось загрузить список заявок.';
    }

    return $out;
}

/**
 * Пересечение с расписанием, активными заявками или обслуживанием. null — конфликта нет.
 */
function audit_fund_request_slot_conflict_code(
    PDO $pdo,
    int $roomId,
    string $bookingDate,
    string $timeStartDb,
    string $timeEndDb
): ?string {
    $sched = $pdo->prepare(
        'SELECT 1 FROM schedule_entries
         WHERE room_id = ?
           AND entry_date = ?
           AND ? < time_end
           AND ? > time_start
         LIMIT 1'
    );
    $sched->execute([$roomId, $bookingDate, $timeStartDb, $timeEndDb]);
    if ($sched->fetchColumn() !== false) {
        return 'busy';
    }

    $req = $pdo->prepare(
        'SELECT 1 FROM booking_requests
         WHERE room_id = ?
           AND booking_date = ?
           AND status IN ("pending", "approved")
           AND ? < time_end
           AND ? > time_start
         LIMIT 1'
    );
    $req->execute([$roomId, $bookingDate, $timeStartDb, $timeEndDb]);
    if ($req->fetchColumn() !== false) {
        return 'busy';
    }
    if (audit_fund_has_maintenance_conflict($pdo, $roomId, $bookingDate, $timeStartDb, $timeEndDb)) {
        return 'maint';
    }

    return null;
}

/**
 * Проверка слота без создания заявки (для AJAX и предупреждения до отправки).
 *
 * @return array{ok:bool, code:?string, message:string, hints:string}
 */
function audit_fund_request_precheck_slot(int $roomId, string $bookingDate, string $timeStart, string $timeEnd): array
{
    $messages = audit_fund_flash_messages_request_create();
    $finish = static function (?string $code, int $rid, string $bd, string $ts, string $te) use ($messages): array {
        $hints = '';
        if ($code !== null && ($code === 'busy' || $code === 'maint')) {
            $hints = audit_fund_request_create_busy_hints($rid, $bd, $ts, $te);
        }

        return [
            'ok' => $code === null,
            'code' => $code,
            'message' => $code === null ? '' : ($messages[$code] ?? 'Проверьте данные.'),
            'hints' => $hints,
        ];
    };

    if ($roomId <= 0) {
        return $finish('need_room', $roomId, $bookingDate, $timeStart, $timeEnd);
    }

    $bookingDate = trim($bookingDate);
    $timeStart = trim($timeStart);
    $timeEnd = trim($timeEnd);

    if ($bookingDate === '') {
        return $finish('need_date', $roomId, $bookingDate, $timeStart, $timeEnd);
    }
    if ($timeStart === '' || $timeEnd === '') {
        return $finish('need_time', $roomId, $bookingDate, $timeStart, $timeEnd);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $bookingDate)) {
        return $finish('bad_date', $roomId, $bookingDate, $timeStart, $timeEnd);
    }
    $timeStartN = audit_fund_normalize_time_hhmm($timeStart);
    $timeEndN = audit_fund_normalize_time_hhmm($timeEnd);
    if ($timeStartN === null || $timeEndN === null) {
        return $finish('bad_time', $roomId, $bookingDate, $timeStart, $timeEnd);
    }
    if (!audit_fund_request_validate_interval_order($timeStartN, $timeEndN)) {
        return $finish('bad_interval', $roomId, $bookingDate, $timeStart, $timeEnd);
    }

    [$timeStartDb, $timeEndDb] = audit_fund_request_interval_times_for_db($timeStartN, $timeEndN);

    if (!audit_fund_request_booking_interval_not_in_future($bookingDate, $timeEndDb)) {
        return $finish('past', $roomId, $bookingDate, $timeStart, $timeEnd);
    }

    try {
        $pdo = audit_fund_pdo();
        audit_fund_requests_ensure_columns($pdo);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'requests_data precheck');

        return [
            'ok' => false,
            'code' => 'db',
            'message' => $messages['db'] ?? 'Ошибка базы данных.',
            'hints' => '',
        ];
    }

    $chk = $pdo->prepare('SELECT operational_status FROM rooms WHERE id = ? LIMIT 1');
    $chk->execute([$roomId]);
    $roomRow = $chk->fetch(PDO::FETCH_ASSOC);
    if ($roomRow === false) {
        return $finish('room_not_found', $roomId, $bookingDate, $timeStart, $timeEnd);
    }
    if (($roomRow['operational_status'] ?? '') === 'maint') {
        return $finish('room_maint', $roomId, $bookingDate, $timeStart, $timeEnd);
    }

    $conflict = audit_fund_request_slot_conflict_code($pdo, $roomId, $bookingDate, $timeStartDb, $timeEndDb);

    return $finish($conflict, $roomId, $bookingDate, $timeStart, $timeEnd);
}

/**
 * Создание заявки. null — успех, иначе код ошибки.
 */
function audit_fund_request_create(
    int $roomId,
    string $bookingDate,
    string $timeStart,
    string $timeEnd,
    string $applicantDisplay,
    string $comment,
    ?int $userId = null
): ?string {
    if ($roomId <= 0) {
        return 'need_room';
    }

    $bookingDate = trim($bookingDate);
    $timeStart = trim($timeStart);
    $timeEnd = trim($timeEnd);
    $applicantDisplay = trim($applicantDisplay);
    $comment = trim($comment);

    if ($bookingDate === '') {
        return 'need_date';
    }
    if ($timeStart === '' || $timeEnd === '') {
        return 'need_time';
    }
    if ($applicantDisplay === '') {
        return 'need_applicant';
    }
    if (strlen($applicantDisplay) > 255) {
        return 'applicant_too_long';
    }
    if (strlen($comment) > 65535) {
        return 'comment_too_long';
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $bookingDate)) {
        return 'bad_date';
    }
    $timeStartN = audit_fund_normalize_time_hhmm($timeStart);
    $timeEndN = audit_fund_normalize_time_hhmm($timeEnd);
    if ($timeStartN === null || $timeEndN === null) {
        return 'bad_time';
    }
    if (!audit_fund_request_validate_interval_order($timeStartN, $timeEndN)) {
        return 'bad_interval';
    }

    [$timeStartDb, $timeEndDb] = audit_fund_request_interval_times_for_db($timeStartN, $timeEndN);
    $userIdForDb = ($userId !== null && $userId > 0) ? $userId : null;

    if (!audit_fund_request_booking_interval_not_in_future($bookingDate, $timeEndDb)) {
        return 'past';
    }

    try {
        $pdo = audit_fund_pdo();
        audit_fund_requests_ensure_columns($pdo);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'requests_data');
        return 'db';
    }

    $chk = $pdo->prepare('SELECT operational_status FROM rooms WHERE id = ? LIMIT 1');
    $chk->execute([$roomId]);
    $roomRow = $chk->fetch(PDO::FETCH_ASSOC);
    if ($roomRow === false) {
        return 'room_not_found';
    }
    if (($roomRow['operational_status'] ?? '') === 'maint') {
        return 'room_maint';
    }

    $lockPairs = [[$roomId, $bookingDate]];
    if (!audit_fund_mysql_acquire_room_date_locks($pdo, $lockPairs)) {
        return 'db';
    }
    try {
        try {
            $slotErr = audit_fund_request_slot_conflict_code($pdo, $roomId, $bookingDate, $timeStartDb, $timeEndDb);
            if ($slotErr !== null) {
                return $slotErr;
            }

            $year = (int) substr($bookingDate, 0, 4);
            $seqStmt = $pdo->prepare(
                'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(request_number, "-", -1) AS UNSIGNED)), 0)
                 FROM booking_requests
                 WHERE request_number LIKE ?'
            );
            $seqStmt->execute([$year . '-%']);
            $nextSeq = (int) $seqStmt->fetchColumn() + 1;
            $requestNumber = sprintf('%d-%03d', $year, $nextSeq);

            $ins = $pdo->prepare(
                'INSERT INTO booking_requests
                 (request_number, room_id, requested_by_user_id, applicant_display, booking_date, time_start, time_end, comment, status, submitted_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, "pending", NOW())'
            );
            $ins->execute([
                $requestNumber,
                $roomId,
                $userIdForDb,
                $applicantDisplay,
                $bookingDate,
                $timeStartDb,
                $timeEndDb,
                $comment !== '' ? $comment : null,
            ]);
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                return 'duplicate';
            }

            return 'db';
        }

        return null;
    } finally {
        audit_fund_mysql_release_room_date_locks($pdo, $lockPairs);
    }
}

/** Секунды от полуночи по значению TIME из БД/строки. */
function audit_fund_parse_sql_time_to_seconds(string $t): int
{
    $t = trim($t);
    if (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $t, $m)) {
        $h = (int) $m[1];
        $mi = (int) $m[2];
        $s = (int) $m[3];

        return min(86400, max(0, $h * 3600 + $mi * 60 + $s));
    }
    if (preg_match('/^(\d{1,2}):(\d{2})$/', $t, $m)) {
        $h = (int) $m[1];
        $mi = (int) $m[2];

        return min(86400, max(0, $h * 3600 + $mi * 60));
    }

    return 0;
}

/** Правая граница интервала [start, end) в секундах; 23:59:59 — конец календарного дня. */
function audit_fund_interval_end_exclusive_seconds(string $timeEndSql): int
{
    if (strlen($timeEndSql) >= 8 && substr($timeEndSql, 0, 8) === '23:59:59') {
        return 86400;
    }

    return max(0, min(86400, audit_fund_parse_sql_time_to_seconds($timeEndSql)));
}

/**
 * @return list<array{0:int,1:int}>
 */
function audit_fund_room_day_busy_intervals_seconds(PDO $pdo, int $roomId, string $dateYmd): array
{
    $out = [];

    $sched = $pdo->prepare(
        'SELECT time_start, time_end FROM schedule_entries WHERE room_id = ? AND entry_date = ?'
    );
    $sched->execute([$roomId, $dateYmd]);
    while ($row = $sched->fetch(PDO::FETCH_ASSOC)) {
        $ts = (string) ($row['time_start'] ?? '');
        $te = (string) ($row['time_end'] ?? '');
        $s = audit_fund_parse_sql_time_to_seconds($ts);
        $e = audit_fund_interval_end_exclusive_seconds($te);
        if ($e > $s) {
            $out[] = [$s, $e];
        }
    }

    $req = $pdo->prepare(
        'SELECT time_start, time_end FROM booking_requests
         WHERE room_id = ? AND booking_date = ? AND status IN ("pending", "approved")'
    );
    $req->execute([$roomId, $dateYmd]);
    while ($row = $req->fetch(PDO::FETCH_ASSOC)) {
        $ts = (string) ($row['time_start'] ?? '');
        $te = (string) ($row['time_end'] ?? '');
        $s = audit_fund_parse_sql_time_to_seconds($ts);
        $e = audit_fund_interval_end_exclusive_seconds($te);
        if ($e > $s) {
            $out[] = [$s, $e];
        }
    }

    $maint = $pdo->prepare(
        'SELECT time_start, time_end FROM room_maintenance_windows WHERE room_id = ? AND maint_date = ?'
    );
    $maint->execute([$roomId, $dateYmd]);
    while ($row = $maint->fetch(PDO::FETCH_ASSOC)) {
        $ts = (string) ($row['time_start'] ?? '');
        $te = (string) ($row['time_end'] ?? '');
        $s = audit_fund_parse_sql_time_to_seconds($ts);
        $e = audit_fund_interval_end_exclusive_seconds($te);
        if ($e > $s) {
            $out[] = [$s, $e];
        }
    }

    return $out;
}

/**
 * @param list<array{0:int,1:int}> $intervals
 * @return list<array{0:int,1:int}>
 */
function audit_fund_intervals_merge(array $intervals): array
{
    if ($intervals === []) {
        return [];
    }
    usort(
        $intervals,
        static function (array $a, array $b): int {
            return $a[0] <=> $b[0];
        }
    );
    $merged = [];
    $curS = $intervals[0][0];
    $curE = $intervals[0][1];
    $n = count($intervals);
    for ($i = 1; $i < $n; $i++) {
        $s = $intervals[$i][0];
        $e = $intervals[$i][1];
        if ($s <= $curE) {
            $curE = max($curE, $e);
        } else {
            $merged[] = [$curS, $curE];
            $curS = $s;
            $curE = $e;
        }
    }
    $merged[] = [$curS, $curE];

    return $merged;
}

/**
 * @param list<array{0:int,1:int}> $mergedBusy
 * @return list<array{0:int,1:int}>
 */
function audit_fund_nearby_free_slots_seconds(array $mergedBusy, int $userStartSec, int $durationSec, int $maxSuggestions): array
{
    if ($durationSec <= 0 || $maxSuggestions <= 0) {
        return [];
    }

    $dayStart = 0;
    $dayEnd = 86400;
    $gaps = [];
    $prev = $dayStart;
    foreach ($mergedBusy as [$bs, $be]) {
        $bs = max($dayStart, min($dayEnd, $bs));
        $be = max($dayStart, min($dayEnd, $be));
        if ($bs > $prev) {
            $gaps[] = [$prev, $bs];
        }
        $prev = max($prev, $be);
    }
    if ($prev < $dayEnd) {
        $gaps[] = [$prev, $dayEnd];
    }

    $candidates = [];
    foreach ($gaps as [$gs, $ge]) {
        if ($ge - $gs < $durationSec) {
            continue;
        }
        $latestStart = $ge - $durationSec;
        $starts = [$gs, $latestStart];
        $aligned = min(max($userStartSec, $gs), $latestStart);
        $starts[] = $aligned;
        $starts = array_unique($starts);
        foreach ($starts as $st) {
            if ($st >= $gs && $st <= $latestStart) {
                $candidates[] = [$st, $st + $durationSec];
            }
        }
    }

    $seen = [];
    $scored = [];
    foreach ($candidates as [$cs, $ce]) {
        $key = $cs . '-' . $ce;
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $scored[] = ['slot' => [$cs, $ce], 'dist' => abs($cs - $userStartSec)];
    }
    usort(
        $scored,
        static function (array $a, array $b): int {
            if ($a['dist'] !== $b['dist']) {
                return $a['dist'] <=> $b['dist'];
            }

            return $a['slot'][0] <=> $b['slot'][0];
        }
    );

    $out = [];
    foreach ($scored as $item) {
        $out[] = $item['slot'];
        if (count($out) >= $maxSuggestions) {
            break;
        }
    }

    return $out;
}

function audit_fund_format_slot_range_label(int $startSec, int $endExclusiveSec): string
{
    $sh = intdiv($startSec, 3600);
    $sm = intdiv($startSec % 3600, 60);
    $startLabel = sprintf('%02d:%02d', $sh, $sm);
    if ($endExclusiveSec >= 86400) {
        return $startLabel . '–00:00';
    }
    $eh = intdiv($endExclusiveSec, 3600);
    $em = intdiv($endExclusiveSec % 3600, 60);

    return $startLabel . '–' . sprintf('%02d:%02d', $eh, $em);
}

/**
 * Текст подсказок при отказе из-за занятости/обслуживания (свободные окна той же длительности в этот день).
 */
function audit_fund_request_create_busy_hints(
    int $roomId,
    string $bookingDate,
    string $timeStart,
    string $timeEnd
): string {
    if ($roomId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($bookingDate))) {
        return '';
    }
    $bookingDate = trim($bookingDate);
    $timeStartN = audit_fund_normalize_time_hhmm($timeStart);
    $timeEndN = audit_fund_normalize_time_hhmm($timeEnd);
    if ($timeStartN === null || $timeEndN === null || !audit_fund_request_validate_interval_order($timeStartN, $timeEndN)) {
        return '';
    }
    [$timeStartDb, $timeEndDb] = audit_fund_request_interval_times_for_db($timeStartN, $timeEndN);
    $userStartSec = audit_fund_parse_sql_time_to_seconds($timeStartDb);
    $userEndExcl = audit_fund_interval_end_exclusive_seconds($timeEndDb);
    $durationSec = $userEndExcl - $userStartSec;
    if ($durationSec <= 0) {
        return '';
    }

    try {
        $pdo = audit_fund_pdo();
        audit_fund_requests_ensure_columns($pdo);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'requests_data busy_hints');

        return '';
    }

    try {
        $raw = audit_fund_room_day_busy_intervals_seconds($pdo, $roomId, $bookingDate);
        $merged = audit_fund_intervals_merge($raw);
        $slots = audit_fund_nearby_free_slots_seconds($merged, $userStartSec, $durationSec, 5);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'requests_data busy_hints query');

        return '';
    }

    if ($slots === []) {
        return 'За этот день не найдено свободного окна той же длительности — попробуйте другую аудиторию или дату.';
    }
    $parts = [];
    foreach ($slots as [$s, $e]) {
        $parts[] = audit_fund_format_slot_range_label($s, $e);
    }

    return 'Ближайшие свободные интервалы той же длительности: ' . implode(', ', $parts) . '.';
}

/**
 * Для одобренных: отличаются ли текущие аудитория/дата от зафиксированных при одобрении (корректировка в расписании).
 *
 * @param array<string,mixed> $row
 * @return array{room:bool,date:bool,time:bool}
 */
function audit_fund_request_approved_slot_changed_flags(array $row): array
{
    if (($row['status'] ?? '') !== 'approved') {
        return ['room' => false, 'date' => false, 'time' => false];
    }
    $slotRoom = $row['approved_slot_room_id'] ?? null;
    $slotDate = $row['approved_slot_booking_date'] ?? null;
    if ($slotRoom === null || $slotDate === null || $slotRoom === '' || $slotDate === '') {
        return ['room' => false, 'date' => false, 'time' => false];
    }
    $curRoom = (int) ($row['room_id'] ?? 0);
    $curDate = (string) ($row['booking_date'] ?? '');

    $slotTs = $row['approved_slot_time_start'] ?? null;
    $slotTe = $row['approved_slot_time_end'] ?? null;
    $timeChanged = false;
    if ($slotTs !== null && $slotTe !== null && $slotTs !== '' && $slotTe !== '') {
        $timeChanged = (string) ($row['time_start'] ?? '') !== (string) $slotTs
            || (string) ($row['time_end'] ?? '') !== (string) $slotTe;
    }

    return [
        'room' => $curRoom !== (int) $slotRoom,
        'date' => $curDate !== (string) $slotDate,
        'time' => $timeChanged,
    ];
}

function audit_fund_request_status_badge(string $status): array
{
    switch ($status) {
        case 'approved':
            return ['class' => 'badge badge--free', 'label' => 'Одобрено'];
        case 'rejected':
            return ['class' => 'badge badge--rejected', 'label' => 'Отклонено'];
        default:
            return ['class' => 'badge badge--pending', 'label' => 'На рассмотрении'];
    }
}

function audit_fund_request_set_status_with_reason(int $requestId, string $targetStatus, string $rejectionReason): ?string
{
    if ($requestId <= 0 || !in_array($targetStatus, ['approved', 'rejected'], true)) {
        return 'invalid';
    }
    $rejectionReason = trim($rejectionReason);
    if ($targetStatus === 'rejected' && $rejectionReason === '') {
        return 'rejection_reason_required';
    }
    if (strlen($rejectionReason) > 1000) {
        return 'rejection_reason_too_long';
    }

    try {
        $pdo = audit_fund_pdo();
        audit_fund_requests_ensure_columns($pdo);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'requests_data');
        return 'db';
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'SELECT id, room_id, booking_date, time_start, time_end, status, comment
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
        if ((string) $row['status'] !== 'pending') {
            $pdo->rollBack();
            return 'already_processed';
        }

        if ($targetStatus === 'approved') {
            $lockPairs = [[(int) $row['room_id'], (string) $row['booking_date']]];
            if (!audit_fund_mysql_acquire_room_date_locks($pdo, $lockPairs)) {
                $pdo->rollBack();
                return 'db';
            }
            try {
                $stmt->execute([$requestId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row === false || (string) $row['status'] !== 'pending') {
                    $pdo->rollBack();
                    return 'already_processed';
                }

                $sched = $pdo->prepare(
                    'SELECT 1 FROM schedule_entries
                     WHERE room_id = ?
                       AND entry_date = ?
                       AND ? < time_end
                       AND ? > time_start
                     LIMIT 1'
                );
                $sched->execute([(int) $row['room_id'], (string) $row['booking_date'], (string) $row['time_start'], (string) $row['time_end']]);
                if ($sched->fetchColumn() !== false) {
                    $pdo->rollBack();
                    return 'busy';
                }

                $req = $pdo->prepare(
                    'SELECT 1 FROM booking_requests
                     WHERE room_id = ?
                       AND booking_date = ?
                       AND status = "approved"
                       AND id <> ?
                       AND ? < time_end
                       AND ? > time_start
                     LIMIT 1'
                );
                $req->execute([
                    (int) $row['room_id'],
                    (string) $row['booking_date'],
                    $requestId,
                    (string) $row['time_start'],
                    (string) $row['time_end'],
                ]);
                if ($req->fetchColumn() !== false) {
                    $pdo->rollBack();
                    return 'busy';
                }
                if (audit_fund_has_maintenance_conflict($pdo, (int) $row['room_id'], (string) $row['booking_date'], (string) $row['time_start'], (string) $row['time_end'])) {
                    $pdo->rollBack();
                    return 'maint';
                }

                $upd = $pdo->prepare('UPDATE booking_requests SET status = ?, rejection_reason = ? WHERE id = ? LIMIT 1');
                $upd->execute([
                    $targetStatus,
                    $targetStatus === 'rejected' ? $rejectionReason : null,
                    $requestId,
                ]);

                $insSchedule = $pdo->prepare(
                    'INSERT INTO schedule_entries (room_id, entry_date, time_start, time_end, comment)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $insSchedule->execute([
                    (int) $row['room_id'],
                    (string) $row['booking_date'],
                    (string) $row['time_start'],
                    (string) $row['time_end'],
                    (string) ($row['comment'] ?? ''),
                ]);
                $snap = $pdo->prepare(
                    'UPDATE booking_requests SET approved_slot_room_id = ?, approved_slot_booking_date = ?,
                     approved_slot_time_start = ?, approved_slot_time_end = ?
                     WHERE id = ? LIMIT 1'
                );
                $snap->execute([
                    (int) $row['room_id'],
                    (string) $row['booking_date'],
                    (string) $row['time_start'],
                    (string) $row['time_end'],
                    $requestId,
                ]);

                $pdo->commit();
            } finally {
                audit_fund_mysql_release_room_date_locks($pdo, $lockPairs);
            }

            return null;
        }

        $upd = $pdo->prepare('UPDATE booking_requests SET status = ?, rejection_reason = ? WHERE id = ? LIMIT 1');
        $upd->execute([
            $targetStatus,
            $targetStatus === 'rejected' ? $rejectionReason : null,
            $requestId,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'requests_data');
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return 'db';
    }

    return null;
}

function audit_fund_request_update_own_pending(
    int $requestId,
    int $userId,
    int $roomId,
    string $bookingDate,
    string $timeStart,
    string $timeEnd,
    string $applicantDisplay,
    string $comment
): ?string {
    if ($requestId <= 0 || $userId <= 0) {
        return 'invalid';
    }
    if ($roomId <= 0) {
        return 'need_room';
    }

    $bookingDate = trim($bookingDate);
    $timeStart = trim($timeStart);
    $timeEnd = trim($timeEnd);
    $applicantDisplay = trim($applicantDisplay);
    $comment = trim($comment);

    if ($bookingDate === '') {
        return 'need_date';
    }
    if ($timeStart === '' || $timeEnd === '') {
        return 'need_time';
    }
    if ($applicantDisplay === '') {
        return 'need_applicant';
    }
    if (strlen($applicantDisplay) > 255) {
        return 'applicant_too_long';
    }
    if (strlen($comment) > 65535) {
        return 'comment_too_long';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $bookingDate)) {
        return 'bad_date';
    }
    $timeStartN = audit_fund_normalize_time_hhmm($timeStart);
    $timeEndN = audit_fund_normalize_time_hhmm($timeEnd);
    if ($timeStartN === null || $timeEndN === null) {
        return 'bad_time';
    }
    if (!audit_fund_request_validate_interval_order($timeStartN, $timeEndN)) {
        return 'bad_interval';
    }

    [$timeStartDb, $timeEndDb] = audit_fund_request_interval_times_for_db($timeStartN, $timeEndN);

    if (!audit_fund_request_booking_interval_not_in_future($bookingDate, $timeEndDb)) {
        return 'past';
    }

    try {
        $pdo = audit_fund_pdo();
        audit_fund_requests_ensure_columns($pdo);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'requests_data');
        return 'db';
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT id, room_id, booking_date FROM booking_requests
             WHERE id = ? AND requested_by_user_id = ? AND status = "pending"
             LIMIT 1'
        );
        $stmt->execute([$requestId, $userId]);
        $curRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($curRow === false) {
            return 'forbidden';
        }
        $oldRoomId = (int) $curRow['room_id'];
        $oldDate = (string) $curRow['booking_date'];

        $roomChk = $pdo->prepare('SELECT operational_status FROM rooms WHERE id = ? LIMIT 1');
        $roomChk->execute([$roomId]);
        $roomRow = $roomChk->fetch(PDO::FETCH_ASSOC);
        if ($roomRow === false) {
            return 'room_not_found';
        }
        if (($roomRow['operational_status'] ?? '') === 'maint') {
            return 'room_maint';
        }

        $lockPairs = [[$roomId, $bookingDate]];
        if ($oldRoomId !== $roomId || $oldDate !== $bookingDate) {
            $lockPairs[] = [$oldRoomId, $oldDate];
        }
        if (!audit_fund_mysql_acquire_room_date_locks($pdo, $lockPairs)) {
            return 'db';
        }
        try {
            $stmt->execute([$requestId, $userId]);
            if ($stmt->fetch(PDO::FETCH_ASSOC) === false) {
                return 'forbidden';
            }

            $sched = $pdo->prepare(
                'SELECT 1 FROM schedule_entries
                 WHERE room_id = ?
                   AND entry_date = ?
                   AND ? < time_end
                   AND ? > time_start
                 LIMIT 1'
            );
            $sched->execute([$roomId, $bookingDate, $timeStartDb, $timeEndDb]);
            if ($sched->fetchColumn() !== false) {
                return 'busy';
            }

            $req = $pdo->prepare(
                'SELECT 1 FROM booking_requests
                 WHERE room_id = ?
                   AND booking_date = ?
                   AND status IN ("pending", "approved")
                   AND id <> ?
                   AND ? < time_end
                   AND ? > time_start
                 LIMIT 1'
            );
            $req->execute([$roomId, $bookingDate, $requestId, $timeStartDb, $timeEndDb]);
            if ($req->fetchColumn() !== false) {
                return 'busy';
            }
            if (audit_fund_has_maintenance_conflict($pdo, $roomId, $bookingDate, $timeStartDb, $timeEndDb)) {
                return 'maint';
            }

            $upd = $pdo->prepare(
                'UPDATE booking_requests
                 SET room_id = ?, applicant_display = ?, booking_date = ?, time_start = ?, time_end = ?, comment = ?
                 WHERE id = ? LIMIT 1'
            );
            $upd->execute([
                $roomId,
                $applicantDisplay,
                $bookingDate,
                $timeStartDb,
                $timeEndDb,
                $comment !== '' ? $comment : null,
                $requestId,
            ]);
        } finally {
            audit_fund_mysql_release_room_date_locks($pdo, $lockPairs);
        }
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'requests_data');
        return 'db';
    }

    return null;
}
