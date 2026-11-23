<?php

if ($uri === '/admin' || $uri === '/admin/' || $uri === '/admin/panel' || $uri === '/admin/panel/') {
    $uid = require_login();
    if (!can_manage_contests_global($pdo, $uid)) {
        flash_set('danger', 'Нет доступа');
        redirect('/');
    }
    render_admin('admin/panel', [
        'title' => 'Админ-панель',
        'admin_page_title' => 'Панель управления',
        'admin_sidebar_active' => 'panel',
    ]);
    exit;
}

if ($uri === '/admin/system-roles' || $uri === '/admin/system-roles/') {
    $uid = require_login();
    require_site_admin($pdo, $uid);
    $roles = $pdo->query('SELECT id, slug, name FROM system_roles ORDER BY id')->fetchAll();
    render_admin('admin/system_roles', [
        'title' => 'Системные роли',
        'admin_page_title' => 'Системные роли',
        'admin_sidebar_active' => 'roles',
        'roles' => $roles,
    ]);
    exit;
}

if ($uri === '/admin/consent-documents/delete' && is_post()) {
    $uid = require_login();
    require_site_admin($pdo, $uid);
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare('DELETE FROM consent_documents WHERE id = :id')->execute(['id' => $id]);
        flash_set('success', 'Шаблон удалён');
    }
    redirect('/admin/consent-documents');
    exit;
}

if ($uri === '/admin/consent-documents/create') {
    $uid = require_login();
    require_site_admin($pdo, $uid);
    $errors = [];
    if (is_post()) {
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            $errors[] = 'Укажите название.';
        }
        $filePath = upload_file($_FILES['file'] ?? [], 'consent_templates');
        if ($filePath === null) {
            $errors[] = 'Загрузите файл.';
        }
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        if (!$errors) {
            $ins = $pdo->prepare(
                'INSERT INTO consent_documents (title, file_path, is_active) VALUES (:title, :path, :active)'
            );
            $ins->execute(['title' => $title, 'path' => $filePath, 'active' => $isActive]);
            flash_set('success', 'Шаблон добавлен');
            redirect('/admin/consent-documents');
        }
    }
    render_admin('admin/consent_document_form', [
        'title' => 'Новый шаблон согласия',
        'admin_page_title' => 'Шаблоны согласий',
        'admin_sidebar_active' => 'consent_docs',
        'errors' => $errors,
        'doc' => null,
    ]);
    exit;
}

if ($uri === '/admin/consent-documents/edit') {
    $uid = require_login();
    require_site_admin($pdo, $uid);
    $id = is_post() ? (int) ($_POST['id'] ?? 0) : (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(404);
        render('errors/404', ['title' => 'Не найдено', 'flash' => $flash, 'user' => $user, 'breadcrumbs' => iro_breadcrumbs_not_found()]);
        exit;
    }
    $st = $pdo->prepare('SELECT * FROM consent_documents WHERE id = :id LIMIT 1');
    $st->execute(['id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        render('errors/404', ['title' => 'Не найдено', 'flash' => $flash, 'user' => $user, 'breadcrumbs' => iro_breadcrumbs_not_found()]);
        exit;
    }
    $errors = [];
    if (is_post()) {
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            $errors[] = 'Укажите название.';
        }
        $oldDocPath = trim((string) ($row['file_path'] ?? ''));
        $filePath = $oldDocPath;
        if (!empty($_FILES['file']['name'])) {
            $up = upload_file($_FILES['file'], 'consent_templates');
            if ($up === null) {
                $errors[] = 'Не удалось загрузить файл.';
            } else {
                $filePath = $up;
            }
        }
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        if (!$errors) {
            $pdo->prepare(
                'UPDATE consent_documents SET title = :title, file_path = :path, is_active = :active WHERE id = :id'
            )->execute(['title' => $title, 'path' => $filePath, 'active' => $isActive, 'id' => $id]);
            if ($oldDocPath !== '' && $filePath !== '' && $filePath !== $oldDocPath) {
                iro_delete_public_upload($oldDocPath);
            }
            flash_set('success', 'Шаблон обновлён');
            redirect('/admin/consent-documents');
        }
    }
    render_admin('admin/consent_document_form', [
        'title' => 'Редактировать шаблон',
        'admin_page_title' => 'Шаблоны согласий',
        'admin_sidebar_active' => 'consent_docs',
        'errors' => $errors,
        'doc' => $row,
    ]);
    exit;
}

if ($uri === '/admin/consent-documents' || $uri === '/admin/consent-documents/') {
    $uid = require_login();
    require_site_admin($pdo, $uid);
    $items = $pdo->query('SELECT id, title, file_path, is_active FROM consent_documents ORDER BY id DESC')->fetchAll();
    render_admin('admin/consent_documents_list', [
        'title' => 'Шаблоны согласий',
        'admin_page_title' => 'Шаблоны согласий',
        'admin_sidebar_active' => 'consent_docs',
        'items' => $items,
    ]);
    exit;
}

if ($uri === '/admin/partners/delete' && is_post()) {
    $uid = require_login();
    require_site_admin($pdo, $uid);
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $st = $pdo->prepare('SELECT logo_path FROM site_partners WHERE id = :id LIMIT 1');
        $st->execute(['id' => $id]);
        $path = trim((string) ($st->fetchColumn() ?: ''));
        if ($path !== '') {
            iro_delete_public_upload($path);
        }
        $pdo->prepare('DELETE FROM site_partners WHERE id = :id')->execute(['id' => $id]);
        flash_set('success', 'Партнёр удалён');
    }
    redirect('/admin/partners');
    exit;
}

if ($uri === '/admin/partners/create') {
    $uid = require_login();
    require_site_admin($pdo, $uid);
    $errors = [];
    if (is_post()) {
        $title = trim((string) ($_POST['title'] ?? ''));
        $websiteUrl = trim((string) ($_POST['website_url'] ?? ''));
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);
        if ($title === '') {
            $errors[] = 'Укажите название (подпись к логотипу).';
        }
        $logoPath = upload_file($_FILES['logo'] ?? [], 'partner_logos');
        if ($logoPath === null) {
            $errors[] = 'Загрузите логотип (PNG, JPG, WebP до разумного размера).';
        }
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        if (!$errors) {
            $ins = $pdo->prepare(
                'INSERT INTO site_partners (title, website_url, logo_path, sort_order, is_active)
                 VALUES (:title, :url, :logo, :sort, :active)'
            );
            $ins->execute([
                'title' => $title,
                'url' => $websiteUrl,
                'logo' => $logoPath,
                'sort' => $sortOrder,
                'active' => $isActive,
            ]);
            flash_set('success', 'Партнёр добавлен');
            redirect('/admin/partners');
        }
    }
    $nextSort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM site_partners')->fetchColumn();
    render_admin('admin/partner_form', [
        'title' => 'Новый партнёр',
        'admin_page_title' => 'Партнёры',
        'admin_sidebar_active' => 'partners',
        'errors' => $errors,
        'partner' => null,
        'default_sort_order' => $nextSort,
    ]);
    exit;
}

if ($uri === '/admin/partners/edit') {
    $uid = require_login();
    require_site_admin($pdo, $uid);
    $id = is_post() ? (int) ($_POST['id'] ?? 0) : (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(404);
        echo 'Не найдено';
        exit;
    }
    $stmt = $pdo->prepare('SELECT * FROM site_partners WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo 'Не найдено';
        exit;
    }
    $errors = [];
    if (is_post()) {
        $title = trim((string) ($_POST['title'] ?? ''));
        $websiteUrl = trim((string) ($_POST['website_url'] ?? ''));
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);
        if ($title === '') {
            $errors[] = 'Укажите название.';
        }
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $newLogo = upload_file($_FILES['logo'] ?? [], 'partner_logos');
        $logoPath = trim((string) ($row['logo_path'] ?? ''));
        if ($newLogo !== null) {
            if ($logoPath !== '') {
                iro_delete_public_upload($logoPath);
            }
            $logoPath = $newLogo;
        }
        if ($logoPath === '') {
            $errors[] = 'Нужен логотип (загрузите файл).';
        }
        if (!$errors) {
            $pdo->prepare(
                'UPDATE site_partners SET title = :title, website_url = :url, logo_path = :logo, sort_order = :sort, is_active = :active WHERE id = :id'
            )->execute([
                'title' => $title,
                'url' => $websiteUrl,
                'logo' => $logoPath,
                'sort' => $sortOrder,
                'active' => $isActive,
                'id' => $id,
            ]);
            flash_set('success', 'Партнёр обновлён');
            redirect('/admin/partners');
        }
    }
    render_admin('admin/partner_form', [
        'title' => 'Редактировать партнёра',
        'admin_page_title' => 'Партнёры',
        'admin_sidebar_active' => 'partners',
        'errors' => $errors,
        'partner' => $row,
        'default_sort_order' => (int) ($row['sort_order'] ?? 0),
    ]);
    exit;
}

if ($uri === '/admin/partners' || $uri === '/admin/partners/') {
    $uid = require_login();
    require_site_admin($pdo, $uid);
    $items = $pdo->query(
        'SELECT id, title, website_url, logo_path, sort_order, is_active FROM site_partners ORDER BY sort_order ASC, id ASC'
    )->fetchAll();
    render_admin('admin/partners_list', [
        'title' => 'Партнёры',
        'admin_page_title' => 'Партнёры на главной',
        'admin_sidebar_active' => 'partners',
        'items' => $items,
    ]);
    exit;
}

if ($uri === '/admin/series/delete' && is_post()) {
    $uid = require_login();
    require_site_admin($pdo, $uid);
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare('DELETE FROM contest_series WHERE id = :id')->execute(['id' => $id]);
        flash_set('success', 'Серия удалена');
    }
    redirect('/admin/series');
    exit;
}

if ($uri === '/admin/series/create') {
    $uid = require_login();
    require_site_admin($pdo, $uid);
    $errors = [];
    if (is_post()) {
        $title = trim((string) ($_POST['title'] ?? ''));
        $slugIn = trim((string) ($_POST['slug'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);
        if ($title === '') {
            $errors[] = 'Укажите название.';
        }
        $slugBase = $slugIn !== '' ? admin_slugify($slugIn) : admin_slugify($title);
        $coverPath = null;
        if (!empty($_FILES['cover']['name'])) {
            $coverPath = upload_file($_FILES['cover'], 'series_covers');
            if ($coverPath === null) {
                $errors[] = 'Не удалось загрузить обложку.';
            }
        }
        if (!$errors) {
            $slug = admin_unique_slug($pdo, 'contest_series', $slugBase, null);
            $ins = $pdo->prepare(
                'INSERT INTO contest_series (title, slug, description, cover_image_path, sort_order)
                 VALUES (:title, :slug, :description, :cover, :sort_order)'
            );
            $ins->execute([
                'title' => $title,
                'slug' => $slug,
                'description' => $description,
                'cover' => $coverPath,
                'sort_order' => $sortOrder,
            ]);
            flash_set('success', 'Серия создана');
            redirect('/admin/series');
        }
    }
    render_admin('admin/series_form', [
        'title' => 'Новая серия',
        'admin_page_title' => 'Серии',
        'admin_sidebar_active' => 'series',
        'errors' => $errors,
        'series' => null,
    ]);
    exit;
}

if ($uri === '/admin/series/edit') {
    $uid = require_login();
    require_site_admin($pdo, $uid);
    $id = is_post() ? (int) ($_POST['id'] ?? 0) : (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(404);
        render('errors/404', ['title' => 'Не найдено', 'flash' => $flash, 'user' => $user, 'breadcrumbs' => iro_breadcrumbs_not_found()]);
        exit;
    }
    $st = $pdo->prepare('SELECT * FROM contest_series WHERE id = :id LIMIT 1');
    $st->execute(['id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        render('errors/404', ['title' => 'Не найдено', 'flash' => $flash, 'user' => $user, 'breadcrumbs' => iro_breadcrumbs_not_found()]);
        exit;
    }
    $errors = [];
    if (is_post()) {
        $title = trim((string) ($_POST['title'] ?? ''));
        $slugIn = trim((string) ($_POST['slug'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);
        if ($title === '') {
            $errors[] = 'Укажите название.';
        }
        $slugBase = $slugIn !== '' ? admin_slugify($slugIn) : admin_slugify($title);
        $oldSeriesCover = trim((string) ($row['cover_image_path'] ?? ''));
        $coverPath = $row['cover_image_path'] ?? null;
        if (!empty($_FILES['cover']['name'])) {
            $up = upload_file($_FILES['cover'], 'series_covers');
            if ($up === null) {
                $errors[] = 'Не удалось загрузить обложку.';
            } else {
                $coverPath = $up;
            }
        }
        if (!$errors) {
            $slug = admin_unique_slug($pdo, 'contest_series', $slugBase, $id);
            $pdo->prepare(
                'UPDATE contest_series SET title=:title, slug=:slug, description=:description, cover_image_path=:cover, sort_order=:sort_order WHERE id=:id'
            )->execute([
                'title' => $title,
                'slug' => $slug,
                'description' => $description,
                'cover' => $coverPath,
                'sort_order' => $sortOrder,
                'id' => $id,
            ]);
            $newCover = trim((string) ($coverPath ?? ''));
            if ($oldSeriesCover !== '' && $newCover !== '' && $newCover !== $oldSeriesCover) {
                iro_delete_public_upload($oldSeriesCover);
            }
            flash_set('success', 'Серия обновлена');
            redirect('/admin/series');
        }
        $st->execute(['id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: $row;
    }
    render_admin('admin/series_form', [
        'title' => 'Редактировать серию',
        'admin_page_title' => 'Серии',
        'admin_sidebar_active' => 'series',
        'errors' => $errors,
        'series' => $row,
    ]);
    exit;
}

if ($uri === '/admin/series' || $uri === '/admin/series/') {
    $uid = require_login();
    require_site_admin($pdo, $uid);
    $items = $pdo->query('SELECT id, title, slug, sort_order FROM contest_series ORDER BY sort_order, title')->fetchAll();
    render_admin('admin/series_list', [
        'title' => 'Серии конкурсов',
        'admin_page_title' => 'Серии',
        'admin_sidebar_active' => 'series',
        'items' => $items,
    ]);
    exit;
}

if ($uri === '/admin/news/delete' && is_post()) {
    $uid = require_login();
    require_site_admin($pdo, $uid);
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare('DELETE FROM site_announcements WHERE id = :id')->execute(['id' => $id]);
        flash_set('success', 'Новость удалена');
    }
    redirect('/admin/news');
    exit;
}

if ($uri === '/admin/news/create') {
    $uid = require_login();
    require_site_admin($pdo, $uid);
    $errors = [];
    if (is_post()) {
        $title = trim((string) ($_POST['title'] ?? ''));
        $slugIn = trim((string) ($_POST['slug'] ?? ''));
        $summary = trim((string) ($_POST['summary'] ?? ''));
        $body = sanitize_announcement_html((string) ($_POST['body'] ?? ''));
        $isPublished = isset($_POST['is_published']) ? 1 : 0;
        $isPinned = isset($_POST['is_pinned']) ? 1 : 0;
        $pubRaw = trim((string) ($_POST['published_at'] ?? ''));
        if ($title === '') {
            $errors[] = 'Укажите заголовок.';
        }
        $slugBase = $slugIn !== '' ? admin_slugify($slugIn) : admin_slugify($title);
        if (!$errors) {
            $slug = admin_unique_slug($pdo, 'site_announcements', $slugBase, null);
            $ts = $pubRaw !== '' ? strtotime(str_replace('T', ' ', $pubRaw)) : false;
            $publishedAt = ($pubRaw !== '' && $ts !== false) ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s');
            $ins = $pdo->prepare(
                'INSERT INTO site_announcements (title, slug, summary, body, is_published, is_pinned, published_at)
                 VALUES (:title, :slug, :summary, :body, :pub, :pin, :published_at)'
            );
            $ins->execute([
                'title' => $title,
                'slug' => $slug,
                'summary' => $summary,
                'body' => $body,
                'pub' => $isPublished,
                'pin' => $isPinned,
                'published_at' => $publishedAt,
            ]);
            flash_set('success', 'Новость создана');
            redirect('/admin/news');
        }
    }
    render_admin('admin/news_form', [
        'title' => 'Новая новость',
        'admin_page_title' => 'Новости',
        'admin_sidebar_active' => 'news',
        'errors' => $errors,
        'news' => null,
    ]);
    exit;
}

if ($uri === '/admin/news/edit') {
    $uid = require_login();
    require_site_admin($pdo, $uid);
    $id = is_post() ? (int) ($_POST['id'] ?? 0) : (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(404);
        render('errors/404', ['title' => 'Не найдено', 'flash' => $flash, 'user' => $user, 'breadcrumbs' => iro_breadcrumbs_not_found()]);
        exit;
    }
    $st = $pdo->prepare('SELECT * FROM site_announcements WHERE id = :id LIMIT 1');
    $st->execute(['id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        render('errors/404', ['title' => 'Не найдено', 'flash' => $flash, 'user' => $user, 'breadcrumbs' => iro_breadcrumbs_not_found()]);
        exit;
    }
    $errors = [];
    if (is_post()) {
        $title = trim((string) ($_POST['title'] ?? ''));
        $slugIn = trim((string) ($_POST['slug'] ?? ''));
        $summary = trim((string) ($_POST['summary'] ?? ''));
        $body = sanitize_announcement_html((string) ($_POST['body'] ?? ''));
        $isPublished = isset($_POST['is_published']) ? 1 : 0;
        $isPinned = isset($_POST['is_pinned']) ? 1 : 0;
        $pubRaw = trim((string) ($_POST['published_at'] ?? ''));
        if ($title === '') {
            $errors[] = 'Укажите заголовок.';
        }
        $slugBase = $slugIn !== '' ? admin_slugify($slugIn) : admin_slugify($title);
        if (!$errors) {
            $slug = admin_unique_slug($pdo, 'site_announcements', $slugBase, $id);
            $ts = $pubRaw !== '' ? strtotime(str_replace('T', ' ', $pubRaw)) : false;
            $publishedAt = ($pubRaw !== '' && $ts !== false) ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s');
            $pdo->prepare(
                'UPDATE site_announcements SET title=:title, slug=:slug, summary=:summary, body=:body,
                 is_published=:pub, is_pinned=:pin, published_at=:published_at, updated_at=CURRENT_TIMESTAMP
                 WHERE id=:id'
            )->execute([
                'title' => $title,
                'slug' => $slug,
                'summary' => $summary,
                'body' => $body,
                'pub' => $isPublished,
                'pin' => $isPinned,
                'published_at' => $publishedAt,
                'id' => $id,
            ]);
            flash_set('success', 'Новость обновлена');
            redirect('/admin/news');
        }
        $st->execute(['id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: $row;
    }
    render_admin('admin/news_form', [
        'title' => 'Редактировать новость',
        'admin_page_title' => 'Новости',
        'admin_sidebar_active' => 'news',
        'errors' => $errors,
        'news' => $row,
    ]);
    exit;
}

if ($uri === '/admin/news' || $uri === '/admin/news/') {
    $uid = require_login();
    require_site_admin($pdo, $uid);
    $items = $pdo->query(
        'SELECT id, title, slug, summary, is_published, is_pinned, published_at
         FROM site_announcements ORDER BY published_at DESC, id DESC'
    )->fetchAll();
    render_admin('admin/news_list', [
        'title' => 'Новости',
        'admin_page_title' => 'Новости',
        'admin_sidebar_active' => 'news',
        'items' => $items,
    ]);
    exit;
}

if ($uri === '/admin/contests' || $uri === '/admin/contests/') {
    $uid = require_login();
    if (!can_manage_contests_global($pdo, $uid)) {
        flash_set('danger', 'Нет доступа');
        redirect('/');
    }
    $q = trim((string) ($_GET['q'] ?? ''));
    if ($isAdmin) {
        $sql = 'SELECT c.* FROM contests c';
        $params = [];
        if ($q !== '') {
            $sql .= ' WHERE LOWER(c.title) LIKE :q';
            $params['q'] = '%' . mb_strtolower($q) . '%';
        }
        $sql .= ' ORDER BY c.id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $contests = $stmt->fetchAll();
    } else {
        $sql = 'SELECT c.* FROM contests c JOIN contest_moderators cm ON cm.contest_id = c.id WHERE cm.user_id = :uid';
        $params = ['uid' => $uid];
        if ($q !== '') {
            $sql .= ' AND LOWER(c.title) LIKE :q';
            $params['q'] = '%' . mb_strtolower($q) . '%';
        }
        $sql .= ' ORDER BY c.id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $contests = $stmt->fetchAll();
    }
    render_admin('admin/contests_list', [
        'title' => 'Конкурсы (управление)',
        'admin_page_title' => 'Конкурсы',
        'admin_sidebar_active' => 'contests',
        'contests' => $contests,
        'q' => $q,
    ]);
    exit;
}

if ($uri === '/admin/contests/archived' || $uri === '/admin/contests/archived/') {
    $uid = require_login();
    if (!$isAdmin) {
        flash_set('danger', 'Раздел «Архив» доступен только администратору сайта.');
        redirect('/admin/contests');
    }
    $q = trim((string) ($_GET['q'] ?? ''));
    $sql = 'SELECT c.id, c.title, c.start_date, c.end_date, c.is_archived, c.archived_at, c.archive_report_path
            FROM contests c WHERE COALESCE(c.is_archived, 0) = 1';
    $params = [];
    if ($q !== '') {
        $sql .= ' AND LOWER(c.title) LIKE :q';
        $params['q'] = '%' . mb_strtolower($q) . '%';
    }
    $sql .= ' ORDER BY c.archived_at DESC, c.id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $archived_contests = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    render_admin('admin/contests_archived', [
        'title' => 'Архив конкурсов',
        'admin_page_title' => 'Архив конкурсов',
        'admin_sidebar_active' => 'contests_archived',
        'archived_contests' => $archived_contests,
        'q' => $q,
    ]);
    exit;
}

if ($uri === '/admin/contests/archive' && is_post()) {
    $uid = require_login();
    if (!$isAdmin) {
        flash_set('danger', 'Архивировать конкурсы может только администратор сайта.');
        redirect('/admin/contests');
    }
    $archiveContestId = (int) ($_POST['contest_id'] ?? 0);
    if ($archiveContestId <= 0) {
        flash_set('danger', 'Не указан конкурс.');
        redirect('/admin/contests');
    }
    $result = contest_archive_execute($pdo, $archiveContestId);
    flash_set($result['ok'] ? 'success' : 'danger', $result['message']);
    redirect($result['ok'] ? '/admin/contests/archived' : '/admin/contests');
}

if ($uri === '/admin/users/edit' || $uri === '/admin/users/edit/') {
    $uid = require_login();
    if (!$isAdmin) {
        flash_set('danger', 'Нет доступа');
        redirect('/');
    }

    $returnPage = max(1, (int) ($_POST['return_page'] ?? $_GET['return_page'] ?? 1));
    $returnQ = trim((string) ($_POST['return_q'] ?? $_GET['return_q'] ?? ''));
    $redirList = '/admin/users?page=' . $returnPage . ($returnQ !== '' ? '&q=' . urlencode($returnQ) : '');

    if (is_post()) {
        $editId = (int) ($_POST['user_id'] ?? 0);
        if ($editId <= 0) {
            flash_set('danger', 'Некорректный пользователь');
            redirect($redirList);
        }

        $rolesStmt = $pdo->query('SELECT id, slug, name FROM system_roles ORDER BY name');
        $rolesRows = $rolesStmt->fetchAll();
        $validBySlug = [];
        foreach ($rolesRows as $r) {
            $validBySlug[(string) $r['slug']] = (int) $r['id'];
        }

        $chosen = $_POST['roles'] ?? [];
        if (!is_array($chosen)) {
            $chosen = [];
        }
        $chosenRoleIds = [];
        $editableSlugs = admin_editable_site_role_slugs();
        foreach ($chosen as $slug) {
            $slug = (string) $slug;
            if (!in_array($slug, $editableSlugs, true)) {
                continue;
            }
            if (isset($validBySlug[$slug])) {
                $chosenRoleIds[] = $validBySlug[$slug];
            }
        }
        $chosenRoleIds = array_values(array_unique($chosenRoleIds));

        $editableRoleIds = [];
        foreach ($editableSlugs as $slug) {
            if (isset($validBySlug[$slug])) {
                $editableRoleIds[] = $validBySlug[$slug];
            }
        }
        $adminRoleId = $validBySlug['admin'] ?? 0;
        $removesAdmin = $adminRoleId > 0
            && has_role($pdo, $editId, 'admin')
            && !in_array($adminRoleId, $chosenRoleIds, true);
        if ($removesAdmin) {
            if ($editId === $uid) {
                flash_set('danger', 'Нельзя снять роль администратора с самого себя.');
                redirect('/admin/users/edit?id=' . $editId);
            }
            $adminCount = (int) $pdo->query(
                "SELECT COUNT(DISTINCT ur.user_id) FROM user_roles ur
                 JOIN system_roles sr ON sr.id = ur.role_id
                 JOIN users u ON u.id = ur.user_id
                 WHERE sr.slug = 'admin' AND u.is_active = 1"
            )->fetchColumn();
            if ($adminCount <= 1) {
                flash_set('danger', 'Нельзя снять роль у последнего активного администратора.');
                redirect('/admin/users/edit?id=' . $editId);
            }
        }

        if ($editableRoleIds !== []) {
            $pdo->beginTransaction();
            try {
                $ph = implode(',', array_fill(0, count($editableRoleIds), '?'));
                $pdo->prepare("DELETE FROM user_roles WHERE user_id = ? AND role_id IN ($ph)")
                    ->execute(array_merge([$editId], $editableRoleIds));
                $ins = $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)');
                foreach ($chosenRoleIds as $rid) {
                    $ins->execute(['user_id' => $editId, 'role_id' => $rid]);
                }
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        $newPw = (string) ($_POST['new_password'] ?? '');
        $confirmPw = (string) ($_POST['new_password_confirm'] ?? '');
        $pwMsg = null;
        if ($newPw !== '' || $confirmPw !== '') {
            if ($newPw !== $confirmPw) {
                $pwMsg = 'Пароль и подтверждение не совпадают.';
            } else {
                $pwMsg = Auth::adminSetPassword($pdo, $editId, $newPw);
            }
        }

        if ($pwMsg !== null) {
            flash_set('danger', 'Роли сохранены. Пароль не изменён: ' . $pwMsg);
        } else {
            $parts = ['Роли пользователя обновлены'];
            if ($newPw !== '' && $confirmPw !== '') {
                $parts[] = 'пароль изменён';
            }
            flash_set('success', implode(', ', $parts) . '.');
        }
        redirect($redirList);
    }

    $editId = (int) ($_GET['id'] ?? 0);
    if ($editId <= 0) {
        flash_set('danger', 'Пользователь не указан');
        redirect('/admin/users');
    }

    $uStmt = $pdo->prepare('SELECT id, email, first_name, last_name, middle_name FROM users WHERE id = :id LIMIT 1');
    $uStmt->execute(['id' => $editId]);
    $editUser = $uStmt->fetch(PDO::FETCH_ASSOC);
    if (!$editUser) {
        flash_set('danger', 'Пользователь не найден');
        redirect('/admin/users');
    }

    $editRoleSlugs = [];
    $rs = $pdo->prepare(
        'SELECT sr.slug FROM user_roles ur INNER JOIN system_roles sr ON sr.id = ur.role_id WHERE ur.user_id = :uid'
    );
    $rs->execute(['uid' => $editId]);
    foreach ($rs->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $editRoleSlugs[] = (string) $row['slug'];
    }

    $roles = $pdo->query('SELECT id, slug, name FROM system_roles ORDER BY name')->fetchAll();

    render_admin('admin/user_edit', [
        'title' => 'Редактирование пользователя',
        'admin_page_title' => 'Пользователь',
        'admin_sidebar_active' => 'users',
        'edit_user' => $editUser,
        'edit_user_role_slugs' => $editRoleSlugs,
        'roles' => $roles,
        'return_page' => $returnPage,
        'return_q' => $returnQ,
    ]);
    exit;
}

if ($uri === '/admin/users' || $uri === '/admin/users/') {
    $uid = require_login();
    if (!$isAdmin) {
        flash_set('danger', 'Нет доступа');
        redirect('/');
    }

    $q = trim((string) ($_GET['q'] ?? ''));

    $roles = $pdo->query('SELECT id, slug, name FROM system_roles ORDER BY name')->fetchAll();

    $perPage = 25;
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $countSql = 'SELECT COUNT(*) FROM users u';
    $listSql =
        'SELECT u.id, u.email, u.first_name, u.last_name, u.middle_name
         FROM users u';
    $params = [];
    if ($q !== '') {
        $like = '%' . mb_strtolower($q) . '%';
        $block = users_search_email_fio_clause('u', 'uq', $like);
        $where = ' WHERE ' . $block['clause'];
        $countSql .= $where;
        $listSql .= $where;
        $params = $block['params'];
    }
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalUsers = (int) $countStmt->fetchColumn();
    $pages = max(1, (int) ceil($totalUsers / $perPage));
    if ($page > $pages) {
        $page = $pages;
    }
    $offset = ($page - 1) * $perPage;
    $listSql .= ' ORDER BY u.last_name, u.first_name, u.id LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;
    $stmt = $pdo->prepare($listSql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    $roleMap = [];
    if ($users) {
        $ids = array_map(static fn(array $u): int => (int) $u['id'], $users);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $rStmt = $pdo->prepare(
            "SELECT ur.user_id, sr.slug
             FROM user_roles ur
             JOIN system_roles sr ON sr.id = ur.role_id
             WHERE ur.user_id IN ($in)"
        );
        $rStmt->execute($ids);
        foreach ($rStmt->fetchAll() as $row) {
            $roleMap[(int) $row['user_id']][] = (string) $row['slug'];
        }
    }
    foreach ($users as &$u) {
        $u['role_slugs'] = $roleMap[(int) $u['id']] ?? [];
    }
    unset($u);

    render_admin('admin/users', [
        'title' => 'Пользователи',
        'admin_page_title' => 'Пользователи',
        'admin_sidebar_active' => 'users',
        'q' => $q,
        'users' => $users,
        'roles' => $roles,
        'page' => $page,
        'pages' => $pages,
        'total_users' => $totalUsers,
        'per_page' => $perPage,
    ]);
    exit;
}

if ($uri === '/admin/api/user-picker') {
    header('Content-Type: application/json; charset=utf-8');
    $uid = Auth::userId();
    if ($uid === null) {
        http_response_code(401);
        echo json_encode(['error' => 'auth', 'users' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $role = (string) ($_GET['role'] ?? '');
    $q = trim((string) ($_GET['q'] ?? ''));
    $contestId = (int) ($_GET['contest_id'] ?? 0);
    $forStage = ($_GET['for_stage'] ?? '') === '1' || ($_GET['for_stage'] ?? '') === 'true';

    if (!in_array($role, ['moderator', 'expert'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'bad_role', 'users' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($role === 'moderator') {
        if (!has_role($pdo, $uid, 'admin')) {
            http_response_code(403);
            echo json_encode(['error' => 'forbidden', 'users' => []], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $rows = user_picker_search_moderators($pdo, $q, 50);
    } elseif ($forStage) {
        if ($contestId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'contest_id', 'users' => []], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (!can_manage_contest($pdo, $uid, $contestId)) {
            http_response_code(403);
            echo json_encode(['error' => 'forbidden', 'users' => []], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $rows = user_picker_search_experts_for_stage($pdo, $contestId, $q, 50);
    } else {
        if ($contestId > 0) {
            if (!$isAdmin && !contest_is_moderator($pdo, $uid, $contestId)) {
                http_response_code(403);
                echo json_encode(['error' => 'forbidden', 'users' => []], JSON_UNESCAPED_UNICODE);
                exit;
            }
        } elseif (!can_manage_contests_global($pdo, $uid)) {
            http_response_code(403);
            echo json_encode(['error' => 'forbidden', 'users' => []], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $rows = user_picker_search_experts_global($pdo, $q, 50);
    }

    $users = [];
    foreach ($rows as $r) {
        $users[] = ['id' => (int) $r['id'], 'label' => user_picker_label($r)];
    }
    echo json_encode(['users' => $users], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($uri === '/admin/contests/create') {
    $uid = require_login();
    if (!can_manage_contests_global($pdo, $uid)) {
        flash_set('danger', 'Нет доступа');
        redirect('/');
    }

    $series = $pdo->query('SELECT id, title FROM contest_series ORDER BY sort_order, title')->fetchAll();

    $errors = [];
    $attachmentFields = [];
    $pickerModeratorsInitial = [];
    $pickerExpertsInitial = [];
    if (is_post()) {
        $title = trim((string) ($_POST['title'] ?? ''));
        $start = (string) ($_POST['start_date'] ?? '');
        $end = (string) ($_POST['end_date'] ?? '');
        $description = trim((string) ($_POST['description'] ?? ''));
        $status = (int) ($_POST['status'] ?? 1);
        $docClosed = isset($_POST['document_intake_closed']) ? 1 : 0;
        $gate = isset($_POST['require_explicit_stage_admission']) ? 1 : 0;
        $stageConduct = (int) ($_POST['stage_conduct_mode'] ?? 0);
        $stageConduct = $stageConduct === CONTEST_STAGE_CONDUCT_PARALLEL ? CONTEST_STAGE_CONDUCT_PARALLEL : CONTEST_STAGE_CONDUCT_SEQUENTIAL;
        $seriesId = (int) ($_POST['series_id'] ?? 0);
        $seriesId = $seriesId > 0 ? $seriesId : null;
        $modIds = normalize_int_list($_POST['moderator_ids'] ?? []);
        $expertIds = normalize_int_list($_POST['expert_ids'] ?? []);
        $attachmentFields = normalize_attachment_rows(
            (array) ($_POST['contest_attach_sort_order'] ?? []),
            (array) ($_POST['contest_attach_title'] ?? []),
            (array) ($_POST['contest_attach_requirements'] ?? []),
            (array) ($_POST['contest_attach_id'] ?? []),
        );

        if ($title === '') $errors[] = 'Укажите название.';
        if ($start === '' || $end === '') $errors[] = 'Укажите даты начала и окончания.';
        if ($start !== '' && $end !== '' && $end <= $start) $errors[] = 'Дата окончания должна быть позже даты начала.';
        if ($status === 5 && $seriesId === null) $errors[] = 'Для статуса «Только в серии» нужно выбрать серию.';

        $imagePath = null;
        if (!empty($_FILES['image']['name'])) {
            $imagePath = upload_file($_FILES['image'], 'contest_images');
            if ($imagePath === null) $errors[] = 'Не удалось загрузить изображение.';
        }

        $regulationsPath = null;
        $regUp = contest_regulations_process_upload($_FILES['regulations'] ?? null);
        if ($regUp['error']) {
            $errors[] = $regUp['error'];
        } elseif ($regUp['path'] !== null) {
            $regulationsPath = $regUp['path'];
        }

        $rawDocsLink = trim((string) ($_POST['documents_link'] ?? ''));
        $documentsLink = '';
        if ($rawDocsLink !== '') {
            $normDl = iro_normalize_http_url($rawDocsLink);
            if ($normDl === null) {
                $errors[] = 'Некорректная ссылка в поле «Ссылка на шаблоны, приказы и др.».';
            } else {
                $documentsLink = $normDl;
            }
        }

        if ($errors) {
            $pickerModeratorsInitial = users_picker_initial($pdo, normalize_int_list($_POST['moderator_ids'] ?? []));
            $pickerExpertsInitial = users_picker_initial($pdo, normalize_int_list($_POST['expert_ids'] ?? []));
        }

        if (!$errors) {
            $stmt = $pdo->prepare(
                'INSERT INTO contests (title, start_date, end_date, description, status, document_intake_closed, require_explicit_stage_admission, stage_conduct_mode, image_path, regulations_path, documents_link, series_id, is_external, external_url)
                 VALUES (:title, :start_date, :end_date, :description, :status, :doc_closed, :gate, :conduct, :image_path, :regulations_path, :documents_link, :series_id, 0, \'\')'
            );
            $stmt->execute([
                'title' => $title,
                'start_date' => $start,
                'end_date' => $end,
                'description' => $description,
                'status' => $status,
                'doc_closed' => $docClosed,
                'gate' => $gate,
                'conduct' => $stageConduct,
                'image_path' => $imagePath,
                'regulations_path' => $regulationsPath,
                'documents_link' => $documentsLink,
                'series_id' => $seriesId,
            ]);
            $contestId = (int) $pdo->lastInsertId();

            if (!$isAdmin) {
                $modIds = [$uid];
            } elseif (!$modIds) {
                $modIds = [$uid];
            }
            $pdo->prepare('DELETE FROM contest_moderators WHERE contest_id = :cid')->execute(['cid' => $contestId]);
            $ins = $pdo->prepare('INSERT INTO contest_moderators (contest_id, user_id) VALUES (:cid, :uid)');
            foreach ($modIds as $mid) {
                $ins->execute(['cid' => $contestId, 'uid' => $mid]);
            }

            $pdo->prepare('DELETE FROM contest_expert_pool WHERE contest_id = :cid')->execute(['cid' => $contestId]);
            $ins2 = $pdo->prepare('INSERT INTO contest_expert_pool (contest_id, user_id) VALUES (:cid, :uid)');
            foreach ($expertIds as $eid) {
                $ins2->execute(['cid' => $contestId, 'uid' => $eid]);
            }

            $pdo->prepare('DELETE FROM contest_attachment_fields WHERE contest_id = :cid')->execute(['cid' => $contestId]);
            $ins3 = $pdo->prepare(
                'INSERT INTO contest_attachment_fields (contest_id, sort_order, title, requirements)
                 VALUES (:contest_id, :sort_order, :title, :requirements)'
            );
            foreach ($attachmentFields as $row) {
                $ins3->execute([
                    'contest_id' => $contestId,
                    'sort_order' => $row['sort_order'],
                    'title' => $row['title'],
                    'requirements' => $row['requirements'],
                ]);
            }

            flash_set('success', 'Конкурс создан');
            redirect('/admin/contests');
        }
    }

    render_admin('admin/contest_form', [
        'title' => 'Создать конкурс',
        'admin_page_title' => 'Создать конкурс',
        'admin_sidebar_active' => 'contests',
        'mode' => 'create',
        'errors' => $errors,
        'contest' => null,
        'series' => $series,
        'pickerModeratorsInitial' => $pickerModeratorsInitial,
        'pickerExpertsInitial' => $pickerExpertsInitial,
        'attachmentFields' => $attachmentFields,
    ]);
    exit;
}

if ($uri === '/admin/contests/create-link' || $uri === '/admin/contests/create-link/') {
    $uid = require_login();
    if (!can_manage_contests_global($pdo, $uid)) {
        flash_set('danger', 'Нет доступа');
        redirect('/');
    }

    $series = $pdo->query('SELECT id, title FROM contest_series ORDER BY sort_order, title')->fetchAll();
    $errors = [];

    if (is_post()) {
        $title = trim((string) ($_POST['title'] ?? ''));
        $start = (string) ($_POST['start_date'] ?? '');
        $end = (string) ($_POST['end_date'] ?? '');
        $description = trim((string) ($_POST['description'] ?? ''));
        $status = (int) ($_POST['status'] ?? 1);
        $seriesId = (int) ($_POST['series_id'] ?? 0);
        $seriesId = $seriesId > 0 ? $seriesId : null;
        $rawUrl = trim((string) ($_POST['external_url'] ?? ''));
        $extUrl = iro_normalize_http_url($rawUrl);

        if ($title === '') {
            $errors[] = 'Укажите название.';
        }
        if ($start === '' || $end === '') {
            $errors[] = 'Укажите даты начала и окончания.';
        }
        if ($start !== '' && $end !== '' && $end <= $start) {
            $errors[] = 'Дата окончания должна быть позже даты начала.';
        }
        if ($status === 5 && $seriesId === null) {
            $errors[] = 'Для статуса «Только в серии» нужно выбрать серию.';
        }
        if ($rawUrl === '') {
            $errors[] = 'Укажите ссылку на страницу конкурса.';
        } elseif ($extUrl === null) {
            $errors[] = 'Некорректный адрес ссылки. Используйте http:// или https://.';
        }

        $imagePath = null;
        if (!empty($_FILES['image']['name'])) {
            $imagePath = upload_file($_FILES['image'], 'contest_images');
            if ($imagePath === null) {
                $errors[] = 'Не удалось загрузить изображение.';
            }
        }

        if (!$errors) {
            $stmt = $pdo->prepare(
                'INSERT INTO contests (title, start_date, end_date, description, status, document_intake_closed, require_explicit_stage_admission, image_path, series_id, is_external, external_url)
                 VALUES (:title, :start_date, :end_date, :description, :status, 1, 0, :image_path, :series_id, 1, :external_url)'
            );
            $stmt->execute([
                'title' => $title,
                'start_date' => $start,
                'end_date' => $end,
                'description' => $description,
                'status' => $status,
                'image_path' => $imagePath,
                'series_id' => $seriesId,
                'external_url' => $extUrl,
            ]);
            $contestId = (int) $pdo->lastInsertId();

            $pdo->prepare('DELETE FROM contest_moderators WHERE contest_id = :cid')->execute(['cid' => $contestId]);
            $ins = $pdo->prepare('INSERT INTO contest_moderators (contest_id, user_id) VALUES (:cid, :uid)');
            $ins->execute(['cid' => $contestId, 'uid' => $uid]);

            flash_set('success', 'Конкурс-ссылка создан');
            redirect('/admin/contests');
        }
    }

    render_admin('admin/contest_link_form', [
        'title' => 'Создать конкурс-ссылку',
        'admin_page_title' => 'Конкурс-ссылка',
        'admin_sidebar_active' => 'contests',
        'mode' => 'create',
        'errors' => $errors,
        'contest' => null,
        'series' => $series,
    ]);
    exit;
}

if ($uri === '/admin/contests/edit-link' || $uri === '/admin/contests/edit-link/') {
    $uid = require_login();
    if (!can_manage_contests_global($pdo, $uid)) {
        flash_set('danger', 'Нет доступа');
        redirect('/');
    }
    $contestId = (int) ($_POST['contest_id'] ?? $_GET['id'] ?? 0);
    $contest = get_contest($pdo, $contestId);
    if (!$contest) {
        http_response_code(404);
        render('errors/404', ['title' => 'Не найдено', 'breadcrumbs' => iro_breadcrumbs_not_found()]);
        exit;
    }
    if ((int) ($contest['is_external'] ?? 0) !== 1) {
        redirect('/admin/contests/edit?id=' . $contestId);
    }
    if (!$isAdmin && !contest_is_moderator($pdo, $uid, $contestId)) {
        flash_set('danger', 'Нет доступа к конкурсу');
        redirect('/admin/contests');
    }

    $series = $pdo->query('SELECT id, title FROM contest_series ORDER BY sort_order, title')->fetchAll();
    $errors = [];

    if (is_post()) {
        $title = trim((string) ($_POST['title'] ?? ''));
        $start = (string) ($_POST['start_date'] ?? '');
        $end = (string) ($_POST['end_date'] ?? '');
        $description = trim((string) ($_POST['description'] ?? ''));
        $status = (int) ($_POST['status'] ?? 1);
        $seriesId = (int) ($_POST['series_id'] ?? 0);
        $seriesId = $seriesId > 0 ? $seriesId : null;
        $rawUrl = trim((string) ($_POST['external_url'] ?? ''));
        $extUrl = iro_normalize_http_url($rawUrl);

        if ($title === '') {
            $errors[] = 'Укажите название.';
        }
        if ($start === '' || $end === '') {
            $errors[] = 'Укажите даты начала и окончания.';
        }
        if ($start !== '' && $end !== '' && $end <= $start) {
            $errors[] = 'Дата окончания должна быть позже даты начала.';
        }
        if ($status === 5 && $seriesId === null) {
            $errors[] = 'Для статуса «Только в серии» нужно выбрать серию.';
        }
        if ($rawUrl === '') {
            $errors[] = 'Укажите ссылку на страницу конкурса.';
        } elseif ($extUrl === null) {
            $errors[] = 'Некорректный адрес ссылки. Используйте http:// или https://.';
        }

        $prevLinkImage = trim((string) ($contest['image_path'] ?? ''));
        $imagePath = $contest['image_path'] ?? null;
        if (!empty($_FILES['image']['name'])) {
            $uploaded = upload_file($_FILES['image'], 'contest_images');
            if ($uploaded === null) {
                $errors[] = 'Не удалось загрузить изображение.';
            } else {
                $imagePath = $uploaded;
            }
        }

        if (!$errors) {
            $stmt = $pdo->prepare(
                'UPDATE contests
                 SET title=:title, start_date=:start_date, end_date=:end_date, description=:description, status=:status,
                     image_path=:image_path, series_id=:series_id, external_url=:external_url, document_intake_closed=1
                 WHERE id=:id'
            );
            $stmt->execute([
                'id' => $contestId,
                'title' => $title,
                'start_date' => $start,
                'end_date' => $end,
                'description' => $description,
                'status' => $status,
                'image_path' => $imagePath,
                'series_id' => $seriesId,
                'external_url' => $extUrl,
            ]);
            $newLinkImage = trim((string) ($imagePath ?? ''));
            if ($prevLinkImage !== '' && $newLinkImage !== '' && $newLinkImage !== $prevLinkImage) {
                iro_delete_public_upload($prevLinkImage);
            }
            flash_set('success', 'Конкурс-ссылка обновлён');
            redirect('/admin/contests');
        }
        $contest = get_contest($pdo, $contestId) ?: $contest;
    }

    render_admin('admin/contest_link_form', [
        'title' => 'Редактировать конкурс-ссылку',
        'admin_page_title' => 'Конкурс-ссылка',
        'admin_sidebar_active' => 'contests',
        'mode' => 'edit',
        'errors' => $errors,
        'contest' => $contest,
        'series' => $series,
    ]);
    exit;
}

if ($uri === '/admin/contests/edit') {
    $uid = require_login();
    if (!can_manage_contests_global($pdo, $uid)) {
        flash_set('danger', 'Нет доступа');
        redirect('/');
    }
    $contestId = (int) ($_POST['contest_id'] ?? $_GET['id'] ?? 0);
    $contest = get_contest($pdo, $contestId);
    if (!$contest) {
        http_response_code(404);
        render('errors/404', ['title' => 'Не найдено', 'breadcrumbs' => iro_breadcrumbs_not_found()]);
        exit;
    }
    if ((int) ($contest['is_external'] ?? 0) === 1) {
        redirect('/admin/contests/edit-link?id=' . $contestId);
    }
    if ((int) ($contest['is_archived'] ?? 0) === 1) {
        flash_set('warning', 'Архивный конкурс недоступен для редактирования (остались только название, даты и отчёт Excel в списке).');
        redirect('/admin/contests');
    }
    if (!$isAdmin && !contest_is_moderator($pdo, $uid, $contestId)) {
        flash_set('danger', 'Нет доступа к конкурсу');
        redirect('/admin/contests');
    }

    $series = $pdo->query('SELECT id, title FROM contest_series ORDER BY sort_order, title')->fetchAll();

    $selMods = $pdo->prepare('SELECT user_id FROM contest_moderators WHERE contest_id = :cid');
    $selMods->execute(['cid' => $contestId]);
    $selectedModerators = array_map('intval', array_column($selMods->fetchAll(), 'user_id'));

    $selExp = $pdo->prepare('SELECT user_id FROM contest_expert_pool WHERE contest_id = :cid');
    $selExp->execute(['cid' => $contestId]);
    $selectedExperts = array_map('intval', array_column($selExp->fetchAll(), 'user_id'));

    $pickerModeratorsInitial = users_picker_initial($pdo, $selectedModerators);
    $pickerExpertsInitial = users_picker_initial($pdo, $selectedExperts);

    $errors = [];
    $attachmentFields = [];
    if (is_post()) {
        $title = trim((string) ($_POST['title'] ?? ''));
        $start = (string) ($_POST['start_date'] ?? '');
        $end = (string) ($_POST['end_date'] ?? '');
        $description = trim((string) ($_POST['description'] ?? ''));
        $status = (int) ($_POST['status'] ?? 1);
        $docClosed = isset($_POST['document_intake_closed']) ? 1 : 0;
        $gate = isset($_POST['require_explicit_stage_admission']) ? 1 : 0;
        $stageConduct = (int) ($_POST['stage_conduct_mode'] ?? 0);
        $stageConduct = $stageConduct === CONTEST_STAGE_CONDUCT_PARALLEL ? CONTEST_STAGE_CONDUCT_PARALLEL : CONTEST_STAGE_CONDUCT_SEQUENTIAL;
        $seriesId = (int) ($_POST['series_id'] ?? 0);
        $seriesId = $seriesId > 0 ? $seriesId : null;
        $modIds = normalize_int_list($_POST['moderator_ids'] ?? []);
        $expertIds = normalize_int_list($_POST['expert_ids'] ?? []);
        $attachmentFields = normalize_attachment_rows(
            (array) ($_POST['contest_attach_sort_order'] ?? []),
            (array) ($_POST['contest_attach_title'] ?? []),
            (array) ($_POST['contest_attach_requirements'] ?? []),
            (array) ($_POST['contest_attach_id'] ?? []),
        );

        if ($title === '') $errors[] = 'Укажите название.';
        if ($start === '' || $end === '') $errors[] = 'Укажите даты начала и окончания.';
        if ($start !== '' && $end !== '' && $end <= $start) $errors[] = 'Дата окончания должна быть позже даты начала.';
        if ($status === 5 && $seriesId === null) $errors[] = 'Для статуса «Только в серии» нужно выбрать серию.';

        $prevImage = trim((string) ($contest['image_path'] ?? ''));
        $prevRegulations = trim((string) ($contest['regulations_path'] ?? ''));
        $imagePath = $contest['image_path'] ?? null;
        if (!empty($_FILES['image']['name'])) {
            $uploaded = upload_file($_FILES['image'], 'contest_images');
            if ($uploaded === null) {
                $errors[] = 'Не удалось загрузить изображение.';
            } else {
                $imagePath = $uploaded;
            }
        }

        $regulationsPath = $contest['regulations_path'] ?? null;
        $regUp = contest_regulations_process_upload($_FILES['regulations'] ?? null);
        if ($regUp['error']) {
            $errors[] = $regUp['error'];
        } elseif ($regUp['path'] !== null) {
            $regulationsPath = $regUp['path'];
        }

        $rawDocsLink = trim((string) ($_POST['documents_link'] ?? ''));
        $documentsLink = '';
        if ($rawDocsLink !== '') {
            $normDl = iro_normalize_http_url($rawDocsLink);
            if ($normDl === null) {
                $errors[] = 'Некорректная ссылка в поле «Ссылка на шаблоны, приказы и др.».';
            } else {
                $documentsLink = $normDl;
            }
        }

        $staffIds = array_values(array_unique(array_merge($isAdmin ? $modIds : [], $expertIds)));
        foreach ($staffIds as $staffId) {
            if (contest_user_is_applicant_or_participant($pdo, $contestId, $staffId)) {
                $errors[] = 'Нельзя назначить модератором или экспертом пользователя, который подал заявку или участвует в этом конкурсе (id ' . $staffId . ').';
            }
        }

        if ($errors) {
            $pickerModeratorsInitial = users_picker_initial($pdo, normalize_int_list($_POST['moderator_ids'] ?? []));
            $pickerExpertsInitial = users_picker_initial($pdo, normalize_int_list($_POST['expert_ids'] ?? []));
        }

        if (!$errors) {
            $stmt = $pdo->prepare(
                'UPDATE contests
                 SET title=:title, start_date=:start_date, end_date=:end_date, description=:description, status=:status,
                     document_intake_closed=:doc_closed, require_explicit_stage_admission=:gate, stage_conduct_mode=:conduct,
                     image_path=:image_path, regulations_path=:regulations_path, documents_link=:documents_link, series_id=:series_id
                 WHERE id=:id'
            );
            $stmt->execute([
                'id' => $contestId,
                'title' => $title,
                'start_date' => $start,
                'end_date' => $end,
                'description' => $description,
                'status' => $status,
                'doc_closed' => $docClosed,
                'gate' => $gate,
                'conduct' => $stageConduct,
                'image_path' => $imagePath,
                'regulations_path' => $regulationsPath,
                'documents_link' => $documentsLink,
                'series_id' => $seriesId,
            ]);
            $newImg = trim((string) ($imagePath ?? ''));
            if ($prevImage !== '' && $newImg !== '' && $newImg !== $prevImage) {
                iro_delete_public_upload($prevImage);
            }
            $newReg = trim((string) ($regulationsPath ?? ''));
            if ($prevRegulations !== '' && $newReg !== '' && $newReg !== $prevRegulations) {
                iro_delete_public_upload($prevRegulations);
            }

            if ($isAdmin) {
                if (!$modIds) {
                    $modIds = [$uid];
                }
                $pdo->prepare('DELETE FROM contest_moderators WHERE contest_id = :cid')->execute(['cid' => $contestId]);
                $ins = $pdo->prepare('INSERT INTO contest_moderators (contest_id, user_id) VALUES (:cid, :uid)');
                foreach ($modIds as $mid) {
                    $ins->execute(['cid' => $contestId, 'uid' => $mid]);
                }
                $selectedModerators = $modIds;
            }

            $pdo->prepare('DELETE FROM contest_expert_pool WHERE contest_id = :cid')->execute(['cid' => $contestId]);
            $ins2 = $pdo->prepare('INSERT INTO contest_expert_pool (contest_id, user_id) VALUES (:cid, :uid)');
            foreach ($expertIds as $eid) {
                $ins2->execute(['cid' => $contestId, 'uid' => $eid]);
            }
            $selectedExperts = $expertIds;

            iro_sync_attachment_fields($pdo, 'contest', $contestId, $attachmentFields);

            flash_set('success', 'Конкурс обновлён');
            redirect('/admin/contests');
        }
        $contest = get_contest($pdo, $contestId) ?: $contest;
    }

    if (!$attachmentFields) {
        $afStmt = $pdo->prepare(
            'SELECT id, sort_order, title, requirements FROM contest_attachment_fields WHERE contest_id = :cid ORDER BY sort_order, id'
        );
        $afStmt->execute(['cid' => $contestId]);
        $attachmentFields = $afStmt->fetchAll();
    }

    render_admin('admin/contest_form', [
        'title' => 'Редактировать конкурс',
        'admin_page_title' => 'Редактировать конкурс',
        'admin_sidebar_active' => 'contests',
        'mode' => 'edit',
        'errors' => $errors,
        'contest' => $contest,
        'series' => $series,
        'pickerModeratorsInitial' => $pickerModeratorsInitial,
        'pickerExpertsInitial' => $pickerExpertsInitial,
        'attachmentFields' => $attachmentFields,
    ]);
    exit;
}
