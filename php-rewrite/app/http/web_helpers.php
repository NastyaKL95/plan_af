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

function normalize_attachment_rows(array $sortOrders, array $titles, array $requirements): array
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
        $rows[] = ['sort_order' => $sort, 'title' => $title, 'requirements' => $req];
    }
    usort($rows, static fn($a, $b) => ($a['sort_order'] <=> $b['sort_order']) ?: strcmp($a['title'], $b['title']));
    return $rows;
}

/**
 * Можно ли назначить пользователя экспертом этапа: активен, не администратор сайта;
 * при непустом пуле экспертов конкурса — только из пула.
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
