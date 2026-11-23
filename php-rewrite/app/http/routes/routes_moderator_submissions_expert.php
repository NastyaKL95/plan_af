<?php

if ($uri === '/moderator/consents') {
    $uid = require_login();
    $contestId = (int) ($_GET['contest_id'] ?? 0);
    $contest = get_contest($pdo, $contestId);
    if (!$contest || !can_manage_contest($pdo, $uid, $contestId)) {
        http_response_code(403);
        flash_set('danger', 'Нет доступа');
        redirect('/contests');
    }
    if (is_post()) {
        $consentId = (int) ($_POST['consent_id'] ?? 0);
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'approve') {
            $whoStmt = $pdo->prepare('SELECT user_id FROM user_consents WHERE id = :id AND contest_id = :contest_id LIMIT 1');
            $whoStmt->execute(['id' => $consentId, 'contest_id' => $contestId]);
            $consentTarget = $whoStmt->fetch(PDO::FETCH_ASSOC);
            if (!$consentTarget) {
                flash_set('danger', 'Согласие не найдено');
                redirect('/moderator/consents?contest_id=' . $contestId);
            }
            $participantUserId = (int) $consentTarget['user_id'];
            $stmt = $pdo->prepare(
                'UPDATE user_consents SET is_approved = 1, rejection_reason = "", reviewed_at = CURRENT_TIMESTAMP, reviewed_by = :reviewed_by WHERE id = :id AND contest_id = :contest_id'
            );
            $stmt->execute(['reviewed_by' => $uid, 'id' => $consentId, 'contest_id' => $contestId]);
            $initialAdmitOrder = (int) ($contest['require_explicit_stage_admission'] ?? 0) === 1 ? 0 : 1;
            $cp = $pdo->prepare(
                'INSERT INTO contest_participants (user_id, contest_id, admitted_max_stage_order)
                 SELECT user_id, contest_id, :adm FROM user_consents WHERE id = :id
                 ON DUPLICATE KEY UPDATE admitted_max_stage_order = GREATEST(admitted_max_stage_order, VALUES(admitted_max_stage_order))'
            );
            $cp->execute(['id' => $consentId, 'adm' => $initialAdmitOrder]);
            notify_user(
                $pdo,
                $participantUserId,
                'Согласие одобрено',
                'По конкурсу «' . (string) $contest['title'] . '» ваше согласие одобрено, вы зачислены участником.',
                '/contest?id=' . $contestId
            );
            flash_set('success', 'Согласие одобрено');
        } elseif ($action === 'reject') {
            $reason = trim((string) ($_POST['reason'] ?? ''));
            $whoStmt = $pdo->prepare('SELECT user_id FROM user_consents WHERE id = :id AND contest_id = :contest_id LIMIT 1');
            $whoStmt->execute(['id' => $consentId, 'contest_id' => $contestId]);
            $consentTarget = $whoStmt->fetch(PDO::FETCH_ASSOC);
            if (!$consentTarget) {
                flash_set('danger', 'Согласие не найдено');
                redirect('/moderator/consents?contest_id=' . $contestId);
            }
            $participantUserId = (int) $consentTarget['user_id'];
            $stmt = $pdo->prepare(
                'UPDATE user_consents SET is_approved = 0, rejection_reason = :reason, reviewed_at = CURRENT_TIMESTAMP, reviewed_by = :reviewed_by WHERE id = :id AND contest_id = :contest_id'
            );
            $stmt->execute(['reason' => $reason, 'reviewed_by' => $uid, 'id' => $consentId, 'contest_id' => $contestId]);
            $del = $pdo->prepare(
                'DELETE cp FROM contest_participants cp
                 JOIN user_consents uc ON uc.user_id = cp.user_id AND uc.contest_id = cp.contest_id
                 WHERE uc.id = :id'
            );
            $del->execute(['id' => $consentId]);
            $rejectBody = $reason !== '' ? mb_substr($reason, 0, 4000) : 'См. детали в карточке конкурса.';
            notify_user(
                $pdo,
                $participantUserId,
                'В согласии отказано',
                $rejectBody,
                '/contest?id=' . $contestId
            );
            flash_set('success', 'Согласие отклонено');
        }
        redirect('/moderator/consents?contest_id=' . $contestId);
    }
    $stmt = $pdo->prepare(
        'SELECT uc.*, u.email, u.first_name, u.last_name
         FROM user_consents uc
         JOIN users u ON u.id = uc.user_id
         WHERE uc.contest_id = :contest_id
         ORDER BY
           CASE
             WHEN uc.is_approved = 0 AND (uc.rejection_reason = \'\' OR uc.rejection_reason IS NULL) THEN 0
             ELSE 1
           END,
           uc.uploaded_at DESC'
    );
    $stmt->execute(['contest_id' => $contestId]);
    $consents = $stmt->fetchAll();
    $participantAttachmentsByUser = [];
    $paStmt = $pdo->prepare(
        'SELECT pa.user_id, pa.file_path, caf.title AS field_title
         FROM participant_attachments pa
         INNER JOIN contest_attachment_fields caf ON caf.id = pa.field_id
         WHERE pa.contest_id = :contest_id
         ORDER BY pa.user_id, caf.sort_order, caf.id'
    );
    $paStmt->execute(['contest_id' => $contestId]);
    foreach ($paStmt->fetchAll(PDO::FETCH_ASSOC) as $paRow) {
        $pu = (int) $paRow['user_id'];
        $participantAttachmentsByUser[$pu][] = $paRow;
    }
    $supplementSubmissions = moderator_contest_supplement_submission_rows($pdo, $contestId);
    render('moderator/consents', [
        'title' => 'Согласия и файлы к работам',
        'contest' => $contest,
        'consents' => $consents,
        'participant_attachments_by_user' => $participantAttachmentsByUser,
        'supplement_submissions' => $supplementSubmissions,
        'user' => $user,
        'flash' => $flash,
        'breadcrumbs' => [
            ['label' => 'Главная', 'url' => '/'],
            ['label' => 'Конкурсы', 'url' => '/contests'],
            ['label' => (string) $contest['title'], 'url' => '/contest?id=' . (int) $contest['id']],
            ['label' => 'Согласия и файлы к работам', 'url' => null],
        ],
    ]);
    exit;
}

if ($uri === '/moderator/participant-files/download') {
    $uid = require_login();
    $contestId = (int) ($_GET['contest_id'] ?? 0);
    $participantId = (int) ($_GET['participant_id'] ?? 0);
    $contestDl = get_contest($pdo, $contestId);
    if (!$contestDl || !can_manage_contest($pdo, $uid, $contestId) || $participantId <= 0) {
        flash_set('danger', 'Нет доступа или неверные параметры.');
        redirect($contestId > 0 ? '/moderator/participant-files?contest_id=' . $contestId : '/moderator/participant-files');
    }
    moderator_send_participant_contest_zip_download($pdo, $contestId, $participantId);
}

if ($uri === '/moderator/participant-files') {
    $uid = require_login();
    $contestIdPf = (int) ($_GET['contest_id'] ?? 0);
    if ($isAdmin) {
        $manageableContests = $pdo->query('SELECT id, title FROM contests ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $mcPf = $pdo->prepare(
            'SELECT c.id, c.title FROM contests c INNER JOIN contest_moderators cm ON cm.contest_id = c.id WHERE cm.user_id = :uid ORDER BY c.id DESC'
        );
        $mcPf->execute(['uid' => $uid]);
        $manageableContests = $mcPf->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    if ($contestIdPf <= 0) {
        render('moderator/participant_files', [
            'title' => 'Архив материалов участников',
            'pick_mode' => true,
            'manageable_contests' => $manageableContests,
            'user' => $user,
            'flash' => $flash,
            'breadcrumbs' => [
                ['label' => 'Главная', 'url' => '/'],
                ['label' => 'Конкурсы', 'url' => '/contests'],
                ['label' => 'Материалы участников', 'url' => null],
            ],
        ]);
        exit;
    }
    $contestPf = get_contest($pdo, $contestIdPf);
    if (!$contestPf || !can_manage_contest($pdo, $uid, $contestIdPf)) {
        flash_set('danger', 'Нет доступа к этому конкурсу.');
        redirect('/moderator/participant-files');
    }
    $pStmt = $pdo->prepare(
        'SELECT cp.id AS participant_pk, u.email, u.last_name, u.first_name, u.middle_name
         FROM contest_participants cp
         INNER JOIN users u ON u.id = cp.user_id
         WHERE cp.contest_id = :cid
         ORDER BY u.last_name, u.first_name, u.email'
    );
    $pStmt->execute(['cid' => $contestIdPf]);
    $participantRows = $pStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    render('moderator/participant_files', [
        'title' => 'Материалы участников — ' . (string) $contestPf['title'],
        'pick_mode' => false,
        'contest' => $contestPf,
        'participant_rows' => $participantRows,
        'user' => $user,
        'flash' => $flash,
        'breadcrumbs' => array_merge(
            iro_breadcrumbs_contest_nav((string) $contestPf['title'], (int) $contestPf['id']),
            [['label' => 'Материалы участников', 'url' => null]]
        ),
    ]);
    exit;
}

if ($uri === '/moderator/supplements') {
    $uid = require_login();
    $contestId = (int) ($_GET['contest_id'] ?? 0);
    $contestSup = get_contest($pdo, $contestId);
    if (!$contestSup || !can_manage_contest($pdo, $uid, $contestId)) {
        flash_set('danger', 'Нет доступа');
        redirect('/contests');
    }
    redirect('/moderator/consents?contest_id=' . $contestId . '#moderator-work-files');
    exit;
}

if ($uri === '/submission/create') {
    $uid = require_login();
    $stageId = (int) ($_GET['stage_id'] ?? 0);
    $stageStmt = $pdo->prepare('SELECT * FROM stages WHERE id = :id LIMIT 1');
    $stageStmt->execute(['id' => $stageId]);
    $stage = $stageStmt->fetch();
    if (!$stage) {
        http_response_code(404);
        render('errors/404', ['title' => 'Не найдено', 'flash' => $flash, 'user' => $user, 'breadcrumbs' => iro_breadcrumbs_not_found()]);
        exit;
    }
    $contest = get_contest($pdo, (int) $stage['contest_id']);
    if (!$contest) {
        http_response_code(404);
        render('errors/404', ['title' => 'Не найдено', 'flash' => $flash, 'user' => $user, 'breadcrumbs' => iro_breadcrumbs_not_found()]);
        exit;
    }
    $participantCheck = $pdo->prepare('SELECT * FROM contest_participants WHERE user_id = :user_id AND contest_id = :contest_id LIMIT 1');
    $participantCheck->execute(['user_id' => $uid, 'contest_id' => $contest['id']]);
    $participant = $participantCheck->fetch();
    $consentCheck = $pdo->prepare('SELECT * FROM user_consents WHERE user_id = :user_id AND contest_id = :contest_id AND is_approved = 1 LIMIT 1');
    $consentCheck->execute(['user_id' => $uid, 'contest_id' => $contest['id']]);
    if (!$participant || !$consentCheck->fetch()) {
        flash_set('danger', 'Сначала нужно быть одобренным участником конкурса');
        redirect('/contest?id=' . $contest['id']);
    }

    $today = date('Y-m-d');
    $submitBlock = participant_submit_work_block_reason($pdo, $uid, $contest, $stage, is_array($participant) ? $participant : null, $today);
    if ($submitBlock !== null) {
        $submitBlockMessages = [
            'already_submitted' => 'Работа на этот этап уже подана.',
            'stage_inactive' => 'Этап сейчас неактивен, подача недоступна.',
            'past_deadline' => 'Дедлайн подачи на этот этап истёк.',
            'not_admitted' => 'Вы не допущены к этому этапу модератором.',
            'prev_missing' => 'Сначала подайте работу на предыдущий этап.',
            'not_participant' => 'Подача недоступна.',
            'phase_soon' => 'Для этапа выбран статус «Скоро» — приём работ ещё не открыт.',
            'phase_evaluation' => 'На этапе статус «Оценивание» — приём новых работ закрыт.',
            'phase_done' => 'Этап завершён — подача работ недоступна.',
            'outside_contest_dates' => 'Сейчас вне сроков проведения конкурса — подача недоступна.',
            'in_person_stage' => 'Этот этап очный: подача работ через сайт не предусмотрена. Оценивание ведут эксперты по критериям этапа.',
        ];
        $flashKind = $submitBlock === 'in_person_stage' ? 'info' : 'danger';
        flash_set($flashKind, $submitBlockMessages[$submitBlock] ?? 'Подача недоступна.');
        redirect('/contest?id=' . $contest['id']);
    }
    $effectiveDeadline = stage_effective_deadline($stage);

    $stageAttachmentFieldsStmt = $pdo->prepare(
        'SELECT id, title, requirements FROM stage_attachment_fields WHERE stage_id = :stage_id ORDER BY sort_order, id'
    );
    $stageAttachmentFieldsStmt->execute(['stage_id' => $stageId]);
    $stageAttachmentFields = $stageAttachmentFieldsStmt->fetchAll();

    if (is_post()) {
        $title = trim((string) ($_POST['title'] ?? ''));
        $workLink = trim((string) ($_POST['work_link'] ?? ''));
        $filePath = upload_file($_FILES['file'] ?? [], 'submissions');
        if ($title === '' || $filePath === null) {
            flash_set('danger', 'Заполните название и прикрепите файл');
            redirect('/submission/create?stage_id=' . $stageId);
        }
        $exists = $pdo->prepare('SELECT id FROM submissions WHERE user_id = :user_id AND stage_id = :stage_id LIMIT 1');
        $exists->execute(['user_id' => $uid, 'stage_id' => $stageId]);
        if ($exists->fetch()) {
            flash_set('danger', 'Работа на этот этап уже подана');
            redirect('/contest?id=' . $contest['id']);
        }
        $ins = $pdo->prepare(
            'INSERT INTO submissions (user_id, contest_id, stage_id, title, file_path, work_link)
             VALUES (:user_id, :contest_id, :stage_id, :title, :file_path, :work_link)'
        );
        $ins->execute([
            'user_id' => $uid,
            'contest_id' => $contest['id'],
            'stage_id' => $stageId,
            'title' => $title,
            'file_path' => $filePath,
            'work_link' => $workLink,
        ]);
        $submissionId = (int) $pdo->lastInsertId();

        $uploadedCount = 0;
        foreach ($stageAttachmentFields as $field) {
            $input = 'stage_attachment_' . (int) $field['id'];
            $suppPath = upload_file($_FILES[$input] ?? [], 'submission_optional');
            if ($suppPath === null) {
                flash_set('danger', 'Для подачи нужно загрузить все дополнительные вложения этапа');
                iro_delete_submission_cascade($pdo, $submissionId);
                redirect('/submission/create?stage_id=' . $stageId);
            }
            $slot = $pdo->prepare(
                'INSERT INTO submission_attachments (submission_id, field_id, file_path)
                 VALUES (:submission_id, :field_id, :file_path)
                 ON DUPLICATE KEY UPDATE file_path = VALUES(file_path)'
            );
            $slot->execute([
                'submission_id' => $submissionId,
                'field_id' => $field['id'],
                'file_path' => $suppPath,
            ]);
            $uploadedCount++;
        }
        if ($uploadedCount > 0) {
            $pdo->prepare(
                'UPDATE submissions
                 SET optional_attachments_status = "approved",
                     optional_attachments_rejection_reason = "",
                     optional_attachments_reviewed_at = NULL,
                     optional_attachments_reviewed_by = NULL
                 WHERE id = :id'
            )->execute(['id' => $submissionId]);
        }

        contest_assign_experts_to_submission($pdo, $submissionId, $stage);
        notify_experts_new_submission($pdo, $submissionId);
        $submitterEmail = trim((string) ($user['email'] ?? ''));
        $submitterLabel = $submitterEmail !== '' ? $submitterEmail : 'Участник';
        notify_contest_staff(
            $pdo,
            (int) $contest['id'],
            'Новая работа на этапе',
            $submitterLabel . ' подал работу «' . $title . '» на этап «' . (string) $stage['title'] . '».',
            '/moderator/stage/detail?id=' . $stageId
        );
        flash_set('success', 'Работа успешно подана');
        redirect('/contest?id=' . $contest['id']);
    }
    render('user/submit_work', [
        'title' => 'Подача работы',
        'stage' => $stage,
        'contest' => $contest,
        'effectiveDeadline' => $effectiveDeadline,
        'stageAttachmentFields' => $stageAttachmentFields,
        'user' => $user,
        'flash' => $flash,
        'breadcrumbs' => array_merge(
            iro_breadcrumbs_contest_nav((string) $contest['title'], (int) $contest['id']),
            [['label' => 'Подача работы: ' . (string) $stage['title'], 'url' => null]]
        ),
    ]);
    exit;
}

if ($uri === '/submission/supplements') {
    $uid = require_login();
    $submissionId = (int) ($_GET['submission_id'] ?? 0);
    $subStmt = $pdo->prepare(
        'SELECT s.*, st.title AS stage_title, st.start_date, st.end_date, st.submission_deadline, c.id AS contest_id, c.title AS contest_title
         FROM submissions s
         JOIN stages st ON st.id = s.stage_id
         JOIN contests c ON c.id = s.contest_id
         WHERE s.id = :id AND s.user_id = :user_id
         LIMIT 1'
    );
    $subStmt->execute(['id' => $submissionId, 'user_id' => $uid]);
    $submission = $subStmt->fetch();
    if (!$submission) {
        flash_set('danger', 'Работа не найдена');
        redirect('/my-contests');
    }

    $contestForIntake = get_contest($pdo, (int) $submission['contest_id']);
    if (!$contestForIntake || !contest_documents_intake_open_for_user($pdo, $contestForIntake, $uid)) {
        flash_set('danger', 'Приём документов закрыт — заменить дополнительные файлы к работе нельзя.');
        redirect('/contest?id=' . (int) $submission['contest_id']);
    }

    $stSup = $pdo->prepare('SELECT * FROM stages WHERE id = :id LIMIT 1');
    $stSup->execute(['id' => (int) $submission['stage_id']]);
    $stageForSup = $stSup->fetch(PDO::FETCH_ASSOC);
    if ($stageForSup && stage_is_in_person($stageForSup)) {
        flash_set('info', 'На очном этапе дополнительные файлы к работе через сайт не загружаются.');
        redirect('/contest?id=' . (int) $submission['contest_id']);
    }

    $today = date('Y-m-d');
    $effectiveDeadline = stage_effective_deadline($submission);
    if ($today > $effectiveDeadline || $today < (string) $submission['start_date'] || $today > (string) $submission['end_date']) {
        flash_set('danger', 'Редактирование доп. вложений недоступно (этап завершён или истёк дедлайн).');
        redirect('/contest?id=' . (int) $submission['contest_id']);
    }

    $fieldsStmt = $pdo->prepare(
        'SELECT id, title, requirements FROM stage_attachment_fields WHERE stage_id = :stage_id ORDER BY sort_order, id'
    );
    $fieldsStmt->execute(['stage_id' => $submission['stage_id']]);
    $fields = $fieldsStmt->fetchAll();

    if (is_post()) {
        $uploadedAny = false;
        foreach ($fields as $field) {
            $input = 'stage_attachment_' . (int) $field['id'];
            $suppPath = upload_file($_FILES[$input] ?? [], 'submission_optional');
            if ($suppPath !== null) {
                $fidSup = (int) $field['id'];
                $oldSup = trim((string) ($existingAttachments[$fidSup] ?? ''));
                if ($oldSup !== '' && $oldSup !== $suppPath) {
                    iro_delete_public_upload($oldSup);
                }
                $slot = $pdo->prepare(
                    'INSERT INTO submission_attachments (submission_id, field_id, file_path)
                     VALUES (:submission_id, :field_id, :file_path)
                     ON DUPLICATE KEY UPDATE file_path = VALUES(file_path)'
                );
                $slot->execute([
                    'submission_id' => $submission['id'],
                    'field_id' => $field['id'],
                    'file_path' => $suppPath,
                ]);
                $existingAttachments[$fidSup] = $suppPath;
                $uploadedAny = true;
            }
        }
        if ($uploadedAny) {
            $pdo->prepare(
                'UPDATE submissions
                 SET optional_attachments_status = "approved",
                     optional_attachments_rejection_reason = "",
                     optional_attachments_reviewed_at = NULL,
                     optional_attachments_reviewed_by = NULL
                 WHERE id = :id'
            )->execute(['id' => $submission['id']]);
        }
        flash_set('success', 'Дополнительные вложения обновлены');
        redirect('/contest?id=' . (int) $submission['contest_id']);
    }

    $existingStmt = $pdo->prepare(
        'SELECT sa.field_id, sa.file_path FROM submission_attachments sa WHERE sa.submission_id = :submission_id'
    );
    $existingStmt->execute(['submission_id' => $submission['id']]);
    $existingAttachments = [];
    foreach ($existingStmt->fetchAll() as $row) {
        $existingAttachments[(int) $row['field_id']] = (string) $row['file_path'];
    }

    render('user/edit_submission_supplements', [
        'title' => 'Редактирование доп. вложений',
        'submission' => $submission,
        'fields' => $fields,
        'existingAttachments' => $existingAttachments,
        'effectiveDeadline' => $effectiveDeadline,
        'user' => $user,
        'flash' => $flash,
        'breadcrumbs' => array_merge(
            iro_breadcrumbs_contest_nav((string) $submission['contest_title'], (int) $submission['contest_id']),
            [['label' => 'Дополнительные вложения к работе', 'url' => null]]
        ),
    ]);
    exit;
}

if ($uri === '/expert/submissions') {
    $uid = require_login();
    $stageId = (int) ($_GET['stage_id'] ?? 0);
    $stageStmt = $pdo->prepare('SELECT * FROM stages WHERE id = :id LIMIT 1');
    $stageStmt->execute(['id' => $stageId]);
    $stage = $stageStmt->fetch();
    if (!$stage) {
        http_response_code(404);
        render('errors/404', ['title' => 'Не найдено', 'flash' => $flash, 'user' => $user, 'breadcrumbs' => iro_breadcrumbs_not_found()]);
        exit;
    }
    $contestId = (int) $stage['contest_id'];
    $contestForStage = get_contest($pdo, $contestId);
    if ($contestForStage) {
        stage_sync_phase_done_if_past($pdo, $contestForStage, $stage);
    }
    $isPrivileged = contest_expert_area_privileged($pdo, $uid, $contestId);
    $isStageExpert = false;
    if (!$isPrivileged) {
        $expertCheck = $pdo->prepare('SELECT 1 FROM stage_experts WHERE stage_id = :stage_id AND user_id = :user_id LIMIT 1');
        $expertCheck->execute(['stage_id' => $stageId, 'user_id' => $uid]);
        $isStageExpert = (bool) $expertCheck->fetchColumn();
        $assignedMode = (string) ($stage['evaluation_mode'] ?? 'all') === 'assigned';
        if ($assignedMode) {
            $anyAssignedStmt = $pdo->prepare(
                'SELECT 1 FROM submission_assigned_experts sae
                 INNER JOIN submissions s ON s.id = sae.submission_id
                 WHERE s.stage_id = :stage_id AND sae.user_id = :user_id LIMIT 1'
            );
            $anyAssignedStmt->execute(['stage_id' => $stageId, 'user_id' => $uid]);
            $hasAssignedWork = (bool) $anyAssignedStmt->fetchColumn();
            if (!$isStageExpert && !$hasAssignedWork) {
                flash_set('danger', 'Нет доступа к этапу');
                redirect('/contest?id=' . $contestId);
            }
        } elseif (!$isStageExpert) {
            flash_set('danger', 'Нет доступа к этапу');
            redirect('/contest?id=' . $contestId);
        }
    }
    $sql = 'SELECT s.*, u.email FROM submissions s JOIN users u ON u.id = s.user_id WHERE s.stage_id = :stage_id';
    if (!$isPrivileged && (string) $stage['evaluation_mode'] === 'assigned') {
        $sql .= ' AND EXISTS (SELECT 1 FROM submission_assigned_experts sae WHERE sae.submission_id = s.id AND sae.user_id = :user_id)';
    }
    $sql .= ' ORDER BY s.submission_date DESC';
    $stmt = $pdo->prepare($sql);
    $params = ['stage_id' => $stageId];
    if (str_contains($sql, ':user_id')) {
        $params['user_id'] = $uid;
    }
    $stmt->execute($params);
    $submissions = $stmt->fetchAll();
    $todaySub = date('Y-m-d');
    $stageEvaluationClosed = $contestForStage
        ? stage_evaluation_is_closed($contestForStage, $stage, $todaySub)
        : ($todaySub > (string) ($stage['end_date'] ?? ''));
    $bcExpertSub = [
        ['label' => 'Главная', 'url' => '/'],
        ['label' => 'Оценка работ', 'url' => '/expert/contests'],
    ];
    if ($contestForStage) {
        $bcExpertSub[] = ['label' => (string) $contestForStage['title'], 'url' => '/expert/contests/' . (int) $contestForStage['id'] . '/stages/'];
    }
    $bcExpertSub[] = ['label' => 'Работы: ' . (string) ($stage['title'] ?? ''), 'url' => null];
    render('expert/submissions', [
        'title' => 'Работы этапа',
        'stage' => $stage,
        'submissions' => $submissions,
        'isPrivileged' => $isPrivileged,
        'user' => $user,
        'flash' => $flash,
        'stage_evaluation_closed' => $stageEvaluationClosed,
        'breadcrumbs' => $bcExpertSub,
    ]);
    exit;
}

if ($uri === '/expert/evaluate') {
    $uid = require_login();
    $submissionId = (int) ($_GET['submission_id'] ?? 0);
    $stmt = $pdo->prepare(
        'SELECT s.*, st.title AS stage_title, st.id AS stage_id, st.end_date AS stage_end_date, st.phase_status AS stage_phase_status,
                st.contest_id, st.evaluation_mode, c.title AS contest_title, u.email AS participant_email
         FROM submissions s
         JOIN stages st ON st.id = s.stage_id
         JOIN contests c ON c.id = s.contest_id
         JOIN users u ON u.id = s.user_id
         WHERE s.id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $submissionId]);
    $submission = $stmt->fetch();
    if (!$submission) {
        http_response_code(404);
        render('errors/404', ['title' => 'Не найдено', 'flash' => $flash, 'user' => $user, 'breadcrumbs' => iro_breadcrumbs_not_found()]);
        exit;
    }
    $contestId = (int) $submission['contest_id'];
    $stageId = (int) $submission['stage_id'];
    $isPrivileged = contest_expert_area_privileged($pdo, $uid, $contestId);
    if (!$isPrivileged) {
        $modeEval = (string) ($submission['evaluation_mode'] ?? 'all');
        if ($modeEval === 'assigned') {
            $assignedCheck = $pdo->prepare(
                'SELECT 1 FROM submission_assigned_experts WHERE submission_id = :submission_id AND user_id = :user_id LIMIT 1'
            );
            $assignedCheck->execute(['submission_id' => $submissionId, 'user_id' => $uid]);
            if (!$assignedCheck->fetchColumn()) {
                flash_set('danger', 'Вы не назначены на эту работу');
                redirect('/expert/submissions?stage_id=' . $stageId);
            }
        } else {
            $expertCheck = $pdo->prepare('SELECT 1 FROM stage_experts WHERE stage_id = :stage_id AND user_id = :user_id LIMIT 1');
            $expertCheck->execute(['stage_id' => $stageId, 'user_id' => $uid]);
            if (!$expertCheck->fetchColumn()) {
                flash_set('danger', 'Нет доступа к оцениванию');
                redirect('/expert/submissions?stage_id=' . $stageId);
            }
        }
    }
    $criteria = contest_stage_criteria_ordered($pdo, $stageId);
    $criteriaHasGroups = false;
    foreach ($criteria as $cRow) {
        if (!empty($cRow['group_id'])) {
            $criteriaHasGroups = true;
            break;
        }
    }

    $extraStmt = $pdo->prepare(
        'SELECT sa.file_path, sf.title AS field_title
         FROM submission_attachments sa
         JOIN stage_attachment_fields sf ON sf.id = sa.field_id
         WHERE sa.submission_id = :sid
         ORDER BY sf.sort_order, sf.id'
    );
    $extraStmt->execute(['sid' => $submissionId]);
    $submissionExtraFiles = $extraStmt->fetchAll(PDO::FETCH_ASSOC);

    $contestForEval = get_contest($pdo, $contestId);
    if (!$contestForEval) {
        http_response_code(404);
        render('errors/404', ['title' => 'Не найдено', 'flash' => $flash, 'user' => $user, 'breadcrumbs' => iro_breadcrumbs_not_found()]);
        exit;
    }
    $stageForEval = [
        'id' => $stageId,
        'end_date' => (string) ($submission['stage_end_date'] ?? ''),
        'phase_status' => (int) ($submission['stage_phase_status'] ?? STAGE_PHASE_SOON),
    ];
    stage_sync_phase_done_if_past($pdo, $contestForEval, $stageForEval);
    $submission['stage_phase_status'] = $stageForEval['phase_status'];

    $todayEval = date('Y-m-d');
    $evaluationFrozen = stage_evaluation_is_closed($contestForEval, $stageForEval, $todayEval);

    if (is_post()) {
        if ($evaluationFrozen) {
            flash_set('danger', 'Этап завершён или закрыт для оценивания, изменять оценки нельзя.');
            redirect('/expert/evaluate?submission_id=' . $submissionId);
        }
        foreach ($criteria as $criterion) {
            $points = (int) ($_POST['points_' . $criterion['id']] ?? -1);
            $cMin = (int) ($criterion['min_points'] ?? 0);
            $cMax = (int) ($criterion['max_points'] ?? 0);
            if ($points < $cMin || $points > $cMax) {
                flash_set('danger', 'Проверьте баллы по всем критериям (диапазон по каждому критерию)');
                redirect('/expert/evaluate?submission_id=' . $submissionId);
            }
            $upsert = $pdo->prepare(
                'INSERT INTO evaluations (expert_id, submission_id, criterion_id, points, evaluated_at, comment)
                 VALUES (:expert_id, :submission_id, :criterion_id, :points, CURRENT_TIMESTAMP, :comment)
                 ON DUPLICATE KEY UPDATE points = VALUES(points), evaluated_at = VALUES(evaluated_at), comment = VALUES(comment)'
            );
            $upsert->execute([
                'expert_id' => $uid,
                'submission_id' => $submissionId,
                'criterion_id' => $criterion['id'],
                'points' => $points,
                'comment' => trim((string) ($_POST['comment_' . $criterion['id']] ?? '')),
            ]);
        }
        refresh_awards_from_ranking_if_exist($pdo, $contestId);
        flash_set('success', 'Оценки сохранены');
        redirect('/expert/submissions?stage_id=' . $stageId);
    }
    $existingStmt = $pdo->prepare('SELECT criterion_id, points, comment FROM evaluations WHERE expert_id = :expert_id AND submission_id = :submission_id');
    $existingStmt->execute(['expert_id' => $uid, 'submission_id' => $submissionId]);
    $existing = [];
    foreach ($existingStmt->fetchAll() as $row) {
        $existing[(int) $row['criterion_id']] = $row;
    }
    $participantApplicationFiles = participant_application_files_list($pdo, (int) $submission['user_id'], $contestId);

    render('expert/evaluate', [
        'title' => 'Оценивание работы',
        'submission' => $submission,
        'criteria' => $criteria,
        'criteria_has_groups' => $criteriaHasGroups,
        'existing' => $existing,
        'submission_extra_files' => $submissionExtraFiles,
        'participant_application_files' => $participantApplicationFiles,
        'evaluation_frozen' => $evaluationFrozen,
        'user' => $user,
        'flash' => $flash,
        'breadcrumbs' => [
            ['label' => 'Главная', 'url' => '/'],
            ['label' => 'Оценка работ', 'url' => '/expert/contests'],
            ['label' => (string) $submission['contest_title'], 'url' => '/expert/contests/' . $contestId . '/stages/'],
            ['label' => 'Оценивание работы', 'url' => null],
        ],
    ]);
    exit;
}

if ($uri === '/moderator/assign-experts') {
    $uid = require_login();
    $submissionId = (int) ($_GET['submission_id'] ?? 0);
    $subStmt = $pdo->prepare(
        'SELECT s.id, s.title, s.stage_id, st.contest_id, st.title AS stage_title FROM submissions s JOIN stages st ON st.id = s.stage_id WHERE s.id = :id LIMIT 1'
    );
    $subStmt->execute(['id' => $submissionId]);
    $submission = $subStmt->fetch();
    if (!$submission || !can_manage_contest($pdo, $uid, (int) $submission['contest_id'])) {
        flash_set('danger', 'Нет доступа');
        redirect('/contests');
    }
    if (is_post()) {
        $chosen = $_POST['expert_ids'] ?? [];
        if (!is_array($chosen)) {
            $chosen = [];
        }
        $beforeStmt = $pdo->prepare('SELECT user_id FROM submission_assigned_experts WHERE submission_id = :submission_id');
        $beforeStmt->execute(['submission_id' => $submissionId]);
        $oldExpertIds = array_map('intval', array_column($beforeStmt->fetchAll(PDO::FETCH_ASSOC), 'user_id'));
        $pdo->prepare('DELETE FROM submission_assigned_experts WHERE submission_id = :submission_id')
            ->execute(['submission_id' => $submissionId]);
        $insert = $pdo->prepare('INSERT INTO submission_assigned_experts (submission_id, user_id) VALUES (:submission_id, :user_id)');
        $newExpertIds = [];
        foreach ($chosen as $expertId) {
            $eid = (int) $expertId;
            if ($eid <= 0) {
                continue;
            }
            $insert->execute(['submission_id' => $submissionId, 'user_id' => $eid]);
            $newExpertIds[] = $eid;
        }
        $newExpertIds = array_values(array_unique($newExpertIds));
        $newlyAssigned = array_values(array_diff($newExpertIds, $oldExpertIds));
        notify_experts_newly_assigned($pdo, $submissionId, $newlyAssigned);
        flash_set('success', 'Назначения экспертов сохранены');
        redirect('/expert/submissions?stage_id=' . (int) $submission['stage_id']);
    }
    $expertsStmt = $pdo->prepare(
        'SELECT u.id, u.email
         FROM users u
         JOIN stage_experts se ON se.user_id = u.id
         WHERE se.stage_id = :stage_id
         ORDER BY u.email'
    );
    $expertsStmt->execute(['stage_id' => $submission['stage_id']]);
    $experts = $expertsStmt->fetchAll();
    $assignedStmt = $pdo->prepare('SELECT user_id FROM submission_assigned_experts WHERE submission_id = :submission_id');
    $assignedStmt->execute(['submission_id' => $submissionId]);
    $assigned = array_map('intval', array_column($assignedStmt->fetchAll(), 'user_id'));
    $contestAssign = get_contest($pdo, (int) $submission['contest_id']);
    render('moderator/assign_experts', [
        'title' => 'Назначение экспертов',
        'submission' => $submission,
        'experts' => $experts,
        'assigned' => $assigned,
        'user' => $user,
        'flash' => $flash,
        'breadcrumbs' => $contestAssign ? [
            ['label' => 'Главная', 'url' => '/'],
            ['label' => 'Конкурсы', 'url' => '/contests'],
            ['label' => (string) $contestAssign['title'], 'url' => '/contest?id=' . (int) $contestAssign['id']],
            ['label' => 'Назначение экспертов', 'url' => null],
        ] : [
            ['label' => 'Главная', 'url' => '/'],
            ['label' => 'Назначение экспертов', 'url' => null],
        ],
    ]);
    exit;
}

if ($uri === '/my-contests') {
    if ($userId === null) {
        flash_set('danger', 'Сначала войдите в систему');
        redirect('/login');
    }
    $stmt = $pdo->prepare(
        'SELECT c.id, c.title, c.start_date, c.end_date, c.status, a.place AS award_place, a.award_description
         FROM contest_participants cp
         JOIN contests c ON c.id = cp.contest_id
         LEFT JOIN awards a ON a.participant_id = cp.id AND a.contest_id = c.id
         WHERE cp.user_id = :user_id
         ORDER BY c.start_date DESC'
    );
    $stmt->execute(['user_id' => $userId]);
    $contests = $stmt->fetchAll();
    render('user/my_contests', [
        'title' => 'Мои конкурсы',
        'contests' => $contests,
        'body_class' => 'iro-page-my-contests',
        'breadcrumbs' => [
            ['label' => 'Главная', 'url' => '/'],
            ['label' => 'Мои конкурсы', 'url' => null],
        ],
    ]);
    exit;
}

if ($uri === '/notifications') {
    if ($userId === null) {
        flash_set('danger', 'Сначала войдите в систему');
        redirect('/login');
    }
    $stmt = $pdo->prepare(
        'SELECT id, title, body, link, is_read, created_at FROM in_app_notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 100'
    );
    $stmt->execute(['user_id' => $userId]);
    $notifications = $stmt->fetchAll();
    render('notifications/index', [
        'title' => 'Уведомления',
        'notifications' => $notifications,
        'body_class' => 'iro-page-notifications',
        'breadcrumbs' => [
            ['label' => 'Главная', 'url' => '/'],
            ['label' => 'Уведомления', 'url' => null],
        ],
    ]);
    exit;
}

if ($uri === '/notifications/read-all') {
    $uid = require_login();
    if (!is_post()) {
        redirect('/notifications');
    }
    $pdo->prepare('UPDATE in_app_notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0')->execute(['uid' => $uid]);
    flash_set('success', 'Все уведомления отмечены прочитанными');
    redirect('/notifications');
}

if ($uri === '/notifications/delete') {
    $uid = require_login();
    if (!is_post()) {
        redirect('/notifications');
    }
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        flash_set('danger', 'Некорректный запрос');
        redirect('/notifications');
    }
    $del = $pdo->prepare('DELETE FROM in_app_notifications WHERE id = :id AND user_id = :uid');
    $del->execute(['id' => $id, 'uid' => $uid]);
    if ($del->rowCount() > 0) {
        flash_set('success', 'Уведомление удалено');
    } else {
        flash_set('danger', 'Уведомление не найдено');
    }
    redirect('/notifications');
}

if ($uri === '/notifications/delete-all') {
    $uid = require_login();
    if (!is_post()) {
        redirect('/notifications');
    }
    $pdo->prepare('DELETE FROM in_app_notifications WHERE user_id = :uid')->execute(['uid' => $uid]);
    flash_set('success', 'Все уведомления удалены');
    redirect('/notifications');
}

if ($uri === '/notifications/go') {
    $uid = require_login();
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT id, link FROM in_app_notifications WHERE id = :id AND user_id = :uid LIMIT 1');
    $stmt->execute(['id' => $id, 'uid' => $uid]);
    $row = $stmt->fetch();
    if (!$row) {
        flash_set('danger', 'Уведомление не найдено');
        redirect('/notifications');
    }
    $pdo->prepare('UPDATE in_app_notifications SET is_read = 1 WHERE id = :id')->execute(['id' => $id]);
    $link = iro_safe_relative_url((string) ($row['link'] ?? '/'));
    redirect($link);
}

if ($uri === '/expert/contests') {
    $uid = require_login();
    $stmt = $pdo->prepare(
        'SELECT DISTINCT c.id, c.title, c.start_date, c.end_date, c.status
         FROM contests c
         WHERE EXISTS (
             SELECT 1 FROM stages st
             INNER JOIN stage_experts se ON se.stage_id = st.id
             WHERE st.contest_id = c.id AND se.user_id = :uid1
         )
         OR EXISTS (
             SELECT 1 FROM contest_expert_pool cep
             WHERE cep.contest_id = c.id AND cep.user_id = :uid2
         )
         OR EXISTS (
             SELECT 1 FROM submission_assigned_experts sae
             INNER JOIN submissions s ON s.id = sae.submission_id
             WHERE s.contest_id = c.id AND sae.user_id = :uid3
         )
         ORDER BY c.id DESC'
    );
    $stmt->execute(['uid1' => $uid, 'uid2' => $uid, 'uid3' => $uid]);
    $contests = $stmt->fetchAll();
    render('expert/contests', [
        'title' => 'Кабинет эксперта',
        'contests' => $contests,
        'breadcrumbs' => [
            ['label' => 'Главная', 'url' => '/'],
            ['label' => 'Оценка работ', 'url' => null],
        ],
    ]);
    exit;
}

if (preg_match('~^/expert/contests/(\\d+)/stages/?$~', $uri, $m)) {
    $uid = require_login();
    $contestId = (int) $m[1];
    $contest = get_contest($pdo, $contestId);
    if (!$contest) {
        http_response_code(404);
        render('errors/404', ['title' => 'Не найдено', 'breadcrumbs' => iro_breadcrumbs_not_found()]);
        exit;
    }
    if (!user_has_expert_hub_access_for_contest($pdo, $uid, $contestId)) {
        flash_set('danger', 'Нет доступа к кабинету эксперта по этому конкурсу');
        redirect('/expert/contests');
    }
    $isPrivileged = contest_expert_area_privileged($pdo, $uid, $contestId);
    if ($isPrivileged) {
        $stmt = $pdo->prepare('SELECT id, title, start_date, end_date, sort_order FROM stages WHERE contest_id = :cid ORDER BY sort_order, id');
        $stmt->execute(['cid' => $contestId]);
    } else {
        $stmt = $pdo->prepare(
            'SELECT DISTINCT st.id, st.title, st.start_date, st.end_date, st.sort_order
             FROM stages st
             WHERE st.contest_id = :cid
             AND (
                 EXISTS (
                     SELECT 1 FROM stage_experts se
                     WHERE se.stage_id = st.id AND se.user_id = :uid
                 )
                 OR EXISTS (
                     SELECT 1 FROM submissions s
                     INNER JOIN submission_assigned_experts sae ON sae.submission_id = s.id
                     WHERE s.stage_id = st.id AND sae.user_id = :uid2
                 )
             )
             ORDER BY st.sort_order, st.id'
        );
        $stmt->execute(['cid' => $contestId, 'uid' => $uid, 'uid2' => $uid]);
    }
    $stages = $stmt->fetchAll();
    $inExpertPool = false;
    if (!$isPrivileged && $stages === []) {
        $pchk = $pdo->prepare(
            'SELECT 1 FROM contest_expert_pool WHERE contest_id = :cid AND user_id = :uid LIMIT 1'
        );
        $pchk->execute(['cid' => $contestId, 'uid' => $uid]);
        $inExpertPool = (bool) $pchk->fetchColumn();
    }
    $poolOnly = !$isPrivileged && $stages === [] && $inExpertPool;
    render('expert/stages', [
        'title' => 'Этапы (эксперт)',
        'contest' => $contest,
        'stages' => $stages,
        'isPrivileged' => $isPrivileged,
        'expert_pool_only_no_stage' => $poolOnly,
        'breadcrumbs' => [
            ['label' => 'Главная', 'url' => '/'],
            ['label' => 'Оценка работ', 'url' => '/expert/contests'],
            ['label' => (string) $contest['title'], 'url' => null],
        ],
    ]);
    exit;
}

