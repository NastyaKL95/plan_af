<?php
$participantUi = !empty($isParticipant) && (int) ($contest['is_external'] ?? 0) === 0;
$stageConductParallel = contest_is_parallel_stage_mode($contest);
$labelsPhase = stage_phase_status_labels();
?>

<section class="iro-contest-show <?= $participantUi ? 'iro-contest-show--participant' : '' ?>">
    <?php if ((int) ($contest['is_external'] ?? 0) === 1): ?>
        <?php $phaseExternal = contest_calendar_phase($contest, $today ?? date('Y-m-d')); ?>
        <div class="iro-panel">
            <h1 class="iro-page-title mb-2"><?= h((string) $contest['title']) ?></h1>
            <p class="text-muted"><?= h((string) $contest['start_date']) ?> — <?= h((string) $contest['end_date']) ?></p>
            <div class="iro-contest-badges-row">
                <span class="iro-badge-status <?= h($phaseExternal['badge_class']) ?>"><?= h($phaseExternal['label']) ?></span>
                <span class="iro-badge-status iro-status-external">На другом сайте</span>
            </div>
            <p><?= nl2br(h((string) $contest['description'])) ?></p>

            <?php if (trim((string) ($contest['external_url'] ?? '')) !== ''): ?>
                <div class="d-flex flex-wrap gap-2 align-items-center my-4">
                    <a class="btn btn-primary btn-lg" href="<?= h((string) $contest['external_url']) ?>" target="_blank" rel="noopener noreferrer">
                        Перейти к конкурсу
                    </a>
                    <span class="text-muted small"><?= h((string) $contest['external_url']) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($canManage)): ?>
                <div class="iro-action-toolbar mb-0">
                    <a class="btn btn-sm btn-outline-secondary" href="/admin/contests/edit-link?id=<?= (int) $contest['id'] ?>">Редактировать в админке</a>
                </div>
            <?php endif; ?>
        </div>
        <?php return; ?>
    <?php endif; ?>

    <?php if (!empty($canManage)): ?>
        <div class="iro-panel mb-4">
            <div class="iro-action-toolbar iro-action-toolbar--wrap mb-0" role="toolbar" aria-label="Действия модератора по конкурсу">
                <div class="d-flex flex-wrap gap-2 align-items-center" role="group" aria-label="Быстрые ссылки модератора">
                    <a class="btn btn-sm btn-outline-secondary rounded-pill px-3" href="/moderator/consents?contest_id=<?= (int) $contest['id'] ?>">Согласия и файлы к работам</a>
                    <a class="btn btn-sm btn-outline-secondary rounded-pill px-3" href="/contest/<?= (int) $contest['id'] ?>/participant-files/">Все файлы участников</a>
                    <a class="btn btn-sm btn-outline-secondary rounded-pill px-3" href="/moderator/stage/create?contest_id=<?= (int) $contest['id'] ?>">Добавить этап</a>
                    <a class="btn btn-sm btn-outline-secondary rounded-pill px-3" href="/contest/<?= (int) $contest['id'] ?>/stats/">Статистика и отчёт</a>
                    <div class="btn-group btn-group-sm iro-contest-toolbar-awards" role="group" aria-label="Итоги и места">
                        <a class="btn btn-outline-secondary rounded-start-pill px-3" href="/award/create/<?= (int) $contest['id'] ?>/">Назначить место</a>
                        <form method="post" action="/contest/<?= (int) $contest['id'] ?>/awards/auto/" class="iro-btn-group-form m-0"
                              onsubmit="return confirm('Пересчитать места по правилам конкурса? Вручную закреплённые места сохранятся.');">
                            <?= iro_csrf_field_html() ?>
                            <button type="submit" class="btn btn-outline-secondary rounded-end-pill px-3">Места по баллам</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($canApply)): ?>
        <div class="iro-contest-join-cta" role="region" aria-label="Участие в конкурсе">
            <div class="iro-contest-join-cta__body">
                <p class="iro-contest-join-cta__text mb-0">
                    Загрузите подписанное <strong>согласие</strong> и при необходимости <strong>документы к заявке</strong> — одной отправкой. После проверки модератором вы сможете работать с этапами.
                </p>
                <div class="iro-contest-join-cta__actions">
                    <a href="/contest/apply?contest_id=<?= (int) $contest['id'] ?>"
                       class="btn btn-primary btn-lg rounded-pill iro-btn-join px-4 d-inline-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-person-plus-fill fs-5" aria-hidden="true"></i>
                        <span>Стать участником</span>
                    </a>
                </div>
            </div>
            <p class="iro-contest-join-cta__hint small text-muted mb-0 mt-3">Обычно это занимает пару минут: форма откроется на отдельной странице.</p>
        </div>
    <?php elseif (!empty($apply_role_block) && empty($isParticipant) && empty($canManage) && !empty(contest_status_open_to_participants((int) $contest['status']))): ?>
        <div class="alert alert-secondary">
            Подача заявки участника недоступна: для этого конкурса вы назначены экспертом на этап. Участвовать как организатор оценки и как участник одновременно нельзя.
        </div>
    <?php endif; ?>

    <?php if (!empty($consent)): ?>
        <div class="alert <?= (int) $consent['is_approved'] === 1 ? 'alert-success' : 'alert-warning' ?>">
            Заявка на участие:
            <?php if ((int) $consent['is_approved'] === 1): ?>
                согласие одобрено<?= !empty($isParticipant) ? ', вы участник.' : '.' ?>
            <?php elseif ((string) $consent['rejection_reason'] !== ''): ?>
                отклонена (<?= h((string) $consent['rejection_reason']) ?>).
            <?php else: ?>
                на модерации (согласие и документы к заявке).
            <?php endif; ?>
            <?php if (!empty($can_upload_consent)): ?>
                <a href="/contest/<?= (int) $contest['id'] ?>/upload-consent/"
                   class="btn btn-outline-primary btn-sm rounded-pill ms-2 ms-sm-3 mt-2 mt-sm-0 d-inline-flex align-items-center gap-1">
                    <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                    Обновить заявку
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($isParticipant) && !empty($participant_award)): ?>
        <div class="alert alert-light border mb-3">
            <strong>Итог по конкурсу:</strong>
            <?= (int) $participant_award['place'] ?>-е место
            <?php if (!empty($participant_award['award_description'])): ?>
                <span class="text-muted">— <?= h((string) $participant_award['award_description']) ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($participantUi): ?>
        <?php require __DIR__ . '/participant_dashboard.php'; ?>

        <?php if (!empty($canManage)): ?>
            <div class="iro-panel mt-4">
                <h2 class="h6 text-uppercase text-muted mb-3">Управление этапами (модератор)</h2>
                <?php if (!$stages): ?>
                    <p class="text-muted mb-0">Этапы ещё не добавлены.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-sm align-middle">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Название</th>
                                <?php if ($stageConductParallel): ?>
                                    <th>Статус</th>
                                <?php endif; ?>
                                <th>Начало</th>
                                <th>Окончание</th>
                                <th>Дедлайн подачи</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($stages as $stage): ?>
                                <tr>
                                    <td><?= (int) $stage['sort_order'] ?></td>
                                    <td>
                                        <?= h((string) $stage['title']) ?>
                                        <div class="small mt-1 d-flex flex-wrap gap-1 align-items-center">
                                            <a class="btn btn-sm btn-outline-secondary" href="/moderator/stage/edit?id=<?= (int) $stage['id'] ?>">Правка</a>
                                            <a class="btn btn-sm btn-outline-secondary" href="/moderator/stage/detail?id=<?= (int) $stage['id'] ?>">Настройки</a>
                                            <form method="post" action="/moderator/stage/delete" class="d-inline"
                                                  onsubmit="return confirm('Удалить этот этап и все связанные работы и оценки? Это действие необратимо.');">
                                                <?= iro_csrf_field_html() ?>
                                                <input type="hidden" name="stage_id" value="<?= (int) $stage['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                                            </form>
                                        </div>
                                    </td>
                                    <?php if ($stageConductParallel): ?>
                                        <?php $effPh = stage_effective_phase_status($contest, $stage, $today ?? date('Y-m-d')); ?>
                                        <td class="small"><?= h($labelsPhase[$effPh] ?? '') ?></td>
                                    <?php endif; ?>
                                    <td><?= h((string) $stage['start_date']) ?></td>
                                    <td><?= h((string) $stage['end_date']) ?></td>
                                    <td><?= h((string) ($stage['submission_deadline'] ?: $stage['end_date'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="iro-panel">
            <h1 class="iro-page-title mb-2"><?= h((string) $contest['title']) ?></h1>
            <p class="text-muted"><?= h((string) $contest['start_date']) ?> — <?= h((string) $contest['end_date']) ?></p>
            <p><?= nl2br(h((string) $contest['description'])) ?></p>
            <?php
            $regFile = trim((string) ($contest['regulations_path'] ?? ''));
            $docsUrl = trim((string) ($contest['documents_link'] ?? ''));
            ?>
            <?php if ($regFile !== '' || $docsUrl !== ''): ?>
                <p class="mb-0 d-flex flex-wrap gap-2 align-items-center">
                    <?php if ($regFile !== ''): ?>
                        <a class="btn btn-outline-primary btn-sm rounded-pill" href="<?= h($regFile) ?>"
                           target="_blank" rel="noopener">Документ (файл)</a>
                    <?php endif; ?>
                    <?php if ($docsUrl !== ''): ?>
                        <a class="btn btn-outline-secondary btn-sm rounded-pill" href="<?= h($docsUrl) ?>"
                           target="_blank" rel="noopener noreferrer">Шаблоны и приказы (ссылка)</a>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <h2 class="h5 iro-contest-stages-heading mt-5 mb-3">Этапы конкурса</h2>
            <?php if (!$stages): ?>
                <p class="text-muted mb-0">Этапы ещё не добавлены.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Название</th>
                            <?php if ($stageConductParallel): ?>
                                <th>Статус</th>
                            <?php endif; ?>
                            <th>Начало</th>
                            <th>Окончание</th>
                            <th>Дедлайн подачи</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($stages as $stage): ?>
                            <tr>
                                <td><?= (int) $stage['sort_order'] ?></td>
                                <td>
                                    <?= h((string) $stage['title']) ?>
                                    <?php if (!empty($canManage)): ?>
                                        <div class="small mt-1 d-flex flex-wrap gap-1 align-items-center">
                                            <a class="btn btn-sm btn-outline-secondary" href="/moderator/stage/edit?id=<?= (int) $stage['id'] ?>">Правка</a>
                                            <a class="btn btn-sm btn-outline-secondary" href="/moderator/stage/detail?id=<?= (int) $stage['id'] ?>">Настройки</a>
                                            <form method="post" action="/moderator/stage/delete" class="d-inline"
                                                  onsubmit="return confirm('Удалить этот этап и все связанные работы и оценки? Это действие необратимо.');">
                                                <?= iro_csrf_field_html() ?>
                                                <input type="hidden" name="stage_id" value="<?= (int) $stage['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <?php if ($stageConductParallel): ?>
                                    <?php $effPh = stage_effective_phase_status($contest, $stage, $today ?? date('Y-m-d')); ?>
                                    <td class="small"><?= h($labelsPhase[$effPh] ?? '') ?></td>
                                <?php endif; ?>
                                <td><?= h((string) $stage['start_date']) ?></td>
                                <td><?= h((string) $stage['end_date']) ?></td>
                                <td><?= h((string) ($stage['submission_deadline'] ?: $stage['end_date'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
