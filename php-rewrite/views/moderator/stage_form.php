<?php
$isEdit = ($mode ?? 'create') === 'edit';
$c = $contest ?? [];
$st = $stage ?? [];
$attachRows = $stageAttachmentFields ?? [];
$evalMode = (string) ($st['evaluation_mode'] ?? ($_POST['evaluation_mode'] ?? 'all'));
$stageContestId = (int) ($contest_id ?? $c['id'] ?? 0);
$pickerStageJson = htmlspecialchars(json_encode($pickerStageExpertsInitial ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
$parallelContest = contest_is_parallel_stage_mode($c);
$phaseVal = (int) ($st['phase_status'] ?? ($_POST['phase_status'] ?? STAGE_PHASE_SOON));
$phaseVal = stage_phase_status_normalize($phaseVal);
$labels = stage_phase_status_labels();
$stageFormatVal = stage_format_normalize((int) ($st['stage_format'] ?? ($_POST['stage_format'] ?? STAGE_FORMAT_ONLINE)));
$stageHasEvaluations = !empty($stage_has_evaluations);
$dim = '';
$dimPhase = '';
?>

<section>
    <h1 class="iro-page-title mb-2"><?= $isEdit ? 'Редактирование этапа' : 'Создание этапа' ?></h1>
    <p class="iro-lead mb-4">Конкурс: <?= h((string) ($c['title'] ?? '')) ?></p>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?= h((string) $e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($stageHasEvaluations): ?>
        <div class="alert alert-warning mb-0">По этапу уже выставлены оценки экспертов. Настройки можно менять; при смене критериев, весов или диапазонов баллов уже введённые значения могут стать несопоставимыми с новыми правилами — при необходимости попросите экспертов обновить оценки.</div>
    <?php endif; ?>

    <div class="iro-panel iro-stage-form-panel shadow-sm border-0">
        <form method="post" enctype="multipart/form-data" class="iro-stage-form" action="<?= !empty($isEdit) && !empty($st['id']) ? h('/moderator/stage/edit?id=' . (int) $st['id']) : h('/moderator/stage/create?contest_id=' . (int) $stageContestId) ?>">
            <?= iro_csrf_field_html() ?>
            <?php if (!empty($isEdit) && !empty($st['id'])): ?>
                <input type="hidden" name="stage_id" value="<?= (int) $st['id'] ?>">
            <?php else: ?>
                <input type="hidden" name="contest_id" value="<?= (int) $stageContestId ?>">
            <?php endif; ?>

            <fieldset class="iro-stage-form-section">
                <legend>Основное</legend>
            <div class="mb-3">
                <label class="form-label" for="title">Название этапа</label>
                <input class="form-control" id="title" name="title" value="<?= h((string) ($st['title'] ?? ($_POST['title'] ?? ''))) ?>"<?= $dim ?><?= $dim === '' ? ' required' : '' ?>>
            </div>

            <div class="mb-0">
                <label class="form-label" for="description">Описание этапа</label>
                <textarea class="form-control" id="description" name="description" rows="5"<?= $dim ?>><?= h((string) ($st['description'] ?? ($_POST['description'] ?? ''))) ?></textarea>
            </div>
            </fieldset>

            <fieldset class="iro-stage-form-section">
                <legend>Формат этапа</legend>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="stage_format" id="stage_format_online" value="<?= (int) STAGE_FORMAT_ONLINE ?>"
                        <?= $stageFormatVal === STAGE_FORMAT_ONLINE ? 'checked' : '' ?><?= $dim ?>>
                    <label class="form-check-label" for="stage_format_online">Онлайн — участники подают работы через сайт (как обычно)</label>
                </div>
                <div class="form-check mb-0">
                    <input class="form-check-input" type="radio" name="stage_format" id="stage_format_in_person" value="<?= (int) STAGE_FORMAT_IN_PERSON ?>"
                        <?= $stageFormatVal === STAGE_FORMAT_IN_PERSON ? 'checked' : '' ?><?= $dim ?>>
                    <label class="form-check-label" for="stage_format_in_person">Очный — подача работ через сайт отключена; эксперты выставляют баллы в системе по критериям. На странице этапа можно создать записи работ для участников.</label>
                </div>
            </fieldset>

            <fieldset class="iro-stage-form-section">
                <legend>Материалы и внешняя ссылка</legend>
            <div class="mb-3">
                <label class="form-label" for="instruction_file">Файл (инструкция, шаблон и т.д.)</label>
                <input class="form-control" type="file" id="instruction_file" name="instruction_file"<?= $dim ?>>
                <?php if (!empty($st['instruction_file_path'])): ?>
                    <div class="small mt-2">
                        Текущий файл: <a href="<?= h((string) $st['instruction_file_path']) ?>" target="_blank">открыть</a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="row g-3 mb-0">
                <div class="col-md-8">
                    <label class="form-label" for="external_link">Ссылка (URL)</label>
                    <input class="form-control" id="external_link" name="external_link" placeholder="https://…" value="<?= h((string) ($st['external_link'] ?? ($_POST['external_link'] ?? ''))) ?>"<?= $dim ?>>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="external_link_enabled" name="external_link_enabled"
                            <?= (int)($st['external_link_enabled'] ?? ($_POST['external_link_enabled'] ?? 0)) === 1 ? 'checked' : '' ?><?= $dim ?>>
                        <label class="form-check-label" for="external_link_enabled">Показывать ссылку</label>
                    </div>
                </div>
            </div>
            </fieldset>

            <fieldset class="iro-stage-form-section">
                <legend>Сроки этапа</legend>
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label" for="start_date">Дата начала</label>
                    <input class="form-control" type="date" id="start_date" name="start_date" value="<?= h((string) ($st['start_date'] ?? ($_POST['start_date'] ?? ''))) ?>"<?= $dim ?><?= $dim === '' ? ' required' : '' ?>>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="end_date">Дата окончания</label>
                    <input class="form-control" type="date" id="end_date" name="end_date" value="<?= h((string) ($st['end_date'] ?? ($_POST['end_date'] ?? ''))) ?>"<?= $dim ?><?= $dim === '' ? ' required' : '' ?>>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="submission_deadline">Последний день приёма работ</label>
                    <input class="form-control" type="date" id="submission_deadline" name="submission_deadline" value="<?= h((string) ($st['submission_deadline'] ?? ($_POST['submission_deadline'] ?? ''))) ?>"<?= $dim ?>>
                    <div class="form-text">Пусто — приём до конца этапа.</div>
                </div>
            </div>

            <?php if ($parallelContest): ?>
                <div class="mb-3">
                    <label class="form-label" for="phase_status">Статус этапа</label>
                    <select class="form-select" id="phase_status" name="phase_status"<?= $dimPhase ?>>
                        <?php foreach ($labels as $pk => $pl): ?>
                            <option value="<?= (int) $pk ?>" <?= $phaseVal === (int) $pk ? 'selected' : '' ?>><?= h($pl) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Подача работы доступна только при статусе «Приём работ» (и в пределах дат конкурса). После даты окончания этапа статус считается «Завершён».</div>
                </div>
            <?php endif; ?>
            </fieldset>

            <fieldset class="iro-stage-form-section">
                <legend>Оценивание</legend>
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="include_scores_in_final_ranking" name="include_scores_in_final_ranking"
                        <?= (int)($st['include_scores_in_final_ranking'] ?? ($_POST['include_scores_in_final_ranking'] ?? 0)) === 1 ? 'checked' : '' ?><?= $dim ?>>
                    <label class="form-check-label" for="include_scores_in_final_ranking">Суммировать баллы этапа в итоговом зачёте</label>
                </div>
                <div class="form-text">
                    Если отметить этот пункт, баллы за этот этап (средняя оценка по экспертам с учётом весов критериев) входят в <strong>итоговую сумму</strong> для мест и отчётов.
                    Отметьте все этапы, которые должны участвовать в сумме, в том числе <strong>последний</strong> этап конкурса.
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label d-block mb-2">Как распределяются оценки</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="evaluation_mode" id="eval_all" value="all" <?= $evalMode === 'all' ? 'checked' : '' ?><?= $dim ?>>
                    <label class="form-check-label" for="eval_all">Все эксперты оценивают все работы</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="evaluation_mode" id="eval_assigned" value="assigned" <?= $evalMode === 'assigned' ? 'checked' : '' ?><?= $dim ?>>
                    <label class="form-check-label" for="eval_assigned">Несколько экспертов на каждую работу (из списка этапа)</label>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="experts_per_submission">Сколько экспертов на одну работу</label>
                <input class="form-control" type="number" min="1" max="99" id="experts_per_submission" name="experts_per_submission"
                       value="<?= (int) ($st['experts_per_submission'] ?? ($_POST['experts_per_submission'] ?? 2)) ?>"<?= $dim ?>>
                <div class="form-text">В режиме «назначенные эксперты» не больше числа выбранных экспертов этапа.</div>
            </div>

            <div class="mb-3">
                <label class="form-label">Кто оценивает работы на этом этапе</label>
                <div class="iro-user-picker-root<?= $dim ? ' opacity-50 pe-none user-select-none' : '' ?>"
                     data-iro-user-picker
                     data-picker-role="expert"
                     data-picker-name="expert_ids[]"
                     data-picker-contest-id="<?= $stageContestId ?>"
                     data-picker-for-stage="1"
                     data-picker-initial="<?= $pickerStageJson ?>"
                     <?= $dim ? 'aria-disabled="true"' : '' ?>>
                    <div class="iro-user-picker-chips d-flex flex-wrap gap-2 mb-2"></div>
                    <div class="iro-user-picker-hiddens" aria-hidden="true"></div>
                    <div class="position-relative">
                        <input type="text" class="form-control iro-user-picker-search" autocomplete="off"
                               placeholder="Фамилия, имя или email — или откройте список без ввода">
                        <div class="iro-user-picker-dropdown list-group list-group-flush" role="listbox" hidden></div>
                    </div>
                </div>
                <div class="form-text">
                    Поиск по всем подходящим пользователям (при непустом пуле конкурса — только из пула; администраторов сайта в списке нет).
                </div>
            </div>
            </fieldset>

            <fieldset class="iro-stage-form-section mb-0">
                <legend>Дополнительные файлы при подаче работы</legend>
            <?php if ($stageFormatVal === STAGE_FORMAT_IN_PERSON): ?>
                <p class="text-muted small mb-0">Для очного этапа поля вложений к подаче не используются (участники файлы не загружают).</p>
            <?php else: ?>
            <p class="text-muted small mb-3">Поля вложений, которые участник загружает при подаче работы на этот этап.</p>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                    <tr>
                        <th class="iro-th-col-order">Порядок</th>
                        <th>Название</th>
                        <th>Требования</th>
                    </tr>
                    </thead>
                    <tbody id="stage-attach-rows">
                    <?php if (!empty($attachRows)): ?>
                        <?php foreach ($attachRows as $i => $row): ?>
                            <tr>
                                <td><input type="hidden" name="stage_attach_id[]" value="<?= (int) ($row['id'] ?? 0) ?>"><input class="form-control" type="number" name="stage_attach_sort_order[]" value="<?= (int) ($row['sort_order'] ?? $i) ?>"<?= $dim ?>></td>
                                <td><input class="form-control" type="text" name="stage_attach_title[]" value="<?= h((string) ($row['title'] ?? '')) ?>"<?= $dim ?>></td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <input class="form-control" type="text" name="stage_attach_requirements[]" value="<?= h((string) ($row['requirements'] ?? '')) ?>"<?= $dim ?>>
                                        <button type="button" class="btn btn-outline-danger btn-sm stage-attach-remove"<?= $dim ?>>Удалить</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <tr>
                        <td><input type="hidden" name="stage_attach_id[]" value="0"><input class="form-control" type="number" name="stage_attach_sort_order[]" value="<?= count($attachRows) ?>"<?= $dim ?>></td>
                        <td><input class="form-control" type="text" name="stage_attach_title[]" value=""<?= $dim ?>></td>
                        <td>
                            <div class="d-flex gap-2">
                                <input class="form-control" type="text" name="stage_attach_requirements[]" value=""<?= $dim ?>>
                                <button type="button" class="btn btn-outline-danger btn-sm stage-attach-remove"<?= $dim ?>>Удалить</button>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="stage-attach-add"<?= $dim ?>>Добавить поле</button>
            <?php endif; ?>
            </fieldset>

            <div class="iro-stage-form-actions mt-2">
                <button type="submit" class="btn btn-primary rounded-pill px-4"><?= $isEdit ? 'Сохранить' : 'Создать этап' ?></button>
                <a href="/contest?id=<?= (int) ($c['id'] ?? 0) ?>" class="btn btn-outline-secondary rounded-pill px-4">Отмена</a>
            </div>
        </form>
    </div>
</section>

<script src="/assets/js/user-picker.js"></script>
<script>
    (function () {
        const btn = document.getElementById('stage-attach-add');
        const tbody = document.getElementById('stage-attach-rows');
        if (!btn || !tbody) return;
        tbody.addEventListener('click', function (e) {
            const t = e.target;
            if (t && t.classList && t.classList.contains('stage-attach-remove')) {
                const tr = t.closest('tr');
                if (tr) tr.remove();
            }
        });
        btn.addEventListener('click', function () {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input type="hidden" name="stage_attach_id[]" value="0"><input class="form-control" type="number" name="stage_attach_sort_order[]" value=""></td>
                <td><input class="form-control" type="text" name="stage_attach_title[]" value=""></td>
                <td>
                    <div class="d-flex gap-2">
                        <input class="form-control" type="text" name="stage_attach_requirements[]" value="">
                        <button type="button" class="btn btn-outline-danger btn-sm stage-attach-remove">Удалить</button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    })();
</script>

