<?php
$roleNameBySlug = [];
foreach (($roles ?? []) as $r) {
    $roleNameBySlug[(string) $r['slug']] = (string) $r['name'];
}
?>
<section>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h1 class="iro-page-title mb-0">Пользователи</h1>
    </div>

    <form method="get" class="mb-4 iro-filter-bar">
        <div class="input-group shadow-sm">
            <input type="text" name="q" class="form-control border-end-0" placeholder="Email, часть ФИО или строка целиком (напр. Иванов Иван)" value="<?= h((string) ($q ?? '')) ?>">
            <button type="submit" class="btn btn-primary">Найти</button>
        </div>
    </form>

    <div class="iro-panel p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                <tr>
                    <th class="iro-th-user-fio">ФИО</th>
                    <th class="iro-th-user-email">Email</th>
                    <th class="iro-th-user-roles">Роли</th>
                    <th class="text-end iro-th-user-action">Действия</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="4" class="text-muted">Пользователи не найдены</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <?php
                        $fio = trim(
                            trim((string) ($u['last_name'] ?? '')) . ' ' .
                            trim((string) ($u['first_name'] ?? '')) . ' ' .
                            trim((string) ($u['middle_name'] ?? ''))
                        );
                        $uid = (int) $u['id'];
                        $editHref = '/admin/users/edit?id=' . $uid . '&return_page=' . (int) ($page ?? 1);
                        if (($q ?? '') !== '') {
                            $editHref .= '&return_q=' . rawurlencode((string) $q);
                        }
                        $slugs = (array) ($u['role_slugs'] ?? []);
                        ?>
                        <tr>
                            <td><?= $fio !== '' ? h($fio) : '<span class="text-muted">—</span>' ?></td>
                            <td class="fw-semibold"><?= h((string) $u['email']) ?></td>
                            <td>
                                <?php if ($slugs === []): ?>
                                    <span class="text-muted small">—</span>
                                <?php else: ?>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php foreach ($slugs as $slug): ?>
                                            <span class="badge rounded-pill text-bg-light border"><?= h($roleNameBySlug[(string) $slug] ?? (string) $slug) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="<?= h($editHref) ?>" class="btn btn-sm btn-primary rounded-pill px-3">Редактировать профиль</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (($pages ?? 1) > 1): ?>
        <nav class="mt-3" aria-label="Страницы пользователей">
            <ul class="pagination pagination-sm justify-content-center flex-wrap mb-0 gap-1">
                <?php
                $cur = (int) ($page ?? 1);
                $qs = ($q ?? '') !== '' ? '&q=' . urlencode((string) $q) : '';
                ?>
                <?php if ($cur > 1): ?>
                    <li class="page-item"><a class="page-link" href="/admin/users?page=<?= $cur - 1 ?><?= $qs ?>">Назад</a></li>
                <?php endif; ?>
                <?php for ($p = 1; $p <= (int) ($pages ?? 1); $p++): ?>
                    <li class="page-item<?= $p === $cur ? ' active' : '' ?>">
                        <a class="page-link" href="/admin/users?page=<?= $p ?><?= $qs ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($cur < (int) ($pages ?? 1)): ?>
                    <li class="page-item"><a class="page-link" href="/admin/users?page=<?= $cur + 1 ?><?= $qs ?>">Вперёд</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>

    <p class="text-muted small mt-3 mb-0">
        Всего: <?= (int) ($total_users ?? 0) ?><?php if (($per_page ?? 0) > 0): ?>, на странице до <?= (int) $per_page ?><?php endif; ?>.
    </p>
</section>
