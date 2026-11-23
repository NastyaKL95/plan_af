<section>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h1 class="iro-page-title mb-0">Системные роли</h1>
    </div>
    <p class="text-muted small mb-3">Справочник ролей в базе данных. Назначение ролей пользователям — в разделе «Пользователи и роли».</p>

    <div class="iro-panel p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Slug</th>
                    <th>Название</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($roles as $r): ?>
                    <tr>
                        <td><?= (int) $r['id'] ?></td>
                        <td><code><?= h((string) $r['slug']) ?></code></td>
                        <td><?= h((string) $r['name']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
