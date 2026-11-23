<?php

declare(strict_types=1);

function user_display_name(array $u): string
{
    $parts = array_filter([
        trim((string) ($u['last_name'] ?? '')),
        trim((string) ($u['first_name'] ?? '')),
        trim((string) ($u['middle_name'] ?? '')),
    ]);
    if ($parts !== []) {
        return implode(' ', $parts);
    }

    return (string) ($u['email'] ?? '');
}

/** @return array<int, float> submission_id => средняя сумма баллов по экспертам */
function contest_submission_score_means(PDO $pdo, int $contestId): array
{
    $stmt = $pdo->prepare(
        'SELECT s.id AS submission_id, e.expert_id,
                SUM(e.points * (COALESCE(NULLIF(c.weight, 0), 100) / 100.0)) AS expert_total
         FROM evaluations e
         INNER JOIN submissions s ON s.id = e.submission_id
         INNER JOIN criteria c ON c.id = e.criterion_id
         WHERE s.contest_id = :cid
         GROUP BY s.id, e.expert_id'
    );
    $stmt->execute(['cid' => $contestId]);
    $bySub = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sid = (int) $row['submission_id'];
        $bySub[$sid][] = (float) $row['expert_total'];
    }
    $means = [];
    foreach ($bySub as $sid => $totals) {
        $means[$sid] = $totals === [] ? 0.0 : array_sum($totals) / count($totals);
    }

    return $means;
}

function submission_score_mean(array $means, int $submissionId): float
{
    return $means[$submissionId] ?? 0.0;
}

/** Порог сравнения итоговых баллов при ранжировании (избегаем дрожания float). */
const IRO_RANKING_TOTAL_EPS = 1e-9;

/** Этапы, чьи баллы входят в итоговый зачёт (галочка «Суммировать в итоговом зачёте»), включая последний этап. */
function contest_ranking_stage_ids(PDO $pdo, int $contestId): array
{
    $stmt = $pdo->prepare(
        'SELECT id FROM stages
         WHERE contest_id = :cid AND include_scores_in_final_ranking = 1
         ORDER BY sort_order, id'
    );
    $stmt->execute(['cid' => $contestId]);

    return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
}

/**
 * Итоговый балл для ранжирования наград: сумма средних оценок за работы на этапах из $rankingStageIds
 * (для каждого этапа — одна работа участника; среднее по экспертам с учётом весов критериев).
 *
 * @param array<int, list<array<string, mixed>>> $subsByUser
 * @param list<int> $rankingStageIds
 * @param array<int, float> $scoreMeans
 */
function participant_ranking_total(
    array $subsByUser,
    array $rankingStageIds,
    array $scoreMeans,
    int $userId
): float {
    if ($rankingStageIds === []) {
        return 0.0;
    }
    $userSubs = $subsByUser[$userId] ?? [];
    $byStage = [];
    foreach ($userSubs as $s) {
        $byStage[(int) $s['stage_id']] = $s;
    }
    $total = 0.0;
    foreach ($rankingStageIds as $sid) {
        if (isset($byStage[$sid])) {
            $total += submission_score_mean($scoreMeans, (int) $byStage[$sid]['id']);
        }
    }

    return $total;
}

/**
 * @return list<array{participant: array, place: int, total: float}>
 */
function compute_ranked_participants(PDO $pdo, int $contestId): array
{
    $rankingStageIds = contest_ranking_stage_ids($pdo, $contestId);

    $stmt = $pdo->prepare('SELECT * FROM contest_participants WHERE contest_id = :cid');
    $stmt->execute(['cid' => $contestId]);
    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt2 = $pdo->prepare('SELECT * FROM submissions WHERE contest_id = :cid');
    $stmt2->execute(['cid' => $contestId]);
    $subs = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    $subsByUser = [];
    foreach ($subs as $s) {
        $subsByUser[(int) $s['user_id']][] = $s;
    }

    $means = contest_submission_score_means($pdo, $contestId);

    $rankingStageSet = array_flip($rankingStageIds);
    $rows = [];
    foreach ($participants as $p) {
        $uid = (int) $p['user_id'];
        $total = participant_ranking_total($subsByUser, $rankingStageIds, $means, $uid);
        $hasScores = false;
        foreach ($subsByUser[$uid] ?? [] as $s) {
            if (isset($rankingStageSet[(int) $s['stage_id']]) && isset($means[(int) $s['id']])) {
                $hasScores = true;
                break;
            }
        }
        $rows[] = ['participant' => $p, 'total' => $total, 'user_id' => $uid, 'has_scores' => $hasScores];
    }
    usort($rows, static function (array $a, array $b): int {
        $diff = (float) $a['total'] - (float) $b['total'];
        if (abs($diff) < IRO_RANKING_TOTAL_EPS) {
            return $a['user_id'] <=> $b['user_id'];
        }

        return $diff > 0 ? -1 : 1;
    });

    $ranked = [];
    foreach ($rows as $i => $row) {
        $total = (float) $row['total'];
        if ($i === 0) {
            $place = 1;
        } else {
            $prevTotal = (float) $rows[$i - 1]['total'];
            if ($total < $prevTotal - IRO_RANKING_TOTAL_EPS) {
                $place = $i + 1;
            } else {
                $place = $ranked[$i - 1]['place'];
            }
        }
        $ranked[] = [
            'participant' => $row['participant'],
            'place' => $place,
            'total' => $total,
            'has_scores' => $row['has_scores'],
        ];
    }

    return $ranked;
}

/**
 * Пересчитывает итоговые места. Участники без оценок на зачётных этапах мест не получают;
 * ручные места сохраняются, а автоматические их пропускают (без дублей мест).
 * Возвращает число записей в итогах или -1, если нет ни одного зачётного этапа (итоги не трогаются).
 */
function replace_awards_from_ranking(PDO $pdo, int $contestId): int
{
    if (contest_ranking_stage_ids($pdo, $contestId) === []) {
        return -1;
    }

    $ownTx = !$pdo->inTransaction();
    if ($ownTx) {
        $pdo->beginTransaction();
    }
    try {
        $stmt = $pdo->prepare(
            'SELECT a.participant_id, a.place, a.award_description
             FROM awards a
             INNER JOIN contest_participants cp ON cp.id = a.participant_id AND cp.contest_id = a.contest_id
             WHERE a.contest_id = :cid AND a.is_manual_override = 1
             FOR UPDATE'
        );
        $stmt->execute(['cid' => $contestId]);
        $manual = [];
        $manualPlaces = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $manual[(int) $row['participant_id']] = [
                'place' => (int) $row['place'],
                'award_description' => trim((string) $row['award_description']),
            ];
            $manualPlaces[(int) $row['place']] = true;
        }

        $pdo->prepare('DELETE FROM awards WHERE contest_id = :cid')->execute(['cid' => $contestId]);
        $ins = $pdo->prepare(
            'INSERT INTO awards (participant_id, contest_id, place, award_description, is_manual_override)
             VALUES (:pid, :cid, :place, :descr, :manual)'
        );
        $n = 0;
        foreach ($manual as $pid => $m) {
            $ins->execute([
                'pid' => $pid,
                'cid' => $contestId,
                'place' => $m['place'],
                'descr' => $m['award_description'] !== '' ? $m['award_description'] : null,
                'manual' => 1,
            ]);
            $n++;
        }

        $nextPlace = 1;
        $prevTotal = null;
        $groupPlace = 0;
        $groupSize = 0;
        foreach (compute_ranked_participants($pdo, $contestId) as $r) {
            $pid = (int) $r['participant']['id'];
            if (isset($manual[$pid]) || empty($r['has_scores'])) {
                continue;
            }
            $total = (float) $r['total'];
            if ($prevTotal === null || $total < $prevTotal - IRO_RANKING_TOTAL_EPS) {
                $nextPlace += $groupSize;
                while (isset($manualPlaces[$nextPlace])) {
                    $nextPlace++;
                }
                $groupPlace = $nextPlace;
                $groupSize = 0;
                $prevTotal = $total;
            }
            $groupSize++;
            $ins->execute([
                'pid' => $pid,
                'cid' => $contestId,
                'place' => $groupPlace,
                'descr' => null,
                'manual' => 0,
            ]);
            $n++;
        }
        if ($ownTx) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($ownTx) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return $n;
}

function contest_has_any_awards(PDO $pdo, int $contestId): bool
{
    if ($contestId <= 0) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM awards WHERE contest_id = :cid LIMIT 1');
    $stmt->execute(['cid' => $contestId]);

    return (bool) $stmt->fetchColumn();
}

/**
 * Как Django main.signals: пересчитывает Award по рейтингу только если записи по конкурсу уже есть.
 */
function refresh_awards_from_ranking_if_exist(PDO $pdo, int $contestId): void
{
    if ($contestId <= 0 || !contest_has_any_awards($pdo, $contestId)) {
        return;
    }
    replace_awards_from_ranking($pdo, $contestId);
}

function ru_contest_count_word(int $n): string
{
    $mod100 = $n % 100;
    if ($mod100 >= 11 && $mod100 <= 14) {
        return 'конкурсов';
    }
    $mod10 = $n % 10;
    if ($mod10 === 1) {
        return 'конкурс';
    }
    if ($mod10 >= 2 && $mod10 <= 4) {
        return 'конкурса';
    }

    return 'конкурсов';
}

/** @return array{code: string, label: string, badge_class: string} */
function contest_calendar_phase(array $contest, string $todayYmd): array
{
    $start = (string) $contest['start_date'];
    $end = (string) $contest['end_date'];
    if ($start > $todayYmd) {
        return ['code' => 'upcoming', 'label' => 'Скоро начнётся', 'badge_class' => 'iro-status-soon'];
    }
    if ($end < $todayYmd) {
        return ['code' => 'finished', 'label' => 'Завершён', 'badge_class' => 'iro-status-archive'];
    }

    return ['code' => 'active', 'label' => 'Идёт конкурс', 'badge_class' => 'iro-status-active'];
}

/** Фаза этапа по календарю (для таблицы прогресса участника). */
function stage_calendar_phase(array $stage, string $todayYmd): array
{
    $start = (string) $stage['start_date'];
    $end = (string) $stage['end_date'];
    if ($start > $todayYmd) {
        return ['code' => 'upcoming', 'label' => 'Скоро', 'badge_class' => 'iro-pst-soon'];
    }
    if ($end < $todayYmd) {
        return ['code' => 'finished', 'label' => 'Завершён', 'badge_class' => 'iro-pst-done'];
    }

    return ['code' => 'active', 'label' => 'Идёт сейчас', 'badge_class' => 'iro-pst-active'];
}

/** 0 — этапы по очереди и календарю; 1 — подача на этапы по выбранному статусу (без цепочки предыдущих работ). */
const CONTEST_STAGE_CONDUCT_SEQUENTIAL = 0;
const CONTEST_STAGE_CONDUCT_PARALLEL = 1;

/** 1 скоро, 2 приём работ, 3 оценивание, 4 завершён */
const STAGE_PHASE_SOON = 1;
const STAGE_PHASE_INTAKE = 2;
const STAGE_PHASE_EVALUATION = 3;
const STAGE_PHASE_DONE = 4;

/** 0 — онлайн (подача работ участниками); 1 — очный (без подачи в системе, оценивание экспертами). */
const STAGE_FORMAT_ONLINE = 0;
const STAGE_FORMAT_IN_PERSON = 1;

function stage_format_normalize(int $v): int
{
    return $v === STAGE_FORMAT_IN_PERSON ? STAGE_FORMAT_IN_PERSON : STAGE_FORMAT_ONLINE;
}

function stage_is_in_person(array $stage): bool
{
    return stage_format_normalize((int) ($stage['stage_format'] ?? STAGE_FORMAT_ONLINE)) === STAGE_FORMAT_IN_PERSON;
}

function contest_is_parallel_stage_mode(array $contest): bool
{
    return (int) ($contest['stage_conduct_mode'] ?? CONTEST_STAGE_CONDUCT_SEQUENTIAL) === CONTEST_STAGE_CONDUCT_PARALLEL;
}

/** @return array<int, string> */
function stage_phase_status_labels(): array
{
    return [
        STAGE_PHASE_SOON => 'Скоро',
        STAGE_PHASE_INTAKE => 'Приём работ',
        STAGE_PHASE_EVALUATION => 'Оценивание',
        STAGE_PHASE_DONE => 'Завершён',
    ];
}

function stage_phase_status_normalize(int $v): int
{
    if ($v < STAGE_PHASE_SOON || $v > STAGE_PHASE_DONE) {
        return STAGE_PHASE_SOON;
    }

    return $v;
}

/**
 * Для параллельного режима: после end_date этап считается завершённым (в т.ч. для отображения и правил).
 * Для последовательного — возвращает значение из БД (для справки).
 */
function stage_effective_phase_status(array $contest, array $stage, string $todayYmd): int
{
    $db = stage_phase_status_normalize((int) ($stage['phase_status'] ?? STAGE_PHASE_SOON));
    if (!contest_is_parallel_stage_mode($contest)) {
        return $db;
    }
    if ($todayYmd > (string) $stage['end_date']) {
        return STAGE_PHASE_DONE;
    }

    return $db;
}

/**
 * Оценивание закрыто: всегда после end_date этапа; в параллельном режиме также при статусе «Завершён».
 */
function stage_evaluation_is_closed(array $contest, array $stage, string $todayYmd): bool
{
    if (!contest_is_parallel_stage_mode($contest)) {
        return $todayYmd > (string) $stage['end_date'];
    }

    return stage_effective_phase_status($contest, $stage, $todayYmd) === STAGE_PHASE_DONE;
}

/**
 * Записать в БД «Завершён», если дата окончания этапа уже прошла (параллельный режим). Обновляет $stage.
 */
function stage_sync_phase_done_if_past(PDO $pdo, array $contest, array &$stage): void
{
    if (!contest_is_parallel_stage_mode($contest)) {
        return;
    }
    $today = date('Y-m-d');
    if ($today <= (string) $stage['end_date']) {
        return;
    }
    if (stage_phase_status_normalize((int) ($stage['phase_status'] ?? STAGE_PHASE_SOON)) === STAGE_PHASE_DONE) {
        return;
    }
    $sid = (int) $stage['id'];
    if ($sid <= 0) {
        return;
    }
    $pdo->prepare('UPDATE stages SET phase_status = ? WHERE id = ?')->execute([STAGE_PHASE_DONE, $sid]);
    $stage['phase_status'] = STAGE_PHASE_DONE;
}

/**
 * Для последовательного режима — по календарю; для параллельного — по эффективному статусу этапа.
 *
 * @return array{code: string, label: string, badge_class: string}
 */
function stage_display_phase(array $contest, array $stage, string $todayYmd): array
{
    if (contest_is_parallel_stage_mode($contest)) {
        $ps = stage_effective_phase_status($contest, $stage, $todayYmd);
        $labels = stage_phase_status_labels();
        $badge = match ($ps) {
            STAGE_PHASE_SOON => 'iro-pst-soon',
            STAGE_PHASE_INTAKE => 'iro-pst-active',
            STAGE_PHASE_EVALUATION => 'text-bg-warning',
            STAGE_PHASE_DONE => 'iro-pst-done',
            default => 'iro-pst-soon',
        };

        $lab = $labels[$ps] ?? '—';
        if (stage_is_in_person($stage)) {
            $lab .= ' · очно';
        }

        return [
            'code' => 'phase_' . $ps,
            'label' => $lab,
            'badge_class' => $badge,
        ];
    }

    $cal = stage_calendar_phase($stage, $todayYmd);
    if (stage_is_in_person($stage)) {
        $cal['label'] = ($cal['label'] ?? '') . ' · очно';
    }

    return $cal;
}

/**
 * @return array{
 *   contests: list<array>,
 *   page: int,
 *   pages: int,
 *   total: int,
 *   query: string,
 *   phase: string,
 *   view_mode: string,
 *   contest_count_word: string
 * }
 */
function home_contests_list_context(PDO $pdo, array $get): array
{
    $query = trim((string) ($get['q'] ?? ''));
    $rawPhase = trim((string) ($get['phase'] ?? ''));
    $rawView = trim((string) ($get['view'] ?? ''));
    $validPhases = ['upcoming', 'active', 'finished'];
    $phase = in_array($rawPhase, $validPhases, true) ? $rawPhase : '';
    $viewMode = $rawView === 'list' ? 'list' : 'cards';

    $sqlFrom = ' FROM contests WHERE status = 2 AND COALESCE(is_archived, 0) = 0';
    $params = [];
    if ($query !== '') {
        $sqlFrom .= ' AND (LOWER(title) LIKE ? OR LOWER(description) LIKE ?)';
        $like = '%' . mb_strtolower($query) . '%';
        $params[] = $like;
        $params[] = $like;
    }
    $today = date('Y-m-d');
    if ($phase === 'upcoming') {
        $sqlFrom .= ' AND start_date > ?';
        $params[] = $today;
    } elseif ($phase === 'active') {
        $sqlFrom .= ' AND start_date <= ? AND end_date >= ?';
        $params[] = $today;
        $params[] = $today;
    } elseif ($phase === 'finished') {
        $sqlFrom .= ' AND end_date < ?';
        $params[] = $today;
    }

    $orderSql = '';
    $orderParams = [];
    if ($phase === '') {
        /* Совпадает с прежним usort: активные → скоро завершаются; затем будущие; затем архив */
        $orderSql = <<<'SQL'
 ORDER BY
  CASE WHEN start_date <= ? AND end_date >= ? THEN 0 WHEN start_date > ? THEN 1 ELSE 2 END,
  CASE WHEN start_date <= ? AND end_date >= ? THEN end_date END ASC,
  CASE WHEN start_date > ? THEN start_date END ASC,
  CASE WHEN end_date < ? THEN end_date END DESC,
  id DESC
SQL;
        $orderParams = array_fill(0, 7, $today);
    } elseif ($phase === 'upcoming') {
        $orderSql = ' ORDER BY start_date ASC, id ASC';
    } elseif ($phase === 'active') {
        $orderSql = ' ORDER BY end_date ASC, start_date ASC, id ASC';
    } elseif ($phase === 'finished') {
        $orderSql = ' ORDER BY end_date DESC, id DESC';
    }

    $selectCols = 'SELECT id, title, start_date, end_date, description, status, image_path, is_external, external_url';

    $perPage = $viewMode === 'cards' ? 9 : 18;
    $page = max(1, (int) ($get['page'] ?? 1));

    $countStmt = $pdo->prepare('SELECT COUNT(*)' . $sqlFrom);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $pages = max(1, (int) ceil($total / $perPage));
    if ($page > $pages) {
        $page = $pages;
    }
    $offset = ($page - 1) * $perPage;

    $listParams = array_merge($params, $orderParams);
    $listSql = $selectCols . $sqlFrom . $orderSql
        . ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;
    $stmt = $pdo->prepare($listSql);
    $stmt->execute($listParams);
    $slice = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'contests' => $slice,
        'page' => $page,
        'pages' => $pages,
        'total' => $total,
        'query' => $query,
        'phase' => $phase,
        'view_mode' => $viewMode,
        'contest_count_word' => ru_contest_count_word($total),
    ];
}

/** Каноническая query-строка для главной (без ведущего ?) */
function home_canonical_query(array $get): string
{
    $q = trim((string) ($get['q'] ?? ''));
    $phase = trim((string) ($get['phase'] ?? ''));
    $view = trim((string) ($get['view'] ?? ''));
    $page = (int) ($get['page'] ?? 0);

    $validPhases = ['upcoming', 'active', 'finished'];
    if ($phase !== '' && !in_array($phase, $validPhases, true)) {
        $phase = '';
    }
    if ($view !== '' && $view !== 'list' && $view !== 'cards') {
        $view = '';
    }

    $parts = [];
    if ($q !== '') {
        $parts['q'] = $q;
    }
    if ($phase !== '') {
        $parts['phase'] = $phase;
    }
    if ($view === 'list') {
        $parts['view'] = 'list';
    }
    if ($page > 1) {
        $parts['page'] = (string) $page;
    }

    return http_build_query($parts);
}

/**
 * Детальные строки оценок по каждой работе конкурса (один запрос к БД).
 *
 * @return array<int, list<array{expert: array, points: int, criterion: array}>>
 */
function contest_stats_eval_rows_by_submission(PDO $pdo, int $contestId): array
{
    $stmt = $pdo->prepare(
        'SELECT e.points, e.submission_id,
                u.email AS expert_email, u.first_name AS ef, u.last_name AS el,
                c.name AS criterion_name, c.weight AS criterion_weight,
                c.min_points AS criterion_min, c.max_points AS criterion_max
         FROM evaluations e
         INNER JOIN submissions s ON s.id = e.submission_id
         INNER JOIN users u ON u.id = e.expert_id
         INNER JOIN criteria c ON c.id = e.criterion_id
         WHERE s.contest_id = :cid'
    );
    $stmt->execute(['cid' => $contestId]);
    $bySub = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $er) {
        $sid = (int) $er['submission_id'];
        $bySub[$sid][] = [
            'expert' => [
                'email' => (string) $er['expert_email'],
                'first_name' => $er['ef'] !== null && $er['ef'] !== '' ? (string) $er['ef'] : null,
                'last_name' => $er['el'] !== null && $er['el'] !== '' ? (string) $er['el'] : null,
                'middle_name' => null,
            ],
            'points' => (int) $er['points'],
            'criterion' => [
                'name' => (string) $er['criterion_name'],
                'weight' => (int) ($er['criterion_weight'] ?? 100),
                'min_points' => (int) ($er['criterion_min'] ?? 0),
                'max_points' => (int) ($er['criterion_max'] ?? 0),
            ],
        ];
    }

    return $bySub;
}

/**
 * Контекст для страницы «Статистика конкурса» (как в Django contest_stats).
 *
 * @return array<string, mixed>
 */
function contest_stats_build_context(PDO $pdo, array $contest): array
{
    $contestId = (int) $contest['id'];
    $requireAdmission = (int) ($contest['require_explicit_stage_admission'] ?? 0) === 1;

    $stmt = $pdo->prepare(
        'SELECT * FROM stages WHERE contest_id = :cid ORDER BY sort_order, id'
    );
    $stmt->execute(['cid' => $contestId]);
    $stagesOrdered = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt2 = $pdo->prepare('SELECT * FROM submissions WHERE contest_id = :cid');
    $stmt2->execute(['cid' => $contestId]);
    $allSubs = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    $subsByUser = [];
    foreach ($allSubs as $s) {
        $subsByUser[(int) $s['user_id']][] = $s;
    }

    $means = contest_submission_score_means($pdo, $contestId);
    $evalRowsBySubmission = contest_stats_eval_rows_by_submission($pdo, $contestId);

    $stmt3 = $pdo->prepare(
        'SELECT cp.*, u.email, u.first_name, u.last_name, u.middle_name, u.region, u.school
         FROM contest_participants cp
         JOIN users u ON u.id = cp.user_id
         WHERE cp.contest_id = :cid'
    );
    $stmt3->execute(['cid' => $contestId]);
    $participants = $stmt3->fetchAll(PDO::FETCH_ASSOC);

    $worksCountStmt = $pdo->prepare(
        'SELECT user_id, COUNT(*) AS c FROM submissions WHERE contest_id = :cid GROUP BY user_id'
    );
    $worksCountStmt->execute(['cid' => $contestId]);
    $worksByUser = [];
    foreach ($worksCountStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $worksByUser[(int) $row['user_id']] = (int) $row['c'];
    }

    $rankingStageIds = contest_ranking_stage_ids($pdo, $contestId);

    foreach ($participants as &$p) {
        $uid = (int) $p['user_id'];
        $p['works_count'] = $worksByUser[$uid] ?? 0;
        $p['ranking_total_points'] = participant_ranking_total(
            $subsByUser,
            $rankingStageIds,
            $means,
            $uid
        );
        /* Итоговая сумма — только этапы с «Суммировать в итоговом зачёте» (включая последний, если отмечен). */
        $p['total_points'] = $p['ranking_total_points'];
    }
    unset($p);
    usort($participants, static fn($a, $b) => $b['ranking_total_points'] <=> $a['ranking_total_points']);

    $consentsByUser = [];
    $ucStmt = $pdo->prepare('SELECT * FROM user_consents WHERE contest_id = :cid');
    $ucStmt->execute(['cid' => $contestId]);
    foreach ($ucStmt->fetchAll(PDO::FETCH_ASSOC) as $uc) {
        $consentsByUser[(int) $uc['user_id']] = $uc;
    }

    $participantConsentProgress = [];
    foreach ($participants as $p) {
        $uid = (int) $p['user_id'];
        $uc = $consentsByUser[$uid] ?? null;
        if ($uc === null) {
            $consentLabel = 'Нет записи';
            $consentExtra = '—';
        } elseif ((int) $uc['is_approved'] === 1) {
            $consentLabel = 'Одобрено';
            $consentExtra = $uc['reviewed_at'] ? date('d.m.Y H:i', strtotime((string) $uc['reviewed_at'])) : '—';
        } elseif (trim((string) $uc['rejection_reason']) !== '') {
            $consentLabel = 'Отклонено';
            $consentExtra = mb_substr((string) $uc['rejection_reason'], 0, 240);
        } else {
            $consentLabel = 'На проверке';
            $consentExtra = $uc['uploaded_at'] ? date('d.m.Y H:i', strtotime((string) $uc['uploaded_at'])) : '—';
        }

        $userSubs = $subsByUser[$uid] ?? [];
        $subByStageId = [];
        foreach ($userSubs as $s) {
            $subByStageId[(int) $s['stage_id']] = $s;
        }
        $stageCells = [];
        foreach ($stagesOrdered as $st) {
            $sub = $subByStageId[(int) $st['id']] ?? null;
            $order = (int) $st['sort_order'];
            $admittedHere = !$requireAdmission || ((int) $p['admitted_max_stage_order'] >= $order);
            $stageCells[] = [
                'stage' => $st,
                'has_submission' => $sub !== null,
                'submission_title' => $sub ? (string) $sub['title'] : '',
                'admitted_here' => $admittedHere,
            ];
        }
        $participantConsentProgress[] = [
            'participant' => $p,
            'user' => $p,
            'consent_label' => $consentLabel,
            'consent_extra' => $consentExtra,
            'admitted_max_stage_order' => (int) $p['admitted_max_stage_order'],
            'stage_cells' => $stageCells,
        ];
    }

    $awardsByParticipant = [];
    $awStmt = $pdo->prepare(
        'SELECT * FROM awards WHERE contest_id = :cid'
    );
    $awStmt->execute(['cid' => $contestId]);
    foreach ($awStmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
        $awardsByParticipant[(int) $a['participant_id']] = $a;
    }

    $byParticipantPk = [];
    foreach ($participants as $p) {
        $byParticipantPk[(int) $p['id']] = $p;
    }

    $participantRanked = [];
    foreach (compute_ranked_participants($pdo, $contestId) as $r) {
        $part = $r['participant'];
        $pid = (int) $part['id'];
        $src = $byParticipantPk[$pid] ?? null;
        $award = $awardsByParticipant[$pid] ?? null;
        $calculatedPlace = $r['place'];
        $displayPlace = $award ? (int) $award['place'] : $calculatedPlace;
        $participantRanked[] = [
            'place' => $displayPlace,
            'calculated_place' => $calculatedPlace,
            'place_mismatch' => $award
                && (int) $award['is_manual_override'] === 0
                && (int) $award['place'] !== $calculatedPlace,
            'is_manual_override' => $award && (int) $award['is_manual_override'] === 1,
            'total_points' => $r['total'],
            'works_count' => $src['works_count'] ?? 0,
            'participant' => $src ?? $part,
        ];
    }

    $stageCountStmt = $pdo->prepare('SELECT COUNT(*) FROM stages WHERE contest_id = :cid');
    $stageCountStmt->execute(['cid' => $contestId]);
    $stagesCountContest = (int) $stageCountStmt->fetchColumn();

    $modStmt = $pdo->prepare(
        'SELECT u.id, u.email, u.first_name, u.last_name, u.middle_name
         FROM contest_moderators cm
         JOIN users u ON u.id = cm.user_id
         WHERE cm.contest_id = :cid
         ORDER BY u.email'
    );
    $modStmt->execute(['cid' => $contestId]);
    $moderators = [];
    foreach ($modStmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
        $m['stages_managed'] = $stagesCountContest;
        $moderators[] = $m;
    }

    $expertStmt = $pdo->prepare(
        "SELECT DISTINCT u.id, u.email, u.first_name, u.last_name, u.middle_name
         FROM users u
         INNER JOIN stage_experts se ON se.user_id = u.id
         INNER JOIN stages st ON st.id = se.stage_id
         WHERE st.contest_id = :cid
         ORDER BY u.email"
    );
    $expertStmt->execute(['cid' => $contestId]);
    $expertsRows = $expertStmt->fetchAll(PDO::FETCH_ASSOC);

    $aggStmt = $pdo->prepare(
        'SELECT e.expert_id AS uid,
                COUNT(*) AS eval_rows,
                AVG(e.points) AS avg_pts
         FROM evaluations e
         INNER JOIN submissions s ON s.id = e.submission_id
         WHERE s.contest_id = :cid
         GROUP BY e.expert_id'
    );
    $aggStmt->execute(['cid' => $contestId]);
    $aggByExpert = [];
    foreach ($aggStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $aggByExpert[(int) $row['uid']] = [
            'submissions_evaluated' => (int) $row['eval_rows'],
            'avg_score' => (float) ($row['avg_pts'] ?: 0),
        ];
    }

    $stgCntStmt = $pdo->prepare(
        'SELECT se.user_id AS uid, COUNT(DISTINCT se.stage_id) AS sc
         FROM stage_experts se
         INNER JOIN stages st ON st.id = se.stage_id
         WHERE st.contest_id = :cid
         GROUP BY se.user_id'
    );
    $stgCntStmt->execute(['cid' => $contestId]);
    $stagesByExpert = [];
    foreach ($stgCntStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $stagesByExpert[(int) $row['uid']] = (int) $row['sc'];
    }

    $experts = [];
    foreach ($expertsRows as $ex) {
        $eid = (int) $ex['id'];
        $agg = $aggByExpert[$eid] ?? ['submissions_evaluated' => 0, 'avg_score' => 0.0];
        $ex['stages_count'] = $stagesByExpert[$eid] ?? 0;
        $ex['submissions_evaluated'] = $agg['submissions_evaluated'];
        $ex['avg_score'] = $agg['avg_score'];
        $experts[] = $ex;
    }

    $avgAll = $pdo->prepare(
        'SELECT AVG(e.points) FROM evaluations e
         JOIN submissions s ON s.id = e.submission_id
         WHERE s.contest_id = :cid'
    );
    $avgAll->execute(['cid' => $contestId]);
    $avgScoreContest = (float) ($avgAll->fetchColumn() ?: 0);

    $subCount = $pdo->prepare('SELECT COUNT(*) FROM submissions WHERE contest_id = :cid');
    $subCount->execute(['cid' => $contestId]);

    $contestStats = [
        'participants_count' => count($participants),
        'submissions_count' => (int) $subCount->fetchColumn(),
        'stages_count' => $stagesCountContest,
        'moderators_count' => count($moderators),
        'experts_count' => count($experts),
        'avg_score' => $avgScoreContest,
    ];

    $detailedParticipants = [];
    foreach ($participants as $participant) {
        $uid = (int) $participant['user_id'];
        $userSubs = $subsByUser[$uid] ?? [];
        $stageBlocks = [];
        foreach ($stagesOrdered as $stage) {
            $sid = (int) $stage['id'];
            $stageSubmissions = array_values(array_filter(
                $userSubs,
                static fn($s) => (int) $s['stage_id'] === $sid
            ));
            $points = 0.0;
            foreach ($stageSubmissions as $s) {
                $points += submission_score_mean($means, (int) $s['id']);
            }
            $subIds = array_map(static fn($s) => (int) $s['id'], $stageSubmissions);
            $evalRows = [];
            foreach ($subIds as $subId) {
                foreach ($evalRowsBySubmission[$subId] ?? [] as $er) {
                    $evalRows[] = $er;
                }
            }
            $stageBlocks[] = [
                'stage' => $stage,
                'points' => $points,
                'evaluations' => $evalRows,
            ];
        }
        $detailedParticipants[] = [
            'user' => $participant,
            'works_count' => (int) $participant['works_count'],
            'total_points' => (float) $participant['total_points'],
            'stages' => $stageBlocks,
        ];
    }

    $consentProgressColspan = 3 + ($requireAdmission ? 1 : 0) + count($stagesOrdered);

    return [
        'stages_ordered' => $stagesOrdered,
        'participants' => $participants,
        'participant_ranked' => $participantRanked,
        'moderators' => $moderators,
        'experts' => $experts,
        'contest_stats' => $contestStats,
        'detailed_participants' => $detailedParticipants,
        'participant_consent_progress' => $participantConsentProgress,
        'consent_progress_colspan' => $consentProgressColspan,
        'stats_hint_no_ranking_stages' => $stagesCountContest > 0 && $rankingStageIds === [],
    ];
}

function contest_report_rows(PDO $pdo, int $contestId): array
{
    $means = contest_submission_score_means($pdo, $contestId);
    $stmt = $pdo->prepare(
        'SELECT s.*, u.email, u.first_name, u.last_name, u.middle_name
         FROM submissions s
         JOIN users u ON u.id = s.user_id
         WHERE s.contest_id = :cid
         ORDER BY s.id'
    );
    $stmt->execute(['cid' => $contestId]);
    $report = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $sub) {
        $report[] = [
            'submission' => $sub,
            'total_points' => round(submission_score_mean($means, (int) $sub['id']), 4),
        ];
    }
    usort($report, static function ($a, $b) {
        if ($a['total_points'] == $b['total_points']) {
            return (int) $a['submission']['id'] <=> (int) $b['submission']['id'];
        }

        return $a['total_points'] > $b['total_points'] ? -1 : 1;
    });

    return $report;
}

/** Индекс столбца 0=A в буквы Excel (A, B, …, Z, AA, …). */
function contest_stats_xlsx_col(int $zeroBased): string
{
    $s = '';
    $n = $zeroBased;
    while ($n >= 0) {
        $s = chr(ord('A') + ($n % 26)) . $s;
        $n = intdiv($n, 26) - 1;
    }

    return $s;
}

/**
 * Лист «Экспертный лист»: матрица этапов/критериев × участников, данные из оценок одного эксперта.
 *
 * @return array{rows: list<array<int, string|int|float|null|array{v: mixed, s?: int}>>, merges: list<string>}
 */
function contest_stats_expert_sheet_matrix(
    PDO $pdo,
    int $contestId,
    int $expertId,
    array $expertUser,
    string $contestTitle
): array {
    $contestName = $contestTitle !== '' ? $contestTitle : 'Конкурс';

    $pStmt = $pdo->prepare(
        'SELECT DISTINCT u.id, u.email, u.first_name, u.last_name, u.middle_name
         FROM evaluations e
         INNER JOIN submissions s ON s.id = e.submission_id
         INNER JOIN users u ON u.id = s.user_id
         WHERE s.contest_id = :cid AND e.expert_id = :eid
         ORDER BY u.last_name ASC, u.first_name ASC, u.id ASC'
    );
    $pStmt->execute(['cid' => $contestId, 'eid' => $expertId]);
    /** @var list<array<string, mixed>> */
    $participants = $pStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $n = count($participants);
    $ncols = max(3, 3 + $n);
    $fill = static function () use ($ncols): array {
        return array_fill(0, $ncols, '');
    };

    $firstWorkTitle = [];
    $wtStmt = $pdo->prepare(
        'SELECT s.user_id, s.title
         FROM submissions s
         INNER JOIN stages st ON st.id = s.stage_id
         WHERE s.contest_id = :cid
         ORDER BY s.user_id ASC, st.sort_order ASC, s.id ASC'
    );
    $wtStmt->execute(['cid' => $contestId]);
    foreach ($wtStmt->fetchAll(PDO::FETCH_ASSOC) as $wr) {
        $uid = (int) $wr['user_id'];
        if (!isset($firstWorkTitle[$uid])) {
            $firstWorkTitle[$uid] = (string) $wr['title'];
        }
    }

    $byUserStage = [];
    $subStmt = $pdo->prepare(
        'SELECT s.id, s.user_id, s.stage_id, s.title
         FROM submissions s
         INNER JOIN stages st ON st.id = s.stage_id
         WHERE s.contest_id = :cid
         ORDER BY st.sort_order ASC, s.id ASC'
    );
    $subStmt->execute(['cid' => $contestId]);
    foreach ($subStmt->fetchAll(PDO::FETCH_ASSOC) as $sr) {
        $key = (int) $sr['user_id'] . '_' . (int) $sr['stage_id'];
        if (!isset($byUserStage[$key])) {
            $byUserStage[$key] = [
                'id' => (int) $sr['id'],
                'title' => (string) $sr['title'],
            ];
        }
    }

    /** @var array<int, array<int, int>> */
    $evalBySubCrit = [];
    $evStmt = $pdo->prepare(
        'SELECT e.criterion_id, e.points, e.submission_id
         FROM evaluations e
         INNER JOIN submissions s ON s.id = e.submission_id
         WHERE s.contest_id = :cid AND e.expert_id = :eid'
    );
    $evStmt->execute(['cid' => $contestId, 'eid' => $expertId]);
    foreach ($evStmt->fetchAll(PDO::FETCH_ASSOC) as $er) {
        $sid = (int) $er['submission_id'];
        $cid = (int) $er['criterion_id'];
        $evalBySubCrit[$sid][$cid] = (int) $er['points'];
    }

    $stStages = $pdo->prepare('SELECT * FROM stages WHERE contest_id = ? ORDER BY sort_order ASC, id ASC');
    $stStages->execute([$contestId]);
    $stages = $stStages->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $rows = [];
    $merges = [];
    $sums = array_fill(0, $n, 0);
    $lastColLetter = contest_stats_xlsx_col($ncols - 1);

    $rows[] = array_replace($fill(), [0 => ['v' => 'Экспертный лист', 's' => 3]]);
    $merges[] = 'A1:' . $lastColLetter . '1';

    $rows[] = array_replace($fill(), [0 => ['v' => $contestName, 's' => 4]]);
    $merges[] = 'A2:' . $lastColLetter . '2';

    $rows[] = $fill();

    $hdrTop = $fill();
    $hdrTop[0] = ['v' => 'Этап', 's' => 5];
    $hdrTop[1] = ['v' => 'Критерий', 's' => 5];
    $hdrTop[2] = ['v' => 'Вес', 's' => 5];
    if ($n > 0) {
        $hdrTop[3] = ['v' => 'Участники', 's' => 5];
        $merges[] = 'D4:' . $lastColLetter . '4';
    }
    $rows[] = $hdrTop;

    $hdrNames = $fill();
    for ($i = 0; $i < $n; $i++) {
        $hdrNames[3 + $i] = ['v' => user_display_name($participants[$i]), 's' => 5];
    }
    $rows[] = $hdrNames;

    $hdrWorks = $fill();
    for ($i = 0; $i < $n; $i++) {
        $uid = (int) $participants[$i]['id'];
        $hdrWorks[3 + $i] = ['v' => (string) ($firstWorkTitle[$uid] ?? ''), 's' => 2];
    }
    $rows[] = $hdrWorks;

    foreach ($stages as $stage) {
        $stageId = (int) $stage['id'];
        $stageTitle = trim((string) ($stage['title'] ?? '')) ?: 'Этап';
        $criteriaRows = contest_stage_criteria_ordered($pdo, $stageId);

        $stageBlockStartRow = count($rows) + 1;
        $shownGroups = [];

        if ($criteriaRows === []) {
            $emptyRow = $fill();
            $emptyRow[0] = ['v' => $stageTitle, 's' => 1];
            $emptyRow[1] = '—';
            $rows[] = $emptyRow;
        } else {
            foreach ($criteriaRows as $cr) {
                $critId = (int) $cr['id'];
                $gt = trim((string) ($cr['group_title'] ?? ''));

                if ($gt !== '' && !isset($shownGroups[$gt])) {
                    $shownGroups[$gt] = true;
                    $gr = $fill();
                    $gr[1] = ['v' => 'Группа критериев: ' . $gt, 's' => 1];
                    $rows[] = $gr;
                    $rn = count($rows);
                    $merges[] = 'B' . $rn . ':C' . $rn;
                }

                $dataRow = $fill();
                $dataRow[1] = (string) ($cr['name'] ?? '');
                $rawW = (int) ($cr['weight'] ?? 100);
                $weightForCalc = $rawW === 0 ? 100 : $rawW;
                $dataRow[2] = $rawW;

                for ($pi = 0; $pi < $n; $pi++) {
                    $uid = (int) $participants[$pi]['id'];
                    $key = $uid . '_' . $stageId;
                    $pts = null;
                    if (isset($byUserStage[$key], $evalBySubCrit[$byUserStage[$key]['id']][$critId])) {
                        $pts = $evalBySubCrit[$byUserStage[$key]['id']][$critId];
                    }
                    if ($pts !== null) {
                        $dataRow[3 + $pi] = $pts;
                        $sums[$pi] += $pts * ($weightForCalc / 100.0);
                    }
                }

                $rows[] = $dataRow;
            }
        }

        $stageEndRow = count($rows);
        if ($stageEndRow >= $stageBlockStartRow) {
            $rows[$stageBlockStartRow - 1][0] = ['v' => $stageTitle, 's' => 1];
            if ($stageEndRow > $stageBlockStartRow) {
                $merges[] = 'A' . $stageBlockStartRow . ':A' . $stageEndRow;
            }
        }
    }

    $tot = $fill();
    $tot[1] = ['v' => 'Итого', 's' => 1];
    for ($i = 0; $i < $n; $i++) {
        $tot[3 + $i] = round($sums[$i], 4);
    }
    $rows[] = $tot;

    $rows[] = $fill();
    $expertName = user_display_name($expertUser);
    $sig = $fill();
    $sig[0] = 'Подпись: _____________________ (' . $expertName . ')';
    $rows[] = $sig;
    $rnSig = count($rows);
    $merges[] = 'A' . $rnSig . ':' . $lastColLetter . $rnSig;

    $rows[] = $fill();
    $dRow = $fill();
    $dRow[0] = 'Дата: _____________________';
    $rows[] = $dRow;

    $posRow = $fill();
    $posRow[0] = 'Должность: _____________________';
    $rows[] = $posRow;

    return ['rows' => $rows, 'merges' => $merges];
}

/**
 * Сборка книги Excel со статистикой конкурса (те же листы, что при скачивании).
 *
 * @return \SimpleXlsx
 */
function contest_stats_build_simple_xlsx(PDO $pdo, array $contest)
{
    require_once __DIR__ . '/SimpleXlsx.php';

    $contestId = (int) $contest['id'];
    $requireAdmission = (int) ($contest['require_explicit_stage_admission'] ?? 0) === 1;
    $ctx = contest_stats_build_context($pdo, $contest);

    $sheet1 = [];
    $sheet1[] = ['Участник', 'Этап', 'Эксперт', 'Критерий', 'Вес', 'Баллы', 'Регион', 'Учебное заведение', 'Всего работ', 'Всего баллов'];
    foreach ($ctx['detailed_participants'] as $p) {
        $u = $p['user'];
        $region = (string) ($u['region'] ?? '');
        $school = (string) ($u['school'] ?? '');
        foreach ($p['stages'] as $stageData) {
            $stageTitle = (string) $stageData['stage']['title'];
            $evals = $stageData['evaluations'];
            if ($evals === []) {
                $sheet1[] = [
                    user_display_name($u),
                    $stageTitle,
                    '-',
                    '-',
                    '-',
                    '-',
                    $region !== '' ? $region : '-',
                    $school !== '' ? $school : '-',
                    $p['works_count'],
                    round((float) $p['total_points'], 4),
                ];
            } else {
                foreach ($evals as $eval) {
                    $sheet1[] = [
                        user_display_name($u),
                        $stageTitle,
                        user_display_name($eval['expert']),
                        $eval['criterion']['name'],
                        (int) ($eval['criterion']['weight'] ?? 100),
                        $eval['points'],
                        $region !== '' ? $region : '-',
                        $school !== '' ? $school : '-',
                        $p['works_count'],
                        round((float) $p['total_points'], 4),
                    ];
                }
            }
        }
    }

    $sheet2 = [];
    $sheet2[] = ['Эксперт', 'Участник', 'Этап', 'Работа', 'Критерий', 'Вес', 'Баллы', 'Дата оценки'];
    $evAll = $pdo->prepare(
        'SELECT e.points, e.evaluated_at, s.title AS sub_title, st.title AS stage_title,
                pu.email AS part_email, pu.first_name AS pf, pu.last_name AS pl,
                eu.email AS exp_email, eu.first_name AS ef, eu.last_name AS el,
                c.name AS criterion_name, c.weight AS criterion_weight
         FROM evaluations e
         JOIN submissions s ON s.id = e.submission_id
         JOIN stages st ON st.id = s.stage_id
         JOIN users pu ON pu.id = s.user_id
         JOIN users eu ON eu.id = e.expert_id
         JOIN criteria c ON c.id = e.criterion_id
         WHERE s.contest_id = :cid
         ORDER BY eu.email, s.id'
    );
    $evAll->execute(['cid' => $contestId]);
    foreach ($evAll->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sheet2[] = [
            user_display_name([
                'email' => $row['exp_email'],
                'first_name' => $row['ef'],
                'last_name' => $row['el'],
                'middle_name' => null,
            ]),
            user_display_name([
                'email' => $row['part_email'],
                'first_name' => $row['pf'],
                'last_name' => $row['pl'],
                'middle_name' => null,
            ]),
            $row['stage_title'],
            $row['sub_title'],
            $row['criterion_name'],
            (int) ($row['criterion_weight'] ?? 100),
            $row['points'],
            $row['evaluated_at'] ? date('d.m.Y H:i', strtotime((string) $row['evaluated_at'])) : '',
        ];
    }

    $progressRows = [];
    $header = ['Участник', 'Согласие', 'Пояснение / дата'];
    if ($requireAdmission) {
        $header[] = 'Допуск до этапа №';
    }
    foreach ($ctx['stages_ordered'] as $st) {
        $o = (int) $st['sort_order'];
        $header[] = 'Э' . $o . ': работа';
        if ($requireAdmission) {
            $header[] = 'Э' . $o . ': допуск';
        }
    }
    $progressRows[] = $header;
    foreach ($ctx['participant_consent_progress'] as $row) {
        $r = [
            user_display_name($row['user']),
            $row['consent_label'],
            $row['consent_extra'],
        ];
        if ($requireAdmission) {
            $r[] = $row['admitted_max_stage_order'];
        }
        foreach ($row['stage_cells'] as $cell) {
            $r[] = $cell['has_submission'] ? 'да' : 'нет';
            if ($requireAdmission) {
                $r[] = $cell['admitted_here'] ? 'да' : 'нет';
            }
        }
        $progressRows[] = $r;
    }

    $xlsx = new SimpleXlsx();
    $xlsx->addSheet('Статистика участников', $sheet1);
    $xlsx->addSheet('Статистика экспертов', $sheet2);
    $xlsx->addSheet('Согласия и этапы', $progressRows);

    $contestTitleForSheets = trim((string) ($contest['title'] ?? ''));

    $expertIdsStmt = $pdo->prepare(
        'SELECT DISTINCT e.expert_id FROM evaluations e
         JOIN submissions s ON s.id = e.submission_id
         WHERE s.contest_id = :cid'
    );
    $expertIdsStmt->execute(['cid' => $contestId]);
    $expertIds = array_map('intval', array_column($expertIdsStmt->fetchAll(PDO::FETCH_ASSOC), 'expert_id'));

    foreach ($expertIds as $eid) {
        $uStmt = $pdo->prepare('SELECT id, email, first_name, last_name, middle_name FROM users WHERE id = ?');
        $uStmt->execute([$eid]);
        $expertUser = $uStmt->fetch(PDO::FETCH_ASSOC);
        if (!$expertUser) {
            continue;
        }
        $name = user_display_name($expertUser);
        $built = contest_stats_expert_sheet_matrix(
            $pdo,
            $contestId,
            $eid,
            $expertUser,
            $contestTitleForSheets
        );
        $xlsx->addSheet(
            mb_substr(str_replace(['\\', '/', '*', '?', ':', '[', ']'], '_', $name), 0, 31),
            $built['rows'],
            $built['merges']
        );
    }

    return $xlsx;
}

/**
 * Выгрузка Excel со статистикой (несколько листов, как в Django).
 */
function contest_stats_send_xlsx(PDO $pdo, array $contest): void
{
    $xlsx = contest_stats_build_simple_xlsx($pdo, $contest);
    $xlsx->sendDownload('contest_' . (int) $contest['id'] . '_stats.xlsx');
}

function contest_report_send_xlsx(PDO $pdo, array $contest): void
{
    require_once __DIR__ . '/SimpleXlsx.php';
    $contestId = (int) $contest['id'];
    $rows = [];
    $rows[] = ['ФИО участника', 'Email участника', 'Работа', 'Сумма баллов (среднее по экспертам)'];
    $stmt = $pdo->prepare(
        'SELECT s.*, u.email, u.first_name, u.last_name, u.middle_name
         FROM submissions s
         JOIN users u ON u.id = s.user_id
         WHERE s.contest_id = :cid
         ORDER BY s.id'
    );
    $stmt->execute(['cid' => $contestId]);
    $means = contest_submission_score_means($pdo, $contestId);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $sub) {
        $fio = user_display_name($sub);
        $rows[] = [
            $fio,
            $sub['email'],
            $sub['title'],
            round(submission_score_mean($means, (int) $sub['id']), 4),
        ];
    }
    $x = new SimpleXlsx();
    $x->addSheet('Баллы', $rows);
    $x->sendDownload('otchet_konkurs_' . $contestId . '.xlsx');
}

function award_calculated_place_for_participant(PDO $pdo, int $contestId, int $participantPk): ?int
{
    foreach (compute_ranked_participants($pdo, $contestId) as $r) {
        if ((int) $r['participant']['id'] === $participantPk) {
            return $r['place'];
        }
    }

    return null;
}

/** Конкурсы, в которых пользователь может назначать итоговые места (админ — все, модератор — свои). */
function contests_eligible_for_awards(PDO $pdo, int $userId): array
{
    if (has_role($pdo, $userId, 'admin')) {
        return $pdo->query('SELECT id, title FROM contests ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
    }
    $stmt = $pdo->prepare(
        'SELECT c.id, c.title FROM contests c
         INNER JOIN contest_moderators cm ON cm.contest_id = c.id AND cm.user_id = :uid
         ORDER BY c.id DESC'
    );
    $stmt->execute(['uid' => $userId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Сохранение записи Award с логикой is_manual_override, как в Django _save_award_from_moderator_form.
 *
 * @return array{0: bool, 1: list<string>}
 */
function moderator_save_award(
    PDO $pdo,
    int $contestId,
    int $participantPk,
    int $place,
    ?string $awardDescription,
    ?int $existingAwardId,
    bool $releaseManual
): array {
    if ($place < 1 || $place > 9999) {
        return [false, ['Укажите место от 1 до 9999']];
    }
    $chk = $pdo->prepare(
        'SELECT id FROM contest_participants WHERE id = :id AND contest_id = :cid LIMIT 1'
    );
    $chk->execute(['id' => $participantPk, 'cid' => $contestId]);
    if (!$chk->fetch()) {
        return [false, ['Выбранный участник не относится к этому конкурсу']];
    }
    $desc = trim((string) ($awardDescription ?? ''));
    $descVal = $desc === '' ? null : $desc;

    if ($existingAwardId === null) {
        $dup = $pdo->prepare(
            'SELECT id FROM awards WHERE participant_id = :pid AND contest_id = :cid LIMIT 1'
        );
        $dup->execute(['pid' => $participantPk, 'cid' => $contestId]);
        if ($dup->fetch()) {
            return [false, [
                'У этого участника в этом конкурсе уже указано место в итогах. '
                . 'Откройте существующую запись для редактирования.',
            ]];
        }
        $ins = $pdo->prepare(
            'INSERT INTO awards (participant_id, contest_id, place, award_description, is_manual_override)
             VALUES (:pid, :cid, :place, :descr, 1)'
        );
        $ins->execute([
            'pid' => $participantPk,
            'cid' => $contestId,
            'place' => $place,
            'descr' => $descVal,
        ]);

        return [true, []];
    }

    $ex = $pdo->prepare('SELECT * FROM awards WHERE id = :id AND contest_id = :cid LIMIT 1');
    $ex->execute(['id' => $existingAwardId, 'cid' => $contestId]);
    $row = $ex->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return [false, ['Запись не найдена']];
    }
    if ((int) $row['participant_id'] !== $participantPk) {
        return [false, ['Несоответствие данных участника']];
    }

    $wasManual = (int) $row['is_manual_override'] === 1;

    if ($releaseManual && $wasManual) {
        $calc = award_calculated_place_for_participant($pdo, $contestId, $participantPk);
        $finalPlace = $calc ?? $place;
        $up = $pdo->prepare(
            'UPDATE awards SET place = :place, award_description = :descr, is_manual_override = 0 WHERE id = :id'
        );
        $up->execute(['place' => $finalPlace, 'descr' => $descVal, 'id' => $existingAwardId]);

        return [true, []];
    }

    if ($wasManual) {
        $manual = 1;
    } else {
        $calc = award_calculated_place_for_participant($pdo, $contestId, $participantPk);
        $manual = ($calc !== null && $calc !== $place) ? 1 : 0;
    }
    $up = $pdo->prepare(
        'UPDATE awards SET place = :place, award_description = :descr, is_manual_override = :man WHERE id = :id'
    );
    $up->execute([
        'place' => $place,
        'descr' => $descVal,
        'man' => $manual,
        'id' => $existingAwardId,
    ]);

    return [true, []];
}

/**
 * Удаление этапа и связанных данных (в схеме нет каскадных FK). Файлы работ и инструкции удаляются с диска.
 *
 * @param list<string>|null $deferInstructionFiles при $useOwnTransaction = false: пути к файлу инструкции этапа
 *        копятся сюда, чтобы вызывающий удалил их с диска после commit внешней транзакции
 */
function delete_stage_cascade(
    PDO $pdo,
    int $stageId,
    bool $useOwnTransaction = true,
    ?array &$deferInstructionFiles = null
): void {
    $stPath = $pdo->prepare('SELECT instruction_file_path FROM stages WHERE id = :sid LIMIT 1');
    $stPath->execute(['sid' => $stageId]);
    $instr = trim((string) ($stPath->fetchColumn() ?: ''));

    $doSql = static function () use ($pdo, $stageId): void {
        $subStmt = $pdo->prepare('SELECT id FROM submissions WHERE stage_id = :sid');
        $subStmt->execute(['sid' => $stageId]);
        foreach (array_map('intval', array_column($subStmt->fetchAll(PDO::FETCH_ASSOC), 'id')) as $subId) {
            if ($subId > 0) {
                iro_delete_submission_cascade($pdo, $subId);
            }
        }
        $pdo->prepare('DELETE FROM criteria WHERE stage_id = :sid')->execute(['sid' => $stageId]);
        $pdo->prepare('DELETE FROM criterion_groups WHERE stage_id = :sid')->execute(['sid' => $stageId]);
        $pdo->prepare('DELETE FROM stage_attachment_fields WHERE stage_id = :sid')->execute(['sid' => $stageId]);
        $pdo->prepare('DELETE FROM stage_experts WHERE stage_id = :sid')->execute(['sid' => $stageId]);
        $pdo->prepare('DELETE FROM stages WHERE id = :sid')->execute(['sid' => $stageId]);
    };

    if ($useOwnTransaction) {
        $pdo->beginTransaction();
        try {
            $doSql();
            $pdo->commit();
            iro_delete_public_upload($instr);
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    } else {
        $doSql();
        if ($deferInstructionFiles !== null) {
            if ($instr !== '') {
                $deferInstructionFiles[] = $instr;
            }
        } else {
            iro_delete_public_upload($instr);
        }
    }
}

/** @return list<array<string, mixed>> */
function contest_stage_criterion_groups_list(PDO $pdo, int $stageId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, stage_id, title, sort_order FROM criterion_groups WHERE stage_id = :sid ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute(['sid' => $stageId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Критерии этапа с учётом групп и порядка (для модератора и эксперта).
 *
 * @return list<array<string, mixed>>
 */
function contest_stage_criteria_ordered(PDO $pdo, int $stageId): array
{
    $stmt = $pdo->prepare(
        'SELECT c.*, cg.title AS group_title, cg.sort_order AS group_sort
         FROM criteria c
         LEFT JOIN criterion_groups cg ON cg.id = c.group_id
         WHERE c.stage_id = :sid
         ORDER BY COALESCE(cg.sort_order, 999999) ASC,
                  COALESCE(cg.id, 0) ASC,
                  c.sort_order ASC,
                  c.id ASC'
    );
    $stmt->execute(['sid' => $stageId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Назначение экспертов (тело алгоритма). Без транзакции — вызывать уже внутри блокировки этапа или обёртки.
 */
function contest_assign_experts_to_submission_impl(PDO $pdo, int $submissionId, array $stage): void
{
    $stageId = (int) ($stage['id'] ?? 0);
    if ($stageId <= 0) {
        return;
    }
    if ((string) ($stage['evaluation_mode'] ?? 'all') !== 'assigned') {
        return;
    }
    $expertIdsStmt = $pdo->prepare('SELECT user_id FROM stage_experts WHERE stage_id = :stage_id ORDER BY user_id');
    $expertIdsStmt->execute(['stage_id' => $stageId]);
    $expertIds = user_ids_excluding_site_admins(
        $pdo,
        array_map('intval', array_column($expertIdsStmt->fetchAll(PDO::FETCH_ASSOC), 'user_id'))
    );
    $authorStmt = $pdo->prepare('SELECT user_id FROM submissions WHERE id = :id LIMIT 1');
    $authorStmt->execute(['id' => $submissionId]);
    $authorId = (int) ($authorStmt->fetchColumn() ?: 0);
    $expertIds = array_values(array_filter($expertIds, static fn (int $eid): bool => $eid !== $authorId));
    if ($expertIds === []) {
        return;
    }
    $nExp = count($expertIds);
    $k = min(max((int) ($stage['experts_per_submission'] ?? 2), 1), $nExp);

    $counts = [];
    foreach ($expertIds as $eid) {
        $counts[$eid] = 0;
    }
    $cntStmt = $pdo->prepare(
        'SELECT sae.user_id, COUNT(*) AS cnt
         FROM submission_assigned_experts sae
         INNER JOIN submissions s ON s.id = sae.submission_id
         WHERE s.stage_id = :sid AND s.id <> :exclude_id
         GROUP BY sae.user_id'
    );
    $cntStmt->execute(['sid' => $stageId, 'exclude_id' => $submissionId]);
    foreach ($cntStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $uid = (int) ($row['user_id'] ?? 0);
        if (isset($counts[$uid])) {
            $counts[$uid] = (int) ($row['cnt'] ?? 0);
        }
    }

    $chosen = [];
    for ($pick = 0; $pick < $k; $pick++) {
        $bestId = null;
        $bestCnt = PHP_INT_MAX;
        foreach ($expertIds as $eid) {
            if (in_array($eid, $chosen, true)) {
                continue;
            }
            $c = $counts[$eid];
            if ($c < $bestCnt || ($c === $bestCnt && ($bestId === null || $eid < $bestId))) {
                $bestCnt = $c;
                $bestId = $eid;
            }
        }
        if ($bestId === null) {
            break;
        }
        $chosen[] = $bestId;
        $counts[$bestId]++;
    }

    $assign = $pdo->prepare(
        'INSERT IGNORE INTO submission_assigned_experts (submission_id, user_id) VALUES (:submission_id, :user_id)'
    );
    foreach ($chosen as $expertId) {
        $assign->execute(['submission_id' => $submissionId, 'user_id' => $expertId]);
    }
}

/**
 * Назначение экспертов на работу в режиме «несколько экспертов на каждую работу».
 * Распределение по минимальной текущей нагрузке на этапе (жадно). Транзакция + блокировка строки этапа,
 * чтобы параллельные подачи работ не давали гонок при подсчёте нагрузки.
 */
function contest_assign_experts_to_submission(PDO $pdo, int $submissionId, array $stage): void
{
    $stageId = (int) ($stage['id'] ?? 0);
    if ($stageId <= 0 || (string) ($stage['evaluation_mode'] ?? 'all') !== 'assigned') {
        return;
    }
    $pdo->beginTransaction();
    try {
        $lk = $pdo->prepare('SELECT id FROM stages WHERE id = :id FOR UPDATE');
        $lk->execute(['id' => $stageId]);
        contest_assign_experts_to_submission_impl($pdo, $submissionId, $stage);
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Пересоздаёт назначения экспертов по всем работам этапа (режим assigned), тот же алгоритм, что при подаче работы.
 *
 * @return array{ok: bool, updated: int, message?: string}
 */
function contest_redistribute_assigned_experts_for_stage(PDO $pdo, int $stageId, int $contestId): array
{
    $stStmt = $pdo->prepare('SELECT * FROM stages WHERE id = :id AND contest_id = :cid LIMIT 1');
    $stStmt->execute(['id' => $stageId, 'cid' => $contestId]);
    $stage = $stStmt->fetch(PDO::FETCH_ASSOC);
    if (!$stage) {
        return ['ok' => false, 'updated' => 0, 'message' => 'Этап не найден'];
    }
    if ((string) ($stage['evaluation_mode'] ?? 'all') !== 'assigned') {
        return ['ok' => false, 'updated' => 0, 'message' => 'Перераспределение доступно только в режиме «несколько экспертов на работу».'];
    }
    $expertIdsStmt = $pdo->prepare('SELECT user_id FROM stage_experts WHERE stage_id = :stage_id ORDER BY user_id');
    $expertIdsStmt->execute(['stage_id' => $stageId]);
    $expertIds = user_ids_excluding_site_admins(
        $pdo,
        array_map('intval', array_column($expertIdsStmt->fetchAll(PDO::FETCH_ASSOC), 'user_id'))
    );
    if ($expertIds === []) {
        return ['ok' => false, 'updated' => 0, 'message' => 'Нет экспертов этапа для автоматического назначения (добавьте их в настройках этапа).'];
    }

    $pdo->beginTransaction();
    try {
        $lk = $pdo->prepare('SELECT id FROM stages WHERE id = :id FOR UPDATE');
        $lk->execute(['id' => $stageId]);
        $subsStmt = $pdo->prepare('SELECT id FROM submissions WHERE stage_id = :sid ORDER BY id');
        $subsStmt->execute(['sid' => $stageId]);
        $subIds = array_map(static fn ($row): int => (int) ($row['id'] ?? 0), $subsStmt->fetchAll(PDO::FETCH_ASSOC));
        $del = $pdo->prepare('DELETE FROM submission_assigned_experts WHERE submission_id = :sid');
        $updated = 0;
        foreach ($subIds as $subId) {
            if ($subId <= 0) {
                continue;
            }
            $del->execute(['sid' => $subId]);
            contest_assign_experts_to_submission_impl($pdo, $subId, $stage);
            $updated++;
        }
        $pdo->commit();

        return ['ok' => true, 'updated' => $updated];
    } catch (\Throwable $e) {
        $pdo->rollBack();

        return ['ok' => false, 'updated' => 0, 'message' => 'Не удалось перераспределить экспертов.'];
    }
}

/** Путь-заглушка для записи работы очного этапа (файл в public). */
function contest_in_person_submission_placeholder_path(): string
{
    return '/assets/offline-stage-placeholder.txt';
}

/**
 * Создаёт для всех участников конкурса записи работ на очном этапе (если ещё нет). Без уведомлений «новая работа».
 *
 * @return array{ok: bool, created: int, message?: string}
 */
function contest_seed_in_person_submissions(PDO $pdo, int $stageId, int $contestId): array
{
    $stStmt = $pdo->prepare('SELECT * FROM stages WHERE id = :id AND contest_id = :cid LIMIT 1');
    $stStmt->execute(['id' => $stageId, 'cid' => $contestId]);
    $stage = $stStmt->fetch(PDO::FETCH_ASSOC);
    if (!$stage) {
        return ['ok' => false, 'created' => 0, 'message' => 'Этап не найден'];
    }
    if (!stage_is_in_person($stage)) {
        return ['ok' => false, 'created' => 0, 'message' => 'Этап не отмечен как очный'];
    }
    $gateStmt = $pdo->prepare('SELECT require_explicit_stage_admission FROM contests WHERE id = :cid LIMIT 1');
    $gateStmt->execute(['cid' => $contestId]);
    $gateRow = $gateStmt->fetch(PDO::FETCH_ASSOC);
    $requireExplicitAdmission = $gateRow !== false && (int) ($gateRow['require_explicit_stage_admission'] ?? 0) === 1;
    $stageOrder = (int) ($stage['sort_order'] ?? 0);

    $placeholder = contest_in_person_submission_placeholder_path();
    $title = 'Очный этап';
    if ($requireExplicitAdmission) {
        $cpStmt = $pdo->prepare(
            'SELECT DISTINCT user_id FROM contest_participants
             WHERE contest_id = :cid AND admitted_max_stage_order >= :so'
        );
        $cpStmt->execute(['cid' => $contestId, 'so' => $stageOrder]);
    } else {
        $cpStmt = $pdo->prepare('SELECT DISTINCT user_id FROM contest_participants WHERE contest_id = :cid');
        $cpStmt->execute(['cid' => $contestId]);
    }
    $created = 0;
    $ins = $pdo->prepare(
        'INSERT INTO submissions (user_id, contest_id, stage_id, title, file_path, work_link)
         VALUES (:u, :c, :st, :title, :fp, "")'
    );
    $exists = $pdo->prepare('SELECT 1 FROM submissions WHERE user_id = :u AND stage_id = :sid LIMIT 1');
    $pdo->beginTransaction();
    try {
        $pdo->prepare('SELECT id FROM stages WHERE id = :id FOR UPDATE')->execute(['id' => $stageId]);
        foreach ($cpStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $uid = (int) $row['user_id'];
            if ($uid <= 0) {
                continue;
            }
            $exists->execute(['u' => $uid, 'sid' => $stageId]);
            if ($exists->fetchColumn()) {
                continue;
            }
            $ins->execute([
                'u' => $uid,
                'c' => $contestId,
                'st' => $stageId,
                'title' => $title,
                'fp' => $placeholder,
            ]);
            $subId = (int) $pdo->lastInsertId();
            if ($subId > 0) {
                contest_assign_experts_to_submission_impl($pdo, $subId, $stage);
                $created++;
            }
        }
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return ['ok' => true, 'created' => $created];
}
