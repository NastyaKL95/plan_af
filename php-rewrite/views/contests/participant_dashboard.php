<?php
/**
 * @var array $contest
 * @var array $stages
 * @var array $participant_stage_rows
 * @var array|null $currentStage
 * @var string $today
 */
$today = $today ?? date('Y-m-d');
$contestPhase = contest_calendar_phase($contest, $today);
$stPub = (int) ($contest['status'] ?? 1);
$pubClass = $stPub === 2 ? 'text-bg-primary' : contest_status_badge_class($stPub);
$deadlineYmd = '';
if (!empty($currentStage)) {
    $dl = trim((string) ($currentStage['submission_deadline'] ?? ''));
    $deadlineYmd = $dl !== '' ? $dl : (string) ($currentStage['end_date'] ?? '');
}
$conductParallel = !empty($stage_conduct_parallel);
?>
<div class="iro-participant-contest">
    <h1 class="iro-participant-page-title mb-4"><?= h((string) $contest['title']) ?></h1>

    <div class="iro-participant-card iro-participant-card--meta mb-4">
        <div class="iro-participant-meta-row flex-wrap gap-2">
            <span class="text-muted">Публикация:</span>
            <span class="badge rounded-pill <?= h($pubClass) ?>"><?= h(contest_status_label((int) ($contest['status'] ?? 1))) ?></span>
            <span class="text-muted ms-md-3">По датам:</span>
            <span class="badge rounded-pill iro-participant-badge-dates"><?= h($contestPhase['label']) ?></span>
        </div>
        <div class="iro-participant-meta-dates mt-3">
            <div><span class="text-muted">Дата начала:</span> <strong><?= h(iro_format_date_long_ru((string) $contest['start_date'])) ?></strong></div>
            <div><span class="text-muted">Дата окончания:</span> <strong><?= h(iro_format_date_long_ru((string) $contest['end_date'])) ?></strong></div>
        </div>
        <div class="mt-3 pt-3 border-top border-light-subtle">
            <div class="text-muted text-uppercase small fw-semibold mb-2">Описание</div>
            <div class="iro-participant-desc mb-0"><?= nl2br(h((string) $contest['description'])) ?></div>
        </div>
        <?php
        $regFilePd = trim((string) ($contest['regulations_path'] ?? ''));
        $docsUrlPd = trim((string) ($contest['documents_link'] ?? ''));
        ?>
        <?php if ($regFilePd !== '' || $docsUrlPd !== ''): ?>
            <div class="mt-3 pt-3 border-top border-light-subtle d-flex flex-wrap gap-2">
                <?php if ($regFilePd !== ''): ?>
                    <a class="btn btn-outline-primary btn-sm rounded-pill" href="<?= h($regFilePd) ?>"
                       target="_blank" rel="noopener">Документ (файл)</a>
                <?php endif; ?>
                <?php if ($docsUrlPd !== ''): ?>
                    <a class="btn btn-outline-secondary btn-sm rounded-pill" href="<?= h($docsUrlPd) ?>"
                       target="_blank" rel="noopener noreferrer">Шаблоны и приказы (ссылка)</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$conductParallel && !empty($currentStage)): ?>
        <div class="iro-participant-current mb-4">
            <div class="iro-participant-current-head">Текущий этап</div>
            <div class="iro-participant-current-body">
                <div class="iro-participant-current-stage-title"><?= h((string) $currentStage['title']) ?></div>
                <p class="text-muted mb-1">
                    Сроки этапа:
                    <?= h(iro_format_date_long_ru((string) $currentStage['start_date'])) ?>
                    —
                    <?= h(iro_format_date_long_ru((string) $currentStage['end_date'])) ?>
                </p>
                <?php if ($deadlineYmd !== ''): ?>
                    <p class="small text-muted mb-3">
                        Приём новых работ до <?= h(iro_format_date_long_ru($deadlineYmd)) ?> включительно
                    </p>
                <?php endif; ?>
                <?php if (!empty($show_participant_submit_work)): ?>
                    <a class="btn btn-success btn-lg rounded-pill px-4 iro-participant-btn-submit"
                       href="/submission/create?stage_id=<?= (int) $currentStage['id'] ?>">Подать работу</a>
                <?php else: ?>
                    <?php
                    $curSid = (int) ($currentStage['id'] ?? 0);
                    $rowCur = null;
                    foreach ($participant_stage_rows ?? [] as $pr) {
                        if ((int) ($pr['stage']['id'] ?? 0) === $curSid) {
                            $rowCur = $pr;
                            break;
                        }
                    }
                    $subCur = $rowCur['submission'] ?? null;
                    $evalCur = !empty($rowCur['has_evaluation']);
                    $blockCode = $submit_work_block_code ?? null;
                    ?>
                    <p class="text-muted small mb-0">
                        <?php if ($subCur && $evalCur): ?>
                            Работа на этот этап уже подана и получила оценки экспертов — подать снова нельзя.
                        <?php elseif ($subCur): ?>
                            Работа на этот этап уже подана.
                        <?php elseif ($blockCode === 'not_admitted'): ?>
                            Вы не допущены к этому этапу модератором. Дождитесь отметки допуска на странице этапа у организаторов.
                        <?php elseif ($blockCode === 'prev_missing'): ?>
                            Сначала подайте работу на предыдущий этап.
                        <?php elseif ($blockCode === 'in_person_stage'): ?>
                            Этап очный: подача работ через сайт не предусмотрена. Оценивание ведут эксперты по критериям.
                        <?php elseif ($blockCode === 'stage_inactive'): ?>
                            Этап по датам сейчас неактивен — подача недоступна.
                        <?php elseif ($blockCode === 'past_deadline'): ?>
                            Истёк дедлайн приёма работ на этот этап.
                        <?php elseif ($blockCode === 'not_participant'): ?>
                            Подача недоступна.
                        <?php else: ?>
                            Подача работы на этот этап сейчас недоступна (сроки этапа, дедлайн, допуск к этапу или цепочка этапов).
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
                <?php if (!empty($is_expert_for_contest) && !empty($canManage)): ?>
                    <a class="btn btn-outline-primary btn-lg rounded-pill px-4 ms-2 mt-2 mt-sm-0"
                       href="/expert/submissions?stage_id=<?= (int) $currentStage['id'] ?>">Кабинет эксперта</a>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif ($conductParallel): ?>
        <div class="alert alert-light border mb-4">
            <strong>Режим этапов:</strong> подача работ на этапы независимая (без очереди по этапам). Для каждого этапа организатор задаёт статус;
            кнопка «Подать работу» доступна в таблице ниже, когда для этапа выбран статус «Приём работ» и вы допущены к этапу (если включён ручной допуск).
        </div>
    <?php endif; ?>

    <div class="iro-participant-progress-block mb-4">
        <h2 class="iro-participant-progress-title mb-1">Все этапы и ваш прогресс</h2>
        <p class="iro-participant-progress-sub text-muted mb-3">Сроки этапов, допуск, подача и результат.</p>

        <?php if (empty($participant_stage_rows)): ?>
            <div class="iro-participant-card text-muted py-4 text-center">Этапы ещё не добавлены организаторами.</div>
        <?php else: ?>
            <div class="table-responsive iro-participant-table-wrap">
                <table class="table iro-participant-progress-table mb-0">
                    <thead>
                    <tr>
                        <th scope="col">Этап</th>
                        <th scope="col">Сроки</th>
                        <th scope="col"><?= $conductParallel ? 'Статус этапа' : 'По календарю' ?></th>
                        <th scope="col">Работа</th>
                        <th scope="col">Результат</th>
                        <?php if ($conductParallel): ?>
                            <th scope="col">Подача</th>
                        <?php endif; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($participant_stage_rows as $row): ?>
                        <?php
                        $st = $row['stage'];
                        $ph = $row['phase'];
                        $sub = $row['submission'];
                        if ($conductParallel) {
                            $isRowActive = (($ph['code'] ?? '') === 'phase_2');
                        } else {
                            $isRowActive = ($ph['code'] ?? '') === 'active';
                        }
                        ?>
                        <tr class="<?= $isRowActive ? 'iro-participant-tr-active' : '' ?>">
                            <td>
                                <span class="fw-semibold"><?= (int) $st['sort_order'] ?>.</span>
                                <?= h((string) $st['title']) ?>
                            </td>
                            <td class="text-nowrap small">
                                <?= h(iro_format_date_dmy((string) $st['start_date'])) ?>
                                —
                                <?= h(iro_format_date_dmy((string) $st['end_date'])) ?>
                            </td>
                            <td>
                                <span class="badge rounded-pill <?= h($ph['badge_class']) ?>"><?= h($ph['label']) ?></span>
                            </td>
                            <td>
                                <?php if ($sub): ?>
                                    <?php if (!empty(trim((string) ($sub['work_link'] ?? '')))): ?>
                                        <a href="<?= h((string) $sub['work_link']) ?>" target="_blank" rel="noopener noreferrer" class="fw-medium"><?= h((string) $sub['title']) ?></a>
                                    <?php else: ?>
                                        <a href="/submission/supplements?submission_id=<?= (int) $sub['id'] ?>" class="fw-medium"><?= h((string) $sub['title']) ?></a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="small">
                                <?php if (!$sub): ?>
                                    Работа на этот этап пока не подана.
                                <?php elseif (empty($row['has_evaluation'])): ?>
                                    Работа подана, оценка экспертами ещё не выставлена.
                                <?php else: ?>
                                    Средняя сумма баллов по экспертам: <?= h(number_format((float) $row['mean_score'], 2, '.', '')) ?> (по критериям этапа).
                                <?php endif; ?>
                            </td>
                            <?php if ($conductParallel): ?>
                                <td class="small">
                                    <?php if (!empty($row['can_submit_here'])): ?>
                                        <a class="btn btn-success btn-sm rounded-pill px-3"
                                           href="/submission/create?stage_id=<?= (int) $st['id'] ?>">Подать работу</a>
                                    <?php else: ?>
                                        <?php
                                        $bc = $row['submit_block_code'] ?? null;
                                        ?>
                                        <?php if ($bc === 'already_submitted'): ?>
                                            Уже подано
                                        <?php elseif ($bc === 'not_admitted'): ?>
                                            Нет допуска
                                        <?php elseif ($bc === 'phase_soon'): ?>
                                            Статус «Скоро»
                                        <?php elseif ($bc === 'phase_evaluation'): ?>
                                            Оценивание
                                        <?php elseif ($bc === 'phase_done'): ?>
                                            Завершён
                                        <?php elseif ($bc === 'outside_contest_dates'): ?>
                                            Вне сроков конкурса
                                        <?php elseif ($bc === 'in_person_stage'): ?>
                                            Очный этап
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
