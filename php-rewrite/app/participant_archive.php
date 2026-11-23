<?php

declare(strict_types=1);

/**
 * Сбор путей к файлам участника по конкурсу (согласие, заявка, работы по этапам) для ZIP.
 *
 * @return array{entries: list<array{fs: string, name: string}>, temp_files: list<string>}
 */
function participant_contest_all_files_for_zip(PDO $pdo, int $userId, int $contestId): array
{
    $usedNames = [];
    $add = static function (string $fs, string $name) use (&$usedNames): array {
        if ($name === '' || $name === '.' || $name === '..') {
            $name = 'file';
        }
        if (isset($usedNames[$name])) {
            $usedNames[$name]++;
            $pi = pathinfo($name);
            $base = (string) ($pi['filename'] ?? 'file');
            $ext = isset($pi['extension']) ? '.' . $pi['extension'] : '';
            $name = $base . '_' . $usedNames[$name] . $ext;
        } else {
            $usedNames[$name] = 0;
        }

        return ['fs' => $fs, 'name' => $name];
    };

    $rows = [];
    $tempFiles = [];
    $p = '1_Заявка_согласие_и_документы';
    $i = 0;
    foreach (participant_application_files_list($pdo, $userId, $contestId) as $app) {
        $i++;
        $fs = iro_public_web_file_realpath($app['web_path']);
        if ($fs === null) {
            continue;
        }
        $label = (string) $app['label'];
        $ext = pathinfo($fs, PATHINFO_EXTENSION);
        $base = $i . '_' . iro_zip_filename_component($label, $ext);
        if ($ext !== '' && !str_ends_with(strtolower($base), '.' . strtolower($ext))) {
            $base .= '.' . $ext;
        }
        $rows[] = $add($fs, $p . '/' . $base);
    }

    $subStmt = $pdo->prepare(
        'SELECT s.id, s.title, s.file_path, s.work_link, st.sort_order, st.title AS stage_title
         FROM submissions s
         INNER JOIN stages st ON st.id = s.stage_id
         WHERE s.user_id = :u AND s.contest_id = :c
         ORDER BY st.sort_order, st.id, s.id'
    );
    $subStmt->execute(['u' => $userId, 'c' => $contestId]);
    $subList = $subStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $attStmt = $pdo->prepare(
        'SELECT sa.file_path, sf.title AS field_title
         FROM submission_attachments sa
         INNER JOIN stage_attachment_fields sf ON sf.id = sa.field_id
         WHERE sa.submission_id = :sid
         ORDER BY sf.sort_order, sf.id'
    );
    $n = 0;
    foreach ($subList as $sub) {
        $n++;
        $sid = (int) $sub['id'];
        $stOrder = (int) ($sub['sort_order'] ?? 0);
        $stTitle = iro_zip_filename_component((string) ($sub['stage_title'] ?? 'этап'), '');
        $folder = '2_Работы/Этап_' . $stOrder . '_' . ($stTitle !== '' ? $stTitle : 'без_названия') . '/Работа_' . $n . '_id' . $sid;
        $mainPath = trim((string) ($sub['file_path'] ?? ''));
        if ($mainPath !== '') {
            $mfs = iro_public_web_file_realpath($mainPath);
            if ($mfs !== null) {
                $ext = pathinfo($mfs, PATHINFO_EXTENSION);
                $t = iro_zip_filename_component((string) ($sub['title'] ?? 'работа'), (string) $ext);
                if ($ext !== '' && !str_ends_with(strtolower($t), '.' . strtolower($ext))) {
                    $t .= '.' . $ext;
                } else {
                    if ($ext === '' && $t !== '' && !str_contains($t, '.')) {
                        $t .= '.bin';
                    }
                }
                $rows[] = $add($mfs, $folder . '/01_основной_файл_' . $t);
            }
        }
        $wl = trim((string) ($sub['work_link'] ?? ''));
        if ($wl !== '') {
            $linkFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'iro_link_' . bin2hex(random_bytes(4)) . '.txt';
            $linkBody = "Ссылка на работу (участник указал при подаче):\r\n" . $wl . "\r\n";
            if (@file_put_contents($linkFile, $linkBody) !== false) {
                $tempFiles[] = $linkFile;
                $rows[] = $add($linkFile, $folder . '/00_ссылка_на_работу.txt');
            }
        }
        $attStmt->execute(['sid' => $sid]);
        $ai = 0;
        foreach ($attStmt->fetchAll(PDO::FETCH_ASSOC) as $ar) {
            $ai++;
            $afs = iro_public_web_file_realpath(trim((string) ($ar['file_path'] ?? '')));
            if ($afs === null) {
                continue;
            }
            $ft = trim((string) ($ar['field_title'] ?? 'вложение'));
            $ext = pathinfo($afs, PATHINFO_EXTENSION);
            $fn = sprintf('%02d_', $ai + 1) . 'вложение_' . iro_zip_filename_component($ft, (string) $ext);
            if ($ext !== '' && !str_ends_with(strtolower($fn), '.' . strtolower($ext))) {
                $fn .= '.' . $ext;
            }
            $rows[] = $add($afs, $folder . '/' . $fn);
        }
    }

    return ['entries' => $rows, 'temp_files' => $tempFiles];
}

/** Отправка ZIP участнику конкурса в ответ HTTP; завершает выполнение скрипта. */
function moderator_send_participant_contest_zip_download(PDO $pdo, int $contestId, int $participantId): void
{
    $cp = $pdo->prepare(
        'SELECT cp.user_id, cp.contest_id, u.last_name, u.first_name
         FROM contest_participants cp
         INNER JOIN users u ON u.id = cp.user_id
         WHERE cp.id = :pid AND cp.contest_id = :cid LIMIT 1'
    );
    $cp->execute(['pid' => $participantId, 'cid' => $contestId]);
    $row = $cp->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo 'Участник не найден';
        exit;
    }
    $userId = (int) $row['user_id'];
    $bundle = participant_contest_all_files_for_zip($pdo, $userId, $contestId);
    $entries = $bundle['entries'];
    $tempLinks = $bundle['temp_files'];
    if ($entries === []) {
        foreach ($tempLinks as $tf) {
            @unlink($tf);
        }
        http_response_code(404);
        echo 'Нет файлов для архива (или файлы недоступны на сервере).';
        exit;
    }
    if (!class_exists(ZipArchive::class)) {
        http_response_code(500);
        echo 'На сервере не включено расширение ZIP.';
        exit;
    }
    $contestStmt = $pdo->prepare('SELECT title FROM contests WHERE id = :id LIMIT 1');
    $contestStmt->execute(['id' => $contestId]);
    $contestTitle = trim((string) ($contestStmt->fetchColumn() ?: 'contest'));

    $tmp = tempnam(sys_get_temp_dir(), 'irozip');
    if ($tmp === false) {
        http_response_code(500);
        echo 'Не удалось создать временный файл.';
        exit;
    }
    @unlink($tmp);
    $zipPath = $tmp . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        foreach ($tempLinks as $tf) {
            @unlink($tf);
        }
        http_response_code(500);
        echo 'Не удалось создать архив.';
        exit;
    }
    foreach ($entries as $ent) {
        $zip->addFile($ent['fs'], $ent['name']);
    }
    $zip->close();

    $ln = preg_replace('~[^\p{L}\p{N}_\-]+~u', '_', (string) ($row['last_name'] ?? '')) ?? '';
    $fn = preg_replace('~[^\p{L}\p{N}_\-]+~u', '_', (string) ($row['first_name'] ?? '')) ?? '';
    $slug = trim($ln . '_' . $fn, '_');
    if ($slug === '') {
        $slug = 'participant_' . $participantId;
    }
    $ctShort = mb_substr(preg_replace('~[^\p{L}\p{N}_\-]+~u', '_', $contestTitle) ?? '', 0, 40);
    $ctShort = trim((string) $ctShort, '_');
    if ($ctShort === '') {
        $ctShort = 'contest_' . $contestId;
    }
    $downloadName = $slug . '_' . $ctShort . '_' . date('Y-m-d') . '.zip';

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
    header('Content-Length: ' . (string) filesize($zipPath));
    readfile($zipPath);
    @unlink($zipPath);
    foreach ($tempLinks as $tf) {
        @unlink($tf);
    }
    exit;
}
