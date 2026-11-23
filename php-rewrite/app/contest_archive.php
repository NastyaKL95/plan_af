<?php

declare(strict_types=1);

/**
 * Перенос конкурса в архив: полная выгрузка статистики в Excel на диск, удаление связанных данных и файлов,
 * в строке contests остаются название, даты, признак архива и путь к сохранённому отчёту.
 */

/** Уведомления, связанные с конкурсом и работами (до удаления записей в БД). */
function contest_archive_notifications_cleanup(PDO $pdo, int $contestId, array $submissionIds): void
{
    $cid = (int) $contestId;
    $patterns = [
        '%contest?id=' . $cid . '%',
        '%contest_id=' . $cid . '%',
        '%/contest/' . $cid . '/%',
    ];
    $delLike = $pdo->prepare('DELETE FROM in_app_notifications WHERE link LIKE ?');
    foreach ($patterns as $pat) {
        $delLike->execute([$pat]);
    }

    $delExact = $pdo->prepare('DELETE FROM in_app_notifications WHERE link = ?');
    foreach ($submissionIds as $sid) {
        $sid = (int) $sid;
        if ($sid > 0) {
            $delExact->execute(['/expert/evaluate?submission_id=' . $sid]);
        }
    }
}

/**
 * Файлы на диске, которые удаляются вручную после транзакции (остальное — в delete_stage_cascade / unlink внутри каскадов).
 *
 * @return list<string>
 */
function contest_archive_collect_contest_level_web_paths(PDO $pdo, int $contestId, array $contestRow): array
{
    $paths = [];
    foreach (['image_path', 'regulations_path'] as $k) {
        $p = trim((string) ($contestRow[$k] ?? ''));
        if ($p !== '') {
            $paths[] = $p;
        }
    }

    $pa = $pdo->prepare('SELECT file_path FROM participant_attachments WHERE contest_id = ?');
    $pa->execute([$contestId]);
    foreach ($pa->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $p = trim((string) ($row['file_path'] ?? ''));
        if ($p !== '') {
            $paths[] = $p;
        }
    }

    $uc = $pdo->prepare('SELECT consent_file_path FROM user_consents WHERE contest_id = ?');
    $uc->execute([$contestId]);
    foreach ($uc->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $p = trim((string) ($row['consent_file_path'] ?? ''));
        if ($p !== '') {
            $paths[] = $p;
        }
    }

    $oldReport = trim((string) ($contestRow['archive_report_path'] ?? ''));
    if ($oldReport !== '') {
        $paths[] = $oldReport;
    }

    return array_values(array_unique($paths));
}

/**
 * @return array{ok: bool, message: string, report_path?: string}
 */
function contest_archive_execute(PDO $pdo, int $contestId): array
{
    $st = $pdo->prepare('SELECT * FROM contests WHERE id = ? LIMIT 1');
    $st->execute([$contestId]);
    $contest = $st->fetch(PDO::FETCH_ASSOC);
    if (!$contest) {
        return ['ok' => false, 'message' => 'Конкурс не найден'];
    }
    if ((int) ($contest['is_external'] ?? 0) === 1) {
        return ['ok' => false, 'message' => 'Архивация конкурса-ссылки не поддерживается'];
    }
    if ((int) ($contest['is_archived'] ?? 0) === 1) {
        return ['ok' => false, 'message' => 'Конкурс уже в архиве'];
    }

    $xlsx = contest_stats_build_simple_xlsx($pdo, $contest);
    $dir = env_path('public/uploads/contest_archive_reports');
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return ['ok' => false, 'message' => 'Не удалось создать каталог для отчёта'];
    }
    $safeName = 'contest_' . $contestId . '_archive_' . date('Y-m-d_His') . '.xlsx';
    $absPath = $dir . DIRECTORY_SEPARATOR . $safeName;
    try {
        $xlsx->writeToFile($absPath);
    } catch (Throwable $e) {
        @unlink($absPath);

        return ['ok' => false, 'message' => 'Не удалось сформировать Excel: ' . $e->getMessage()];
    }
    $webReportPath = '/uploads/contest_archive_reports/' . $safeName;

    $subStmt = $pdo->prepare('SELECT id FROM submissions WHERE contest_id = ?');
    $subStmt->execute([$contestId]);
    $submissionIds = array_map('intval', array_column($subStmt->fetchAll(PDO::FETCH_ASSOC), 'id'));

    $pathsToUnlink = contest_archive_collect_contest_level_web_paths($pdo, $contestId, $contest);
    $pathsToUnlink = array_values(array_filter($pathsToUnlink, static fn ($p): bool => trim((string) $p) !== $webReportPath));

    /** @var list<string> */
    $deferredInstructionFiles = [];

    $pdo->beginTransaction();
    try {
        contest_archive_notifications_cleanup($pdo, $contestId, $submissionIds);

        $pdo->prepare('DELETE FROM awards WHERE contest_id = ?')->execute([$contestId]);
        $pdo->prepare('DELETE FROM user_consents WHERE contest_id = ?')->execute([$contestId]);
        $pdo->prepare('DELETE FROM participant_attachments WHERE contest_id = ?')->execute([$contestId]);
        $pdo->prepare('DELETE FROM contest_participants WHERE contest_id = ?')->execute([$contestId]);

        $stagesStmt = $pdo->prepare('SELECT id FROM stages WHERE contest_id = ? ORDER BY id');
        $stagesStmt->execute([$contestId]);
        foreach (array_map('intval', array_column($stagesStmt->fetchAll(PDO::FETCH_ASSOC), 'id')) as $stageId) {
            if ($stageId > 0) {
                delete_stage_cascade($pdo, $stageId, false, $deferredInstructionFiles);
            }
        }

        $pdo->prepare('DELETE FROM contest_moderators WHERE contest_id = ?')->execute([$contestId]);
        $pdo->prepare('DELETE FROM contest_expert_pool WHERE contest_id = ?')->execute([$contestId]);
        $pdo->prepare('DELETE FROM contest_attachment_fields WHERE contest_id = ?')->execute([$contestId]);

        $pdo->prepare(
            'UPDATE contests SET description = \'\',
                image_path = NULL,
                regulations_path = NULL,
                documents_link = \'\',
                series_id = NULL,
                document_intake_closed = 1,
                is_archived = 1,
                archived_at = CURRENT_TIMESTAMP,
                archive_report_path = :rp,
                status = 4
             WHERE id = :id'
        )->execute(['rp' => $webReportPath, 'id' => $contestId]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        @unlink($absPath);

        return ['ok' => false, 'message' => 'Ошибка при архивации: ' . $e->getMessage()];
    }

    foreach ($pathsToUnlink as $p) {
        iro_delete_public_upload($p);
    }
    foreach ($deferredInstructionFiles as $p) {
        iro_delete_public_upload($p);
    }

    return ['ok' => true, 'message' => 'Конкурс перенесён в архив: данные и файлы удалены, сохранён отчёт Excel.', 'report_path' => $webReportPath];
}
