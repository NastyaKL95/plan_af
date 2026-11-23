<?php
$c = $contest;
$reqAdm = (int) ($c['require_explicit_stage_admission'] ?? 0) === 1;
?>
<div>
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <h1 class="iro-page-title mb-0">Статистика: <?= h((string) $c['title']) ?></h1>
        <div class="iro-action-toolbar">
            <button type="button" class="btn btn-sm" data-bs-toggle="modal" data-bs-target="#detailedStatsModal">
                <i class="bi bi-table"></i> Подробно
            </button>
            <form method="post" action="/contest/<?= (int) $c['id'] ?>/awards/auto/" class="d-inline"
                  onsubmit="return confirm('Пересчитать места по сумме баллов этапов с отметкой «суммировать в итоговом зачёте»? Места, закреплённые вручную, сохранятся.');">
                <?= iro_csrf_field_html() ?>
                <button type="submit" class="btn btn-sm">
                    <i class="bi bi-trophy"></i> Места по баллам
                </button>
            </form>
            <a href="/award/create/<?= (int) $c['id'] ?>/" class="btn btn-sm">
                <i class="bi bi-award"></i> Назначить место
            </a>
            <a href="/contest/<?= (int) $c['id'] ?>/stats/?export=1" class="btn btn-sm">
                <i class="bi bi-download"></i> Excel
            </a>
            <a href="/contest/<?= (int) $c['id'] ?>/report/" class="btn btn-sm">
                <i class="bi bi-file-text"></i> Отчёт
            </a>
            <a href="/contest?id=<?= (int) $c['id'] ?>" class="btn btn-sm">
                <i class="bi bi-arrow-left"></i> К конкурсу
            </a>
        </div>
    </div>

    <p class="iro-lead mb-4">Краткая сводка по конкурсу и участникам.</p>

    <?php if (!empty($stats_hint_no_ranking_stages)): ?>
        <div class="alert alert-info mb-4" role="alert">
            Пока ни один этап не отмечен галочкой «Суммировать в итоговом зачёте», колонка итоговых баллов и пересчёт мест по сумме этапов дадут ноль для всех участников.
            Отметьте нужные этапы при <a href="/contest?id=<?= (int) $c['id'] ?>">редактировании этапов конкурса</a> или убедитесь, что это ожидаемо.
        </div>
    <?php endif; ?>

    <div class="iro-stat-grid mb-4">
        <div class="iro-stat-tile iro-stat-tile--primary">
            <div class="iro-stat-tile-label">Участники</div>
            <p class="iro-stat-tile-value"><?= (int) $contest_stats['participants_count'] ?></p>
        </div>
        <div class="iro-stat-tile iro-stat-tile--success">
            <div class="iro-stat-tile-label">Работы</div>
            <p class="iro-stat-tile-value"><?= (int) $contest_stats['submissions_count'] ?></p>
        </div>
        <div class="iro-stat-tile iro-stat-tile--navy">
            <div class="iro-stat-tile-label">Средний балл</div>
            <p class="iro-stat-tile-value"><?= h(number_format((float) $contest_stats['avg_score'], 1, '.', '')) ?></p>
        </div>
        <div class="iro-stat-tile iro-stat-tile--warning">
            <div class="iro-stat-tile-label">Этапы</div>
            <p class="iro-stat-tile-value"><?= (int) $contest_stats['stages_count'] ?></p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="iro-panel p-0 overflow-hidden mb-4 mb-md-0 h-100">
                <div class="p-3 border-bottom bg-light">
                    <h2 class="iro-block-title mb-0"><i class="bi bi-person-gear me-1"></i>Модераторы (<?= (int) $contest_stats['moderators_count'] ?>)</h2>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Модератор</th>
                            <th>Этапов</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($moderators as $moderator): ?>
                            <tr>
                                <td><?= h(user_display_name($moderator)) ?></td>
                                <td><?= (int) $moderator['stages_managed'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($moderators)): ?>
                            <tr><td colspan="2" class="text-muted">Нет данных</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="iro-panel p-0 overflow-hidden h-100">
                <div class="p-3 border-bottom bg-light">
                    <h2 class="iro-block-title mb-0"><i class="bi bi-person-check me-1"></i>Эксперты (<?= (int) $contest_stats['experts_count'] ?>)</h2>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Эксперт</th>
                            <th>Оценок</th>
                            <th>Ср. балл</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($experts as $expert): ?>
                            <tr>
                                <td><?= h(user_display_name($expert)) ?></td>
                                <td><?= (int) $expert['submissions_evaluated'] ?></td>
                                <td><?= h(number_format((float) $expert['avg_score'], 1, '.', '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($experts)): ?>
                            <tr><td colspan="3" class="text-muted">Нет данных</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="iro-panel p-0 overflow-hidden mt-4">
        <div class="p-3 border-bottom bg-light">
            <h2 class="iro-block-title mb-0"><i class="bi bi-people me-1"></i>Участники и места (<?= (int) $contest_stats['participants_count'] ?>)</h2>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th>Место</th>
                    <th>Участник</th>
                    <th>Регион</th>
                    <th>Учебное заведение</th>
                    <th>Работ</th>
                    <th title="Сумма баллов по этапам, отмеченным «суммировать в итоговом зачёте»">Баллы (итог)</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($participant_ranked as $row): ?>
                    <?php $p = $row['participant']; ?>
                    <tr>
                        <td>
                            <?= (int) $row['place'] ?>
                            <?php if (!empty($row['is_manual_override'])): ?>
                                <span class="badge text-bg-secondary small ms-1">вручную</span>
                            <?php endif; ?>
                            <?php if (!empty($row['place_mismatch'])): ?>
                                <span class="text-muted small d-block" title="Расчётное место по текущим правилам итогового зачёта">по баллам было бы <?= (int) $row['calculated_place'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= h(user_display_name($p)) ?></td>
                        <td><?= h((string) ($p['region'] ?? '') ?: '—') ?></td>
                        <td><?= h((string) ($p['school'] ?? '') ?: '—') ?></td>
                        <td><?= (int) $row['works_count'] ?></td>
                        <td><?= h(number_format((float) $row['total_points'], 1, '.', '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($participant_ranked)): ?>
                    <tr>
                        <td colspan="6" class="text-muted">Участников пока нет.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="small text-muted mb-0 p-3 border-top">Показывается сохранённое место, иначе расчётное. Кнопка «Места по баллам» обновляет только автоматические места. При равенстве баллов место общее.</p>
    </div>

    <div class="iro-panel p-0 overflow-hidden mt-4">
        <div class="p-3 border-bottom bg-light d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="iro-block-title mb-0"><i class="bi bi-file-earmark-check me-1"></i>Согласия и прохождение этапов</h2>
                <p class="small text-muted mb-0 mt-1">Статус согласия, допуск по этапам и факт подачи работы.</p>
            </div>
            <div class="iro-action-toolbar flex-shrink-0">
                <a href="/moderator/consents?contest_id=<?= (int) $c['id'] ?>" class="btn btn-sm">Согласия и файлы к работам</a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead>
                <tr>
                    <th class="text-nowrap">Участник</th>
                    <th class="text-nowrap">Согласие</th>
                    <th>Пояснение / дата</th>
                    <?php if ($reqAdm): ?>
                        <th class="text-center text-nowrap" title="Максимальный номер этапа, до которого участник допущен модератором">Допуск до&nbsp;№</th>
                    <?php endif; ?>
                    <?php foreach ($stages_ordered as $st): ?>
                        <th class="text-center small text-nowrap iro-th--min-5-5">
                            <span class="d-block fw-semibold">Этап <?= (int) $st['sort_order'] ?></span>
                            <span class="d-block fw-normal text-muted iro-mw-8rem"><?= h(mb_strimwidth((string) $st['title'], 0, 22, '…')) ?></span>
                        </th>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($participant_consent_progress as $row): ?>
                    <tr>
                        <td><?= h(user_display_name($row['user'])) ?></td>
                        <td>
                            <?php if ($row['consent_label'] === 'Одобрено'): ?>
                                <span class="badge bg-success">Одобрено</span>
                            <?php elseif ($row['consent_label'] === 'Отклонено'): ?>
                                <span class="badge bg-danger">Отклонено</span>
                            <?php elseif ($row['consent_label'] === 'На проверке'): ?>
                                <span class="badge bg-warning text-dark">На проверке</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Нет записи</span>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?= h((string) $row['consent_extra']) ?></td>
                        <?php if ($reqAdm): ?>
                            <td class="text-center"><?= (int) $row['admitted_max_stage_order'] ?></td>
                        <?php endif; ?>
                        <?php foreach ($row['stage_cells'] as $cell): ?>
                            <td class="text-center small">
                                <?php if ($cell['has_submission']): ?>
                                    <span class="text-success" title="<?= h((string) $cell['submission_title']) ?>">работа</span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                                <?php if ($reqAdm): ?>
                                    <br>
                                    <?php if ($cell['admitted_here']): ?>
                                        <span class="text-success">допуск</span>
                                    <?php else: ?>
                                        <span class="text-danger">нет доп.</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($participant_consent_progress)): ?>
                    <tr>
                        <td colspan="<?= (int) $consent_progress_colspan ?>" class="text-muted">Нет зачисленных участников.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="small text-muted mb-0 p-3 border-top">Этот блок выгружается в Excel на лист «Согласия и этапы».</p>
    </div>
</div>

<div class="modal fade" id="detailedStatsModal" tabindex="-1" aria-labelledby="detailedStatsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailedStatsModalLabel">Подробная статистика: <?= h((string) $c['title']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <h6 class="iro-block-title"><i class="bi bi-people me-1"></i>Участники</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-striped align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Участник</th>
                            <th>Этап</th>
                            <th>Баллы за этап</th>
                            <th>Эксперт</th>
                            <th>Критерий</th>
                            <th>Вес</th>
                            <th>Баллы</th>
                            <th>Всего работ</th>
                            <th>Всего баллов</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($detailed_participants as $participant): ?>
                            <?php foreach ($participant['stages'] as $stage): ?>
                                <?php if ($stage['evaluations'] !== []): ?>
                                    <?php foreach ($stage['evaluations'] as $eval): ?>
                                        <tr>
                                            <td><?= h(user_display_name($participant['user'])) ?></td>
                                            <td><?= h((string) $stage['stage']['title']) ?></td>
                                            <td><?= h((string) $stage['points']) ?></td>
                                            <td><?= h(user_display_name($eval['expert'])) ?></td>
                                            <td><?= h((string) $eval['criterion']['name']) ?></td>
                                            <td><?= (int) ($eval['criterion']['weight'] ?? 100) ?></td>
                                            <td><?= (int) $eval['points'] ?></td>
                                            <td><?= (int) $participant['works_count'] ?></td>
                                            <td><?= h(number_format((float) $participant['total_points'], 1, '.', '')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td><?= h(user_display_name($participant['user'])) ?></td>
                                        <td><?= h((string) $stage['stage']['title']) ?></td>
                                        <td><?= h((string) $stage['points']) ?></td>
                                        <td>—</td>
                                        <td>—</td>
                                        <td>—</td>
                                        <td>—</td>
                                        <td><?= (int) $participant['works_count'] ?></td>
                                        <td><?= h(number_format((float) $participant['total_points'], 1, '.', '')) ?></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <h6 class="iro-block-title mt-4"><i class="bi bi-person-check me-1"></i>Статистика экспертов</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Эксперт</th>
                            <th>Этапов</th>
                            <th>Оцененных работ</th>
                            <th>Средний балл</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($experts as $expert): ?>
                            <tr>
                                <td><?= h(user_display_name($expert)) ?></td>
                                <td><?= (int) $expert['stages_count'] ?></td>
                                <td><?= (int) $expert['submissions_evaluated'] ?></td>
                                <td><?= h(number_format((float) $expert['avg_score'], 1, '.', '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>
