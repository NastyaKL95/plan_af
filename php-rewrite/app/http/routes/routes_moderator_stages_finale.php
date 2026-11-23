<?php

if ($uri === '/moderator/stage/create') {
    $uid = require_login();
    $contestId = (int) ($_POST['contest_id'] ?? $_GET['contest_id'] ?? 0);
    $contest = get_contest($pdo, $contestId);
    if (!$contest || !can_manage_contest($pdo, $uid, $contestId)) {
        flash_set('danger', 'Нет доступа');
        redirect('/contests');
    }
    if ((int) ($contest['is_external'] ?? 0) === 1) {
        flash_set('danger', 'У конкурса-ссылки нет этапов на этом сайте.');
        redirect('/contest?id=' . $contestId);
    }

    $errors = [];
    $stageAttachmentFields = [];
    $pickerStageExpertsInitial = [];

    $maxStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) AS m FROM stages WHERE contest_id = :cid');
    $maxStmt->execute(['cid' => $contestId]);
    $nextOrder = ((int) ($maxStmt->fetchColumn() ?: 0)) + 1;

    if (is_post()) {
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $externalLink = trim((string) ($_POST['external_link'] ?? ''));
        $externalEnabled = isset($_POST['external_link_enabled']) ? 1 : 0;
        $start = (string) ($_POST['start_date'] ?? '');
        $end = (string) ($_POST['end_date'] ?? '');
        $deadline = (string) ($_POST['submission_deadline'] ?? '');
        $deadline = $deadline !== '' ? $deadline : null;
        $evaluationMode = (string) ($_POST['evaluation_mode'] ?? 'all');
        $expertsPer = (int) ($_POST['experts_per_submission'] ?? 2);
        $includeScores = isset($_POST['include_scores_in_final_ranking']) ? 1 : 0;
        $phaseStatus = STAGE_PHASE_SOON;
        if (contest_is_parallel_stage_mode($contest)) {
            $phaseStatus = stage_phase_status_normalize((int) ($_POST['phase_status'] ?? STAGE_PHASE_SOON));
        }
        $stageFormat = stage_format_normalize((int) ($_POST['stage_format'] ?? STAGE_FORMAT_ONLINE));
        $expertIds = normalize_int_list($_POST['expert_ids'] ?? []);
        $stageAttachmentFields = normalize_attachment_rows(
            (array) ($_POST['stage_attach_sort_order'] ?? []),
            (array) ($_POST['stage_attach_title'] ?? []),
            (array) ($_POST['stage_attach_requirements'] ?? []),
        );
        if ($stageFormat === STAGE_FORMAT_IN_PERSON) {
            $stageAttachmentFields = [];
        }

        if ($title === '') $errors[] = 'Укажите название этапа.';
        if ($start === '' || $end === '') $errors[] = 'Укажите даты начала и окончания этапа.';
        if ($start !== '' && $end !== '' && $end <= $start) $errors[] = 'Дата окончания этапа должна быть позже даты начала.';
        if ($start !== '' && $start < (string) $contest['start_date']) $errors[] = 'Дата начала этапа не может быть раньше начала конкурса.';
        if ($end !== '' && $end > (string) $contest['end_date']) $errors[] = 'Дата окончания этапа не может быть позже окончания конкурса.';
        if ($deadline !== null && $deadline < $start) $errors[] = 'Дедлайн не может быть раньше начала этапа.';
        if ($deadline !== null && $deadline > $end) $errors[] = 'Дедлайн не может быть позже окончания этапа.';
        if ($externalEnabled === 1 && $externalLink === '') $errors[] = 'Включён показ ссылки — укажите URL.';
        if (!in_array($evaluationMode, ['all', 'assigned'], true)) $evaluationMode = 'all';
        if ($expertsPer < 1) $expertsPer = 1;

        foreach ($expertIds as $eid) {
            if (!contest_stage_expert_user_allowed($pdo, $contestId, $eid)) {
                $errors[] = 'Указан недопустимый эксперт этапа (не в пуле конкурса, неактивен или администратор сайта).';
                break;
            }
        }
        if ($evaluationMode === 'assigned') {
            if ($expertIds === []) {
                $errors[] = 'В режиме «назначенные эксперты» выберите хотя бы одного эксперта этапа.';
            } elseif ($expertsPer > count($expertIds)) {
                $errors[] = '«Сколько экспертов на одну работу» не может быть больше числа экспертов этапа (' . count($expertIds) . ').';
            }
        }

        $instructionPath = null;
        if (!empty($_FILES['instruction_file']['name'])) {
            $instructionPath = upload_file($_FILES['instruction_file'], 'stage_attachments');
            if ($instructionPath === null) $errors[] = 'Не удалось загрузить файл к этапу.';
        }

        if (!$errors && !contest_is_parallel_stage_mode($contest)) {
            [$ok, $msg] = validate_stage_timeline($pdo, $contestId, 0, $nextOrder, $start, $end);
            if (!$ok) {
                $errors[] = $msg;
            }
        }

        if ($errors) {
            $pickerStageExpertsInitial = users_picker_initial($pdo, $expertIds);
        }

        if (!$errors) {
            $stmt = $pdo->prepare(
                'INSERT INTO stages
                 (contest_id, title, description, instruction_file_path, external_link, external_link_enabled, sort_order,
                  start_date, end_date, submission_deadline, evaluation_mode, experts_per_submission, include_scores_in_final_ranking,
                  phase_status, stage_format)
                 VALUES
                 (:contest_id, :title, :description, :instruction, :external_link, :external_enabled, :sort_order,
                  :start_date, :end_date, :deadline, :evaluation_mode, :experts_per, :include_scores, :phase_status, :stage_format)'
            );
            $stmt->execute([
                'contest_id' => $contestId,
                'title' => $title,
                'description' => $description,
                'instruction' => $instructionPath,
                'external_link' => $externalLink,
                'external_enabled' => $externalEnabled,
                'sort_order' => $nextOrder,
                'start_date' => $start,
                'end_date' => $end,
                'deadline' => $deadline,
                'evaluation_mode' => $evaluationMode,
                'experts_per' => $expertsPer,
                'include_scores' => $includeScores,
                'phase_status' => $phaseStatus,
                'stage_format' => $stageFormat,
            ]);
            $stageId = (int) $pdo->lastInsertId();

            $pdo->prepare('DELETE FROM stage_experts WHERE stage_id = :sid')->execute(['sid' => $stageId]);
            $ins = $pdo->prepare('INSERT INTO stage_experts (stage_id, user_id) VALUES (:sid, :uid)');
            foreach ($expertIds as $eid) {
                $ins->execute(['sid' => $stageId, 'uid' => $eid]);
            }

            $pdo->prepare('DELETE FROM stage_attachment_fields WHERE stage_id = :sid')->execute(['sid' => $stageId]);
            $ins2 = $pdo->prepare(
                'INSERT INTO stage_attachment_fields (stage_id, sort_order, title, requirements)
                 VALUES (:stage_id, :sort_order, :title, :requirements)'
            );
            foreach ($stageAttachmentFields as $row) {
                $ins2->execute([
                    'stage_id' => $stageId,
                    'sort_order' => $row['sort_order'],
                    'title' => $row['title'],
                    'requirements' => $row['requirements'],
                ]);
            }

            flash_set('success', 'Этап создан');
            redirect('/contest?id=' . $contestId);
        }
    }

    render('moderator/stage_form', [
        'title' => 'Создать этап',
        'mode' => 'create',
        'errors' => $errors,
        'contest' => $contest,
        'stage' => null,
        'contest_id' => $contestId,
        'pickerStageExpertsInitial' => $pickerStageExpertsInitial,
        'stageAttachmentFields' => $stageAttachmentFields,
        'stage_has_evaluations' => false,
        'breadcrumbs' => array_merge(
            iro_breadcrumbs_contest_nav((string) $contest['title'], $contestId),
            [['label' => 'Создать этап', 'url' => null]]
        ),
    ]);
    exit;
}

if ($uri === '/moderator/stage/edit') {
    $uid = require_login();
    $stageId = (int) ($_POST['stage_id'] ?? $_GET['id'] ?? 0);
    $stStmt = $pdo->prepare('SELECT * FROM stages WHERE id = :id LIMIT 1');
    $stStmt->execute(['id' => $stageId]);
    $stage = $stStmt->fetch();
    if (!$stage) {
        http_response_code(404);
        render('errors/404', ['title' => 'Не найдено', 'breadcrumbs' => iro_breadcrumbs_not_found()]);
        exit;
    }
    $contestId = (int) $stage['contest_id'];
    $contest = get_contest($pdo, $contestId);
    if (!$contest || !can_manage_contest($pdo, $uid, $contestId)) {
        flash_set('danger', 'Нет доступа');
        redirect('/contests');
    }

    $selStmt = $pdo->prepare('SELECT user_id FROM stage_experts WHERE stage_id = :sid');
    $selStmt->execute(['sid' => $stageId]);
    $selectedExperts = array_map('intval', array_column($selStmt->fetchAll(), 'user_id'));
    $pickerStageExpertsInitial = users_picker_initial($pdo, $selectedExperts);

    $sfStmt = $pdo->prepare('SELECT sort_order, title, requirements FROM stage_attachment_fields WHERE stage_id = :sid ORDER BY sort_order, id');
    $sfStmt->execute(['sid' => $stageId]);
    $stageAttachmentFields = $sfStmt->fetchAll();

    stage_sync_phase_done_if_past($pdo, $contest, $stage);

    $evCountStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM evaluations e INNER JOIN submissions s ON s.id = e.submission_id WHERE s.stage_id = :sid'
    );
    $evCountStmt->execute(['sid' => $stageId]);
    $stageHasEvaluations = (int) $evCountStmt->fetchColumn() > 0;

    $errors = [];
    if (is_post()) {
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $externalLink = trim((string) ($_POST['external_link'] ?? ''));
        $externalEnabled = isset($_POST['external_link_enabled']) ? 1 : 0;
        $start = (string) ($_POST['start_date'] ?? '');
        $end = (string) ($_POST['end_date'] ?? '');
        $deadline = (string) ($_POST['submission_deadline'] ?? '');
        $deadline = $deadline !== '' ? $deadline : null;
        $evaluationMode = (string) ($_POST['evaluation_mode'] ?? 'all');
        $expertsPer = (int) ($_POST['experts_per_submission'] ?? 2);
        $includeScores = isset($_POST['include_scores_in_final_ranking']) ? 1 : 0;
        $phaseStatus = stage_phase_status_normalize((int) ($stage['phase_status'] ?? STAGE_PHASE_SOON));
        if (contest_is_parallel_stage_mode($contest)) {
            $phaseStatus = stage_phase_status_normalize((int) ($_POST['phase_status'] ?? STAGE_PHASE_SOON));
        }
        $stageFormat = stage_format_normalize((int) ($_POST['stage_format'] ?? STAGE_FORMAT_ONLINE));
        $expertIds = normalize_int_list($_POST['expert_ids'] ?? []);
        $stageAttachmentFields = normalize_attachment_rows(
            (array) ($_POST['stage_attach_sort_order'] ?? []),
            (array) ($_POST['stage_attach_title'] ?? []),
            (array) ($_POST['stage_attach_requirements'] ?? []),
        );
        if ($stageFormat === STAGE_FORMAT_IN_PERSON) {
            $stageAttachmentFields = [];
        }

        if ($title === '') $errors[] = 'Укажите название этапа.';
        if ($start === '' || $end === '') $errors[] = 'Укажите даты начала и окончания этапа.';
        if ($start !== '' && $end !== '' && $end <= $start) $errors[] = 'Дата окончания этапа должна быть позже даты начала.';
        if ($start !== '' && $start < (string) $contest['start_date']) $errors[] = 'Дата начала этапа не может быть раньше начала конкурса.';
        if ($end !== '' && $end > (string) $contest['end_date']) $errors[] = 'Дата окончания этапа не может быть позже окончания конкурса.';
        if ($deadline !== null && $deadline < $start) $errors[] = 'Дедлайн не может быть раньше начала этапа.';
        if ($deadline !== null && $deadline > $end) $errors[] = 'Дедлайн не может быть позже окончания этапа.';
        if ($externalEnabled === 1 && $externalLink === '') $errors[] = 'Включён показ ссылки — укажите URL.';
        if (!in_array($evaluationMode, ['all', 'assigned'], true)) $evaluationMode = 'all';
        if ($expertsPer < 1) $expertsPer = 1;

        foreach ($expertIds as $eid) {
            if (!contest_stage_expert_user_allowed($pdo, $contestId, $eid)) {
                $errors[] = 'Указан недопустимый эксперт этапа (не в пуле конкурса, неактивен или администратор сайта).';
                break;
            }
        }
        if ($evaluationMode === 'assigned') {
            if ($expertIds === []) {
                $errors[] = 'В режиме «назначенные эксперты» выберите хотя бы одного эксперта этапа.';
            } elseif ($expertsPer > count($expertIds)) {
                $errors[] = '«Сколько экспертов на одну работу» не может быть больше числа экспертов этапа (' . count($expertIds) . ').';
            }
        }

        $instructionPath = $stage['instruction_file_path'] ?? null;
        $prevInstruction = trim((string) ($stage['instruction_file_path'] ?? ''));
        if (!empty($_FILES['instruction_file']['name'])) {
            $uploaded = upload_file($_FILES['instruction_file'], 'stage_attachments');
            if ($uploaded === null) {
                $errors[] = 'Не удалось загрузить файл к этапу.';
            } else {
                $instructionPath = $uploaded;
            }
        }

        if (!$errors && !contest_is_parallel_stage_mode($contest)) {
            [$ok, $msg] = validate_stage_timeline(
                $pdo,
                $contestId,
                $stageId,
                (int) ($stage['sort_order'] ?? 0),
                $start,
                $end
            );
            if (!$ok) {
                $errors[] = $msg;
            }
        }

        if ($errors) {
            $pickerStageExpertsInitial = users_picker_initial($pdo, $expertIds);
        }

        if (!$errors) {
            $stmt = $pdo->prepare(
                'UPDATE stages
                 SET title=:title, description=:description, instruction_file_path=:instruction,
                     external_link=:external_link, external_link_enabled=:external_enabled,
                     start_date=:start_date, end_date=:end_date, submission_deadline=:deadline,
                     evaluation_mode=:evaluation_mode, experts_per_submission=:experts_per,
                     include_scores_in_final_ranking=:include_scores, phase_status=:phase_status,
                     stage_format=:stage_format
                 WHERE id=:id'
            );
            $stmt->execute([
                'id' => $stageId,
                'title' => $title,
                'description' => $description,
                'instruction' => $instructionPath,
                'external_link' => $externalLink,
                'external_enabled' => $externalEnabled,
                'start_date' => $start,
                'end_date' => $end,
                'deadline' => $deadline,
                'evaluation_mode' => $evaluationMode,
                'experts_per' => $expertsPer,
                'include_scores' => $includeScores,
                'phase_status' => $phaseStatus,
                'stage_format' => $stageFormat,
            ]);

            $pdo->prepare('DELETE FROM stage_experts WHERE stage_id = :sid')->execute(['sid' => $stageId]);
            $ins = $pdo->prepare('INSERT INTO stage_experts (stage_id, user_id) VALUES (:sid, :uid)');
            foreach ($expertIds as $eid) {
                $ins->execute(['sid' => $stageId, 'uid' => $eid]);
            }

            $pdo->prepare('DELETE FROM stage_attachment_fields WHERE stage_id = :sid')->execute(['sid' => $stageId]);
            $ins2 = $pdo->prepare(
                'INSERT INTO stage_attachment_fields (stage_id, sort_order, title, requirements)
                 VALUES (:stage_id, :sort_order, :title, :requirements)'
            );
            foreach ($stageAttachmentFields as $row) {
                $ins2->execute([
                    'stage_id' => $stageId,
                    'sort_order' => $row['sort_order'],
                    'title' => $row['title'],
                    'requirements' => $row['requirements'],
                ]);
            }

            if ($prevInstruction !== '' && (string) $instructionPath !== $prevInstruction) {
                iro_delete_public_upload($prevInstruction);
            }

            flash_set('success', 'Этап обновлён');
            redirect('/contest?id=' . $contestId);
        }
        $stStmt->execute(['id' => $stageId]);
        $stage = $stStmt->fetch() ?: $stage;
    }

    render('moderator/stage_form', [
        'title' => 'Редактировать этап',
        'mode' => 'edit',
        'errors' => $errors,
        'contest' => $contest,
        'stage' => $stage,
        'contest_id' => $contestId,
        'pickerStageExpertsInitial' => $pickerStageExpertsInitial,
        'stageAttachmentFields' => $stageAttachmentFields,
        'stage_has_evaluations' => $stageHasEvaluations,
        'breadcrumbs' => [
            ['label' => 'Главная', 'url' => '/'],
            ['label' => 'Конкурсы', 'url' => '/contests'],
            ['label' => (string) $contest['title'], 'url' => '/contest?id=' . $contestId],
            ['label' => (string) $stage['title'], 'url' => '/moderator/stage/detail?id=' . $stageId],
            ['label' => 'Редактировать этап', 'url' => null],
        ],
    ]);
    exit;
}

if ($uri === '/moderator/stage/detail') {
    $uid = require_login();
    $stageId = (int) ($_GET['id'] ?? 0);
    $stStmt = $pdo->prepare('SELECT * FROM stages WHERE id = :id LIMIT 1');
    $stStmt->execute(['id' => $stageId]);
    $stage = $stStmt->fetch();
    if (!$stage) {
        http_response_code(404);
        render('errors/404', ['title' => 'Не найдено', 'breadcrumbs' => iro_breadcrumbs_not_found()]);
        exit;
    }
    $contestId = (int) $stage['contest_id'];
    $contest = get_contest($pdo, $contestId);
    if (!$contest || !can_manage_contest($pdo, $uid, $contestId)) {
        flash_set('danger', 'Нет доступа');
        redirect('/contests');
    }

    stage_sync_phase_done_if_past($pdo, $contest, $stage);

    $evStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM evaluations e INNER JOIN submissions s ON s.id = e.submission_id WHERE s.stage_id = :sid'
    );
    $evStmt->execute(['sid' => $stageId]);
    $stageHasEvaluations = (int) $evStmt->fetchColumn() > 0;

    if (is_post() && isset($_POST['redistribute_stage_experts'])) {
        $postedSid = (int) ($_POST['stage_id'] ?? 0);
        if ($postedSid !== $stageId) {
            flash_set('danger', 'Неверный запрос.');
            redirect('/moderator/stage/detail?id=' . $stageId . '&tab=experts');
        }
        $red = contest_redistribute_assigned_experts_for_stage($pdo, $stageId, $contestId);
        if (!empty($red['ok'])) {
            flash_set(
                'success',
                'Эксперты перераспределены по работам этапа (обработано работ: ' . (int) ($red['updated'] ?? 0) . ').'
            );
        } else {
            flash_set('danger', (string) ($red['message'] ?? 'Не удалось перераспределить экспертов.'));
        }
        redirect('/moderator/stage/detail?id=' . $stageId . '&tab=experts');
    }

    if (is_post() && isset($_POST['in_person_seed_submissions'])) {
        $seed = contest_seed_in_person_submissions($pdo, $stageId, $contestId);
        if (!empty($seed['ok'])) {
            $seedMsg = 'Создано записей работ: ' . (int) ($seed['created'] ?? 0) . ' (для участников, у которых ещё не было записи на этом этапе).';
            if ((int) ($contest['require_explicit_stage_admission'] ?? 0) === 1) {
                $seedMsg .= ' Учитываются только участники, допущенные модератором к этому этапу.';
            }
            flash_set('success', $seedMsg);
        } else {
            flash_set('danger', (string) ($seed['message'] ?? 'Не удалось создать записи.'));
        }
        redirect('/moderator/stage/detail?id=' . $stageId);
    }

    if (is_post() && isset($_POST['advance_participants_stage_gate'])) {
        if ((int) ($contest['require_explicit_stage_admission'] ?? 0) !== 1) {
            flash_set('warning', 'Для этого конкурса не включён ручной допуск к этапам (настройка в карточке конкурса).');
        } else {
            $raw = $_POST['participant_admit'] ?? [];
            if (!is_array($raw)) {
                $raw = [];
            }
            $pks = array_values(array_unique(array_filter(array_map(static fn($x): int => (int) $x, $raw), static fn(int $x): bool => $x > 0)));
            if ($pks === []) {
                flash_set('warning', 'Отметьте хотя бы одного участника.');
            } else {
                $so = (int) $stage['sort_order'];
                $in = implode(',', array_fill(0, count($pks), '?'));
                $uq = $pdo->prepare(
                    'SELECT DISTINCT user_id FROM contest_participants WHERE id IN (' . $in . ') AND contest_id = ?'
                );
                $uq->execute(array_merge($pks, [$contestId]));
                $userIds = array_values(array_unique(array_map(static fn($x): int => (int) $x, $uq->fetchAll(PDO::FETCH_COLUMN, 0) ?: [])));
                if ($userIds === []) {
                    flash_set('warning', 'Не удалось определить участников по выбранным строкам.');
                } else {
                    $uin = implode(',', array_fill(0, count($userIds), '?'));
                    $sql = 'UPDATE contest_participants SET admitted_max_stage_order = ? WHERE contest_id = ? AND user_id IN (' . $uin . ') AND admitted_max_stage_order < ?';
                    $params = array_merge([$so, $contestId], $userIds, [$so]);
                    $upd = $pdo->prepare($sql);
                    $upd->execute($params);
                    $n = $upd->rowCount();
                    flash_set(
                        'success',
                        'Допущено к этапу «' . (string) $stage['title'] . '»: ' . $n . ' из отмеченных (остальные уже были допущены).'
                    );
                }
            }
        }
        redirect('/moderator/stage/detail?id=' . $stageId . '&tab=admission');
    }

    if (is_post() && isset($_POST['add_criterion_group'])) {
        $gt = trim((string) ($_POST['criterion_group_title'] ?? ''));
        if ($gt === '') {
            flash_set('danger', 'Укажите название группы критериев.');
        } else {
            $maxSoStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM criterion_groups WHERE stage_id = :sid');
            $maxSoStmt->execute(['sid' => $stageId]);
            $nextSo = (int) $maxSoStmt->fetchColumn() + 1;
            $pdo->prepare(
                'INSERT INTO criterion_groups (stage_id, title, sort_order) VALUES (:sid, :title, :so)'
            )->execute(['sid' => $stageId, 'title' => $gt, 'so' => $nextSo]);
            flash_set('success', 'Группа добавлена. Ниже можно добавить в неё критерии.');
        }
        redirect('/moderator/stage/detail?id=' . $stageId . '&tab=criteria');
    }

    if (is_post() && isset($_POST['delete_criterion_group'])) {
        $gidDel = (int) ($_POST['criterion_group_id'] ?? 0);
        if ($gidDel <= 0) {
            flash_set('danger', 'Не указана группа.');
        } else {
            $chkG = $pdo->prepare(
                'SELECT id FROM criterion_groups WHERE id = :id AND stage_id = :sid LIMIT 1'
            );
            $chkG->execute(['id' => $gidDel, 'sid' => $stageId]);
            if (!$chkG->fetch()) {
                flash_set('danger', 'Группа не найдена.');
            } else {
                $pdo->prepare(
                    'DELETE FROM criterion_groups WHERE id = :id AND stage_id = :sid'
                )->execute(['id' => $gidDel, 'sid' => $stageId]);
                flash_set(
                    'success',
                    'Группа удалена. Критерии из неё остаются на этапе без группы (баллы сохраняются).'
                );
            }
        }
        redirect('/moderator/stage/detail?id=' . $stageId . '&tab=criteria');
    }

    if (is_post() && isset($_POST['add_criterion'])) {
        $name = trim((string) ($_POST['criterion_name'] ?? ''));
        $min = (int) ($_POST['criterion_min_points'] ?? 0);
        $max = (int) ($_POST['criterion_max_points'] ?? 0);
        $weight = (int) ($_POST['criterion_weight'] ?? 100);
        $groupIdPost = (int) ($_POST['criterion_group_id'] ?? 0);
        $groupIdForCrit = $groupIdPost > 0 ? $groupIdPost : null;
        if ($groupIdForCrit !== null) {
            $gOk = $pdo->prepare(
                'SELECT 1 FROM criterion_groups WHERE id = :id AND stage_id = :sid LIMIT 1'
            );
            $gOk->execute(['id' => $groupIdForCrit, 'sid' => $stageId]);
            if (!$gOk->fetchColumn()) {
                flash_set('danger', 'Выбранная группа не найдена для этого этапа.');
                redirect('/moderator/stage/detail?id=' . $stageId . '&tab=criteria');
            }
        }
        if ($name === '' || $max <= 0) {
            flash_set('danger', 'Укажите название критерия и максимальные баллы (>0)');
        } elseif ($min < 0 || $min > $max) {
            flash_set('danger', 'Минимальный балл должен быть от 0 и не больше максимума.');
        } elseif ($weight < 1 || $weight > 100000) {
            flash_set('danger', 'Вес критерия: целое число от 1 до 100000 (100 — обычный вес).');
        } else {
            $sortStmt = $pdo->prepare(
                'SELECT COALESCE(MAX(sort_order), 0) FROM criteria WHERE stage_id = :sid AND group_id <=> :gid'
            );
            $sortStmt->bindValue(':sid', $stageId, PDO::PARAM_INT);
            if ($groupIdForCrit === null) {
                $sortStmt->bindValue(':gid', null, PDO::PARAM_NULL);
            } else {
                $sortStmt->bindValue(':gid', $groupIdForCrit, PDO::PARAM_INT);
            }
            $sortStmt->execute();
            $nextSort = (int) $sortStmt->fetchColumn() + 1;

            $ins = $pdo->prepare(
                'INSERT INTO criteria (stage_id, group_id, name, min_points, max_points, weight, sort_order)
                 VALUES (:stage_id, :group_id, :name, :min_points, :max_points, :weight, :sort_order)'
            );
            $ins->execute([
                'stage_id' => $stageId,
                'group_id' => $groupIdForCrit,
                'name' => $name,
                'min_points' => $min,
                'max_points' => $max,
                'weight' => $weight,
                'sort_order' => $nextSort,
            ]);
            flash_set('success', 'Критерий добавлен');
        }
        redirect('/moderator/stage/detail?id=' . $stageId . '&tab=criteria');
    }

    $criteria = contest_stage_criteria_ordered($pdo, $stageId);
    $criterionGroupsForForm = contest_stage_criterion_groups_list($pdo, $stageId);

    $participantsPendingAdmission = [];
    $participantsAdmittedToStage = [];
    $previousStage = null;
    if ((int) ($contest['require_explicit_stage_admission'] ?? 0) === 1) {
        $scoreMeans = contest_submission_score_means($pdo, $contestId);
        $so = (int) $stage['sort_order'];
        // Одна запись на пользователя: в БД может не быть UNIQUE(user_id, contest_id), тогда
        // разные строки дают «двойное» попадание в оба списка. Берём канонический допуск = MAX по строкам.
        $allCpStmt = $pdo->prepare(
            'SELECT cp.id, cp.user_id, cp.admitted_max_stage_order, u.email, u.last_name, u.first_name, u.middle_name
             FROM contest_participants cp
             JOIN users u ON u.id = cp.user_id
             WHERE cp.contest_id = :cid
             ORDER BY cp.user_id ASC, cp.admitted_max_stage_order DESC, cp.id DESC'
        );
        $allCpStmt->execute(['cid' => $contestId]);
        $allCpRows = $allCpStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $canonicalByUser = [];
        foreach ($allCpRows as $cpRow) {
            $uid = (int) $cpRow['user_id'];
            if (!isset($canonicalByUser[$uid])) {
                $canonicalByUser[$uid] = $cpRow;
            }
        }
        $sortAdmissionByName = static function (array $a, array $b): int {
            $ka = ($a['last_name'] ?? '') . "\0" . ($a['first_name'] ?? '') . "\0" . ($a['email'] ?? '');
            $kb = ($b['last_name'] ?? '') . "\0" . ($b['first_name'] ?? '') . "\0" . ($b['email'] ?? '');

            return $ka <=> $kb;
        };
        foreach ($canonicalByUser as $cpRow) {
            if ((int) $cpRow['admitted_max_stage_order'] < $so) {
                $participantsPendingAdmission[] = $cpRow;
            } else {
                $participantsAdmittedToStage[] = $cpRow;
            }
        }
        usort($participantsPendingAdmission, $sortAdmissionByName);
        usort($participantsAdmittedToStage, $sortAdmissionByName);
        $prevStmt = $pdo->prepare(
            'SELECT * FROM stages WHERE contest_id = :cid AND sort_order < :so ORDER BY sort_order DESC, id DESC LIMIT 1'
        );
        $prevStmt->execute(['cid' => $contestId, 'so' => $so]);
        $previousStage = $prevStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $enrichPrevStage = static function (array $rows) use ($pdo, $scoreMeans, $previousStage): array {
            if ($previousStage === null || $rows === []) {
                return $rows;
            }
            $prevStageId = (int) $previousStage['id'];
            foreach ($rows as $k => $row) {
                $us = (int) $row['user_id'];
                $subOne = $pdo->prepare(
                    'SELECT id FROM submissions WHERE stage_id = :sid AND user_id = :uid ORDER BY submission_date DESC, id DESC LIMIT 1'
                );
                $subOne->execute(['sid' => $prevStageId, 'uid' => $us]);
                $prevSid = (int) $subOne->fetchColumn();
                $rows[$k]['prev_submission_id'] = $prevSid > 0 ? $prevSid : null;
                $rows[$k]['prev_stage_score'] = $prevSid > 0 ? submission_score_mean($scoreMeans, $prevSid) : null;
            }

            return $rows;
        };
        $participantsPendingAdmission = $enrichPrevStage($participantsPendingAdmission);
        $participantsAdmittedToStage = $enrichPrevStage($participantsAdmittedToStage);
    }

    $tab = (string) ($_GET['tab'] ?? 'criteria');
    if (!in_array($tab, ['criteria', 'experts', 'admission'], true)) {
        $tab = 'criteria';
    }

    $evaluationMode = (string) ($stage['evaluation_mode'] ?? 'all');
    $expertsPerSubmission = max(1, (int) ($stage['experts_per_submission'] ?? 2));
    $stageExpertsStmt = $pdo->prepare(
        'SELECT u.id, u.email, u.first_name, u.last_name, u.middle_name
         FROM stage_experts se
         INNER JOIN users u ON u.id = se.user_id
         WHERE se.stage_id = :sid
         ORDER BY u.last_name, u.first_name, u.email, u.id'
    );
    $stageExpertsStmt->execute(['sid' => $stageId]);
    $stageExpertsForTab = $stageExpertsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stageDetailSubsPerPage = 50;
    $subsPage = max(1, (int) ($_GET['subs_page'] ?? 1));
    $stageSubmissionsRaw = [];
    $subsTotalForPager = 0;
    $subsPages = 1;
    if ($tab === 'experts') {
        $subsCntPager = $pdo->prepare('SELECT COUNT(*) FROM submissions WHERE stage_id = :sid');
        $subsCntPager->execute(['sid' => $stageId]);
        $subsTotalForPager = (int) $subsCntPager->fetchColumn();
        $subsPages = max(1, (int) ceil($subsTotalForPager / $stageDetailSubsPerPage));
        if ($subsPage > $subsPages) {
            $subsPage = $subsPages;
        }
        $subsOffset = ($subsPage - 1) * $stageDetailSubsPerPage;
        $subExStmt = $pdo->prepare(
            'SELECT s.id, s.title, s.user_id, u.email, u.first_name, u.last_name
             FROM submissions s
             INNER JOIN users u ON u.id = s.user_id
             WHERE s.stage_id = :sid
             ORDER BY s.submission_date DESC, s.id DESC
             LIMIT ' . (int) $stageDetailSubsPerPage . ' OFFSET ' . (int) $subsOffset
        );
        $subExStmt->execute(['sid' => $stageId]);
        $stageSubmissionsRaw = $subExStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    $assignedExpertsBySubmission = [];
    if ($evaluationMode === 'assigned' && $stageSubmissionsRaw !== []) {
        $sids = array_values(array_unique(array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $stageSubmissionsRaw)));
        $sids = array_values(array_filter($sids, static fn (int $id): bool => $id > 0));
        if ($sids !== []) {
            $ph = implode(',', array_fill(0, count($sids), '?'));
            $aStmt = $pdo->prepare(
                "SELECT sae.submission_id, u.email, u.last_name, u.first_name, u.middle_name
                 FROM submission_assigned_experts sae
                 INNER JOIN users u ON u.id = sae.user_id
                 WHERE sae.submission_id IN ($ph)
                 ORDER BY sae.submission_id, u.last_name, u.first_name, u.email"
            );
            $aStmt->execute($sids);
            foreach ($aStmt->fetchAll(PDO::FETCH_ASSOC) as $ar) {
                $sid = (int) ($ar['submission_id'] ?? 0);
                if ($sid > 0) {
                    $assignedExpertsBySubmission[$sid][] = user_display_name($ar);
                }
            }
        }
    }

    $stageSubmissionsExpertRows = [];
    foreach ($stageSubmissionsRaw as $sr) {
        $sid = (int) ($sr['id'] ?? 0);
        $stageSubmissionsExpertRows[] = [
            'id' => $sid,
            'title' => (string) ($sr['title'] ?? ''),
            'participant_label' => user_display_name([
                'last_name' => $sr['last_name'] ?? '',
                'first_name' => $sr['first_name'] ?? '',
                'email' => $sr['email'] ?? '',
            ]),
            'participant_email' => (string) ($sr['email'] ?? ''),
            'assigned_expert_names' => $assignedExpertsBySubmission[$sid] ?? [],
        ];
    }

    $pcCountStmt = $pdo->prepare('SELECT COUNT(*) FROM contest_participants WHERE contest_id = :cid');
    $pcCountStmt->execute(['cid' => $contestId]);
    $contestParticipantCount = (int) $pcCountStmt->fetchColumn();
    $stageSubCountStmt = $pdo->prepare('SELECT COUNT(*) FROM submissions WHERE stage_id = :sid');
    $stageSubCountStmt->execute(['sid' => $stageId]);
    $stageSubmissionCount = (int) $stageSubCountStmt->fetchColumn();

    render('moderator/stage_detail', [
        'title' => 'Настройки этапа',
        'breadcrumbs' => [
            ['label' => 'Главная', 'url' => '/'],
            ['label' => 'Конкурсы', 'url' => '/contests'],
            ['label' => (string) $contest['title'], 'url' => '/contest?id=' . (int) $contest['id']],
            ['label' => (string) $stage['title'], 'url' => null],
        ],
        'contest' => $contest,
        'stage' => $stage,
        'criteria' => $criteria,
        'criterion_groups' => $criterionGroupsForForm,
        'stage_has_evaluations' => $stageHasEvaluations,
        'participants_pending_admission' => $participantsPendingAdmission,
        'participants_admitted_to_stage' => $participantsAdmittedToStage,
        'previous_stage' => $previousStage,
        'evaluation_mode' => $evaluationMode,
        'experts_per_submission' => $expertsPerSubmission,
        'stage_experts_for_tab' => $stageExpertsForTab,
        'stage_submissions_expert_rows' => $stageSubmissionsExpertRows,
        'stage_detail_active_tab' => $tab,
        'stage_submissions_page' => $subsPage,
        'stage_submissions_pages' => $subsPages,
        'stage_submissions_total' => $subsTotalForPager,
        'stage_submissions_per_page' => $stageDetailSubsPerPage,
        'contest_participant_count' => $contestParticipantCount,
        'stage_submission_count' => $stageSubmissionCount,
    ]);
    exit;
}

if ($uri === '/moderator/criterion/delete') {
    $uid = require_login();
    if (!is_post()) {
        redirect('/contests');
    }
    $criterionId = (int) ($_POST['criterion_id'] ?? 0);
    $rowStmt = $pdo->prepare(
        'SELECT c.id, c.stage_id, s.contest_id
         FROM criteria c
         JOIN stages s ON s.id = c.stage_id
         WHERE c.id = :id
         LIMIT 1'
    );
    $rowStmt->execute(['id' => $criterionId]);
    $row = $rowStmt->fetch();
    if (!$row) {
        flash_set('danger', 'Критерий не найден');
        redirect('/contests');
    }
    $contestId = (int) $row['contest_id'];
    if (!can_manage_contest($pdo, $uid, $contestId)) {
        flash_set('danger', 'Нет доступа');
        redirect('/contests');
    }
    $stageIdForCrit = (int) $row['stage_id'];
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM evaluations WHERE criterion_id = :cid')->execute(['cid' => $criterionId]);
        $pdo->prepare('DELETE FROM criteria WHERE id = :id')->execute(['id' => $criterionId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash_set('danger', 'Не удалось удалить критерий.');
        redirect('/moderator/stage/detail?id=' . $stageIdForCrit . '&tab=criteria');
    }
    flash_set('success', 'Критерий удалён (связанные оценки экспертов по нему сняты)');
    redirect('/moderator/stage/detail?id=' . $stageIdForCrit . '&tab=criteria');
}

if ($uri === '/moderator/stage/delete') {
    $uid = require_login();
    if (!is_post()) {
        redirect('/contests');
    }
    $stageId = (int) ($_POST['stage_id'] ?? 0);
    $stStmt = $pdo->prepare('SELECT id, contest_id FROM stages WHERE id = :id LIMIT 1');
    $stStmt->execute(['id' => $stageId]);
    $stageRow = $stStmt->fetch(PDO::FETCH_ASSOC);
    if (!$stageRow) {
        flash_set('danger', 'Этап не найден');
        redirect('/contests');
    }
    $contestId = (int) $stageRow['contest_id'];
    if (!can_manage_contest($pdo, $uid, $contestId)) {
        flash_set('danger', 'Нет прав удалять этапы этого конкурса');
        redirect('/contests');
    }
    delete_stage_cascade($pdo, $stageId);
    refresh_awards_from_ranking_if_exist($pdo, $contestId);
    flash_set('success', 'Этап удалён');
    redirect('/contest?id=' . $contestId);
    exit;
}

if ($uri === '/moderator/award-pick-contest') {
    $uid = require_login();
    $contests = contests_eligible_for_awards($pdo, $uid);
    if ($contests === []) {
        http_response_code(403);
        render('errors/403', [
            'title' => 'Нет конкурсов',
            'message' => 'Нет конкурсов, для которых вы можете назначить место в итогах.',
            'breadcrumbs' => iro_breadcrumbs_forbidden(),
        ]);
        exit;
    }
    if (count($contests) === 1) {
        redirect('/award/create/' . (int) $contests[0]['id'] . '/');
        exit;
    }
    render('moderator/award_pick_contest', [
        'title' => 'Выберите конкурс',
        'contests' => $contests,
        'breadcrumbs' => [
            ['label' => 'Главная', 'url' => '/'],
            ['label' => 'Конкурсы', 'url' => '/contests'],
            ['label' => 'Назначить место в итогах', 'url' => null],
        ],
    ]);
    exit;
}

if ($uri === '/moderator/award-create') {
    $uid = require_login();
    $contestId = (int) ($_GET['contest_id'] ?? 0);
    $contest = $contestId > 0 ? get_contest($pdo, $contestId) : null;
    if (!$contest || !can_manage_contest($pdo, $uid, $contestId)) {
        http_response_code(403);
        render('errors/403', [
            'title' => 'Доступ запрещён',
            'message' => 'У вас нет прав назначать места в итогах по этому конкурсу.',
            'breadcrumbs' => iro_breadcrumbs_forbidden(),
        ]);
        exit;
    }
    $partStmt = $pdo->prepare(
        'SELECT cp.id, u.email, u.first_name, u.last_name, u.middle_name
         FROM contest_participants cp
         JOIN users u ON u.id = cp.user_id
         WHERE cp.contest_id = :cid
         ORDER BY u.last_name, u.first_name, u.email'
    );
    $partStmt->execute(['cid' => $contestId]);
    $participants = $partStmt->fetchAll(PDO::FETCH_ASSOC);
    $exStmt = $pdo->prepare(
        'SELECT a.*, u.email, u.first_name, u.last_name
         FROM awards a
         JOIN contest_participants cp ON cp.id = a.participant_id
         JOIN users u ON u.id = cp.user_id
         WHERE a.contest_id = :cid
         ORDER BY a.place, a.id'
    );
    $exStmt->execute(['cid' => $contestId]);
    $existingAwards = $exStmt->fetchAll(PDO::FETCH_ASSOC);

    $errors = [];
    if (is_post()) {
        $participantPk = (int) ($_POST['participant_id'] ?? 0);
        $place = (int) ($_POST['place'] ?? 0);
        $descr = (string) ($_POST['award_description'] ?? '');
        [$ok, $errs] = moderator_save_award($pdo, $contestId, $participantPk, $place, $descr, null, false);
        if ($ok) {
            flash_set('success', 'Место в итогах сохранено');
            redirect('/contest?id=' . $contestId);
            exit;
        }
        $errors = $errs;
    }

    render('moderator/award_form', [
        'title' => 'Назначить место — ' . (string) $contest['title'],
        'contest' => $contest,
        'participants' => $participants,
        'existing_awards' => $existingAwards,
        'participants_count' => count($participants),
        'editing_award' => null,
        'editing_participant_name' => null,
        'errors' => $errors,
        'form_participant_id' => (int) ($_POST['participant_id'] ?? 0),
        'form_place' => (int) ($_POST['place'] ?? 1),
        'form_description' => (string) ($_POST['award_description'] ?? ''),
        'breadcrumbs' => array_merge(
            iro_breadcrumbs_contest_nav((string) $contest['title'], (int) $contest['id']),
            [['label' => 'Назначить место в итогах', 'url' => null]]
        ),
    ]);
    exit;
}

if ($uri === '/moderator/award-edit') {
    $uid = require_login();
    $awardId = (int) ($_GET['award_id'] ?? 0);
    $awStmt = $pdo->prepare(
        'SELECT a.*, cp.user_id AS participant_user_id
         FROM awards a
         JOIN contest_participants cp ON cp.id = a.participant_id
         WHERE a.id = :id LIMIT 1'
    );
    $awStmt->execute(['id' => $awardId]);
    $award = $awStmt->fetch(PDO::FETCH_ASSOC);
    if (!$award) {
        http_response_code(404);
        render('errors/404', ['title' => 'Не найдено', 'flash' => $flash, 'user' => $user, 'breadcrumbs' => iro_breadcrumbs_not_found()]);
        exit;
    }
    $contestId = (int) $award['contest_id'];
    $contest = get_contest($pdo, $contestId);
    if (!$contest || !can_manage_contest($pdo, $uid, $contestId)) {
        http_response_code(403);
        render('errors/403', [
            'title' => 'Доступ запрещён',
            'message' => 'У вас нет прав редактировать итоги по этому конкурсу.',
            'breadcrumbs' => iro_breadcrumbs_forbidden(),
        ]);
        exit;
    }
    $partStmt = $pdo->prepare(
        'SELECT cp.id, u.email, u.first_name, u.last_name, u.middle_name
         FROM contest_participants cp
         JOIN users u ON u.id = cp.user_id
         WHERE cp.contest_id = :cid
         ORDER BY u.last_name, u.first_name, u.email'
    );
    $partStmt->execute(['cid' => $contestId]);
    $participants = $partStmt->fetchAll(PDO::FETCH_ASSOC);

    $errors = [];
    if (is_post()) {
        $participantPk = (int) $award['participant_id'];
        $place = (int) ($_POST['place'] ?? 0);
        $descr = (string) ($_POST['award_description'] ?? '');
        $release = isset($_POST['release_manual_ranking']);
        [$ok, $errs] = moderator_save_award($pdo, $contestId, $participantPk, $place, $descr, $awardId, $release);
        if ($ok) {
            flash_set('success', 'Запись в итогах обновлена');
            redirect('/award/create/' . $contestId . '/');
            exit;
        }
        $errors = $errs;
    }

    $puStmt = $pdo->prepare(
        'SELECT u.email, u.first_name, u.last_name, u.middle_name
         FROM contest_participants cp
         JOIN users u ON u.id = cp.user_id
         WHERE cp.id = :pid LIMIT 1'
    );
    $puStmt->execute(['pid' => (int) $award['participant_id']]);
    $editingPu = $puStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    render('moderator/award_form', [
        'title' => 'Изменить итог — ' . (string) $contest['title'],
        'contest' => $contest,
        'participants' => $participants,
        'existing_awards' => [],
        'participants_count' => count($participants),
        'editing_award' => $award,
        'editing_participant_name' => user_display_name($editingPu),
        'errors' => $errors,
        'form_participant_id' => (int) $award['participant_id'],
        'form_place' => (int) (is_post() ? ($_POST['place'] ?? $award['place']) : $award['place']),
        'form_description' => (string) (is_post() ? ($_POST['award_description'] ?? '') : ($award['award_description'] ?? '')),
        'breadcrumbs' => array_merge(
            iro_breadcrumbs_contest_nav((string) $contest['title'], (int) $contest['id']),
            [['label' => 'Изменить итог', 'url' => null]]
        ),
    ]);
    exit;
}

if ($uri === '/moderator/contest-stats') {
    $uid = require_login();
    $contestId = (int) ($_GET['contest_id'] ?? 0);
    $contest = $contestId > 0 ? get_contest($pdo, $contestId) : null;
    if (!$contest || !can_manage_contest($pdo, $uid, $contestId)) {
        http_response_code(403);
        render('errors/403', [
            'title' => 'Доступ запрещён',
            'message' => 'У вас нет доступа к статистике этого конкурса.',
            'breadcrumbs' => iro_breadcrumbs_forbidden(),
        ]);
        exit;
    }
    if ((int) ($contest['is_archived'] ?? 0) === 1) {
        flash_set('warning', 'Конкурс в архиве: статистика и повторная выгрузка недоступны.');
        redirect('/contests');
    }
    if (isset($_GET['export'])) {
        $lockResult = iro_stats_xlsx_try_lock($contestId);
        $locks = $lockResult['locks'];
        if ($locks === null) {
            $err = $lockResult['error'] ?? '';
            if ($err === 'busy_global') {
                flash_set(
                    'warning',
                    'Сейчас формируется другая Excel-выгрузка на сервере. Подождите несколько секунд и нажмите «Excel» снова.'
                );
            } elseif ($err === 'busy_contest') {
                flash_set(
                    'warning',
                    'По этому конкурсу уже выполняется выгрузка Excel. Дождитесь завершения или повторите позже.'
                );
            } else {
                flash_set('danger', 'Не удалось начать выгрузку. Проверьте права на каталог public/uploads/tmp.');
            }
            redirect('/contest/' . $contestId . '/stats/');
        }
        try {
            contest_stats_send_xlsx($pdo, $contest);
        } finally {
            iro_stats_xlsx_unlock($locks);
        }
        exit;
    }
    $ctx = contest_stats_build_context($pdo, $contest);
    render('moderator/contest_stats', array_merge($ctx, [
        'title' => 'Статистика: ' . (string) $contest['title'],
        'contest' => $contest,
        'breadcrumbs' => array_merge(
            iro_breadcrumbs_contest_nav((string) $contest['title'], (int) $contest['id']),
            [['label' => 'Статистика', 'url' => null]]
        ),
    ]));
    exit;
}

if ($uri === '/moderator/contest-report') {
    $uid = require_login();
    $contestId = (int) ($_GET['contest_id'] ?? 0);
    $contest = $contestId > 0 ? get_contest($pdo, $contestId) : null;
    if (!$contest || !can_manage_contest($pdo, $uid, $contestId)) {
        http_response_code(403);
        render('errors/403', [
            'title' => 'Доступ запрещён',
            'message' => 'У вас нет доступа к отчёту по этому конкурсу.',
            'breadcrumbs' => iro_breadcrumbs_forbidden(),
        ]);
        exit;
    }
    render('moderator/contest_report', [
        'title' => 'Отчёт по баллам',
        'contest' => $contest,
        'report' => contest_report_rows($pdo, $contestId),
        'breadcrumbs' => array_merge(
            iro_breadcrumbs_contest_nav((string) $contest['title'], (int) $contest['id']),
            [['label' => 'Отчёт по баллам', 'url' => null]]
        ),
    ]);
    exit;
}

if ($uri === '/moderator/contest-report-export') {
    $uid = require_login();
    $contestId = (int) ($_GET['contest_id'] ?? 0);
    $contest = $contestId > 0 ? get_contest($pdo, $contestId) : null;
    if (!$contest || !can_manage_contest($pdo, $uid, $contestId)) {
        http_response_code(403);
        render('errors/403', [
            'title' => 'Доступ запрещён',
            'message' => 'У вас нет доступа к выгрузке отчёта.',
            'breadcrumbs' => iro_breadcrumbs_forbidden(),
        ]);
        exit;
    }
    $lockResult = iro_stats_xlsx_try_lock($contestId);
    $locks = $lockResult['locks'];
    if ($locks === null) {
        $err = $lockResult['error'] ?? '';
        if ($err === 'busy_global') {
            flash_set(
                'warning',
                'Сейчас формируется другая Excel-выгрузка на сервере. Подождите несколько секунд и повторите.'
            );
        } elseif ($err === 'busy_contest') {
            flash_set(
                'warning',
                'По этому конкурсу уже выполняется выгрузка Excel. Дождитесь завершения или повторите позже.'
            );
        } else {
            flash_set('danger', 'Не удалось начать выгрузку. Проверьте права на каталог public/uploads/tmp.');
        }
        redirect('/contest/' . $contestId . '/report/');
    }
    try {
        contest_report_send_xlsx($pdo, $contest);
    } finally {
        iro_stats_xlsx_unlock($locks);
    }
    exit;
}

if ($uri === '/moderator/contest-awards-auto') {
    $contestId = (int) ($_GET['contest_id'] ?? 0);
    if (!is_post()) {
        if ($contestId > 0) {
            redirect('/contest/' . $contestId . '/stats/');
        }
        redirect('/contests');
    }
    $uid = require_login();
    $contest = $contestId > 0 ? get_contest($pdo, $contestId) : null;
    if (!$contest || !can_manage_contest($pdo, $uid, $contestId)) {
        flash_set('danger', 'Нет прав менять итоги (места) по этому конкурсу.');
        redirect('/contests');
    }
    $n = replace_awards_from_ranking($pdo, $contestId);
    flash_set(
        'success',
        'Итоговые места пересчитаны по сумме баллов этапов с отметкой «суммировать в итоговом зачёте». '
            . 'Записей в итогах: ' . $n . '. Места, закреплённые вручную, не изменены.'
    );
    redirect('/contest/' . $contestId . '/stats/');
    exit;
}

if ($uri === '/logout' && is_post()) {
    Auth::logout();
    flash_set('success', 'Вы вышли из системы');
    redirect('/login');
}

http_response_code(404);
render('errors/404', ['title' => 'Не найдено', 'flash' => $flash, 'user' => $user, 'breadcrumbs' => iro_breadcrumbs_not_found()]);
