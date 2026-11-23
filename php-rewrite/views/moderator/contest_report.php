<section class="iro-panel mb-4">
    <h1 class="iro-page-title mb-2">Отчёт по баллам</h1>
    <p class="iro-lead mb-4"><?= h((string) $contest['title']) ?></p>
    <div class="iro-action-toolbar mb-3">
        <a href="/contest/<?= (int) $contest['id'] ?>/report/export.xlsx" class="btn btn-sm">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i>Скачать Excel (.xlsx)
        </a>
    </div>
</section>

<div class="iro-panel p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-striped align-middle mb-0">
            <thead>
            <tr>
                <th>ФИО участника</th>
                <th>Работа</th>
                <th>Сумма баллов</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($report as $item): ?>
                <?php $sub = $item['submission']; ?>
                <tr>
                    <td><?= h(user_display_name($sub)) ?></td>
                    <td><?= h((string) $sub['title']) ?></td>
                    <td><strong><?= h((string) $item['total_points']) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($report)): ?>
                <tr>
                    <td colspan="3" class="text-muted">Нет данных</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
