<?php
// $mode: create|edit
$isEdit = ($mode ?? 'create') === 'edit';
$c = $contest ?? [];
$attachRows = $attachmentFields ?? [];
$contestPickerId = (int) ($c['id'] ?? 0);
$pickerModsJson = htmlspecialchars(json_encode($pickerModeratorsInitial ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
$pickerExpJson = htmlspecialchars(json_encode($pickerExpertsInitial ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
$documentsLinkFieldVal = !empty($errors)
    ? trim((string) ($_POST['documents_link'] ?? ''))
    : trim((string) ($c['documents_link'] ?? ($_POST['documents_link'] ?? '')));
?>

<section>
    <h1 class="iro-page-title mb-2"><?= $isEdit ? 'Редактирование конкурса' : 'Создание конкурса' ?></h1>
    <p class="iro-lead mb-4"><?= $isEdit ? h((string) ($c['title'] ?? '')) : 'Заполните данные конкурса.' ?></p>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?= h((string) $e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="iro-panel iro-stage-form-panel border-0 shadow-sm">
        <form method="post" enctype="multipart/form-data" class="iro-contest-form" action="<?= $isEdit ? h('/admin/contests/edit?id=' . (int) ($c['id'] ?? 0)) : h('/admin/contests/create') ?>">
            <?= iro_csrf_field_html() ?>
            <?php if ($isEdit && !empty($c['id'])): ?>
                <input type="hidden" name="contest_id" value="<?= (int) $c['id'] ?>">
            <?php endif; ?>

            <fieldset class="iro-stage-form-section">
                <legend>Основное</legend>
            <div class="mb-3">
                <label class="form-label" for="title">Название</label>
                <input class="form-control" id="title" name="title" value="<?= h((string) ($c['title'] ?? ($_POST['title'] ?? ''))) ?>" required>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label" for="start_date">Дата начала</label>
                    <input class="form-control" type="date" id="start_date" name="start_date" value="<?= h((string) ($c['start_date'] ?? ($_POST['start_date'] ?? ''))) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="end_date">Дата окончания</label>
                    <input class="form-control" type="date" id="end_date" name="end_date" value="<?= h((string) ($c['end_date'] ?? ($_POST['end_date'] ?? ''))) ?>" required>
                </div>
            </div>

            <div class="mb-0">
                <label class="form-label" for="description">Описание</label>
                <textarea class="form-control" id="description" name="description" rows="5" required><?= h((string) ($c['description'] ?? ($_POST['description'] ?? ''))) ?></textarea>
            </div>
            </fieldset>

            <fieldset class="iro-stage-form-section">
                <legend>Публикация и серия</legend>
            <div class="mb-3">
                <label class="form-label" for="status">Статус публикации</label>
                <?php $st = (int) ($c['status'] ?? ($_POST['status'] ?? 1)); ?>
                <select class="form-select" id="status" name="status">
                    <option value="1" <?= $st===1?'selected':'' ?>>Черновик</option>
                    <option value="2" <?= $st===2?'selected':'' ?>>Опубликован</option>
                    <option value="3" <?= $st===3?'selected':'' ?>>Отменён</option>
                    <option value="4" <?= $st===4?'selected':'' ?>>Скрытый</option>
                    <option value="5" <?= $st===5?'selected':'' ?>>Только в серии</option>
                </select>
                <div class="form-text">
                    «Только в серии» требует выбранную серию. Тексты “скоро/идёт/завершён” считаются по датам.
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="series_id">Серия конкурсов</label>
                <?php $sid = (int) ($c['series_id'] ?? ($_POST['series_id'] ?? 0)); ?>
                <select class="form-select" id="series_id" name="series_id">
                    <option value="0">— без серии —</option>
                    <?php foreach (($series ?? []) as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= (int)$s['id'] === $sid ? 'selected' : '' ?>>
                            <?= h((string) $s['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            </fieldset>

            <fieldset class="iro-stage-form-section">
                <legend>Участники и этапы</legend>
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="document_intake_closed" name="document_intake_closed"
                        <?= (int)($c['document_intake_closed'] ?? ($_POST['document_intake_closed'] ?? 0)) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="document_intake_closed">Закрыть приём документов от участников</label>
                </div>
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="require_explicit_stage_admission" name="require_explicit_stage_admission"
                        <?= (int)($c['require_explicit_stage_admission'] ?? ($_POST['require_explicit_stage_admission'] ?? 0)) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="require_explicit_stage_admission">Допуск к этапам только по решению модератора</label>
                </div>
            </div>

            <?php $scm = (int) ($c['stage_conduct_mode'] ?? ($_POST['stage_conduct_mode'] ?? 0)); ?>
            <div class="mb-3">
                <label class="form-label d-block mb-2">Способ проведения этапов</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="stage_conduct_mode" id="stage_conduct_seq" value="0"
                        <?= $scm !== 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="stage_conduct_seq">Последовательно: активный этап по срокам, подача на следующий — после работы на предыдущем</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="stage_conduct_mode" id="stage_conduct_par" value="1"
                        <?= $scm === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="stage_conduct_par">По статусам этапов: подача на любой этап без очереди; у каждого этапа задаётся статус (скоро, приём работ, оценивание, завершён)</label>
                </div>
                <div class="form-text">Во втором режиме сроки этапов остаются справочными; приём работ открыт, когда для этапа выбран статус «Приём работ», в пределах дат самого конкурса.</div>
            </div>
            </fieldset>

            <fieldset class="iro-stage-form-section">
                <legend>Документы</legend>
            <div class="mb-3">
                <label class="form-label" for="image">Обложка (изображение для карточки)</label>
                <input class="form-control" type="file" id="image" name="image" accept="image/*">
                <?php if (!empty($c['image_path'])): ?>
                    <div class="small mt-2">
                        Текущая обложка: <a href="<?= h((string) $c['image_path']) ?>" target="_blank">открыть</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label class="form-label" for="regulations">Файл документа (положение и т.п.)</label>
                <input class="form-control" type="file" id="regulations" name="regulations"
                       accept=".pdf,.doc,.docx,.odt,.rtf,application/pdf">
                <div class="form-text">Файл для участников на странице конкурса. Форматы: PDF, DOC, DOCX, ODT или RTF. При редактировании пустое поле не меняет уже загруженный файл.</div>
                <?php if (!empty($c['regulations_path'])): ?>
                    <div class="small mt-2">
                        Текущий файл: <a href="<?= h((string) $c['regulations_path']) ?>" target="_blank" rel="noopener">открыть</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mb-0">
                <label class="form-label" for="documents_link">Ссылка на шаблоны, приказы и др.</label>
                <input class="form-control" type="url" inputmode="url" id="documents_link" name="documents_link"
                       value="<?= h($documentsLinkFieldVal) ?>"
                       placeholder="https://…">
                <div class="form-text">Необязательно: внешняя страница с шаблонами, приказами или другими материалами. Должен быть адрес с http:// или https://.</div>
            </div>
            </fieldset>

            <fieldset class="iro-stage-form-section">
                <legend>Модераторы и эксперты</legend>
            <?php if (!empty($isAdmin)): ?>
                <div class="mb-3">
                    <label class="form-label">Модераторы</label>
                    <div class="iro-user-picker-root"
                         data-iro-user-picker
                         data-picker-role="moderator"
                         data-picker-name="moderator_ids[]"
                         data-picker-contest-id="0"
                         data-picker-for-stage="0"
                         data-picker-initial="<?= $pickerModsJson ?>">
                        <div class="iro-user-picker-chips d-flex flex-wrap gap-2 mb-2"></div>
                        <div class="iro-user-picker-hiddens" aria-hidden="true"></div>
                        <div class="position-relative">
                            <input type="text" class="form-control iro-user-picker-search" autocomplete="off"
                                   placeholder="Фамилия, имя или email — или откройте список без ввода">
                            <div class="iro-user-picker-dropdown list-group list-group-flush" role="listbox" hidden></div>
                        </div>
                    </div>
                    <div class="form-text">Любой пользователь сайта: введите часть ФИО/email или кликните в поле — покажем первых по алфавиту.</div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">Модераторы конкурса фиксированы (править может только администратор).</div>
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label">Пул экспертов</label>
                <div class="iro-user-picker-root"
                     data-iro-user-picker
                     data-picker-role="expert"
                     data-picker-name="expert_ids[]"
                     data-picker-contest-id="<?= $contestPickerId ?>"
                     data-picker-for-stage="0"
                     data-picker-initial="<?= $pickerExpJson ?>">
                    <div class="iro-user-picker-chips d-flex flex-wrap gap-2 mb-2"></div>
                    <div class="iro-user-picker-hiddens" aria-hidden="true"></div>
                    <div class="position-relative">
                        <input type="text" class="form-control iro-user-picker-search" autocomplete="off"
                               placeholder="Фамилия, имя или email — или откройте список без ввода">
                        <div class="iro-user-picker-dropdown list-group list-group-flush" role="listbox" hidden></div>
                    </div>
                </div>
                <div class="form-text">
                    Кого можно выбрать экспертом этапа: при заполненном пуле — только из пула; при пустом пуле — любой активный пользователь, кроме администраторов сайта.
                </div>
            </div>
            </fieldset>

            <fieldset class="iro-stage-form-section mb-0">
                <legend>Файлы к заявке (до этапов)</legend>
            <p class="text-muted small mb-3">Настраиваемые поля файлов, которые участник загружает вместе с согласием.</p>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                    <tr>
                        <th class="iro-th-col-order">Порядок</th>
                        <th>Название</th>
                        <th>Требования</th>
                    </tr>
                    </thead>
                    <tbody id="contest-attach-rows">
                    <?php if (!empty($attachRows)): ?>
                        <?php foreach ($attachRows as $i => $row): ?>
                            <tr>
                                <td><input type="hidden" name="contest_attach_id[]" value="<?= (int) ($row['id'] ?? 0) ?>"><input class="form-control" type="number" name="contest_attach_sort_order[]" value="<?= (int) ($row['sort_order'] ?? $i) ?>"></td>
                                <td><input class="form-control" type="text" name="contest_attach_title[]" value="<?= h((string) ($row['title'] ?? '')) ?>"></td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <input class="form-control" type="text" name="contest_attach_requirements[]" value="<?= h((string) ($row['requirements'] ?? '')) ?>">
                                        <button type="button" class="btn btn-outline-danger btn-sm contest-attach-remove">Удалить</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <tr>
                        <td><input type="hidden" name="contest_attach_id[]" value="0"><input class="form-control" type="number" name="contest_attach_sort_order[]" value="<?= count($attachRows) ?>"></td>
                        <td><input class="form-control" type="text" name="contest_attach_title[]" value=""></td>
                        <td>
                            <div class="d-flex gap-2">
                                <input class="form-control" type="text" name="contest_attach_requirements[]" value="">
                                <button type="button" class="btn btn-outline-danger btn-sm contest-attach-remove">Удалить</button>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="contest-attach-add">Добавить поле</button>
            </fieldset>

            <div class="iro-stage-form-actions mt-2">
                <button type="submit" class="btn btn-primary rounded-pill px-4"><?= $isEdit ? 'Сохранить' : 'Создать конкурс' ?></button>
                <a href="/admin/contests" class="btn btn-outline-secondary rounded-pill px-4">Отмена</a>
            </div>
        </form>
    </div>
</section>

<script src="/assets/js/user-picker.js"></script>
<script>
    (function () {
        const btn = document.getElementById('contest-attach-add');
        const tbody = document.getElementById('contest-attach-rows');
        if (!btn || !tbody) return;
        tbody.addEventListener('click', function (e) {
            const t = e.target;
            if (t && t.classList && t.classList.contains('contest-attach-remove')) {
                const tr = t.closest('tr');
                if (tr) tr.remove();
            }
        });
        btn.addEventListener('click', function () {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input type="hidden" name="contest_attach_id[]" value="0"><input class="form-control" type="number" name="contest_attach_sort_order[]" value=""></td>
                <td><input class="form-control" type="text" name="contest_attach_title[]" value=""></td>
                <td>
                    <div class="d-flex gap-2">
                        <input class="form-control" type="text" name="contest_attach_requirements[]" value="">
                        <button type="button" class="btn btn-outline-danger btn-sm contest-attach-remove">Удалить</button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    })();
</script>

