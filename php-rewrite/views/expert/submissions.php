<section class="iro-panel">
    <h1 class="iro-page-title mb-3">Работы этапа</h1>
    <p class="text-muted">Этап: <strong><?= h((string) $stage['title']) ?></strong></p>

    <?php if (!empty($stage_evaluation_closed)): ?>
        <div class="alert alert-warning">Этап завершён для оценивания (срок этапа прошёл или статус «Завершён»). Выставлять и менять оценки нельзя.</div>
    <?php endif; ?>

    <?php if (empty($submissions)): ?>
        <p class="text-muted">Работ пока нет.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                <tr>
                    <th>Участник</th>
                    <th>Работа</th>
                    <th>Файл</th>
                    <th>Действия</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($submissions as $item): ?>
                    <tr>
                        <td><?= h((string) $item['email']) ?></td>
                        <td><?= h((string) $item['title']) ?></td>
                        <td><a href="<?= h((string) $item['file_path']) ?>" target="_blank">Скачать</a></td>
                        <td>
                            <?php if (empty($stage_evaluation_closed)): ?>
                                <a class="btn btn-sm btn-primary" href="/expert/evaluate?submission_id=<?= (int) $item['id'] ?>">Оценить</a>
                            <?php else: ?>
                                <span class="text-muted small">Оценивание закрыто</span>
                            <?php endif; ?>
                            <?php if (!empty($isPrivileged)): ?>
                                <a class="btn btn-sm btn-outline-secondary" href="/moderator/assign-experts?submission_id=<?= (int) $item['id'] ?>">Эксперты</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
