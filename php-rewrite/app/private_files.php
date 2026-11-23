<?php

declare(strict_types=1);

/**
 * Каталоги uploads с персональными данными и работами участников. Прямой доступ к ним закрыт
 * (public/.htaccess направляет такие запросы в index.php), файлы отдаются только после проверки прав.
 *
 * @return list<string>
 */
function iro_private_upload_subdirs(): array
{
    return ['consents', 'participant_contest_files', 'submissions', 'submission_optional', 'contest_archive_reports'];
}

function iro_is_private_upload_path(string $uri): bool
{
    foreach (iro_private_upload_subdirs() as $dir) {
        if (str_starts_with($uri, '/uploads/' . $dir . '/')) {
            return true;
        }
    }

    return false;
}

/** Владелец, модератор/админ конкурса или эксперт конкурса. */
function iro_private_upload_contest_access(PDO $pdo, int $userId, int $ownerId, int $contestId): bool
{
    if ($userId === $ownerId) {
        return true;
    }
    if ($contestId <= 0) {
        return has_role($pdo, $userId, 'admin');
    }

    return can_manage_contest($pdo, $userId, $contestId)
        || user_has_expert_hub_access_for_contest($pdo, $userId, $contestId);
}

function iro_private_upload_allowed(PDO $pdo, int $userId, string $webPath): bool
{
    if (has_role($pdo, $userId, 'admin')) {
        return true;
    }
    $subdir = explode('/', ltrim($webPath, '/'))[1] ?? '';

    switch ($subdir) {
        case 'consents':
            $stmt = $pdo->prepare('SELECT user_id, contest_id FROM user_consents WHERE consent_file_path = :p LIMIT 1');
            break;
        case 'participant_contest_files':
            $stmt = $pdo->prepare('SELECT user_id, contest_id FROM participant_attachments WHERE file_path = :p LIMIT 1');
            break;
        case 'submissions':
            $stmt = $pdo->prepare('SELECT user_id, contest_id FROM submissions WHERE file_path = :p LIMIT 1');
            break;
        case 'submission_optional':
            $stmt = $pdo->prepare(
                'SELECT s.user_id, s.contest_id FROM submission_attachments sa
                 INNER JOIN submissions s ON s.id = sa.submission_id
                 WHERE sa.file_path = :p LIMIT 1'
            );
            break;
        case 'contest_archive_reports':
            $stmt = $pdo->prepare('SELECT 0 AS user_id, id AS contest_id FROM contests WHERE archive_report_path = :p LIMIT 1');
            $stmt->execute(['p' => $webPath]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row !== false && can_manage_contest($pdo, $userId, (int) $row['contest_id']);
        default:
            return false;
    }
    $stmt->execute(['p' => $webPath]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        return false;
    }

    return iro_private_upload_contest_access($pdo, $userId, (int) $row['user_id'], (int) $row['contest_id']);
}

function iro_serve_private_upload(PDO $pdo, ?int $userId, string $uri): never
{
    $webPath = rawurldecode($uri);
    if (str_contains($webPath, '..') || str_contains($webPath, "\0")) {
        http_response_code(404);
        exit;
    }
    if ($userId === null) {
        flash_set('warning', 'Войдите, чтобы открыть файл.');
        redirect('/login');
    }
    $full = iro_public_web_file_realpath($webPath);
    if ($full === null || !iro_private_upload_allowed($pdo, $userId, $webPath)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Файл не найден или нет доступа.';
        exit;
    }

    $ext = strtolower((string) pathinfo($full, PATHINFO_EXTENSION));
    $types = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'rtf' => 'application/rtf',
        'zip' => 'application/zip',
    ];
    $inline = in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . (string) filesize($full));
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . basename($full) . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($full);
    exit;
}
