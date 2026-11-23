<?php

declare(strict_types=1);

/**
 * Внутрисайтовые уведомления (колокольчик), аналог IRO InAppNotification / notifications_helpers.
 */

function iro_safe_internal_link(string $path): string
{
    $path = trim($path);
    if ($path === '' || !str_starts_with($path, '/') || str_starts_with($path, '//')) {
        return '/';
    }

    return mb_substr($path, 0, 500);
}

function notify_user(PDO $pdo, int $userId, string $title, string $body = '', string $linkPath = '/'): void
{
    if ($userId <= 0) {
        return;
    }
    $stmt = $pdo->prepare(
        'INSERT INTO in_app_notifications (user_id, title, body, link) VALUES (:user_id, :title, :body, :link)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'title' => mb_substr($title !== '' ? $title : 'Уведомление', 0, 200),
        'body' => $body,
        'link' => iro_safe_internal_link($linkPath),
    ]);
}

/** Модераторы конкурса и пользователи с ролью admin. */
function notify_contest_staff(PDO $pdo, int $contestId, string $title, string $body = '', string $linkPath = '/'): void
{
    $link = iro_safe_internal_link($linkPath);
    $titleS = mb_substr($title !== '' ? $title : 'Уведомление', 0, 200);
    $stmt = $pdo->prepare(
        'SELECT DISTINCT u.id FROM users u
         WHERE u.id IN (SELECT user_id FROM contest_moderators WHERE contest_id = :cid)
            OR u.id IN (
                SELECT ur.user_id FROM user_roles ur
                INNER JOIN system_roles sr ON sr.id = ur.role_id AND sr.slug = \'admin\'
            )'
    );
    $stmt->execute(['cid' => $contestId]);
    $ins = $pdo->prepare(
        'INSERT INTO in_app_notifications (user_id, title, body, link) VALUES (:user_id, :title, :body, :link)'
    );
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $uid = (int) ($row['id'] ?? 0);
        if ($uid <= 0) {
            continue;
        }
        $ins->execute([
            'user_id' => $uid,
            'title' => $titleS,
            'body' => $body,
            'link' => $link,
        ]);
    }
}

/** Только модераторы конкурса из contest_moderators (без глобальных администраторов сайта). */
function notify_contest_moderators(PDO $pdo, int $contestId, string $title, string $body = '', string $linkPath = '/'): void
{
    $link = iro_safe_internal_link($linkPath);
    $titleS = mb_substr($title !== '' ? $title : 'Уведомление', 0, 200);
    $stmt = $pdo->prepare(
        'SELECT user_id AS id FROM contest_moderators WHERE contest_id = :cid'
    );
    $stmt->execute(['cid' => $contestId]);
    $ins = $pdo->prepare(
        'INSERT INTO in_app_notifications (user_id, title, body, link) VALUES (:user_id, :title, :body, :link)'
    );
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $uid = (int) ($row['id'] ?? 0);
        if ($uid <= 0) {
            continue;
        }
        $ins->execute([
            'user_id' => $uid,
            'title' => $titleS,
            'body' => $body,
            'link' => $link,
        ]);
    }
}

/**
 * Эксперты, которым имеет смысл слать уведомление о работе (без автора).
 *
 * @return list<int>
 */
function submission_expert_notify_user_ids(PDO $pdo, int $submissionId, int $authorUserId): array
{
    $stmt = $pdo->prepare(
        'SELECT s.stage_id, st.evaluation_mode
         FROM submissions s
         JOIN stages st ON st.id = s.stage_id
         WHERE s.id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $submissionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return [];
    }
    $stageId = (int) $row['stage_id'];
    $mode = (string) ($row['evaluation_mode'] ?? 'all');
    if ($mode === 'assigned') {
        $q = $pdo->prepare('SELECT user_id FROM submission_assigned_experts WHERE submission_id = :sid');
        $q->execute(['sid' => $submissionId]);
        $ids = array_map('intval', array_column($q->fetchAll(PDO::FETCH_ASSOC), 'user_id'));
    } else {
        $q = $pdo->prepare('SELECT user_id FROM stage_experts WHERE stage_id = :stid');
        $q->execute(['stid' => $stageId]);
        $ids = array_map('intval', array_column($q->fetchAll(PDO::FETCH_ASSOC), 'user_id'));
    }

    $ids = user_ids_excluding_site_admins($pdo, $ids);

    return array_values(array_filter($ids, static fn (int $id): bool => $id > 0 && $id !== $authorUserId));
}

function notify_experts_new_submission(PDO $pdo, int $submissionId): void
{
    $stmt = $pdo->prepare(
        'SELECT s.id, s.title, s.user_id, c.title AS contest_title, st.title AS stage_title, u.email AS participant_email
         FROM submissions s
         JOIN stages st ON st.id = s.stage_id
         JOIN contests c ON c.id = s.contest_id
         JOIN users u ON u.id = s.user_id
         WHERE s.id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $submissionId]);
    $sub = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$sub) {
        return;
    }
    $authorId = (int) $sub['user_id'];
    $userIds = submission_expert_notify_user_ids($pdo, $submissionId, $authorId);
    if ($userIds === []) {
        return;
    }
    $cfg = $GLOBALS['config'] ?? null;
    $maxN = is_array($cfg) ? iro_expert_notify_max_recipients($cfg) : 100;
    if (count($userIds) > $maxN) {
        $userIds = array_slice($userIds, 0, $maxN);
    }
    $link = iro_safe_internal_link('/expert/evaluate?submission_id=' . $submissionId);
    $titleS = mb_substr('Новая работа для оценки', 0, 200);
    $body = sprintf(
        'Конкурс «%s», этап «%s»: работа «%s» (автор: %s).',
        (string) $sub['contest_title'],
        (string) $sub['stage_title'],
        (string) $sub['title'],
        (string) $sub['participant_email']
    );
    $ins = $pdo->prepare(
        'INSERT INTO in_app_notifications (user_id, title, body, link) VALUES (:user_id, :title, :body, :link)'
    );
    foreach ($userIds as $eid) {
        $ins->execute(['user_id' => $eid, 'title' => $titleS, 'body' => $body, 'link' => $link]);
    }
}

/** @param list<int>|array<int, int> $newExpertUserIds только вновь назначенные эксперты */
function notify_experts_newly_assigned(PDO $pdo, int $submissionId, array $newExpertUserIds): void
{
    $newExpertUserIds = array_values(array_unique(array_map('intval', $newExpertUserIds)));
    if ($newExpertUserIds === []) {
        return;
    }
    $stmt = $pdo->prepare(
        'SELECT s.title, s.user_id, c.title AS contest_title, st.title AS stage_title, u.email AS participant_email
         FROM submissions s
         JOIN stages st ON st.id = s.stage_id
         JOIN contests c ON c.id = s.contest_id
         JOIN users u ON u.id = s.user_id
         WHERE s.id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $submissionId]);
    $sub = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$sub) {
        return;
    }
    $authorId = (int) $sub['user_id'];
    $link = iro_safe_internal_link('/expert/evaluate?submission_id=' . $submissionId);
    $titleS = mb_substr('Вас назначили оценивать работу', 0, 200);
    $body = sprintf(
        'Конкурс «%s», этап «%s»: «%s» (автор: %s).',
        (string) $sub['contest_title'],
        (string) $sub['stage_title'],
        (string) $sub['title'],
        (string) $sub['participant_email']
    );
    $ins = $pdo->prepare(
        'INSERT INTO in_app_notifications (user_id, title, body, link) VALUES (:user_id, :title, :body, :link)'
    );
    foreach ($newExpertUserIds as $eid) {
        if ($eid <= 0 || $eid === $authorId) {
            continue;
        }
        $ins->execute(['user_id' => $eid, 'title' => $titleS, 'body' => $body, 'link' => $link]);
    }
}

/**
 * Удаляет устаревшие уведомления пользователя: прочитанные старше 30 суток, непрочитанные старше 90 суток.
 */
function iro_purge_stale_notifications(PDO $pdo, int $userId): void
{
    if ($userId <= 0) {
        return;
    }
    $pdo->prepare(
        'DELETE FROM in_app_notifications
         WHERE user_id = :uid
           AND (
             (is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY))
             OR (is_read = 0 AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY))
           )'
    )->execute(['uid' => $userId]);
}
