<?php
$participant_attachments_by_user = is_array($participant_attachments_by_user ?? null)
    ? $participant_attachments_by_user
    : [];
$supplement_submissions = is_array($supplement_submissions ?? null) ? $supplement_submissions : [];
$consents = is_array($consents ?? null) ? $consents : [];
$nPending = 0;
$nApproved = 0;
$nRejected = 0;
foreach ($consents as $crow) {
    if ((int) ($crow['is_approved'] ?? 0) === 1) {
        $nApproved++;
    } elseif (trim((string) ($crow['rejection_reason'] ?? '')) !== '') {
        $nRejected++;
    } else {
        $nPending++;
    }
}
?>
<section>
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="iro-page-title mb-1">Согласия и файлы к работам</h1>
            <p class="text-muted small mb-0">
                <?= h((string) $contest['title']) ?>
            </p>
            <p class="text-muted small mb-0 mt-2">На этой странице модератор проверяет согласия участников и при необходимости просматривает дополнительные файлы к поданным работам.</p>
        </div>
        <a href="/contest?id=<?= (int) ($contest['id'] ?? 0) ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">← К конкурсу</a>
    </div>

    <h2 class="iro-block-title h5 mb-2 mt-4" id="moderator-consents">Согласия участников</h2>

    <?php if ($consents !== []): ?>
        <div class="d-flex flex-wrap gap-1 gap-sm-2 mb-2 small">
            <span class="badge rounded-pill text-bg-warning-subtle text-dark border">На проверке: <?= (int) $nPending ?></span>
            <span class="badge rounded-pill text-bg-success-subtle text-dark border">Одобрено: <?= (int) $nApproved ?></span>
            <span class="badge rounded-pill text-bg-danger-subtle text-dark border">Отклонено: <?= (int) $nRejected ?></span>
        </div>
    <?php endif; ?>

    <?php if (empty($consents)): ?>
        <div class="iro-panel text-center py-4">
            <i class="bi bi-inbox fs-2 text-muted opacity-50 d-block mb-2"></i>
            <p class="text-muted small mb-0">Заявок с согласиями пока нет.</p>
        </div>
    <?php else: ?>
        <div class="iro-consent-review-list vstack gap-2">
            <?php foreach ($consents as $item): ?>
                <?php
                $uidRow = (int) ($item['user_id'] ?? 0);
                $paList = $participant_attachments_by_user[$uidRow] ?? [];
                $isApproved = (int) ($item['is_approved'] ?? 0) === 1;
                $rej = trim((string) ($item['rejection_reason'] ?? ''));
                $isRejected = !$isApproved && $rej !== '';
                $isPending = !$isApproved && !$isRejected;
                $displayName = trim((string) (($item['last_name'] ?? '') . ' ' . ($item['first_name'] ?? '')));
                if ($displayName === '') {
                    $displayName = (string) ($item['email'] ?? '');
                }
                $cid = (int) $item['id'];
                ?>
                <article class="iro-consent-item border rounded-3 bg-body px-3 py-2">
                    <div class="row g-2 g-md-3 align-items-start align-items-md-center">
                        <div class="col-md-4 col-lg-3">
                            <div class="d-flex align-items-start gap-2">
                                <div class="rounded-circle bg-body-secondary d-flex align-items-center justify-content-center flex-shrink-0 iro-consent-review-avatar" aria-hidden="true">
                                    <i class="bi bi-person-fill text-muted"></i>
                                </div>
                                <div class="min-w-0 flex-grow-1">
                                    <div class="d-flex flex-wrap align-items-center gap-1 gap-sm-2 mb-0">
                                        <span class="fw-semibold text-body small"><?= h($displayName) ?></span>
                                        <?php if ($isApproved): ?>
                                            <span class="badge rounded-pill text-bg-success iro-badge--2xs">Одобрено</span>
                                        <?php elseif ($isRejected): ?>
                                            <span class="badge rounded-pill text-bg-danger iro-badge--2xs">Отклонено</span>
                                        <?php else: ?>
                                            <span class="badge rounded-pill text-bg-warning text-dark iro-badge--2xs">На проверке</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="small text-muted text-break iro-text-note-sm"><?= h((string) ($item['email'] ?? '')) ?></div>
                                    <div class="text-muted iro-text-note-xs"><?= h(iro_format_datetime_long_ru((string) ($item['uploaded_at'] ?? ''))) ?></div>
                                    <?php if ($isRejected && $rej !== ''): ?>
                                        <div class="mt-1 small text-body-secondary border-start border-2 ps-2 py-0 iro-text-note-sm">
                                            <span class="text-uppercase text-muted iro-text-overline-xs">Причина</span><br>
                                            <?= nl2br(h($rej)) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5 col-lg-5">
                            <div class="d-flex flex-wrap align-items-center gap-2 gap-md-3 small">
                                <a href="<?= h((string) $item['consent_file_path']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill">
                                    <i class="bi bi-file-earmark-arrow-down me-1"></i>Согласие
                                </a>
                                <?php if ($paList !== []): ?>
                                    <?php foreach ($paList as $pa): ?>
                                        <a href="<?= h((string) $pa['file_path']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill text-break">
                                            <i class="bi bi-file-earmark-arrow-down me-1"></i><?= h((string) ($pa['field_title'] ?? 'Файл')) ?>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted iro-text-note-sm">Нет вложений к заявке</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-3 col-lg-4">
                            <?php if ($isApproved): ?>
                                <div class="small text-muted mb-0 py-md-1">
                                    <i class="bi bi-lock me-1"></i>Решение принято, статус здесь не меняется.
                                </div>
                            <?php else: ?>
                                <form method="post" class="iro-consent-actions-compact">
                                    <?= iro_csrf_field_html() ?>
                                    <input type="hidden" name="consent_id" value="<?= $cid ?>">
                                    <div class="d-flex flex-wrap align-items-stretch gap-2">
                                        <button class="btn btn-sm btn-success rounded-pill px-3" name="action" value="approve" type="submit" title="Одобрить">
                                            <i class="bi bi-check-lg me-1"></i>Одобрить
                                        </button>
                                        <div class="flex-grow-1 iro-flex-grow--clamp">
                                            <label class="visually-hidden" for="reason-<?= $cid ?>">Причина отказа</label>
                                            <textarea class="form-control form-control-sm" id="reason-<?= $cid ?>" name="reason" rows="1" placeholder="Причина при отклонении"></textarea>
                                        </div>
                                        <button class="btn btn-sm btn-outline-danger rounded-pill px-3" name="action" value="reject" type="submit" title="Отклонить">
                                            <i class="bi bi-x-lg me-1"></i>Отклонить
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="iro-panel mt-4 pt-3 border-top" id="moderator-work-files">
        <h2 class="iro-block-title h5 mb-2">Дополнительные файлы к работам</h2>
        <p class="small text-muted mb-3">Файлы к этапам не проходят отдельную модерацию — список для просмотра вместе с согласиями.</p>

        <?php if ($supplement_submissions === []): ?>
            <p class="text-muted small mb-0">Пока нет работ с загруженными дополнительными файлами по этому конкурсу.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Участник</th>
                        <th>Этап / работа</th>
                        <th>Доп. файлы</th>
                        <th class="text-end">Оценка</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($supplement_submissions as $item): ?>
                        <?php
                        $sid = (int) ($item['submission_id'] ?? $item['id'] ?? 0);
                        $email = (string) ($item['participant_email'] ?? $item['email'] ?? '');
                        $stageTitle = (string) ($item['stage_title'] ?? '');
                        $workTitle = (string) ($item['work_title'] ?? $item['title'] ?? '');
                        ?>
                        <tr>
                            <td><?= h($email) ?></td>
                            <td>
                                <div><strong><?= h($stageTitle) ?></strong></div>
                                <div><?= h($workTitle) ?></div>
                            </td>
                            <td>
                                <?php if (empty($item['attachments'])): ?>
                                    <span class="text-muted small">—</span>
                                <?php else: ?>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($item['attachments'] as $att): ?>
                                            <a href="<?= h((string) ($att['file_path'] ?? '#')) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill text-break">
                                                <i class="bi bi-file-earmark-arrow-down me-1"></i><?= h((string) ($att['field_title'] ?? 'Файл')) ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="/expert/evaluate?submission_id=<?= $sid ?>">Открыть оценку</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
