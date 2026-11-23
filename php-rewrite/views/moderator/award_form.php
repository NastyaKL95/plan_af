<?php
$editing = $editing_award !== null;
?>
<section class="iro-panel">
    <h1 class="iro-page-title mb-2"><?= $editing ? 'Изменить место в итогах' : 'Назначить место участнику' ?></h1>
    <p class="text-muted mb-4"><?= h((string) $contest['title']) ?></p>

    <?php if ($editing): ?>
        <p class="small text-muted mb-4">
            Участник: <strong><?= h((string) ($editing_participant_name ?? '—')) ?></strong>.
            <a href="/award/create/<?= (int) $contest['id'] ?>/" class="text-primary fw-semibold text-decoration-none">Назад к списку и новой записи</a>
        </p>
    <?php endif; ?>

    <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger"><?= h((string) $err) ?></div>
    <?php endforeach; ?>

    <div class="iro-panel bg-light border mb-4">
        <h2 class="iro-block-title"><?= $editing ? 'Редактирование' : 'Ручное назначение' ?></h2>
        <p class="small text-muted mb-3">
            Массовый пересчёт — в <a href="/contest/<?= (int) $contest['id'] ?>/stats/">статистике</a> или на странице конкурса («Места по баллам»).
            Закреплённые вручную места при этом не меняются автоматически.
        </p>
        <form method="post" class="row g-3">
            <?= iro_csrf_field_html() ?>
            <?php if (!$editing): ?>
                <div class="col-12">
                    <label class="form-label" for="participant_id">Участник</label>
                    <select name="participant_id" id="participant_id" class="form-select" required <?= $participants_count === 0 ? 'disabled' : '' ?>>
                        <option value="">— выберите —</option>
                        <?php foreach ($participants as $p): ?>
                            <option value="<?= (int) $p['id'] ?>" <?= (int) $form_participant_id === (int) $p['id'] ? 'selected' : '' ?>>
                                <?= h(user_display_name($p)) ?> (<?= h((string) $p['email']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <input type="hidden" name="participant_id" value="<?= (int) $editing_award['participant_id'] ?>">
            <?php endif; ?>
            <div class="col-md-4">
                <label class="form-label" for="place">Место</label>
                <input type="number" name="place" id="place" class="form-control" min="1" max="9999" required
                       value="<?= (int) $form_place ?>">
                <div class="form-text">Целое число; при равенстве баллов место может совпадать у нескольких участников.</div>
            </div>
            <div class="col-12">
                <label class="form-label" for="award_description">Пояснение к итогу</label>
                <textarea name="award_description" id="award_description" class="form-control" rows="4"
                          placeholder="Например: диплом лауреата I степени…"><?= h((string) $form_description) ?></textarea>
                <div class="form-text">Необязательно: номинация, формулировка для диплома.</div>
            </div>
            <?php if ($editing && (int) ($editing_award['is_manual_override'] ?? 0) === 1): ?>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="release_manual_ranking" value="1" id="release_manual_ranking">
                        <label class="form-check-label" for="release_manual_ranking">
                            Вернуть автоматическое место по баллам
                        </label>
                    </div>
                    <div class="form-text">Снимает ручное закрепление и подставляет место по текущим правилам расчёта.</div>
                </div>
            <?php endif; ?>
            <div class="col-12 iro-action-toolbar">
                <button type="submit" class="btn btn-sm"<?= !$editing && $participants_count === 0 ? ' disabled' : '' ?>>Сохранить</button>
                <a href="/contest/<?= (int) $contest['id'] ?>/stats/" class="btn btn-sm">Статистика</a>
                <a href="/contest?id=<?= (int) $contest['id'] ?>" class="btn btn-sm">К конкурсу</a>
            </div>
        </form>
    </div>

    <?php if (!$editing && !empty($existing_awards)): ?>
        <div class="iro-panel p-0 overflow-hidden">
            <div class="p-3 border-bottom bg-light">
                <h2 class="iro-block-title mb-0">Уже назначенные места (<?= count($existing_awards) ?>)</h2>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                    <tr>
                        <th>Место</th>
                        <th>Участник</th>
                        <th>Пояснение</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($existing_awards as $a): ?>
                        <tr>
                            <td><?= (int) $a['place'] ?></td>
                            <td><?= h(user_display_name($a)) ?></td>
                            <td class="text-muted small"><?= h(mb_strimwidth((string) ($a['award_description'] ?? ''), 0, 80, '…')) ?: '—' ?></td>
                            <td><a href="/award/<?= (int) $a['id'] ?>/edit/" class="btn btn-sm btn-outline-primary rounded-pill">Изменить</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</section>
