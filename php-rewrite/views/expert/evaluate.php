<?php $evaluationFrozen = !empty($evaluation_frozen); ?>
<section class="iro-panel">
    <h1 class="iro-page-title mb-3">Оценивание работы</h1>
    <?php if ($evaluationFrozen): ?>
        <div class="alert alert-secondary mb-3 mb-md-4">
            Оценивание закрыто: срок этапа прошёл или для этапа установлен статус «Завершён». Баллы и комментарии можно только просматривать, сохранение отключено.
        </div>
    <?php endif; ?>
    <p class="mb-1"><strong>Конкурс:</strong> <?= h((string) $submission['contest_title']) ?></p>
    <p class="mb-1"><strong>Этап:</strong> <?= h((string) $submission['stage_title']) ?></p>
    <p class="mb-3"><strong>Участник:</strong> <?= h((string) $submission['participant_email']) ?></p>

    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
        <a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="<?= h((string) $submission['file_path']) ?>">Скачать файл работы</a>
        <?php if ((string) ($submission['work_link'] ?? '') !== ''): ?>
            <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener noreferrer" href="<?= h((string) $submission['work_link']) ?>">Открыть ссылку</a>
        <?php endif; ?>
    </div>

    <?php
    $applicationFiles = is_array($participant_application_files ?? null) ? $participant_application_files : [];
    ?>
    <?php if ($applicationFiles !== []): ?>
        <div class="border rounded-3 p-3 mb-4 bg-body-secondary bg-opacity-10">
            <div class="small text-uppercase text-muted fw-semibold mb-2">Согласие и файлы к заявке</div>
            <ul class="list-unstyled mb-0 vstack gap-1">
                <?php foreach ($applicationFiles as $af): ?>
                    <li>
                        <span class="text-muted"><?= h((string) ($af['label'] ?? '')) ?>:</span>
                        <a target="_blank" rel="noopener" href="<?= h((string) ($af['web_path'] ?? '')) ?>">открыть</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php
    $extraFiles = $submission_extra_files ?? [];
    ?>
    <?php if (!empty($extraFiles)): ?>
        <div class="border rounded-3 p-3 mb-4 bg-body-tertiary bg-opacity-25">
            <div class="small text-uppercase text-muted fw-semibold mb-2">Дополнительные файлы к этапу</div>
            <ul class="list-unstyled mb-0 vstack gap-1">
                <?php foreach ($extraFiles as $xf): ?>
                    <li>
                        <span class="text-muted"><?= h((string) ($xf['field_title'] ?? '')) ?>:</span>
                        <a target="_blank" rel="noopener" href="<?= h((string) ($xf['file_path'] ?? '')) ?>">открыть</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" class="d-grid gap-3">
        <?= iro_csrf_field_html() ?>
        <?php
        $prevEvalSection = '';
        foreach ($criteria as $criterion):
            $gidEv = isset($criterion['group_id']) ? (int) $criterion['group_id'] : 0;
            $secEv = $gidEv > 0 ? 'g' . $gidEv : 'u';
            if ($secEv !== $prevEvalSection):
                $prevEvalSection = $secEv;
                if ($gidEv > 0 && trim((string) ($criterion['group_title'] ?? '')) !== ''): ?>
                    <div class="border-start border-4 border-secondary ps-3 mb-1 mt-4 pt-2">
                        <div class="small text-uppercase text-muted fw-semibold"><?= h((string) $criterion['group_title']) ?></div>
                    </div>
                <?php elseif ($secEv === 'u' && !empty($criteria_has_groups)): ?>
                    <div class="small text-uppercase text-muted fw-semibold mt-4 mb-2">Без группы</div>
                <?php endif;
            endif;
            $eid = (int) $criterion['id'];
            $cMin = (int) ($criterion['min_points'] ?? 0);
            $cMax = (int) ($criterion['max_points'] ?? 0);
            $cW = (int) ($criterion['weight'] ?? 100);
            $valRaw = isset($existing[$eid]) ? (int) $existing[$eid]['points'] : $cMin;
            $val = max($cMin, min($cMax, $valRaw));
            $comment = isset($existing[$eid]) ? (string) ($existing[$eid]['comment'] ?? '') : '';
        ?>
            <div class="border rounded p-3">
                <div class="fw-semibold mb-2">
                    <?= h((string) $criterion['name']) ?>
                    <span class="text-muted fw-normal small">
                        · баллы <?= $cMin ?>–<?= $cMax ?> · вес <?= $cW ?>
                    </span>
                </div>
                <div class="row g-2">
                    <div class="col-md-2">
                        <label class="form-label" for="points_<?= $eid ?>">Баллы</label>
                        <input class="form-control" type="number" min="<?= $cMin ?>" max="<?= $cMax ?>" id="points_<?= $eid ?>" name="points_<?= $eid ?>" value="<?= $val ?>"<?= $evaluationFrozen ? ' readonly' : ' required' ?>>
                    </div>
                    <div class="col-md-10">
                        <label class="form-label" for="comment_<?= $eid ?>">Комментарий</label>
                        <input class="form-control" type="text" id="comment_<?= $eid ?>" name="comment_<?= $eid ?>" value="<?= h($comment) ?>"<?= $evaluationFrozen ? ' readonly' : '' ?>>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <div>
            <?php if (!$evaluationFrozen): ?>
                <button class="btn btn-primary" type="submit">Сохранить оценки</button>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="/expert/submissions?stage_id=<?= (int) $submission['stage_id'] ?>">Назад</a>
        </div>
    </form>
</section>
