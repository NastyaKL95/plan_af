<section class="iro-panel iro-panel-narrow iro-assign-experts">
    <header class="iro-assign-experts__head mb-4">
        <h1 class="iro-page-title mb-3">Назначение экспертов</h1>
        <p class="mb-2"><strong>Работа:</strong> <?= h((string) $submission['title']) ?></p>
        <p class="text-muted mb-0"><strong>Этап:</strong> <?= h((string) $submission['stage_title']) ?></p>
    </header>

    <form method="post" class="vstack gap-3">
        <?= iro_csrf_field_html() ?>
        <?php if (empty($experts)): ?>
            <p class="text-muted mb-0">Для этого этапа не добавлены эксперты.</p>
        <?php else: ?>
            <div class="vstack gap-2" role="group" aria-label="Эксперты этапа">
                <?php foreach ($experts as $expert): ?>
                    <?php
                    $eid = (int) $expert['id'];
                    $inputId = 'assign_expert_' . $eid;
                    ?>
                    <div class="iro-assign-experts__row d-flex align-items-center gap-3 rounded border px-3 py-2">
                        <input class="form-check-input flex-shrink-0 iro-assign-experts__checkbox mt-0"
                               type="checkbox"
                               name="expert_ids[]"
                               value="<?= $eid ?>"
                               id="<?= h($inputId) ?>"<?= in_array($eid, $assigned, true) ? ' checked' : '' ?>>
                        <label class="form-check-label flex-grow-1 mb-0 user-select-none" for="<?= h($inputId) ?>">
                            <?= h((string) $expert['email']) ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="d-flex flex-wrap gap-2 pt-3 mt-1 border-top">
                <button class="btn btn-primary" type="submit">Сохранить назначения</button>
                <a class="btn btn-outline-secondary" href="/expert/submissions?stage_id=<?= (int) $submission['stage_id'] ?>">Назад</a>
            </div>
        <?php endif; ?>
    </form>
</section>
