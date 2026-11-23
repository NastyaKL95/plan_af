<?php

declare(strict_types=1);

function env_path(string $relative): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . ltrim($relative, DIRECTORY_SEPARATOR);
}

/**
 * Номера страниц для пагинации с многоточием в разрывах (1 … 5 6 7 … 20).
 *
 * @return list<int|string> целые — номер страницы, строка 'ellipsis' — пропуск
 */
function iro_pagination_pages(int $current, int $totalPages, int $neighbor = 2): array
{
    $totalPages = max(1, $totalPages);
    $current = max(1, min($current, $totalPages));

    $want = [];
    $mark = static function (int $p) use (&$want, $totalPages): void {
        if ($p >= 1 && $p <= $totalPages) {
            $want[$p] = true;
        }
    };

    $mark(1);
    $mark($totalPages);
    for ($i = $current - $neighbor; $i <= $current + $neighbor; $i++) {
        $mark($i);
    }

    $sorted = array_keys($want);
    sort($sorted, SORT_NUMERIC);

    $out = [];
    $prev = null;
    foreach ($sorted as $p) {
        if ($prev !== null && $p > $prev + 1) {
            $out[] = 'ellipsis';
        }
        $out[] = $p;
        $prev = $p;
    }

    return $out;
}

/**
 * Очередь Excel: неблокирующие flock — один активный экспорт на весь сайт и один на конкурс.
 *
 * @return array{locks: array{global: resource, contest: resource}|null, error: string|null}
 */
function iro_stats_xlsx_try_lock(int $contestId): array
{
    $dir = env_path('public/uploads/tmp');
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['locks' => null, 'error' => 'mkdir'];
    }

    $pathG = $dir . DIRECTORY_SEPARATOR . 'iro_export_stats_global.lock';
    $pathC = $dir . DIRECTORY_SEPARATOR . 'iro_export_stats_contest_' . $contestId . '.lock';

    $g = fopen($pathG, 'c+');
    if ($g === false) {
        return ['locks' => null, 'error' => 'global_open'];
    }
    if (!flock($g, LOCK_EX | LOCK_NB)) {
        fclose($g);

        return ['locks' => null, 'error' => 'busy_global'];
    }

    $c = fopen($pathC, 'c+');
    if ($c === false) {
        flock($g, LOCK_UN);
        fclose($g);

        return ['locks' => null, 'error' => 'contest_open'];
    }
    if (!flock($c, LOCK_EX | LOCK_NB)) {
        fclose($c);
        flock($g, LOCK_UN);
        fclose($g);

        return ['locks' => null, 'error' => 'busy_contest'];
    }

    return ['locks' => ['global' => $g, 'contest' => $c], 'error' => null];
}

/** @param array{global: resource, contest: resource}|null $locks */
function iro_stats_xlsx_unlock(?array $locks): void
{
    if ($locks === null) {
        return;
    }
    if (isset($locks['contest'])) {
        flock($locks['contest'], LOCK_UN);
        fclose($locks['contest']);
    }
    if (isset($locks['global'])) {
        flock($locks['global'], LOCK_UN);
        fclose($locks['global']);
    }
}

/**
 * Максимум получателей уведомления «новая работа» для экспертов (режим «все эксперты этапа»).
 */
function iro_expert_notify_max_recipients(?array $config): int
{
    $n = (int) (($config['app']['expert_notify_max_recipients'] ?? 100) ?: 100);

    return max(1, min($n, 5000));
}

/**
 * Путь запроса для маршрутизации: без завершающего слэша (кроме корня),
 * с учётом установки в подкаталоге (префикс из dirname(SCRIPT_NAME) или app.router_base_path).
 */
function router_uri_path(?array $config = null): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $path = $path === false || $path === '' ? '/' : str_replace('\\', '/', $path);

    $base = '';
    if (is_array($config)) {
        $cfg = trim(str_replace('\\', '/', (string) ($config['app']['router_base_path'] ?? '')), '/');
        if ($cfg !== '') {
            $base = '/' . $cfg;
        }
    }
    if ($base === '') {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $dir = dirname($script);
        if ($dir !== '/' && $dir !== '.' && $dir !== '') {
            $base = rtrim($dir, '/');
        }
    }
    if ($base !== '' && $base !== '/') {
        $len = strlen($base);
        if (str_starts_with($path, $base . '/') || $path === $base || $path === $base . '/') {
            $path = substr($path, $len) ?: '/';
            if ($path !== '/' && $path !== '' && !str_starts_with($path, '/')) {
                $path = '/' . $path;
            }
        }
    }

    if ($path === '' || $path === '/') {
        return '/';
    }
    if (str_ends_with($path, '/')) {
        $path = rtrim($path, '/') ?: '/';
    }

    return $path;
}

/** @return list<array{label: string, url?: string|null}> */
function iro_breadcrumbs_not_found(): array
{
    return [
        ['label' => 'Главная', 'url' => '/'],
        ['label' => 'Страница не найдена', 'url' => null],
    ];
}

/** @return list<array{label: string, url?: string|null}> */
function iro_breadcrumbs_forbidden(): array
{
    return [
        ['label' => 'Главная', 'url' => '/'],
        ['label' => 'Доступ ограничен', 'url' => null],
    ];
}

/**
 * Главная → Конкурсы → карточка конкурса (без текущей страницы).
 *
 * @return list<array{label: string, url?: string|null}>
 */
function iro_breadcrumbs_contest_nav(string $contestTitle, int $contestId): array
{
    return [
        ['label' => 'Главная', 'url' => '/'],
        ['label' => 'Конкурсы', 'url' => '/contests'],
        ['label' => $contestTitle, 'url' => '/contest?id=' . $contestId],
    ];
}

function render(string $view, array $data = []): void
{
    $base = $GLOBALS['base_view_data'] ?? [];
    if (is_array($base) && $base) {
        $data = array_merge($base, $data);
    }
    extract($data, EXTR_SKIP);
    $viewFile = env_path('views/' . $view . '.php');
    require env_path('views/layout.php');
}

/** Фрагмент без layout (для AJAX partial) */
function render_partial(string $view, array $data = []): void
{
    $base = $GLOBALS['base_view_data'] ?? [];
    if (is_array($base) && $base) {
        $data = array_merge($base, $data);
    }
    extract($data, EXTR_SKIP);
    require env_path('views/' . $view . '.php');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/**
 * Путь только внутри сайта (для редиректов из БД и т.п.): не пустой, начинается с /, не protocol-relative //.
 */
function iro_safe_relative_url(string $path): string
{
    $path = trim($path);
    if ($path === '' || !str_starts_with($path, '/') || str_starts_with($path, '//')) {
        return '/';
    }

    return $path;
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Ключи строки из PDO/MySQL приводятся к нижнему регистру (на части конфигураций имена колонок приходят в верхнем).
 *
 * @return array<string, mixed>
 */
function iro_mysql_assoc_lower_keys(array $row): array
{
    $out = [];
    foreach ($row as $k => $v) {
        $out[strtolower((string) $k)] = $v;
    }

    return $out;
}

/** Нормализация URL для конкурса-ссылки (http/https). Пустая строка → null. */
function iro_normalize_http_url(string $raw): ?string
{
    $url = trim($raw);
    if ($url === '') {
        return null;
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return null;
    }
    if (!preg_match('#^https?://#i', $url)) {
        return null;
    }

    return $url;
}

/** Дата для карточек конкурсов (как в Django date «j E Y» для ru). */
function iro_format_date_long_ru(string $ymd): string
{
    $ts = strtotime($ymd . ' 12:00:00');
    if ($ts === false) {
        return $ymd;
    }
    $months = [
        1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля', 5 => 'мая', 6 => 'июня',
        7 => 'июля', 8 => 'августа', 9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
    ];
    $d = (int) date('j', $ts);
    $m = (int) date('n', $ts);
    $y = (int) date('Y', $ts);

    return $d . ' ' . ($months[$m] ?? date('m', $ts)) . ' ' . $y;
}

/** Дата в формате дд.мм.гггг (таблицы этапов). */
function iro_format_date_dmy(string $ymd): string
{
    $ts = strtotime($ymd . ' 12:00:00');
    if ($ts === false) {
        return $ymd;
    }

    return date('d.m.Y', $ts);
}

/** Дата и время публикации новости (как в Django «j E Y, H:i»). */
function iro_format_datetime_long_ru(string $datetime): string
{
    $ts = strtotime($datetime);
    if ($ts === false) {
        return $datetime;
    }
    $datePart = iro_format_date_long_ru(date('Y-m-d', $ts));

    return $datePart . ', ' . date('H:i', $ts);
}

/** Тизер для списка новостей (как SiteAnnouncement.list_teaser в IRO). */
function iro_announcement_list_teaser(string $summary, string $body): string
{
    $s = trim($summary);
    if ($s !== '') {
        return $s;
    }
    $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($body)));

    return $plain;
}

/** Обрезка по числу слов (для списка новостей, truncatewords в Django). */
function iro_truncate_words(string $text, int $maxWords): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    if (!preg_match_all('/\S+/u', $text, $m)) {
        return $text;
    }
    $words = $m[0];
    if (count($words) <= $maxWords) {
        return $text;
    }

    return implode(' ', array_slice($words, 0, $maxWords)) . '…';
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_all(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($items) ? $items : [];
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function require_login(): int
{
    $pdo = Database::connection($GLOBALS['config'] ?? []);
    $user = Auth::user($pdo);
    if ($user === null) {
        flash_set('danger', 'Сначала войдите в систему');
        redirect('/login');
    }

    return (int) $user['id'];
}

/** Есть ли колонки oauth_provider / oauth_subject. */
function iro_users_has_oauth_columns(PDO $pdo): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        $st = $pdo->query("SHOW COLUMNS FROM users LIKE 'oauth_provider'");
        $ready = $st !== false && $st->fetch(PDO::FETCH_ASSOC) !== false;
    } catch (Throwable) {
        $ready = false;
    }

    return $ready;
}

function iro_password_reset_table_ready(PDO $pdo): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        $st = $pdo->query("SHOW TABLES LIKE 'password_reset_tokens'");
        $ready = $st !== false && $st->fetch(PDO::FETCH_ASSOC) !== false;
    } catch (Throwable) {
        $ready = false;
    }

    return $ready;
}

/**
 * Абсолютный URL сайта (для писем). Берёт app.public_origin или собирает из запроса.
 */
function iro_public_absolute_url(string $path = '/', ?array $config = null): string
{
    $config ??= ($GLOBALS['config'] ?? []);
    $path = '/' . ltrim($path, '/');
    if ($path === '//') {
        $path = '/';
    }

    $origin = rtrim(trim((string) ($config['app']['public_origin'] ?? '')), '/');
    if ($origin === '') {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        $scheme = ($https !== '' && $https !== 'off') ? 'https' : 'http';
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $fwd = strtolower(trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
            if ($fwd === 'https' || $fwd === 'http') {
                $scheme = $fwd;
            }
        }
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $origin = $scheme . '://' . $host;
    }

    $base = trim(str_replace('\\', '/', (string) ($config['app']['router_base_path'] ?? '')), '/');
    $prefix = $base !== '' ? '/' . $base : '';

    return $origin . $prefix . ($path === '/' ? '/' : $path);
}

function require_site_admin(PDO $pdo, int $userId): void
{
    if (!has_role($pdo, $userId, 'admin')) {
        flash_set('danger', 'Нет доступа к разделу администратора');
        redirect('/');
    }
}

/** Layout с боковым меню (как Django /admin/). */
function render_admin(string $view, array $data = []): void
{
    $base = $GLOBALS['base_view_data'] ?? [];
    $data = array_merge(is_array($base) ? $base : [], $data);
    $data['admin_view_file'] = env_path('views/' . $view . '.php');
    extract($data, EXTR_SKIP);
    require env_path('views/layout_admin.php');
}

function admin_slugify(string $title): string
{
    $s = mb_strtolower(trim($title), 'UTF-8');
    $s = preg_replace('/[\s\-_]+/u', '-', $s);
    $s = preg_replace('/[^\p{L}\p{N}\-]+/u', '', $s);
    $s = trim($s, '-');
    if ($s === '') {
        $s = 'item-' . bin2hex(random_bytes(4));
    }

    return mb_substr($s, 0, 270);
}

/** Уникальный slug для таблиц с ограничением UNIQUE(slug). */
function admin_unique_slug(PDO $pdo, string $table, string $slug, ?int $excludeId): string
{
    $allowed = ['site_announcements', 'contest_series'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException('Invalid table for slug');
    }
    $candidate = $slug;
    $i = 2;
    while (true) {
        if ($excludeId === null) {
            $st = $pdo->prepare("SELECT id FROM `{$table}` WHERE slug = :s LIMIT 1");
            $st->execute(['s' => $candidate]);
        } else {
            $st = $pdo->prepare("SELECT id FROM `{$table}` WHERE slug = :s AND id <> :id LIMIT 1");
            $st->execute(['s' => $candidate, 'id' => $excludeId]);
        }
        if (!$st->fetchColumn()) {
            return $candidate;
        }
        $candidate = mb_substr($slug, 0, 240) . '-' . $i;
        $i++;
    }
}

function has_role(PDO $pdo, int $userId, string $slug): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM user_roles ur
         JOIN system_roles sr ON sr.id = ur.role_id
         WHERE ur.user_id = :user_id AND sr.slug = :slug
         LIMIT 1'
    );
    $stmt->execute(['user_id' => $userId, 'slug' => $slug]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Slug'и system_roles, которые можно выставить вручную в админке «Пользователи».
 * Модератор, эксперт и участник конкурса задаются только в контексте конкурса (contest_moderators, пул/этапы, contest_participants).
 *
 * @return list<string>
 */
function admin_editable_site_role_slugs(): array
{
    return ['admin'];
}

function contest_is_moderator(PDO $pdo, int $userId, int $contestId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM contest_moderators WHERE user_id = :user_id AND contest_id = :contest_id LIMIT 1');
    $stmt->execute(['user_id' => $userId, 'contest_id' => $contestId]);
    return (bool) $stmt->fetchColumn();
}

/** Модератор хотя бы одного конкурса (глобальной роли «модератор» в системе больше нет). */
function user_is_any_contest_moderator(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM contest_moderators WHERE user_id = :uid LIMIT 1');
    $stmt->execute(['uid' => $userId]);

    return (bool) $stmt->fetchColumn();
}

/** Показ пункта «Оценка работ»: этап, пул конкурса или явное назначение на работу. */
function user_has_expert_portal_nav(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM stage_experts WHERE user_id = :uid LIMIT 1'
    );
    $stmt->execute(['uid' => $userId]);
    if ($stmt->fetchColumn()) {
        return true;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM contest_expert_pool WHERE user_id = :uid LIMIT 1');
    $stmt->execute(['uid' => $userId]);
    if ($stmt->fetchColumn()) {
        return true;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM submission_assigned_experts WHERE user_id = :uid LIMIT 1');
    $stmt->execute(['uid' => $userId]);

    return (bool) $stmt->fetchColumn();
}

/** Экспертная роль в рамках конкурса (этап / назначение на чужую работу). */
function user_is_expert_for_contest(PDO $pdo, int $userId, int $contestId): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM stage_experts se
         INNER JOIN stages st ON st.id = se.stage_id
         WHERE st.contest_id = :cid AND se.user_id = :uid
         LIMIT 1'
    );
    $stmt->execute(['cid' => $contestId, 'uid' => $userId]);
    if ($stmt->fetchColumn()) {
        return true;
    }
    $stmt = $pdo->prepare(
        'SELECT 1 FROM submission_assigned_experts sae
         INNER JOIN submissions s ON s.id = sae.submission_id
         WHERE s.contest_id = :cid AND sae.user_id = :uid
         LIMIT 1'
    );
    $stmt->execute(['cid' => $contestId, 'uid' => $userId]);

    return (bool) $stmt->fetchColumn();
}

/** Доступ к разделу «эксперт этого конкурса»: модератор конкурса, этап, пул или назначение на работу. */
function user_has_expert_hub_access_for_contest(PDO $pdo, int $userId, int $contestId): bool
{
    if (contest_expert_area_privileged($pdo, $userId, $contestId)) {
        return true;
    }
    if (user_is_expert_for_contest($pdo, $userId, $contestId)) {
        return true;
    }
    $stmt = $pdo->prepare(
        'SELECT 1 FROM contest_expert_pool WHERE contest_id = :cid AND user_id = :uid LIMIT 1'
    );
    $stmt->execute(['cid' => $contestId, 'uid' => $userId]);

    return (bool) $stmt->fetchColumn();
}

function can_manage_contest(PDO $pdo, int $userId, int $contestId): bool
{
    return has_role($pdo, $userId, 'admin') || contest_is_moderator($pdo, $userId, $contestId);
}

/**
 * В кабинете эксперта: просмотр всех работ этапа и назначение экспертов без ограничений — у модераторов конкурса.
 * Администраторы сайта без записи в contest_moderators не получают этот режим (не подменяют пул экспертов).
 */
function contest_expert_area_privileged(PDO $pdo, int $userId, int $contestId): bool
{
    return contest_is_moderator($pdo, $userId, $contestId);
}

/**
 * Для автораспределения и рассылки «новая работа»: исключить администраторов сайта из списка экспертов.
 *
 * @param list<int> $userIds
 * @return list<int>
 */
function user_ids_excluding_site_admins(PDO $pdo, array $userIds): array
{
    $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
    if ($userIds === []) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT u.id FROM users u
         WHERE u.id IN ($placeholders)
         AND NOT EXISTS (
           SELECT 1 FROM user_roles ur
           INNER JOIN system_roles sr ON sr.id = ur.role_id AND sr.slug = 'admin'
           WHERE ur.user_id = u.id
         )"
    );
    $stmt->execute($userIds);

    return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
}

/** Пользователь не может подавать заявку участника: админ, модератор конкурса или эксперт этапа этого конкурса. */
function user_cannot_apply_to_contest_as_staff(PDO $pdo, int $userId, int $contestId): bool
{
    if (can_manage_contest($pdo, $userId, $contestId)) {
        return true;
    }
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM stage_experts se
         INNER JOIN stages st ON st.id = se.stage_id
         WHERE st.contest_id = :contest_id AND se.user_id = :user_id
         LIMIT 1'
    );
    $stmt->execute(['contest_id' => $contestId, 'user_id' => $userId]);
    if ($stmt->fetchColumn()) {
        return true;
    }
    $stmt = $pdo->prepare(
        'SELECT 1 FROM contest_expert_pool WHERE contest_id = :contest_id AND user_id = :user_id
         UNION ALL
         SELECT 1 FROM submission_assigned_experts sae
         INNER JOIN submissions s ON s.id = sae.submission_id
         WHERE s.contest_id = :contest_id2 AND sae.user_id = :user_id2
         LIMIT 1'
    );
    $stmt->execute([
        'contest_id' => $contestId,
        'user_id' => $userId,
        'contest_id2' => $contestId,
        'user_id2' => $userId,
    ]);

    return (bool) $stmt->fetchColumn();
}

/** Строка ФИО для подписи в списках (без email). */
function user_full_name_line(array $u): string
{
    $parts = array_filter([
        trim((string) ($u['last_name'] ?? '')),
        trim((string) ($u['first_name'] ?? '')),
        trim((string) ($u['middle_name'] ?? '')),
    ], static fn(string $s): bool => $s !== '');

    return trim(implode(' ', $parts));
}

/** Подпись пользователя в полях с автодополнением (как в админке Django / IRO). */
function user_picker_label(array $u): string
{
    $name = user_full_name_line($u);
    $email = trim((string) ($u['email'] ?? ''));

    if ($name !== '' && $email !== '') {
        return $name . ' — ' . $email;
    }
    if ($email !== '') {
        return $email;
    }

    return 'Пользователь #' . (int) ($u['id'] ?? 0);
}

/**
 * @param array<int> $ids
 * @return array<int, array{id: int, label: string}>
 */
function users_picker_initial(PDO $pdo, array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $v): bool => $v > 0)));
    if ($ids === []) {
        return [];
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT id, email, first_name, last_name, middle_name FROM users WHERE id IN ($in)"
    );
    $stmt->execute($ids);
    $byId = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $byId[(int) $row['id']] = $row;
    }
    $out = [];
    foreach ($ids as $id) {
        if (isset($byId[$id])) {
            $out[] = ['id' => $id, 'label' => user_picker_label($byId[$id])];
        }
    }

    return $out;
}

/**
 * Условие поиска по email и ФИО: отдельные поля и строки «Фамилия Имя Отчество» / «Имя Фамилия Отчество».
 *
 * @return array{clause: string, params: array<string, string>}
 */
function users_search_email_fio_clause(string $tableAlias, string $paramPrefix, string $likeLower): array
{
    $a = preg_replace('/[^a-zA-Z0-9_]/', '', $tableAlias) ?: 'u';
    $pfx = preg_replace('/[^a-zA-Z]/', '', $paramPrefix) ?: 'sq';
    $keys = [$pfx . '1', $pfx . '2', $pfx . '3', $pfx . '4', $pfx . '5', $pfx . '6'];
    $clause =
        '('
        . "LOWER({$a}.email) LIKE :{$keys[0]} "
        . "OR LOWER(COALESCE({$a}.first_name, '')) LIKE :{$keys[1]} "
        . "OR LOWER(COALESCE({$a}.last_name, '')) LIKE :{$keys[2]} "
        . "OR LOWER(COALESCE({$a}.middle_name, '')) LIKE :{$keys[3]} "
        . "OR LOWER(TRIM(CONCAT_WS(' ', {$a}.last_name, {$a}.first_name, {$a}.middle_name))) LIKE :{$keys[4]} "
        . "OR LOWER(TRIM(CONCAT_WS(' ', {$a}.first_name, {$a}.last_name, {$a}.middle_name))) LIKE :{$keys[5]}"
        . ')';
    $params = [];
    foreach ($keys as $k) {
        $params[$k] = $likeLower;
    }

    return ['clause' => $clause, 'params' => $params];
}

/**
 * Поиск пользователей для назначения в конкурсе (модераторы / эксперты этапа / пул).
 *
 * @param list<int>|null $restrictToUserIds если задан — только эти id (например пул экспертов)
 * @param bool $excludeSiteAdmins для экспертов не показываем администраторов сайта; для модераторов конкурса — показываем всех
 */
function user_picker_search_for_contest_staff(
    PDO $pdo,
    string $q,
    int $limit = 50,
    ?array $restrictToUserIds = null,
    bool $excludeSiteAdmins = false
): array {
    $limit = max(1, min(80, $limit));
    $sql = "SELECT DISTINCT u.id, u.email, u.first_name, u.last_name, u.middle_name
            FROM users u
            WHERE u.is_active = 1";
    $params = [];
    if ($excludeSiteAdmins) {
        $sql .= " AND NOT EXISTS (
            SELECT 1 FROM user_roles ur
            INNER JOIN system_roles sr ON sr.id = ur.role_id AND sr.slug = 'admin'
            WHERE ur.user_id = u.id
        )";
    }
    if ($restrictToUserIds !== null) {
        $restrictToUserIds = array_values(array_unique(array_filter(array_map('intval', $restrictToUserIds), static fn (int $x): bool => $x > 0)));
        if ($restrictToUserIds === []) {
            return [];
        }
        $inParts = [];
        foreach ($restrictToUserIds as $i => $id) {
            $key = 'cstrid' . $i;
            $inParts[] = ':' . $key;
            $params[$key] = $id;
        }
        $sql .= ' AND u.id IN (' . implode(',', $inParts) . ')';
    }
    $qTrim = trim($q);
    if ($qTrim !== '') {
        $pat = '%' . mb_strtolower($qTrim) . '%';
        $block = users_search_email_fio_clause('u', 'cst', $pat);
        $sql .= ' AND ' . $block['clause'];
        foreach ($block['params'] as $k => $v) {
            $params[$k] = $v;
        }
    }
    $sql .= " ORDER BY u.last_name, u.first_name, u.email LIMIT {$limit}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function user_picker_search_moderators(PDO $pdo, string $q, int $limit = 30): array
{
    return user_picker_search_for_contest_staff($pdo, $q, $limit, null, false);
}

function user_picker_search_experts_global(PDO $pdo, string $q, int $limit = 30): array
{
    return user_picker_search_for_contest_staff($pdo, $q, $limit, null, true);
}

/**
 * Эксперты этапа: при непустом пуле конкурса — только из пула, иначе все активные пользователи (кроме админов сайта).
 */
function user_picker_search_experts_for_stage(PDO $pdo, int $contestId, string $q, int $limit = 30): array
{
    $poolStmt = $pdo->prepare('SELECT user_id FROM contest_expert_pool WHERE contest_id = :cid');
    $poolStmt->execute(['cid' => $contestId]);
    $poolIds = array_map('intval', array_column($poolStmt->fetchAll(PDO::FETCH_ASSOC), 'user_id'));
    if ($poolIds === []) {
        return user_picker_search_for_contest_staff($pdo, $q, $limit, null, true);
    }

    return user_picker_search_for_contest_staff($pdo, $q, $limit, $poolIds, true);
}

/** Подпись статуса конкурса (как Contest.STATUS_CHOICES в IRO). */
function contest_status_label(int $status): string
{
    return match ($status) {
        2 => 'Опубликован',
        3 => 'Отменён',
        4 => 'Скрытый',
        5 => 'Только в серии',
        default => 'Черновик',
    };
}

/** Класс Bootstrap для бейджа статуса конкурса. */
function contest_status_badge_class(int $status): string
{
    return match ($status) {
        2 => 'text-bg-success',
        3 => 'text-bg-danger',
        4 => 'text-bg-secondary',
        5 => 'text-bg-info',
        default => 'text-bg-warning text-dark',
    };
}

/** Статусы, в которых участник может подавать согласие / заявку (как Contest.STATUSES_OPEN_TO_PARTICIPANTS в IRO). */
function contest_status_open_to_participants(int $status): bool
{
    return in_array($status, [2, 4, 5], true);
}

/**
 * У конкурса задана привязка к серии (для статуса «Только в серии» и публичной страницы).
 * PDO может отдавать BIGINT как строку; пустые значения не считаем привязкой.
 */
function contest_series_id_effective(array $contest): ?int
{
    if (!array_key_exists('series_id', $contest)) {
        return null;
    }
    $raw = $contest['series_id'];
    if ($raw === null || $raw === false) {
        return null;
    }
    if (is_string($raw)) {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
    }
    $id = filter_var($raw, FILTER_VALIDATE_INT);
    if (!is_int($id) || $id <= 0) {
        return null;
    }

    return $id;
}

/**
 * Видимость карточки конкурса по прямой ссылке (как Contest.is_visible_to в IRO).
 */
function contest_is_visible_to(PDO $pdo, array $contest, ?int $userId): bool
{
    if (!empty($contest['is_archived'])) {
        if ($userId === null) {
            return false;
        }

        return has_role($pdo, $userId, 'admin');
    }

    $status = (int) ($contest['status'] ?? 0);
    $cid = (int) ($contest['id'] ?? 0);
    if ($status === 2) {
        return true;
    }
    if ($status === 4) {
        return true;
    }
    if ($status === 5) {
        return contest_series_id_effective($contest) !== null;
    }
    if ($userId === null) {
        return false;
    }
    if (has_role($pdo, $userId, 'admin')) {
        return true;
    }
    if ($cid > 0 && contest_is_moderator($pdo, $userId, $cid)) {
        return true;
    }
    if ($status === 3) {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM contest_participants WHERE user_id = :u AND contest_id = :c LIMIT 1'
        );
        $stmt->execute(['u' => $userId, 'c' => $cid]);
        if ($stmt->fetchColumn()) {
            return true;
        }
        $stmt2 = $pdo->prepare(
            'SELECT 1 FROM stage_experts se
             INNER JOIN stages st ON st.id = se.stage_id
             WHERE st.contest_id = :c AND se.user_id = :u LIMIT 1'
        );
        $stmt2->execute(['c' => $cid, 'u' => $userId]);

        return (bool) $stmt2->fetchColumn();
    }

    return false;
}

/**
 * Приём документов (согласие, вложения к заявке): участники уважают закрытие; админ/модератор конкурса — нет (как в IRO).
 */
function contest_documents_intake_open_for_user(PDO $pdo, array $contest, ?int $userId): bool
{
    if (empty($contest['document_intake_closed'])) {
        return true;
    }
    if ($userId === null) {
        return false;
    }

    return can_manage_contest($pdo, $userId, (int) ($contest['id'] ?? 0));
}

/** Поля «файл к заявке до этапов» (как Contest.submission_attachment_fields в IRO). */
function contest_participant_attachment_fields(PDO $pdo, int $contestId): array
{
    $st = $pdo->prepare(
        'SELECT id, sort_order, title, requirements FROM contest_attachment_fields WHERE contest_id = :c ORDER BY sort_order, id'
    );
    $st->execute(['c' => $contestId]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Уже загруженные вложения участника к заявке по конкурсу: ключ — id поля.
 *
 * @return array<int, array<string, mixed>>
 */
function participant_attachments_by_field_map(PDO $pdo, int $userId, int $contestId): array
{
    $st = $pdo->prepare(
        'SELECT pa.field_id, pa.file_path, caf.title AS field_title
         FROM participant_attachments pa
         INNER JOIN contest_attachment_fields caf ON caf.id = pa.field_id AND caf.contest_id = pa.contest_id
         WHERE pa.user_id = :u AND pa.contest_id = :c'
    );
    $st->execute(['u' => $userId, 'c' => $contestId]);
    $map = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[(int) $row['field_id']] = $row;
    }

    return $map;
}

/**
 * Абсолютный путь к загруженному файлу из public по URL вида /uploads/..., или null если файл недоступен.
 */
function iro_public_upload_realpath(string $webPath): ?string
{
    $webPath = str_replace('\\', '/', trim($webPath));
    if ($webPath === '' || !str_starts_with($webPath, '/uploads/')) {
        return null;
    }
    $full = realpath(env_path('public' . $webPath));
    $base = realpath(env_path('public/uploads'));
    if ($full === false || $base === false || !str_starts_with($full, $base) || !is_file($full)) {
        return null;
    }

    return $full;
}

/**
 * Любой файл из каталога public по URL (/uploads/, /assets/, …) — для архивов и проверок.
 */
function iro_public_web_file_realpath(string $webPath): ?string
{
    $webPath = str_replace('\\', '/', trim($webPath));
    if ($webPath === '' || $webPath[0] !== '/') {
        return null;
    }
    $full = realpath(env_path('public' . $webPath));
    $pub = realpath(env_path('public'));
    if ($full === false || $pub === false || !str_starts_with($full, $pub . DIRECTORY_SEPARATOR) || !is_file($full)) {
        return null;
    }

    return $full;
}

/**
 * Подписанное согласие и файлы к заявке (до этапов) для отображения эксперту / сборки архива.
 *
 * @return list<array{label: string, web_path: string}>
 */
function participant_application_files_list(PDO $pdo, int $userId, int $contestId): array
{
    $out = [];
    $uc = $pdo->prepare('SELECT consent_file_path FROM user_consents WHERE user_id = :u AND contest_id = :c LIMIT 1');
    $uc->execute(['u' => $userId, 'c' => $contestId]);
    $cf = trim((string) ($uc->fetchColumn() ?: ''));
    if ($cf !== '') {
        $out[] = ['label' => 'Подписанное согласие', 'web_path' => $cf];
    }
    $pa = $pdo->prepare(
        'SELECT pa.file_path, caf.title AS field_title
         FROM participant_attachments pa
         INNER JOIN contest_attachment_fields caf ON caf.id = pa.field_id AND caf.contest_id = pa.contest_id
         WHERE pa.user_id = :u AND pa.contest_id = :c
         ORDER BY caf.sort_order, caf.id'
    );
    $pa->execute(['u' => $userId, 'c' => $contestId]);
    foreach ($pa->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $title = trim((string) ($row['field_title'] ?? ''));
        $out[] = [
            'label' => $title !== '' ? $title : 'Файл к заявке',
            'web_path' => trim((string) ($row['file_path'] ?? '')),
        ];
    }

    return array_values(array_filter($out, static fn(array $x): bool => ($x['web_path'] ?? '') !== ''));
}

/** Имя компонента пути для ZIP (без каталогов). */
function iro_zip_filename_component(string $name, string $fallbackExt = ''): string
{
    $name = preg_replace('~[^\p{L}\p{N}_\-\.\s\(\)]~u', '_', $name) ?? '';
    $name = trim((string) $name);
    if ($name === '' || $name === '.' || $name === '..') {
        $name = 'file' . ($fallbackExt !== '' ? '.' . ltrim($fallbackExt, '.') : '');
    }

    return mb_substr($name, 0, 180);
}

/** Все обязательные поля вложений к заявке заполнены (как contest.participant_attachment_package_complete в IRO). */
function participant_attachment_package_complete(PDO $pdo, int $userId, int $contestId): bool
{
    foreach (contest_participant_attachment_fields($pdo, $contestId) as $f) {
        $check = $pdo->prepare(
            'SELECT 1 FROM participant_attachments WHERE user_id = :u AND contest_id = :c AND field_id = :f LIMIT 1'
        );
        $check->execute(['u' => $userId, 'c' => $contestId, 'f' => (int) $f['id']]);
        if (!$check->fetchColumn()) {
            return false;
        }
    }

    return true;
}

/** Расширения файла согласия: PDF, DOC, DOCX (как в IRO ConsentUploadForm). */
function iro_consent_file_extension_allowed(string $filename): bool
{
    $ext = mb_strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

    return in_array($ext, ['pdf', 'doc', 'docx'], true);
}

/**
 * Очистка HTML новостей перед сохранением (аналог main.html_sanitize.sanitize_announcement_html в IRO / bleach).
 */
function sanitize_announcement_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }
    $allowedTags = [
        'p', 'br', 'div', 'strong', 'b', 'em', 'i', 'u', 's', 'strike',
        'ul', 'ol', 'li', 'a', 'blockquote', 'hr',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    ];
    $allowedSet = array_fill_keys($allowedTags, true);
    libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $wrapper = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body><div id="iro-sanitize-root">'
        . $html
        . '</div></body></html>';
    if (!@$doc->loadHTML($wrapper, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        return '';
    }
    $root = $doc->getElementById('iro-sanitize-root');
    if (!$root) {
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        return '';
    }
    iro_sanitize_announcement_dom_node($doc, $root, $allowedSet);
    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    libxml_clear_errors();
    libxml_use_internal_errors(false);

    return $out;
}

/**
 * @param array<string, true> $allowedSet
 */
function iro_sanitize_announcement_dom_node(DOMDocument $doc, DOMNode $container, array $allowedSet): void
{
    $nodes = [];
    foreach ($container->childNodes as $n) {
        $nodes[] = $n;
    }
    foreach ($nodes as $node) {
        if ($node instanceof DOMText || $node instanceof DOMCdataSection) {
            continue;
        }
        if ($node instanceof DOMComment) {
            $container->removeChild($node);
            continue;
        }
        if (!($node instanceof DOMElement)) {
            if ($node->parentNode) {
                $node->parentNode->removeChild($node);
            }
            continue;
        }
        $tag = strtolower($node->tagName);
        if (!isset($allowedSet[$tag])) {
            while ($node->firstChild) {
                $container->insertBefore($node->firstChild, $node);
            }
            $container->removeChild($node);
            continue;
        }
        $saved = [];
        if ($node->hasAttributes()) {
            foreach (iterator_to_array($node->attributes) as $a) {
                $saved[strtolower($a->name)] = (string) $a->value;
            }
        }
        foreach (array_keys($saved) as $an) {
            $node->removeAttribute($an);
        }
        if ($tag === 'a') {
            $href = iro_sanitize_announcement_href($saved['href'] ?? '');
            if ($href !== null) {
                $node->setAttribute('href', $href);
            }
            foreach (['title', 'rel', 'target'] as $k) {
                if (!isset($saved[$k])) {
                    continue;
                }
                $v = trim($saved[$k]);
                if ($v === '') {
                    continue;
                }
                if ($k === 'target' && !in_array(strtolower($v), ['_blank', '_self'], true)) {
                    continue;
                }
                $node->setAttribute($k, $v);
            }
        }
        iro_sanitize_announcement_dom_node($doc, $node, $allowedSet);
    }
}

function iro_sanitize_announcement_href(string $href): ?string
{
    $href = trim($href);
    if ($href === '') {
        return null;
    }
    $lower = strtolower($href);
    if (str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'data:')) {
        return null;
    }
    if (preg_match('#^(https?://|mailto:)#i', $href)) {
        return $href;
    }

    return null;
}

/**
 * Обрабатывает загрузку файла документа конкурса (положение и т.п.).
 *
 * @return array{path: ?string, error: ?string} path — новый URL при успехе; error — текст ошибки.
 */
function contest_regulations_process_upload(?array $file): array
{
    $file = $file ?? ['error' => UPLOAD_ERR_NO_FILE];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['path' => null, 'error' => null];
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['path' => null, 'error' => 'Не удалось загрузить файл документа конкурса.'];
    }
    $name = (string) ($file['name'] ?? '');
    $ext = mb_strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    $allowed = ['pdf', 'doc', 'docx', 'odt', 'rtf'];
    if (!in_array($ext, $allowed, true)) {
        return ['path' => null, 'error' => 'Файл документа: допустимы PDF, DOC, DOCX, ODT или RTF.'];
    }
    $path = upload_file($file, 'contest_regulations');
    if ($path === null) {
        return ['path' => null, 'error' => 'Не удалось сохранить файл документа конкурса.'];
    }

    return ['path' => $path, 'error' => null];
}

/**
 * Работы конкурса с прикреплёнными дополнительными файлами (submission_attachments) для модераторской страницы.
 *
 * @return list<array<string, mixed>>
 */
function moderator_contest_supplement_submission_rows(PDO $pdo, int $contestId): array
{
    $stmt = $pdo->prepare(
        'SELECT s.id AS submission_id, s.title AS work_title, s.submission_date AS submission_date,
                u.email AS participant_email, st.title AS stage_title
         FROM submissions s
         INNER JOIN users u ON u.id = s.user_id
         INNER JOIN stages st ON st.id = s.stage_id
         WHERE s.contest_id = :contest_id
           AND EXISTS (SELECT 1 FROM submission_attachments sa WHERE sa.submission_id = s.id)
         ORDER BY s.submission_date DESC'
    );
    $stmt->execute(['contest_id' => $contestId]);
    $items = array_map(
        static fn(array $row): array => iro_mysql_assoc_lower_keys($row),
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );
    if ($items === []) {
        return [];
    }
    $ids = array_map(static fn(array $row): int => (int) ($row['submission_id'] ?? 0), $items);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $attStmt = $pdo->prepare(
        "SELECT sa.submission_id, sa.file_path, sf.title AS field_title
         FROM submission_attachments sa
         JOIN stage_attachment_fields sf ON sf.id = sa.field_id
         WHERE sa.submission_id IN ($in)
         ORDER BY sa.submission_id, sf.sort_order, sf.id"
    );
    $attStmt->execute($ids);
    $attachmentsBySubmission = [];
    foreach ($attStmt->fetchAll(PDO::FETCH_ASSOC) as $att) {
        $att = iro_mysql_assoc_lower_keys($att);
        $sid = (int) ($att['submission_id'] ?? 0);
        $attachmentsBySubmission[$sid][] = $att;
    }
    foreach ($items as &$item) {
        $sid = (int) ($item['submission_id'] ?? 0);
        $item['attachments'] = $attachmentsBySubmission[$sid] ?? [];
    }
    unset($item);

    return $items;
}

/**
 * Удаляет файл из public/uploads по пути вида /uploads/... (без выхода за каталог).
 */
function iro_delete_public_upload(?string $webPath): void
{
    if ($webPath === null || $webPath === '') {
        return;
    }
    $webPath = str_replace('\\', '/', $webPath);
    if (!str_starts_with($webPath, '/uploads/')) {
        return;
    }
    $full = realpath(env_path('public' . $webPath));
    $base = realpath(env_path('public/uploads'));
    if ($full === false || $base === false || !str_starts_with($full, $base . DIRECTORY_SEPARATOR)) {
        return;
    }
    if (is_file($full)) {
        @unlink($full);
    }
}

/**
 * Удаление работы: строки в БД и файлы с диска. Если передан $deferFiles, пути файлов добавляются туда,
 * чтобы вызывающий код удалил их только после успешного commit (иначе при откате файлы пропадут).
 */
function iro_delete_submission_cascade(PDO $pdo, int $submissionId, ?array &$deferFiles = null): void
{
    $mainStmt = $pdo->prepare('SELECT file_path FROM submissions WHERE id = :id LIMIT 1');
    $mainStmt->execute(['id' => $submissionId]);
    $paths = [trim((string) ($mainStmt->fetchColumn() ?: ''))];

    $attStmt = $pdo->prepare('SELECT file_path FROM submission_attachments WHERE submission_id = :id');
    $attStmt->execute(['id' => $submissionId]);
    foreach ($attStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $paths[] = trim((string) ($row['file_path'] ?? ''));
    }

    $pdo->prepare('DELETE FROM submission_attachments WHERE submission_id = :id')->execute(['id' => $submissionId]);
    $pdo->prepare('DELETE FROM submission_assigned_experts WHERE submission_id = :id')->execute(['id' => $submissionId]);
    $pdo->prepare('DELETE FROM evaluations WHERE submission_id = :id')->execute(['id' => $submissionId]);
    $pdo->prepare('DELETE FROM submissions WHERE id = :id')->execute(['id' => $submissionId]);

    $paths = array_values(array_filter($paths, static fn (string $p): bool => $p !== ''));
    if ($deferFiles !== null) {
        array_push($deferFiles, ...$paths);

        return;
    }
    foreach ($paths as $p) {
        iro_delete_public_upload($p);
    }
}

/**
 * Разрешённые расширения (без точки) для каталога под uploads. null — неизвестный подкаталог, загрузка запрещена.
 *
 * @return list<string>|null
 */
function iro_upload_allowed_extensions(string $subdir): ?array
{
    $subdir = trim(str_replace('\\', '/', $subdir), '/');
    static $bySubdir = [
        'consents' => ['pdf', 'doc', 'docx'],
        'consent_templates' => ['pdf', 'doc', 'docx'],
        'participant_contest_files' => ['pdf', 'doc', 'docx', 'odt', 'rtf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'zip'],
        'submissions' => ['pdf', 'doc', 'docx', 'odt', 'rtf', 'zip', 'jpg', 'jpeg', 'png', 'gif', 'webp'],
        'submission_optional' => ['pdf', 'doc', 'docx', 'odt', 'rtf', 'zip', 'jpg', 'jpeg', 'png', 'gif', 'webp'],
        'series_covers' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'contest_images' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'partner_logos' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'stage_attachments' => ['pdf', 'doc', 'docx', 'odt', 'zip'],
        'contest_regulations' => ['pdf', 'doc', 'docx', 'odt', 'rtf'],
    ];

    return $bySubdir[$subdir] ?? null;
}

function upload_file(array $file, string $subdir): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $subdirNorm = trim(str_replace('\\', '/', $subdir), '/');
    $allowed = iro_upload_allowed_extensions($subdirNorm);
    if ($allowed === null) {
        return null;
    }

    $name = (string) ($file['name'] ?? 'file.bin');
    $ext = mb_strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    if ($ext === '' || !in_array($ext, $allowed, true)) {
        return null;
    }
    $maxBytes = (int) ($GLOBALS['config']['app']['upload_max_bytes'] ?? 50 * 1024 * 1024);
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || ($maxBytes > 0 && $size > $maxBytes)) {
        return null;
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return null;
    }
    if (class_exists('finfo')) {
        $mime = (string) ((new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '');
        $isImageExt = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
        if ($isImageExt && !str_starts_with($mime, 'image/')) {
            return null;
        }
        if (preg_match('#^(text/html|text/x-php|application/x-php|application/x-httpd-php|image/svg\+xml|application/javascript|text/javascript)#', $mime) === 1) {
            return null;
        }
    }

    $baseDir = env_path('public/uploads/' . $subdirNorm);
    if (!is_dir($baseDir) && !mkdir($baseDir, 0755, true) && !is_dir($baseDir)) {
        return null;
    }
    $safe = bin2hex(random_bytes(8)) . '.' . $ext;
    $target = $baseDir . DIRECTORY_SEPARATOR . $safe;
    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        return null;
    }
    return '/uploads/' . $subdirNorm . '/' . $safe;
}
