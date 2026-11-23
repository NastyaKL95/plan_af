<?php
$attachmentFields = $attachment_fields ?? [];
$existingAttachments = $existing_attachments ?? [];
$existingConsent = $existing_consent ?? null;
$consentOptional = !empty($consent_file_optional);
$consentInputRequired = !empty($consent_file_input_required);
$existingConsentPathUi = '';
if ($existingConsent !== null) {
    $existingConsentPathUi = trim((string) ($existingConsent['consent_file_path'] ?? ''));
}
?>
<section class="iro-panel iro-panel-narrow mx-auto">
    <h1 class="iro-page-title mb-3"><?= $consentOptional ? 'Документы к заявке' : 'Стать участником' ?></h1>
    <p class="text-muted mb-2">Конкурс: <strong><?= h((string) $contest['title']) ?></strong></p>
    <?php if ($consentOptional): ?>
        <p class="text-muted small mb-4">Согласие уже одобрено — осталось догрузить обязательные файлы к заявке. Работы по этапам подаются отдельно.</p>
    <?php else: ?>
        <p class="text-muted small mb-4">
            Загрузите <strong>подписанное согласие</strong> и все <strong>обязательные документы</strong>, которые заданы для этого конкурса (если списка нет — достаточно согласия).
            После проверки модератором вы будете зачислены участником. Файлы работ по этапам загружаются позже, на соответствующих этапах.
        </p>
    <?php endif; ?>

    <?php if ($existingConsent !== null && (string) ($existingConsent['rejection_reason'] ?? '') !== ''): ?>
        <div class="alert alert-danger mb-3">
            <strong>Предыдущая версия отклонена:</strong> <?= h((string) $existingConsent['rejection_reason']) ?>
        </div>
        <p class="small text-muted mb-3">Загрузите исправленное согласие и при необходимости документы к заявке для повторной проверки.</p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="d-grid gap-3">
        <?= iro_csrf_field_html() ?>
        <div>
            <label class="form-label" for="consent_file">Подписанное согласие</label>
            <div class="form-text mb-1">Формат: PDF, DOC или DOCX.</div>
            <?php if ($consentOptional): ?>
                <div class="form-text mb-1">Согласие уже одобрено — при необходимости можно не менять файл, только дозагрузить недостающие вложения к заявке.</div>
            <?php endif; ?>
            <?php if ($existingConsentPathUi !== ''): ?>
                <div class="small mb-1">Текущий файл согласия: <a href="<?= h($existingConsentPathUi) ?>" target="_blank" rel="noopener">открыть</a></div>
            <?php endif; ?>
            <input class="form-control" type="file" id="consent_file" name="consent_file"
                   accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                <?= $consentInputRequired ? ' required' : '' ?>>
        </div>

        <?php foreach ($attachmentFields as $spec): ?>
            <?php
            $fid = (int) $spec['id'];
            $hasFile = isset($existingAttachments[$fid]);
            $req = !$hasFile;
            $reqText = $spec['requirements'] ?? '';
            ?>
            <div>
                <label class="form-label" for="participant_attachment_<?= $fid ?>"><?= h((string) ($spec['title'] ?? '')) ?></label>
                <?php if (trim((string) $reqText) !== ''): ?>
                    <div class="form-text mb-1"><?= nl2br(h((string) $reqText)) ?></div>
                <?php endif; ?>
                <span class="d-block small text-muted mb-1"><?= $req ? 'Обязательное поле' : 'Файл уже загружен, можно заменить' ?></span>
                <?php if ($hasFile): ?>
                    <div class="small mb-1">Текущий файл: <a href="<?= h((string) $existingAttachments[$fid]['file_path']) ?>" target="_blank" rel="noopener">открыть</a></div>
                <?php endif; ?>
                <input class="form-control" type="file" id="participant_attachment_<?= $fid ?>"
                       name="participant_attachment_<?= $fid ?>"
                    <?= $req ? ' required' : '' ?>>
            </div>
        <?php endforeach; ?>

        <div class="d-flex flex-column flex-sm-row flex-wrap gap-2 gap-sm-3 pt-1">
            <button class="btn btn-primary btn-lg rounded-pill iro-btn-join px-4 order-1 order-sm-0 d-inline-flex align-items-center justify-content-center gap-2"
                    type="submit">
                <?php if ($consentOptional): ?>
                    <i class="bi bi-cloud-upload fs-5" aria-hidden="true"></i>
                    <span>Сохранить документы</span>
                <?php else: ?>
                    <i class="bi bi-person-plus-fill fs-5" aria-hidden="true"></i>
                    <span>Стать участником</span>
                <?php endif; ?>
            </button>
            <a class="btn btn-outline-secondary rounded-pill px-4 align-self-stretch align-self-sm-center text-center"
               href="/contest?id=<?= (int) $contest['id'] ?>">Назад к конкурсу</a>
        </div>
    </form>
</section>
