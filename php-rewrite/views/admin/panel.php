<section>
    <h1 class="iro-page-title mb-3">Панель управления</h1>
    <p class="text-muted mb-4">Разделы администрирования сайта</p>

    <div class="row g-3">
    <div class="col-md-6 col-xl-4">
        <a href="/admin/contests" class="text-decoration-none">
            <div class="card h-100 shadow-sm border-0 iro-admin-tile">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <span class="iro-admin-tile-icon text-primary"><i class="bi bi-trophy fs-3"></i></span>
                        <div>
                            <h2 class="h5 mb-1 text-body">Конкурсы</h2>
                            <p class="small text-muted mb-0">Список, создание и редактирование конкурсов</p>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <?php if (!empty($isAdmin)): ?>
        <div class="col-md-6 col-xl-4">
            <a href="/admin/users" class="text-decoration-none">
                <div class="card h-100 shadow-sm border-0 iro-admin-tile">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <span class="iro-admin-tile-icon text-success"><i class="bi bi-people fs-3"></i></span>
                            <div>
                                <h2 class="h5 mb-1 text-body">Пользователи</h2>
                                <p class="small text-muted mb-0">Роли: администратор, модератор, эксперт, участник</p>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-6 col-xl-4">
            <a href="/admin/news" class="text-decoration-none">
                <div class="card h-100 shadow-sm border-0 iro-admin-tile">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <span class="iro-admin-tile-icon text-info"><i class="bi bi-newspaper fs-3"></i></span>
                            <div>
                                <h2 class="h5 mb-1 text-body">Новости</h2>
                                <p class="small text-muted mb-0">Публикации на главной и в разделе новостей</p>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-6 col-xl-4">
            <a href="/admin/series" class="text-decoration-none">
                <div class="card h-100 shadow-sm border-0 iro-admin-tile">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <span class="iro-admin-tile-icon text-warning"><i class="bi bi-collection fs-3"></i></span>
                            <div>
                                <h2 class="h5 mb-1 text-body">Серии конкурсов</h2>
                                <p class="small text-muted mb-0">Группировка конкурсов, обложка, порядок</p>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-6 col-xl-4">
            <a href="/admin/consent-documents" class="text-decoration-none">
                <div class="card h-100 shadow-sm border-0 iro-admin-tile">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <span class="iro-admin-tile-icon text-secondary"><i class="bi bi-file-earmark-text fs-3"></i></span>
                            <div>
                                <h2 class="h5 mb-1 text-body">Шаблоны согласий</h2>
                                <p class="small text-muted mb-0">Файлы-образцы для участников</p>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-6 col-xl-4">
            <a href="/admin/partners" class="text-decoration-none">
                <div class="card h-100 shadow-sm border-0 iro-admin-tile">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <span class="iro-admin-tile-icon text-primary"><i class="bi bi-building fs-3"></i></span>
                            <div>
                                <h2 class="h5 mb-1 text-body">Партнёры</h2>
                                <p class="small text-muted mb-0">Логотипы и ссылки на главной</p>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-6 col-xl-4">
            <a href="/admin/system-roles" class="text-decoration-none">
                <div class="card h-100 shadow-sm border-0 iro-admin-tile">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <span class="iro-admin-tile-icon text-dark"><i class="bi bi-shield-check fs-3"></i></span>
                            <div>
                                <h2 class="h5 mb-1 text-body">Системные роли</h2>
                                <p class="small text-muted mb-0">Справочник (только просмотр)</p>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    <?php endif; ?>
</div>

<?php if (empty($isAdmin)): ?>
    <p class="small text-muted mt-4 mb-0">Расширенные разделы (новости, серии, шаблоны согласий, партнёры) доступны только администраторам сайта.</p>
<?php endif; ?>
</section>
