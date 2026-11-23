<?php

declare(strict_types=1);

function get_contest(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM contests WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function can_manage_contests_global(PDO $pdo, ?int $userId): bool
{
    if ($userId === null) {
        return false;
    }
    return has_role($pdo, $userId, 'admin') || user_is_any_contest_moderator($pdo, $userId);
}

function normalize_int_list($raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $x) {
        $v = (int) $x;
        if ($v > 0) {
            $out[] = $v;
        }
    }
    $out = array_values(array_unique($out));
    sort($out);
    return $out;
}

function normalize_attachment_rows(array $sortOrders, array $titles, array $requirements, array $ids = []): array
{
    $rows = [];
    $n = max(count($sortOrders), count($titles), count($requirements));
    for ($i = 0; $i < $n; $i++) {
        $title = trim((string) ($titles[$i] ?? ''));
        $req = trim((string) ($requirements[$i] ?? ''));
        $sort = (int) ($sortOrders[$i] ?? $i);
        if ($title === '') {
            continue;
        }
        $id = (int) ($ids[$i] ?? 0);
        $rows[] = ['id' => $id > 0 ? $id : 0, 'sort_order' => $sort, 'title' => $title, 'requirements' => $req];
    }
    usort($rows, static fn($a, $b) => ($a['sort_order'] <=> $b['sort_order']) ?: strcmp($a['title'], $b['title']));
    return $rows;
}

/**
 * Сохраняет поля вложений без пересоздания: существующие (по id) обновляются, новые добавляются,
 * убранные из формы удаляются вместе с загруженными в них файлами.
 * Пересоздание ломало связь уже загруженных файлов с полями (новые id).
 *
 * @param 'contest'|'stage' $kind
 * @param list<array{id:int,sort_order:int,title:string,requirements:string}> $rows
 */
function iro_sync_attachment_fields(PDO $pdo, string $kind, int $ownerId, array $rows): void
{
    if ($kind === 'contest') {
        $table = 'contest_attachment_fields';
        $ownerCol = 'contest_id';
        $filesTable = 'participant_attachments';
    } else {
        $table = 'stage_attachment_fields';
        $ownerCol = 'stage_id';
        $filesTable = 'submission_attachments';
    }

    $exStmt = $pdo->prepare("SELECT id FROM {$table} WHERE {$ownerCol} = :oid");
    $exStmt->execute(['oid' => $ownerId]);
    $existingIds = array_map('intval', array_column($exStmt->fetchAll(PDO::FETCH_ASSOC), 'id'));

    $upd = $pdo->prepare(
        "UPDATE {$table} SET sort_order = :sort_order, title = :title, requirements = :requirements
         WHERE id = :id AND {$ownerCol} = :oid"
    );
    $ins = $pdo->prepare(
        "INSERT INTO {$table} ({$ownerCol}, sort_order, title, requirements)
         VALUES (:oid, :sort_order, :title, :requirements)"
    );
    $kept = [];
    foreach ($rows as $row) {
        $params = [
            'oid' => $ownerId,
            'sort_order' => $row['sort_order'],
            'title' => $row['title'],
            'requirements' => $row['requirements'],
        ];
        $rid = (int) ($row['id'] ?? 0);
        if ($rid > 0 && in_array($rid, $existingIds, true)) {
            $upd->execute($params + ['id' => $rid]);
            $kept[] = $rid;
        } else {
            $ins->execute($params);
        }
    }

    $removed = array_values(array_diff($existingIds, $kept));
    if ($removed === []) {
        return;
    }
    $ph = implode(',', array_fill(0, count($removed), '?'));
    $filesStmt = $pdo->prepare("SELECT file_path FROM {$filesTable} WHERE field_id IN ($ph)");
    $filesStmt->execute($removed);
    $paths = array_column($filesStmt->fetchAll(PDO::FETCH_ASSOC), 'file_path');
    $pdo->prepare("DELETE FROM {$filesTable} WHERE field_id IN ($ph)")->execute($removed);
    $pdo->prepare("DELETE FROM {$table} WHERE id IN ($ph)")->execute($removed);
    foreach ($paths as $p) {
        iro_delete_public_upload(trim((string) $p));
    }
}

/** Участник конкурса или подал заявку (согласие) — не может быть экспертом/модератором этого конкурса. */
function contest_user_is_applicant_or_participant(PDO $pdo, int $contestId, int $userId): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM contest_participants WHERE contest_id = :cid AND user_id = :uid
         UNION ALL
         SELECT 1 FROM user_consents WHERE contest_id = :cid2 AND user_id = :uid2
         LIMIT 1'
    );
    $st->execute(['cid' => $contestId, 'uid' => $userId, 'cid2' => $contestId, 'uid2' => $userId]);

    return (bool) $st->fetchColumn();
}

/**
 * Можно ли назначить пользователя экспертом этапа: активен, не администратор сайта,
 * не участник/заявитель этого конкурса; при непустом пуле экспертов конкурса — только из пула.
 */
function contest_stage_expert_user_allowed(PDO $pdo, int $contestId, int $candidateUserId): bool
{
    if ($candidateUserId <= 0) {
        return false;
    }
    if (has_role($pdo, $candidateUserId, 'admin')) {
        return false;
    }
    $uStmt = $pdo->prepare('SELECT id, is_active FROM users WHERE id = :id LIMIT 1');
    $uStmt->execute(['id' => $candidateUserId]);
    $row = $uStmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int) ($row['is_active'] ?? 0) !== 1) {
        return false;
    }
    if (contest_user_is_applicant_or_participant($pdo, $contestId, $candidateUserId)) {
        return false;
    }
    $poolStmt = $pdo->prepare('SELECT user_id FROM contest_expert_pool WHERE contest_id = :cid');
    $poolStmt->execute(['cid' => $contestId]);
    $poolIds = array_map('intval', array_column($poolStmt->fetchAll(PDO::FETCH_ASSOC), 'user_id'));
    if ($poolIds !== []) {
        return in_array($candidateUserId, $poolIds, true);
    }

    return true;
}

function validate_stage_timeline(PDO $pdo, int $contestId, int $proposedStageId, int $proposedOrder, string $proposedStart, string $proposedEnd): array
{
    $stmt = $pdo->prepare(
        'SELECT id, sort_order, start_date, end_date, title
         FROM stages
         WHERE contest_id = :cid AND id <> :sid
         ORDER BY sort_order, id'
    );
    $stmt->execute(['cid' => $contestId, 'sid' => $proposedStageId]);
    $timeline = [];
    foreach ($stmt->fetchAll() as $row) {
        $timeline[] = [
            'id' => (int) $row['id'],
            'order' => (int) $row['sort_order'],
            'start' => (string) $row['start_date'],
            'end' => (string) $row['end_date'],
            'title' => (string) $row['title'],
        ];
    }
    $timeline[] = [
        'id' => $proposedStageId > 0 ? $proposedStageId : -1,
        'order' => $proposedOrder,
        'start' => $proposedStart,
        'end' => $proposedEnd,
        'title' => 'этот этап',
    ];
    usort($timeline, static fn($a, $b) => ($a['order'] <=> $b['order']) ?: ($a['id'] <=> $b['id']));

    for ($i = 0; $i < count($timeline) - 1; $i++) {
        $endPrev = $timeline[$i]['end'];
        $startNext = $timeline[$i + 1]['start'];
        if ($endPrev > $startNext) {
            return [
                false,
                'Этапы должны идти друг за другом: дата окончания предыдущего этапа не может быть позже даты начала следующего (допускается стык в один день).',
            ];
        }
    }
    return [true, null];
}

function stage_effective_deadline(array $stage): string
{
    $submissionDeadline = (string) ($stage['submission_deadline'] ?? '');
    if ($submissionDeadline !== '') {
        return $submissionDeadline;
    }
    return (string) $stage['end_date'];
}

/** @return string|null код причины или null, если подать новую работу на этап можно */
function participant_submit_work_block_reason(PDO $pdo, int $userId, array $contest, array $stage, ?array $participantRow, string $todayYmd): ?string
{
    if (!$participantRow) {
        return 'not_participant';
    }
    $stageId = (int) $stage['id'];
    $exists = $pdo->prepare('SELECT 1 FROM submissions WHERE user_id = :u AND stage_id = :sid LIMIT 1');
    $exists->execute(['u' => $userId, 'sid' => $stageId]);
    if ($exists->fetchColumn()) {
        return 'already_submitted';
    }

    if ((int) ($contest['require_explicit_stage_admission'] ?? 0) === 1) {
        if ((int) ($participantRow['admitted_max_stage_order'] ?? 0) < (int) $stage['sort_order']) {
            return 'not_admitted';
        }
    }

    if (stage_is_in_person($stage)) {
        return 'in_person_stage';
    }

    if (contest_is_parallel_stage_mode($contest)) {
        $ps = stage_effective_phase_status($contest, $stage, $todayYmd);
        if ($ps !== STAGE_PHASE_INTAKE) {
            if ($ps === STAGE_PHASE_EVALUATION) {
                return 'phase_evaluation';
            }
            if ($ps === STAGE_PHASE_DONE) {
                return 'phase_done';
            }

            return 'phase_soon';
        }
        $cStart = (string) $contest['start_date'];
        $cEnd = (string) $contest['end_date'];
        if ($todayYmd < $cStart || $todayYmd > $cEnd) {
            return 'outside_contest_dates';
        }
        if (!empty($stage['submission_deadline']) && $todayYmd > stage_effective_deadline($stage)) {
            return 'past_deadline';
        }

        return null;
    }

    if ($todayYmd < (string) $stage['start_date'] || $todayYmd > (string) $stage['end_date']) {
        return 'stage_inactive';
    }
    $deadline = stage_effective_deadline($stage);
    if ($todayYmd > $deadline) {
        return 'past_deadline';
    }
    $prevStmt = $pdo->prepare(
        'SELECT id FROM stages WHERE contest_id = :cid AND sort_order < :so ORDER BY sort_order DESC, id DESC LIMIT 1'
    );
    $prevStmt->execute(['cid' => (int) $contest['id'], 'so' => (int) $stage['sort_order']]);
    $prevRow = $prevStmt->fetch(PDO::FETCH_ASSOC);
    if ($prevRow) {
        $px = $pdo->prepare('SELECT 1 FROM submissions WHERE user_id = :u AND stage_id = :sid LIMIT 1');
        $px->execute(['u' => $userId, 'sid' => (int) $prevRow['id']]);
        if (!$px->fetchColumn()) {
            return 'prev_missing';
        }
    }

    return null;
}
