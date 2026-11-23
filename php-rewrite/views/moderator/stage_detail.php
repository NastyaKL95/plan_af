<?php
$c = $contest ?? [];
$st = $stage ?? [];
$parallelForDetail = contest_is_parallel_stage_mode($c);
$activeTab = (string) ($stage_detail_active_tab ?? 'criteria');
if (!in_array($activeTab, ['criteria', 'experts', 'admission'], true)) {
    $activeTab = 'criteria';
}
$evalMode = (string) ($evaluation_mode ?? ($st['evaluation_mode'] ?? 'all'));
$expertsPer = (int) ($experts_per_submission ?? ($st['experts_per_submission'] ?? 2));
$criteria = is_array($criteria ?? null) ? $criteria : [];
$stageExpertList = is_array($stage_experts_for_tab ?? null) ? $stage_experts_for_tab : [];
$submissionExpertRows = is_array($stage_submissions_expert_rows ?? null) ? $stage_submissions_expert_rows : [];
$subsPageNum = max(1, (int) ($stage_submissions_page ?? 1));
$subsPagesTotal = max(1, (int) ($stage_submissions_pages ?? 1));
$subsTotalListed = (int) ($stage_submissions_total ?? 0);
$subsPerPage = max(1, (int) ($stage_submissions_per_page ?? 50));
$reqAdmission = (int) ($contest['require_explicit_stage_admission'] ?? 0) === 1;
$contestId = (int) ($c['id'] ?? 0);
$stageId = (int) ($st['id'] ?? 0);
$criterionGroupsList = is_array($criterion_groups ?? null) ? $criterion_groups : [];
$criteriaHasGrouped = false;
foreach ($criteria as $cChk) {
    if (!empty($cChk['group_id'])) {
        $criteriaHasGrouped = true;
        break;
    }
}
$tabQs = static function (string $t) use ($stageId): string {
    return '/moderator/stage/detail?id=' . $stageId . '&tab=' . rawurlencode($t);
};
$criteriaActive = $activeTab === 'criteria' ? ' active' : '';
$expertsActive = $activeTab === 'experts' ? ' active' : '';
$admissionActive = $activeTab === 'admission' ? ' active' : '';
$criteriaShow = $activeTab === 'criteria' ? ' show active' : '';
$expertsShow = $activeTab === 'experts' ? ' show active' : '';
$admissionShow = $activeTab === 'admission' ? ' show active' : '';
?>

<section class="iro-stage-detail-page">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <a class="btn btn-outline-secondary rounded-pill px-3" href="/contest?id=<?= $contestId ?>">← Назад к конкурсу</a>
        <a class="btn btn-primary rounded-pill px-4" href="/moderator/stage/edit?id=<?= $stageId ?>">Редактировать этап</a>
    </div>

    <h1 class="iro-page-title mb-2">Настройки этапа</h1>
    <p class="iro-lead mb-4">
        Конкурс: <?= h((string) $c['title']) ?> ·
        Этап: <?= h((string) $st['title']) ?>
    </p>

    <div class="iro-panel mb-3">
        <?php if (!empty($stage_has_evaluations)): ?>
            <div class="alert alert-warning mb-0">
                По этапу уже выставлены оценки экспертов — настройки этапа и критерии по-прежнему можно менять.
                Удаление критерия снимает все баллы по нему; добавление нового критерия не заполняет его за прошлые оценки автоматически.
            </div>
        <?php else: ?>
            <p class="text-muted small mb-0">Критерии и режим оценивания задаются здесь; состав экспертов на этапе — в форме редактирования этапа.</p>
        <?php endif; ?>
    </div>

    <?php if (!empty($stage) && stage_is_in_person($stage)): ?>
        <?php
        $cpc = (int) ($contest_participant_count ?? 0);
        $ssc = (int) ($stage_submission_count ?? 0);
        ?>
        <div class="iro-panel border-primary border-opacity-25 mb-3">
            <h2 class="h6 mb-2"><i class="bi bi-people me-1"></i>Очный этап</h2>
            <p class="small text-muted mb-2 mb-md-3">
                Участники не подают работы на сайте. Чтобы эксперты могли открыть оценку в системе, для каждого участника нужна запись работы на этапе.
                Нажмите кнопку ниже — будут созданы недостающие записи с заглушкой вместо файла (название «Очный этап»).
            </p>
            <p class="small mb-2">
                Участников в конкурсе: <strong><?= $cpc ?></strong>.
                Записей работ на этом этапе: <strong><?= $ssc ?></strong><?= $cpc > 0 && $ssc < $cpc ? ' <span class="text-warning">(не у всех есть запись)</span>' : '' ?>.
            </p>
            <form method="post" class="d-inline" onsubmit="return confirm('Создать недостающие записи работ для участников без записи на этом этапе?');">
                <?= iro_csrf_field_html() ?>
                <input type="hidden" name="in_person_seed_submissions" value="1">
                <button type="submit" class="btn btn-primary btn-sm rounded-pill">
                    Создать записи работ для участников
                </button>
            </form>
            <?php if (!empty($stage_has_evaluations)): ?>
                <p class="small text-muted mb-0 mt-2">Уже есть оценки — для новых участников записи работ по кнопке всё ещё можно добавить.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <ul class="nav nav-tabs iro-stage-detail-tabs mb-3 flex-wrap gap-1" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link<?= $criteriaActive ?>" href="<?= h($tabQs('criteria')) ?>" role="tab" aria-selected="<?= $activeTab === 'criteria' ? 'true' : 'false' ?>">Критерии</a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link<?= $expertsActive ?>" href="<?= h($tabQs('experts')) ?>" role="tab" aria-selected="<?= $activeTab === 'experts' ? 'true' : 'false' ?>">Эксперты</a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link<?= $admissionActive ?>" href="<?= h($tabQs('admission')) ?>" role="tab" aria-selected="<?= $activeTab === 'admission' ? 'true' : 'false' ?>">Допуск к следующему этапу</a>
        </li>
    </ul>

    <div class="tab-content iro-stage-detail-tab-panels">
        <div class="tab-pane fade<?= $criteriaShow ?>" id="iro-stage-tab-criteria" role="tabpanel" aria-labelledby="iro-stage-tab-criteria" tabindex="0">
            <div class="iro-panel mb-4">
                <h2 class="iro-block-title">Критерии оценки</h2>
                <p class="small text-muted mb-3">
                    Сначала при необходимости создайте <strong>группу</strong> (раздел), затем добавляйте в неё или в общий список <strong>критерии</strong> — название, диапазон баллов и вес.
                </p>

                <?php if (empty($criteria)): ?>
                    <p class="text-muted mb-0">Критерии ещё не добавлены.</p>
                <?php else: ?>
                    <div class="table-responsive iro-table-responsive-sticky">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Название</th>
                                <th class="text-end iro-th-criterion-min">Мин.</th>
                                <th class="text-end iro-th-criterion-max">Макс.</th>
                                <th class="text-end iro-th-criterion-weight">Вес</th>
                                <th class="text-end iro-th-criterion-actions">Действия</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            $prevCritSection = '';
                            foreach ($criteria as $cr):
                                $gIdRow = isset($cr['group_id']) ? (int) $cr['group_id'] : 0;
                                $secKey = $gIdRow > 0 ? 'g' . $gIdRow : 'u';
                                if ($secKey !== $prevCritSection):
                                    $prevCritSection = $secKey;
                                    ?>
                                <tr class="table-secondary">
                                    <td colspan="5" class="py-2">
                                        <?php if ($gIdRow > 0): ?>
                                            <span class="fw-semibold"><?= h((string) ($cr['group_title'] ?? 'Группа')) ?></span>
                                            <form method="post" class="d-inline float-end"
                                                  onsubmit="return confirm('Удалить группу? Критерии останутся на этапе без группы (оценки не удаляются).');">
                                                <?= iro_csrf_field_html() ?>
                                                <input type="hidden" name="delete_criterion_group" value="1">
                                                <input type="hidden" name="criterion_group_id" value="<?= $gIdRow ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Удалить группу</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="fw-semibold"><?= $criteriaHasGrouped ? 'Без группы' : 'Критерии' ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td class="fw-semibold"><?= h((string) $cr['name']) ?></td>
                                    <td class="text-end"><?= (int) ($cr['min_points'] ?? 0) ?></td>
                                    <td class="text-end"><?= (int) $cr['max_points'] ?></td>
                                    <td class="text-end"><?= (int) ($cr['weight'] ?? 100) ?></td>
                                    <td class="text-end">
                                        <form method="post" action="/moderator/criterion/delete" class="d-inline"
                                              onsubmit="return confirm('Удалить этот критерий? Все выставленные по нему баллы экспертов будут удалены.');">
                                            <?= iro_csrf_field_html() ?>
                                            <input type="hidden" name="criterion_id" value="<?= (int) $cr['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="iro-panel mb-3 py-3">
                <h2 class="h6 mb-2">Добавить группу критериев</h2>
                <form method="post" class="row g-2 align-items-end">
                    <?= iro_csrf_field_html() ?>
                    <input type="hidden" name="add_criterion_group" value="1">
                    <div class="col min-w-0">
                        <label class="form-label small mb-0" for="criterion_group_title">Название раздела</label>
                        <input class="form-control form-control-sm" id="criterion_group_title" name="criterion_group_title" required maxlength="255" placeholder="Теория, практика…" title="Раздел для группировки критериев на форме оценки">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-outline-primary btn-sm rounded-pill px-3">Создать группу</button>
                    </div>
                </form>
            </div>

            <div class="iro-panel py-3">
                <h2 class="h6 mb-2">Добавить критерий</h2>
                <form method="post">
                    <?= iro_csrf_field_html() ?>
                    <input type="hidden" name="add_criterion" value="1">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-xl-2 col-lg-3">
                            <label class="form-label small mb-0" for="criterion_group_id">Группа</label>
                            <select class="form-select form-select-sm" id="criterion_group_id" name="criterion_group_id" title="<?= h($criterionGroupsList === [] ? 'Создайте группу блоком выше, чтобы выбрать раздел' : 'Раздел для отображения на форме оценки') ?>">
                                <option value="0">Без группы</option>
                                <?php foreach ($criterionGroupsList as $cg): ?>
                                    <option value="<?= (int) $cg['id'] ?>"><?= h((string) $cg['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-lg">
                            <label class="form-label small mb-0" for="criterion_name">Название</label>
                            <input class="form-control form-control-sm" id="criterion_name" name="criterion_name" required>
                        </div>
                        <div class="col-4 col-sm-auto iro-criterion-field-num">
                            <label class="form-label small mb-0 text-nowrap" for="criterion_min_points">Мин.</label>
                            <input class="form-control form-control-sm" id="criterion_min_points" name="criterion_min_points" type="number" min="0" max="100000" value="0">
                        </div>
                        <div class="col-4 col-sm-auto iro-criterion-field-num">
                            <label class="form-label small mb-0 text-nowrap" for="criterion_max_points">Макс.</label>
                            <input class="form-control form-control-sm" id="criterion_max_points" name="criterion_max_points" type="number" min="1" max="100000" required>
                        </div>
                        <div class="col-4 col-sm-auto iro-criterion-field-num">
                            <label class="form-label small mb-0 text-nowrap" for="criterion_weight">Вес</label>
                            <input class="form-control form-control-sm" id="criterion_weight" name="criterion_weight" type="number" min="1" max="100000" value="100" required title="100 — как раньше; 200 — вдвое сильнее влияет на сумму баллов эксперта">
                        </div>
                        <div class="col-12 col-sm-auto">
                            <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4">Добавить</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="tab-pane fade<?= $expertsShow ?>" id="iro-stage-tab-experts" role="tabpanel" aria-labelledby="iro-stage-tab-experts" tabindex="0">
            <div class="iro-panel mb-4">
                <h2 class="iro-block-title">Распределение экспертов по работам</h2>

                <?php if ($evalMode === 'all'): ?>
                    <p class="mb-2">
                        Режим этапа: <strong>все эксперты этапа оценивают все работы</strong>.
                        Отдельное назначение на каждую работу не используется.
                    </p>
                    <?php if ($stageExpertList === []): ?>
                        <div class="alert alert-warning mb-0">В списке этапа нет экспертов — добавьте их при <a href="/moderator/stage/edit?id=<?= $stageId ?>">редактировании этапа</a>.</div>
                    <?php else: ?>
                        <p class="small text-muted mb-3">Эксперты этапа (<?= count($stageExpertList) ?>): <?= h(implode(', ', array_map(static fn (array $e): string => user_display_name($e), $stageExpertList))) ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="mb-2">
                        Режим этапа: <strong>несколько экспертов на каждую работу</strong>.
                        При подаче работы система может автоматически назначить до <?= (int) $expertsPer ?> экспертов из списка этапа (без администраторов сайта в автоназначении).
                        При необходимости список по каждой работе можно скорректировать вручную.
                    </p>
                    <?php if ($stageExpertList === []): ?>
                        <div class="alert alert-warning mb-3">Эксперты этапа не заданы — сначала укажите их в <a href="/moderator/stage/edit?id=<?= $stageId ?>">настройках этапа</a>.</div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($evalMode === 'assigned' && $stageExpertList !== [] && (int) ($stage_submission_count ?? 0) > 0): ?>
                    <form method="post" class="mb-3"
                          action="<?= h('/moderator/stage/detail?id=' . $stageId . '&tab=' . rawurlencode('experts')) ?>"
                          onsubmit="return confirm('Пересчитать назначения экспертов по всем работам этапа по текущему алгоритму балансировки? Ручные правки списков по работам будут заменены.');">
                        <?= iro_csrf_field_html() ?>
                        <input type="hidden" name="stage_id" value="<?= $stageId ?>">
                        <input type="hidden" name="redistribute_stage_experts" value="1">
                        <button type="submit" class="btn btn-outline-primary btn-sm rounded-pill">
                            Перераспределить экспертов по работам этапа
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($submissionExpertRows === []): ?>
                    <p class="text-muted mb-0">Пока нет поданных работ на этом этапе.</p>
                <?php else: ?>
                    <div class="table-responsive iro-table-responsive-sticky">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Участник</th>
                                <th>Работа</th>
                                <th>Эксперты по работе</th>
                                <?php if ($evalMode === 'assigned'): ?>
                                    <th class="text-end text-nowrap">Действия</th>
                                <?php endif; ?>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($submissionExpertRows as $row): ?>
                                <tr>
                                    <td>
                                        <span class="fw-semibold"><?= h((string) $row['participant_label']) ?></span>
                                        <span class="d-block small text-muted text-break"><?= h((string) $row['participant_email']) ?></span>
                                    </td>
                                    <td><?= h((string) $row['title']) ?></td>
                                    <td>
                                        <?php if ($evalMode === 'all'): ?>
                                            <span class="text-muted small">Все эксперты этапа</span>
                                        <?php else: ?>
                                            <?php
                                            $ae = $row['assigned_expert_names'] ?? $row['assigned_emails'] ?? [];
                                            $ae = is_array($ae) ? array_values(array_filter($ae, static fn ($e): bool => trim((string) $e) !== '')) : [];
                                            ?>
                                            <?php if ($ae === []): ?>
                                                <span class="badge text-bg-secondary">не назначены</span>
                                            <?php else: ?>
                                                <ul class="list-unstyled mb-0 small">
                                                    <?php foreach ($ae as $nameLine): ?>
                                                        <li><?= h((string) $nameLine) ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($evalMode === 'assigned'): ?>
                                        <td class="text-end">
                                            <a class="btn btn-sm btn-outline-primary rounded-pill" href="/moderator/assign-experts?submission_id=<?= (int) $row['id'] ?>">Назначить вручную</a>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($activeTab === 'experts' && $subsPagesTotal > 1): ?>
                        <?php
                        $pagerSubsItems = iro_pagination_pages($subsPageNum, $subsPagesTotal, 2);
                        $pagerBaseSubs = '/moderator/stage/detail?id=' . $stageId . '&tab=experts';
                        $mkSubsLink = static function (int $p) use ($pagerBaseSubs): string {
                            return $pagerBaseSubs . '&subs_page=' . $p;
                        };
                        ?>
                        <nav class="mt-3 d-flex flex-wrap align-items-center gap-3" aria-label="Страницы списка работ">
                            <span class="small text-muted">
                                Работ на этапе: <?= (int) ($stage_submission_count ?? 0) ?>.
                                Страница <?= $subsPageNum ?> из <?= $subsPagesTotal ?>
                                (до <?= $subsPerPage ?> на странице).
                            </span>
                            <ul class="pagination pagination-sm mb-0 flex-wrap">
                                <?php if ($subsPageNum > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?= h($mkSubsLink($subsPageNum - 1)) ?>" rel="prev">Назад</a>
                                    </li>
                                <?php endif; ?>
                                <?php foreach ($pagerSubsItems as $pit): ?>
                                    <?php if ($pit === 'ellipsis'): ?>
                                        <li class="page-item disabled" aria-hidden="true"><span class="page-link">&hellip;</span></li>
                                    <?php else: ?>
                                        <li class="page-item<?= (int) $pit === $subsPageNum ? ' active' : '' ?>">
                                            <?php if ((int) $pit === $subsPageNum): ?>
                                                <span class="page-link"><?= (int) $pit ?></span>
                                            <?php else: ?>
                                                <a class="page-link" href="<?= h($mkSubsLink((int) $pit)) ?>"><?= (int) $pit ?></a>
                                            <?php endif; ?>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if ($subsPageNum < $subsPagesTotal): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?= h($mkSubsLink($subsPageNum + 1)) ?>" rel="next">Вперёд</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                    <p class="small text-muted mt-2 mb-0">
                        <a href="/expert/submissions?stage_id=<?= $stageId ?>">Открыть список работ этапа в кабинете эксперта</a> (удобно для выставления оценок).
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="tab-pane fade<?= $admissionShow ?>" id="iro-stage-tab-admission" role="tabpanel" aria-labelledby="iro-stage-tab-admission" tabindex="0">
            <?php if (!$reqAdmission): ?>
                <div class="iro-panel">
                    <h2 class="iro-block-title">Допуск к следующему этапу</h2>
                    <p class="text-muted mb-0">
                        Для этого конкурса не включён ручной допуск к этапам. Участники, чьё согласие одобрено, могут подавать работы согласно срокам и режиму этапов без отдельного «допуска» модератором.
                        Включить ручной допуск может администратор сайта в настройках конкурса.
                    </p>
                </div>
            <?php else: ?>
                <div class="iro-panel">
                    <h2 class="iro-block-title">Допуск участников к этому этапу</h2>
                    <p class="small text-muted mb-4">
                        Участники без допуска не увидят кнопку «Подать работу», пока по календарю активен этот этап (порядок этапа: <?= (int) ($st['sort_order'] ?? 0) ?>).
                        Отметьте прошедших отбор и нажмите кнопку — им будет разрешена подача работы именно на «<?= h((string) $st['title']) ?>».
                    </p>

                    <?php
                    $admittedList = is_array($participants_admitted_to_stage ?? null) ? $participants_admitted_to_stage : [];
                    ?>

                    <h3 class="h6 text-uppercase text-muted fw-semibold mb-2 iro-subhead-eyebrow">Ещё не допущены к этому этапу</h3>
                    <?php if (!empty($participants_pending_admission)): ?>
                        <form method="post" class="mb-4">
                            <?= iro_csrf_field_html() ?>
                            <input type="hidden" name="advance_participants_stage_gate" value="1">
                            <div class="table-responsive iro-table-responsive-sticky mb-3">
                                <table class="table table-striped align-middle mb-0">
                                    <thead>
                                    <tr>
                                        <th scope="col" class="text-nowrap iro-th--w3">
                                            <input type="checkbox" class="form-check-input" id="iro-admit-all" aria-label="Выбрать всех" title="Выбрать всех">
                                        </th>
                                        <th scope="col">Участник</th>
                                        <th scope="col" class="text-muted small">Допуск сейчас до этапа №</th>
                                        <th scope="col" class="text-muted small">
                                            Баллы за предыдущий этап<?php if (!empty($previous_stage)): ?> («<?= h((string) ($previous_stage['title'] ?? '')) ?>»)<?php endif; ?>
                                        </th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($participants_pending_admission as $p): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="form-check-input iro-admit-cb" name="participant_admit[]" value="<?= (int) $p['id'] ?>" id="admit-<?= (int) $p['id'] ?>">
                                            </td>
                                            <td>
                                                <label class="mb-0 fw-normal" for="admit-<?= (int) $p['id'] ?>"><?= h(user_display_name(['last_name' => $p['last_name'] ?? '', 'first_name' => $p['first_name'] ?? '', 'middle_name' => $p['middle_name'] ?? '', 'email' => $p['email'] ?? ''])) ?> <span class="text-muted small"><?= h((string) ($p['email'] ?? '')) ?></span></label>
                                            </td>
                                            <td class="text-muted"><?= (int) ($p['admitted_max_stage_order'] ?? 0) ?></td>
                                            <td class="text-muted">
                                                <?php if (!empty($previous_stage)): ?>
                                                    <?php if (!empty($p['prev_submission_id'])): ?>
                                                        <?= number_format((float) ($p['prev_stage_score'] ?? 0), 2, ',', ' ') ?>
                                                    <?php else: ?>
                                                        нет работы
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <button type="submit" class="btn btn-primary rounded-pill px-4">Допустить выбранных к этому этапу</button>
                        </form>
                        <script>
                        (function () {
                            var master = document.getElementById('iro-admit-all');
                            if (!master) return;
                            master.addEventListener('change', function () {
                                document.querySelectorAll('.iro-admit-cb').forEach(function (cb) { cb.checked = master.checked; });
                            });
                        })();
                        </script>
                    <?php else: ?>
                        <p class="text-muted mb-4">Нет участников без допуска к этому этапу.</p>
                    <?php endif; ?>

                    <h3 class="h6 text-uppercase text-muted fw-semibold mb-2 iro-subhead-eyebrow">Уже допущены к этому этапу</h3>
                    <?php if (!empty($admittedList)): ?>
                        <div class="table-responsive iro-table-responsive-sticky">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead>
                                <tr>
                                    <th scope="col">Участник</th>
                                    <th scope="col" class="text-muted small">Допуск до этапа №</th>
                                    <th scope="col" class="text-muted small">
                                        Баллы за предыдущий этап<?php if (!empty($previous_stage)): ?> («<?= h((string) ($previous_stage['title'] ?? '')) ?>»)<?php endif; ?>
                                    </th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($admittedList as $p): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-semibold"><?= h(user_display_name(['last_name' => $p['last_name'] ?? '', 'first_name' => $p['first_name'] ?? '', 'middle_name' => $p['middle_name'] ?? '', 'email' => $p['email'] ?? ''])) ?></span>
                                            <span class="d-block small text-muted text-break"><?= h((string) ($p['email'] ?? '')) ?></span>
                                        </td>
                                        <td class="text-muted"><?= (int) ($p['admitted_max_stage_order'] ?? 0) ?></td>
                                        <td class="text-muted">
                                            <?php if (!empty($previous_stage)): ?>
                                                <?php if (!empty($p['prev_submission_id'])): ?>
                                                    <?= number_format((float) ($p['prev_stage_score'] ?? 0), 2, ',', ' ') ?>
                                                <?php else: ?>
                                                    нет работы
                                                <?php endif; ?>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">Пока ни у кого нет допуска к этому этапу — после отметки в списке выше участники появятся здесь.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
