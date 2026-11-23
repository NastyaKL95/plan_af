<?php

if ($uri === '/manage-users' || $uri === '/manage-users/') {
    redirect('/admin/users');
    exit;
}

if ($uri === '/contests-create' || $uri === '/contests-create/') {
    redirect('/admin/contests/create');
    exit;
}

if ($uri === '/partial/home-contests' || $uri === '/partial/home-contests/') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        http_response_code(405);
        exit;
    }
    $ctx = home_contests_list_context($pdo, $_GET);
    header('X-IRO-Canonical-Query: ' . home_canonical_query($_GET));
    render_partial('home/contests_inner', array_merge($ctx, ['today' => date('Y-m-d')]));
    exit;
}

if ($uri === '/') {
    $rawPhase = trim((string) ($_GET['phase'] ?? ''));
    $rawView = trim((string) ($_GET['view'] ?? ''));
    $badPhase = $rawPhase !== '' && !in_array($rawPhase, ['upcoming', 'active', 'finished'], true);
    $badView = $rawView !== '' && !in_array($rawView, ['list', 'cards'], true);
    if ($badPhase || $badView) {
        $q = $_GET;
        if ($badPhase) {
            unset($q['phase']);
        }
        if ($badView) {
            unset($q['view']);
        }
        $qs = http_build_query($q);
        redirect($qs !== '' ? '/?' . $qs : '/');
    }

    $news = $pdo->query(
        'SELECT id, title, summary, body, published_at, is_pinned FROM site_announcements
         WHERE is_published = 1 AND published_at <= NOW()
         ORDER BY is_pinned DESC, published_at DESC LIMIT 3'
    )->fetchAll();
    $partners = $pdo->query(
        'SELECT id, title, website_url, logo_path FROM site_partners
         WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'
    )->fetchAll();
    $homeCtx = home_contests_list_context($pdo, $_GET);
    $calendarPhases = [
        ['upcoming', 'Скоро начнётся'],
        ['active', 'Идёт конкурс'],
        ['finished', 'Завершён'],
    ];
    render('home', [
        'title' => 'Главная страница',
        'news' => $news,
        'partners' => $partners,
        'body_class' => 'iro-page-home',
        'calendar_phases' => $calendarPhases,
        'extra_head' => '<script>window.IRO_HOME_AJAX = { partialUrl: "/partial/home-contests/", homePath: "/" };</script>'
            . '<script defer src="/assets/js/home-contests-ajax.js"></script>'
            . '<script defer src="/assets/js/home-partners.js"></script>',
    ] + $homeCtx + ['today' => date('Y-m-d')]);
    exit;
}

if ($uri === '/contests' || $uri === '/contests/') {
    $stmt = $pdo->query(
        'SELECT id, title, start_date, end_date, description, status, image_path, is_external, external_url
         FROM contests
         WHERE status = 2 AND COALESCE(is_archived, 0) = 0
         ORDER BY start_date DESC, id DESC'
    );
    $contests = $stmt->fetchAll();
    render('contests/index', [
        'title' => 'Конкурсы',
        'contests' => $contests,
        'today' => date('Y-m-d'),
        'breadcrumbs' => [
            ['label' => 'Главная', 'url' => '/'],
            ['label' => 'Конкурсы', 'url' => null],
        ],
    ]);
    exit;
}

if ($uri === '/contest') {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0) {
        http_response_code(404);
        render('errors/404', ['title' => 'Не найдено', 'flash' => $flash, 'user' => $user, 'breadcrumbs' => iro_breadcrumbs_not_found()]);
        exit;
    }

    $contest = get_contest($pdo, $id);

    if (!$contest) {
        http_response_code(404);
        render('errors/404', ['title' => 'Не найдено', 'flash' => $flash, 'user' => $user, 'breadcrumbs' => iro_breadcrumbs_not_found()]);
        exit;
    }
    if (!contest_is_visible_to($pdo, $contest, $userId)) {
        http_response_code(404);
        render('errors/404', ['title' => 'Не найдено', 'flash' => $flash, 'user' => $user, 'breadcrumbs' => iro_breadcrumbs_not_found()]);
        exit;
    }

    $isExternalContest = (int) ($contest['is_external'] ?? 0) === 1;
    $stages = [];
    if (!$isExternalContest) {
        $stageStmt = $pdo->prepare(
            'SELECT id, title, start_date, end_date, submission_deadline, sort_order, phase_status, stage_format
             FROM stages WHERE contest_id = :contest_id ORDER BY sort_order, id'
        );
        $stageStmt->execute(['contest_id' => $id]);
        $stages = $stageStmt->fetchAll();
        if ($stages !== [] && contest_is_parallel_stage_mode($contest)) {
            foreach ($stages as $k => &$stg) {
                stage_sync_phase_done_if_past($pdo, $contest, $stg);
            }
            unset($stg);
        }
    }

    $isParticipant = false;
    $canManage = false;
    $canApply = false;
    $canUploadConsent = false;
    $needsParticipantDocuments = false;
    $applyRoleBlock = false;
    $consent = null;
    $currentStage = null;
    $mySubmissions = [];
    $participantAward = null;
    $participantRow = null;
    if ($userId !== null && !$isExternalContest) {
        $canManage = can_manage_contest($pdo, $userId, $id);
        $participantStmt = $pdo->prepare('SELECT * FROM contest_participants WHERE user_id = :user_id AND contest_id = :contest_id LIMIT 1');
        $participantStmt->execute(['user_id' => $userId, 'contest_id' => $id]);
        $participantRow = $participantStmt->fetch(PDO::FETCH_ASSOC);
        $isParticipant = (bool) $participantRow;
        if ($participantRow) {
            $awStmt = $pdo->prepare(
                'SELECT place, award_description, is_manual_override FROM awards WHERE participant_id = :pid AND contest_id = :cid LIMIT 1'
            );
            $awStmt->execute(['pid' => $participantRow['id'], 'cid' => $id]);
            $participantAward = $awStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        $applyRoleBlock = user_cannot_apply_to_contest_as_staff($pdo, $userId, $id);
        $consentStmt = $pdo->prepare('SELECT * FROM user_consents WHERE user_id = :user_id AND contest_id = :contest_id LIMIT 1');
        $consentStmt->execute(['user_id' => $userId, 'contest_id' => $id]);
        $consent = $consentStmt->fetch() ?: null;

        $consentBlocksApply = $consent !== null && (int) ($consent['is_approved'] ?? 0) === 0;
        $docIntakeOk = contest_documents_intake_open_for_user($pdo, $contest, $userId);
        $canApply = !$isParticipant
            && contest_status_open_to_participants((int) $contest['status'])
            && (string) $contest['end_date'] >= date('Y-m-d')
            && !$applyRoleBlock
            && (int) ($contest['is_external'] ?? 0) === 0
            && !$consentBlocksApply
            && $docIntakeOk;
        $canUploadConsent = !$isParticipant
            && contest_status_open_to_participants((int) $contest['status'])
            && !$applyRoleBlock
            && (int) ($contest['is_external'] ?? 0) === 0
            && $docIntakeOk
            && $consent !== null
            && (int) ($consent['is_approved'] ?? 0) === 0;
        $needsParticipantDocuments = $isParticipant
            && (int) ($contest['is_external'] ?? 0) === 0
            && $docIntakeOk
            && $consent !== null
            && (int) ($consent['is_approved'] ?? 0) === 1
            && !participant_attachment_package_complete($pdo, $userId, $id);

        if (!contest_is_parallel_stage_mode($contest)) {
            $stageStmt2 = $pdo->prepare(
                'SELECT * FROM stages WHERE contest_id = :contest_id AND start_date <= CURDATE() AND end_date >= CURDATE() ORDER BY sort_order, id LIMIT 1'
            );
            $stageStmt2->execute(['contest_id' => $id]);
            $currentStage = $stageStmt2->fetch() ?: null;
        } else {
            $currentStage = null;
        }

        $submissionStmt = $pdo->prepare(
            'SELECT s.id, s.stage_id, s.title, s.work_link, s.submission_date, s.optional_attachments_status, s.optional_attachments_rejection_reason, st.title AS stage_title
             FROM submissions s
             JOIN stages st ON st.id = s.stage_id
             WHERE s.user_id = :user_id AND s.contest_id = :contest_id
             ORDER BY s.submission_date DESC'
        );
        $submissionStmt->execute(['user_id' => $userId, 'contest_id' => $id]);
        $mySubmissions = $submissionStmt->fetchAll();
    } elseif ($userId !== null) {
        $canManage = can_manage_contest($pdo, $userId, $id);
    }

    $today = date('Y-m-d');
    $participantStageRows = [];
    if ($isParticipant && !$isExternalContest && $stages) {
        $scoreMeans = contest_submission_score_means($pdo, $id);
        $subsByStage = [];
        foreach ($mySubmissions as $s) {
            $subsByStage[(int) $s['stage_id']] = $s;
        }
        foreach ($stages as $stage) {
            $stId = (int) $stage['id'];
            $sub = $subsByStage[$stId] ?? null;
            $subId = $sub ? (int) $sub['id'] : 0;
            $submitBlock = $participantRow
                ? participant_submit_work_block_reason($pdo, $userId, $contest, $stage, $participantRow, $today)
                : null;
            $participantStageRows[] = [
                'stage' => $stage,
                'phase' => stage_display_phase($contest, $stage, $today),
                'submission' => $sub,
                'mean_score' => $subId > 0 ? submission_score_mean($scoreMeans, $subId) : 0.0,
                'has_evaluation' => $subId > 0 && array_key_exists($subId, $scoreMeans),
                'submit_block_code' => $submitBlock,
                'can_submit_here' => $participantRow && $submitBlock === null,
            ];
        }
    }

    $submitWorkBlockCode = null;
    if ($isParticipant && $currentStage && !$isExternalContest && $participantRow) {
        $submitWorkBlockCode = participant_submit_work_block_reason($pdo, $userId, $contest, $currentStage, $participantRow, $today);
    }
    $showParticipantSubmitWork = $currentStage !== null && $submitWorkBlockCode === null;

    render('contests/show', [
        'title' => $contest['title'],
        'breadcrumbs' => [
            ['label' => 'Главная', 'url' => '/'],
            ['label' => 'Конкурсы', 'url' => '/contests'],
            ['label' => (string) $contest['title'], 'url' => null],
        ],
        'contest' => $contest,
        'stages' => $stages,
        'isParticipant' => $isParticipant,
        'canManage' => $canManage,
        'canApply' => $canApply,
        'can_upload_consent' => $canUploadConsent,
        'needs_participant_documents' => $needsParticipantDocuments,
        'apply_role_block' => $applyRoleBlock,
        'consent' => $consent,
        'currentStage' => $currentStage,
        'mySubmissions' => $mySubmissions,
        'participant_award' => $participantAward ?? null,
        'today' => $today,
        'participant_stage_rows' => $participantStageRows,
        'show_participant_submit_work' => $showParticipantSubmitWork,
        'submit_work_block_code' => $submitWorkBlockCode,
        'stage_conduct_parallel' => contest_is_parallel_stage_mode($contest),
        'is_expert_for_contest' => $userId !== null ? user_is_expert_for_contest($pdo, $userId, $id) : false,
    ]);
    exit;
}

if ($uri === '/news' || $uri === '/news/') {
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 12;
    $total = (int) $pdo->query(
        'SELECT COUNT(*) FROM site_announcements WHERE is_published = 1 AND published_at <= NOW()'
    )->fetchColumn();
    $pages = max(1, (int) ceil($total / $perPage));
    if ($page > $pages) {
        $page = $pages;
    }
    $offset = ($page - 1) * $perPage;
    $stmt = $pdo->prepare(
        'SELECT id, title, summary, body, published_at, is_pinned
         FROM site_announcements
         WHERE is_published = 1 AND published_at <= NOW()
         ORDER BY is_pinned DESC, published_at DESC, id DESC
         LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset
    );
    $stmt->execute();
    $announcements = $stmt->fetchAll();
    render('announcements/index', [
        'title' => 'Новости и объявления',
        'announcements' => $announcements,
        'page' => $page,
        'pages' => $pages,
        'total' => $total,
        'body_class' => 'iro-page-news',
        'breadcrumbs' => [
            ['label' => 'Главная', 'url' => '/'],
            ['label' => 'Новости и объявления', 'url' => null],
        ],
    ]);
    exit;
}

if ($uri === '/news/show') {
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = $pdo->prepare(
        'SELECT id, title, summary, body, published_at, is_pinned FROM site_announcements WHERE is_published = 1 AND published_at <= NOW() AND id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $item = $stmt->fetch();
    if (!$item) {
        http_response_code(404);
        render('errors/404', ['title' => 'Не найдено', 'flash' => $flash, 'user' => $user, 'breadcrumbs' => iro_breadcrumbs_not_found()]);
        exit;
    }
    render('announcements/show', [
        'title' => $item['title'],
        'announcement' => $item,
        'breadcrumbs' => [
            ['label' => 'Главная', 'url' => '/'],
            ['label' => 'Новости', 'url' => '/news'],
            ['label' => (string) $item['title'], 'url' => null],
        ],
    ]);
    exit;
}

if ($uri === '/cookies') {
    render('static/cookies', [
        'title' => 'Политика cookies',
        'breadcrumbs' => [
            ['label' => 'Главная', 'url' => '/'],
            ['label' => 'Политика cookie', 'url' => null],
        ],
    ]);
    exit;
}

if ($uri === '/privacy') {
    render('static/privacy', [
        'title' => 'Политика конфиденциальности',
        'breadcrumbs' => [
            ['label' => 'Главная', 'url' => '/'],
            ['label' => 'Политика конфиденциальности', 'url' => null],
        ],
    ]);
    exit;
}

if ($uri === '/series' || $uri === '/series/') {
    // list only series that have at least one published contest
    $stmt = $pdo->query(
        'SELECT cs.id, cs.title, cs.slug, cs.description, cs.cover_image_path
         FROM contest_series cs
         WHERE EXISTS (
             SELECT 1 FROM contests c
             WHERE c.series_id = cs.id AND c.status IN (2, 5)
         )
         ORDER BY cs.sort_order, cs.title'
    );
    $series = $stmt->fetchAll();
    render('series/index', [
        'title' => 'Серии конкурсов',
        'series' => $series,
        'body_class' => 'iro-page-series',
        'breadcrumbs' => [
            ['label' => 'Главная', 'url' => '/'],
            ['label' => 'Серии конкурсов', 'url' => null],
        ],
    ]);
    exit;
}

if (preg_match('~^/series/(.+)$~', $uri, $m)) {
    $slug = trim(rawurldecode(rtrim((string) $m[1], '/')));
    if ($slug === '') {
        http_response_code(404);
        render('errors/404', ['title' => 'Не найдено', 'breadcrumbs' => iro_breadcrumbs_not_found()]);
        exit;
    }
    $stmt = $pdo->prepare('SELECT * FROM contest_series WHERE slug = :slug LIMIT 1');
    $stmt->execute(['slug' => $slug]);
    $series = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$series) {
        $stmt2 = $pdo->prepare(
            'SELECT * FROM contest_series WHERE LOWER(TRIM(slug)) = LOWER(TRIM(:slug)) LIMIT 1'
        );
        $stmt2->execute(['slug' => $slug]);
        $series = $stmt2->fetch(PDO::FETCH_ASSOC);
    }
    if (!$series) {
        http_response_code(404);
        render('errors/404', ['title' => 'Не найдено', 'breadcrumbs' => iro_breadcrumbs_not_found()]);
        exit;
    }
    $cStmt = $pdo->prepare(
        'SELECT id, title, start_date, end_date, description, status, image_path, is_external, external_url
         FROM contests
         WHERE series_id = :sid AND status IN (2, 5) AND COALESCE(is_archived, 0) = 0
         ORDER BY start_date, id'
    );
    $cStmt->execute(['sid' => $series['id']]);
    $contests = $cStmt->fetchAll();
    render('series/show', [
        'title' => (string) $series['title'],
        'series' => $series,
        'contests' => $contests,
        'today' => date('Y-m-d'),
        'breadcrumbs' => [
            ['label' => 'Главная', 'url' => '/'],
            ['label' => 'Серии', 'url' => '/series/'],
            ['label' => (string) $series['title'], 'url' => null],
        ],
    ]);
    exit;
}

if ($uri === '/login') {
    $error = null;
    $cfg = $GLOBALS['config'] ?? [];
    $oauthErrorMap = [
        'yandex_not_configured' => 'Вход через Яндекс пока не настроен',
        'yandex_bad_state' => 'Сессия авторизации истекла, попробуйте снова',
        'yandex_access_denied' => 'Авторизация через Яндекс отменена',
        'yandex_no_code' => 'Яндекс не вернул код авторизации',
        'yandex_token_http' => 'Не удалось получить токен Яндекса',
        'yandex_token_json' => 'Некорректный ответ Яндекса при обмене кода',
        'yandex_token_missing' => 'Яндекс не вернул access_token',
        'yandex_profile_http' => 'Не удалось получить профиль Яндекса',
        'yandex_profile_json' => 'Некорректный профиль Яндекса',
        'yandex_subject_missing' => 'В профиле Яндекса нет идентификатора',
        'yandex_schema_missing' => 'В БД нет колонок OAuth — выполните migration_oauth_yandex_v1.sql',
        'yandex_oauth_conflict' => 'Этот email уже связан с другим способом входа',
        'yandex_email_required' => 'В аккаунте Яндекса нет email — укажите почту в Яндексе или зарегистрируйтесь вручную',
        'yandex_login_failed' => 'Не удалось войти через Яндекс',
        'inactive' => 'Учётная запись отключена. Обратитесь к администратору.',
    ];
    if (isset($_GET['error'])) {
        $ek = (string) $_GET['error'];
        $error = $oauthErrorMap[$ek] ?? 'Не удалось выполнить вход';
    }
    if (is_post()) {
        $rlBlock = is_array($cfg) ? iro_login_rate_limit_check($cfg) : null;
        if ($rlBlock !== null) {
            flash_set('danger', $rlBlock);
            redirect('/login');
        }
        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        if (Auth::login($pdo, $email, $password)) {
            if (is_array($cfg)) {
                iro_login_rate_limit_clear($cfg);
            }
            iro_log_line('INFO', 'login success', ['user_id' => Auth::userId()]);
            flash_set('success', 'Вы успешно вошли');
            redirect('/contests');
        }
        if (is_array($cfg)) {
            iro_login_rate_limit_hit_failed($cfg);
        }
        iro_log_line('INFO', 'login failed', ['ip' => iro_client_ip_for_rate_limit()]);
        $reason = Auth::loginFailureReason();
        $error = $reason === 'inactive'
            ? 'Учётная запись отключена. Обратитесь к администратору.'
            : 'Неверный email или пароль';
    }
    render('auth/login', [
        'title' => 'Вход',
        'error' => $error,
        'external_auth_login_note' => Auth::externalAuthLoginNote($cfg),
        'login_username_label' => Auth::loginUsernameFieldLabel($cfg),
        'login_username_input_text' => Auth::loginUsernameInputIsText($cfg),
        'yandex_oauth_enabled' => iro_yandex_oauth_enabled($cfg),
        'password_reset_available' => iro_password_reset_table_ready($pdo),
        'breadcrumbs' => [
            ['label' => 'Главная', 'url' => '/'],
            ['label' => 'Вход', 'url' => null],
        ],
    ]);
    exit;
}

if ($uri === '/auth/yandex' || $uri === '/auth/yandex/') {
    if (Auth::userId() !== null) {
        redirect('/contests');
    }
    $cfg = $GLOBALS['config'] ?? [];
    if (!iro_yandex_oauth_enabled($cfg)) {
        redirect('/login?error=yandex_not_configured');
    }
    $oauth = iro_yandex_oauth_config($cfg);
    try {
        $state = bin2hex(random_bytes(16));
    } catch (Throwable) {
        $state = sha1(uniqid('yx_', true));
    }
    $_SESSION['oauth_yandex_state'] = $state;
    $_SESSION['oauth_yandex_state_ts'] = time();
    $query = http_build_query([
        'response_type' => 'code',
        'client_id' => trim((string) ($oauth['client_id'] ?? '')),
        'redirect_uri' => trim((string) ($oauth['redirect_uri'] ?? '')),
        'scope' => trim((string) ($oauth['scope'] ?? 'login:email login:info')),
        'state' => $state,
    ], '', '&', PHP_QUERY_RFC3986);
    header('Location: https://oauth.yandex.ru/authorize?' . $query, true, 302);
    exit;
}

if ($uri === '/auth/yandex/callback' || $uri === '/auth/yandex/callback/') {
    if (Auth::userId() !== null) {
        redirect('/contests');
    }
    $cfg = $GLOBALS['config'] ?? [];
    $fail = static function (string $code): never {
        redirect('/login?error=' . rawurlencode($code));
    };
    if (!iro_yandex_oauth_enabled($cfg)) {
        $fail('yandex_not_configured');
    }
    $oauth = iro_yandex_oauth_config($cfg);
    $state = (string) ($_GET['state'] ?? '');
    $expectedState = (string) ($_SESSION['oauth_yandex_state'] ?? '');
    $stateTs = (int) ($_SESSION['oauth_yandex_state_ts'] ?? 0);
    unset($_SESSION['oauth_yandex_state'], $_SESSION['oauth_yandex_state_ts']);
    if ($state === '' || $expectedState === '' || !hash_equals($expectedState, $state) || (time() - $stateTs) > 600) {
        $fail('yandex_bad_state');
    }
    if (!empty($_GET['error'])) {
        $fail('yandex_access_denied');
    }
    $code = trim((string) ($_GET['code'] ?? ''));
    if ($code === '') {
        $fail('yandex_no_code');
    }
    $tokenResp = iro_yandex_http_post_form('https://oauth.yandex.ru/token', [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'client_id' => trim((string) ($oauth['client_id'] ?? '')),
        'client_secret' => trim((string) ($oauth['client_secret'] ?? '')),
    ]);
    if (!$tokenResp) {
        $fail('yandex_token_http');
    }
    try {
        $tokenData = json_decode((string) $tokenResp['body'], true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        $fail('yandex_token_json');
    }
    $accessToken = trim((string) ($tokenData['access_token'] ?? ''));
    if ($accessToken === '') {
        $fail('yandex_token_missing');
    }
    $infoResp = iro_yandex_http_get_json('https://login.yandex.ru/info?format=json', $accessToken);
    if (!$infoResp) {
        $fail('yandex_profile_http');
    }
    try {
        $profile = json_decode((string) $infoResp['body'], true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        $fail('yandex_profile_json');
    }
    if (!is_array($profile)) {
        $fail('yandex_profile_json');
    }
    $result = iro_yandex_login_or_register($pdo, $profile);
    if (empty($result['ok'])) {
        $fail((string) ($result['error'] ?? 'yandex_login_failed'));
    }
    Auth::establishSession((int) $result['user_id']);
    iro_log_line('INFO', 'yandex login success', ['user_id' => (int) $result['user_id']]);
    flash_set('success', 'Вы успешно вошли через Яндекс');
    redirect('/contests');
}

if ($uri === '/forgot-password' || $uri === '/forgot-password/') {
    if (Auth::userId() !== null) {
        redirect('/contests');
    }
    $cfg = $GLOBALS['config'] ?? [];
    $info = null;
    $error = null;
    if (!iro_password_reset_table_ready($pdo)) {
        $error = 'Восстановление пароля пока не настроено (нужна migration_password_reset_v1.sql).';
    } elseif (is_post()) {
        $rlBlock = iro_login_rate_limit_check($cfg);
        if ($rlBlock !== null) {
            flash_set('danger', $rlBlock);
            redirect('/forgot-password');
        }
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        // Всегда одно и то же сообщение — не раскрываем, есть ли email в базе.
        $info = 'Если аккаунт с таким email существует, мы отправили ссылку для сброса пароля.';
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $st = $pdo->prepare(
                'SELECT id FROM users WHERE LOWER(TRIM(email)) = :email AND is_active = 1 LIMIT 1'
            );
            $st->execute(['email' => $email]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $token = Auth::createPasswordResetToken($pdo, (int) $row['id']);
                if ($token !== null) {
                    $resetUrl = iro_public_absolute_url('/reset-password?token=' . rawurlencode($token), $cfg);
                    $body = "Здравствуйте!\n\n"
                        . "Чтобы задать новый пароль на сайте конкурсов, перейдите по ссылке:\n"
                        . $resetUrl . "\n\n"
                        . "Ссылка действует ограниченное время. Если вы не запрашивали сброс — просто проигнорируйте письмо.\n";
                    if (!iro_mail_enabled($cfg)) {
                        iro_mail_log('password_reset mail disabled; token created for user ' . (int) $row['id']);
                        $info = 'Ссылка для сброса создана, но почта не настроена (mail.enabled в config). Обратитесь к администратору или настройте SMTP.';
                    } elseif (!iro_send_mail($email, 'Сброс пароля — конкурсы', $body, $cfg)) {
                        iro_login_rate_limit_hit_failed($cfg);
                        $error = 'Не удалось отправить письмо. Попробуйте позже или напишите на cit@iro75.ru.';
                        $info = null;
                    }
                }
            }
        }
    }
    render('auth/forgot_password', [
        'title' => 'Восстановление пароля',
        'error' => $error,
        'info' => $info,
        'reset_ready' => iro_password_reset_table_ready($pdo),
        'mail_enabled' => iro_mail_enabled($cfg),
        'breadcrumbs' => [
            ['label' => 'Главная', 'url' => '/'],
            ['label' => 'Вход', 'url' => '/login'],
            ['label' => 'Восстановление пароля', 'url' => null],
        ],
    ]);
    exit;
}

if ($uri === '/reset-password' || $uri === '/reset-password/') {
    if (Auth::userId() !== null) {
        redirect('/contests');
    }
    $token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
    $error = null;
    $found = $token !== '' ? Auth::findPasswordResetToken($pdo, $token) : null;
    if ($found === null) {
        render('auth/reset_password', [
            'title' => 'Новый пароль',
            'token' => '',
            'token_valid' => false,
            'error' => 'Ссылка для сброса пароля недействительна или устарела.',
            'breadcrumbs' => [
                ['label' => 'Главная', 'url' => '/'],
                ['label' => 'Вход', 'url' => '/login'],
                ['label' => 'Новый пароль', 'url' => null],
            ],
        ]);
        exit;
    }
    if (is_post()) {
        $password = (string) ($_POST['password'] ?? '');
        $password2 = (string) ($_POST['password_confirm'] ?? '');
        if ($password !== $password2) {
            $error = 'Пароли не совпадают.';
        } else {
            $err = Auth::consumePasswordResetToken($pdo, $token, $password);
            if ($err === null) {
                flash_set('success', 'Пароль обновлён. Войдите с новым паролем.');
                redirect('/login');
            }
            $error = $err;
            $found = Auth::findPasswordResetToken($pdo, $token);
        }
    }
    render('auth/reset_password', [
        'title' => 'Новый пароль',
        'token' => $token,
        'token_valid' => $found !== null,
        'error' => $error,
        'breadcrumbs' => [
            ['label' => 'Главная', 'url' => '/'],
            ['label' => 'Вход', 'url' => '/login'],
            ['label' => 'Новый пароль', 'url' => null],
        ],
    ]);
    exit;
}

if ($uri === '/signup') {
    $error = null;
    $cfg = $GLOBALS['config'] ?? [];
    if (is_post()) {
        [$ok, $message] = Auth::register($pdo, $_POST);
        if ($ok) {
            flash_set('success', 'Регистрация успешна. Войдите под своей учетной записью.');
            redirect('/login');
        }
        $error = $message;
    }
    render('auth/signup', [
        'title' => 'Регистрация',
        'error' => $error,
        'yandex_oauth_enabled' => iro_yandex_oauth_enabled($cfg),
        'breadcrumbs' => [
            ['label' => 'Главная', 'url' => '/'],
            ['label' => 'Регистрация', 'url' => null],
        ],
    ]);
    exit;
}

if ($uri === '/profile/view') {
    $uid = require_login();
    $profileId = (int) ($_GET['profile_id'] ?? 0);
    if ($profileId <= 0) {
        http_response_code(404);
        render('errors/404', ['title' => 'Не найдено', 'flash' => $flash, 'user' => $user, 'breadcrumbs' => iro_breadcrumbs_not_found()]);
        exit;
    }
    if ($profileId !== $uid && !has_role($pdo, $uid, 'admin')) {
        http_response_code(403);
        render('errors/403', [
            'title' => 'Доступ запрещён',
            'message' => 'Просмотр профиля доступен только владельцу или администратору.',
            'breadcrumbs' => iro_breadcrumbs_forbidden(),
        ]);
        exit;
    }
    $stmt = $pdo->prepare('SELECT id, email, first_name, last_name, middle_name, date_of_birth, school, region, municipality FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $profileId]);
    $profileUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$profileUser) {
        http_response_code(404);
        render('errors/404', ['title' => 'Не найдено', 'flash' => $flash, 'user' => $user, 'breadcrumbs' => iro_breadcrumbs_not_found()]);
        exit;
    }
    $sparse = !array_filter([
        $profileUser['first_name'] ?? '',
        $profileUser['last_name'] ?? '',
        $profileUser['middle_name'] ?? '',
        $profileUser['date_of_birth'] ?? '',
        $profileUser['region'] ?? '',
        $profileUser['municipality'] ?? '',
        $profileUser['school'] ?? '',
    ], static fn($v) => $v !== null && trim((string) $v) !== '');
    render('accounts/profile_view', [
        'title' => 'Профиль: ' . user_display_name($profileUser),
        'profile_user' => $profileUser,
        'profile_is_sparse' => $sparse,
        'is_own' => $profileId === $uid,
        'breadcrumbs' => [
            ['label' => 'Главная', 'url' => '/'],
            ['label' => 'Профиль', 'url' => null],
        ],
    ]);
    exit;
}

if ($uri === '/profile') {
    if ($userId === null) {
        flash_set('danger', 'Сначала войдите в систему');
        redirect('/login');
    }
    if (is_post()) {
        Auth::updateProfile($pdo, $userId, $_POST);
        flash_set('success', 'Профиль обновлён');
        redirect('/profile');
    }
    $user = Auth::user($pdo);
    render('accounts/profile', [
        'title' => 'Профиль',
        'profile' => $user,
        'breadcrumbs' => [
            ['label' => 'Главная', 'url' => '/'],
            ['label' => 'Редактирование профиля', 'url' => null],
        ],
    ]);
    exit;
}

if ($uri === '/contest/apply') {
    $uid = require_login();
    $contestId = (int) ($_GET['contest_id'] ?? 0);
    $contest = get_contest($pdo, $contestId);
    if (!$contest) {
        http_response_code(404);
        render('errors/404', ['title' => 'Не найдено', 'flash' => $flash, 'user' => $user, 'breadcrumbs' => iro_breadcrumbs_not_found()]);
        exit;
    }
    if (!contest_is_visible_to($pdo, $contest, $uid)) {
        http_response_code(404);
        render('errors/404', ['title' => 'Не найдено', 'flash' => $flash, 'user' => $user, 'breadcrumbs' => iro_breadcrumbs_not_found()]);
        exit;
    }
    if ((int) ($contest['is_external'] ?? 0) === 1) {
        flash_set('info', 'Этот конкурс проводится на другом сайте — участие оформляется по внешней ссылке.');
        redirect('/contest?id=' . $contestId);
    }
    if (user_cannot_apply_to_contest_as_staff($pdo, $uid, $contestId)) {
        flash_set('danger', 'Подача заявки недоступна: вы администратор сайта, модератор этого конкурса или эксперт одного из его этапов.');
        redirect('/contest?id=' . $contestId);
    }
    $attachmentFields = contest_participant_attachment_fields($pdo, $contestId);
    $existingByField = participant_attachments_by_field_map($pdo, $uid, $contestId);
    $existingConsentStmt = $pdo->prepare('SELECT * FROM user_consents WHERE user_id = :u AND contest_id = :c LIMIT 1');
    $existingConsentStmt->execute(['u' => $uid, 'c' => $contestId]);
    $existingConsent = $existingConsentStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $packageComplete = participant_attachment_package_complete($pdo, $uid, $contestId);
    $approvedIncomplete = $existingConsent !== null
        && (int) ($existingConsent['is_approved'] ?? 0) === 1
        && !$packageComplete;
    $consentFileOptional = $approvedIncomplete;

    $alreadyParticipant = $pdo->prepare('SELECT 1 FROM contest_participants WHERE user_id = :user_id AND contest_id = :contest_id LIMIT 1');
    $alreadyParticipant->execute(['user_id' => $uid, 'contest_id' => $contestId]);
    if ($alreadyParticipant->fetchColumn() && !$approvedIncomplete) {
        flash_set('info', 'Вы уже участвуете в этом конкурсе.');
        redirect('/contest?id=' . $contestId);
    }
    if (!$approvedIncomplete && !contest_status_open_to_participants((int) $contest['status'])) {
        flash_set('danger', 'Приём заявок на этот конкурс сейчас недоступен.');
        redirect('/contest?id=' . $contestId);
    }
    if (!$approvedIncomplete && (string) $contest['end_date'] < date('Y-m-d')) {
        flash_set('danger', 'Конкурс завершён — приём заявок закрыт.');
        redirect('/contest?id=' . $contestId);
    }
    if (!contest_documents_intake_open_for_user($pdo, $contest, $uid)) {
        flash_set('danger', 'Приём документов по этому конкурсу закрыт модератором.');
        redirect('/contest?id=' . $contestId);
    }

    if ($existingConsent !== null && (int) ($existingConsent['is_approved'] ?? 0) === 1 && $packageComplete) {
        flash_set('warning', 'Заявка на участие уже принята: согласие одобрено и все обязательные документы загружены.');
        redirect('/contest?id=' . $contestId);
    }

    if (is_post()) {
        $validateParticipantUploads = static function (array $attachmentFields, array $existingByField): array {
            $errors = [];
            foreach ($attachmentFields as $spec) {
                $fid = (int) $spec['id'];
                $key = 'participant_attachment_' . $fid;
                $f = $_FILES[$key] ?? ['error' => UPLOAD_ERR_NO_FILE];
                $required = !isset($existingByField[$fid]);
                $ok = ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
                if ($required && !$ok) {
                    $errors[] = 'Загрузите файл: «' . trim((string) ($spec['title'] ?? '')) . '».';
                }
            }

            return $errors;
        };

        $saveParticipantUploads = static function (PDO $pdo, int $uid, int $contestId, array $attachmentFields, array $existingByField): ?string {
            $upsert = $pdo->prepare(
                'INSERT INTO participant_attachments (user_id, contest_id, field_id, file_path)
                 VALUES (:u, :c, :f, :p)
                 ON DUPLICATE KEY UPDATE file_path = VALUES(file_path)'
            );
            foreach ($attachmentFields as $spec) {
                $fid = (int) $spec['id'];
                $key = 'participant_attachment_' . $fid;
                $f = $_FILES[$key] ?? ['error' => UPLOAD_ERR_NO_FILE];
                if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    continue;
                }
                $path = upload_file($f, 'participant_contest_files');
                if ($path === null) {
                    return 'Не удалось сохранить файл для поля «' . trim((string) ($spec['title'] ?? '')) . '».';
                }
                $prevRow = $existingByField[$fid] ?? null;
                $oldPath = is_array($prevRow) ? trim((string) ($prevRow['file_path'] ?? '')) : '';
                if ($oldPath !== '' && $oldPath !== $path) {
                    iro_delete_public_upload($oldPath);
                }
                $upsert->execute(['u' => $uid, 'c' => $contestId, 'f' => $fid, 'p' => $path]);
            }

            return null;
        };

        if ($consentFileOptional) {
            $vErr = $validateParticipantUploads($attachmentFields, $existingByField);
            if ($vErr !== []) {
                flash_set('danger', implode(' ', $vErr));
                redirect('/contest/apply?contest_id=' . $contestId);
            }
            $saveErr = $saveParticipantUploads($pdo, $uid, $contestId, $attachmentFields, $existingByField);
            if ($saveErr !== null) {
                flash_set('danger', $saveErr);
                redirect('/contest/apply?contest_id=' . $contestId);
            }
            flash_set('success', 'Документы к заявке сохранены. Модератор увидит обновление в разделе согласий.');
            redirect('/contest?id=' . $contestId);
        }

        $consentFile = $_FILES['consent_file'] ?? ['error' => UPLOAD_ERR_NO_FILE];
        $consentUploadOk = ($consentFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
        $existingPath = $existingConsent !== null
            ? trim((string) ($existingConsent['consent_file_path'] ?? ''))
            : '';
        $consentPath = null;
        if ($consentUploadOk) {
            $origName = (string) ($consentFile['name'] ?? '');
            if (!iro_consent_file_extension_allowed($origName)) {
                flash_set('danger', 'Согласие: допустимы только файлы PDF, DOC или DOCX.');
                redirect('/contest/apply?contest_id=' . $contestId);
            }
            $consentPath = upload_file($consentFile, 'consents');
            if ($consentPath === null) {
                flash_set('danger', 'Не удалось сохранить файл согласия.');
                redirect('/contest/apply?contest_id=' . $contestId);
            }
        } elseif ($existingPath !== '') {
            $consentPath = $existingPath;
        } else {
            flash_set('danger', 'Загрузите файл согласия.');
            redirect('/contest/apply?contest_id=' . $contestId);
        }

        $vErr = $validateParticipantUploads($attachmentFields, $existingByField);
        if ($vErr !== []) {
            flash_set('danger', implode(' ', $vErr));
            redirect('/contest/apply?contest_id=' . $contestId);
        }
        $saveErr = $saveParticipantUploads($pdo, $uid, $contestId, $attachmentFields, $existingByField);
        if ($saveErr !== null) {
            flash_set('danger', $saveErr);
            redirect('/contest/apply?contest_id=' . $contestId);
        }

        if ($existingConsent) {
            $up = $pdo->prepare(
                'UPDATE user_consents
                 SET consent_file_path = :path, is_approved = 0, rejection_reason = "", reviewed_at = NULL, reviewed_by = NULL, uploaded_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            );
            $up->execute(['path' => $consentPath, 'id' => $existingConsent['id']]);
            if ($existingPath !== '' && $consentPath !== '' && $consentPath !== $existingPath) {
                iro_delete_public_upload($existingPath);
            }
        } else {
            $ins = $pdo->prepare(
                'INSERT INTO user_consents (user_id, contest_id, consent_file_path, is_approved, rejection_reason)
                 VALUES (:user_id, :contest_id, :path, 0, "")'
            );
            $ins->execute(['user_id' => $uid, 'contest_id' => $contestId, 'path' => $consentPath]);
        }
        $emailStmt = $pdo->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
        $emailStmt->execute(['id' => $uid]);
        $applicantEmail = trim((string) ($emailStmt->fetchColumn() ?: ''));
        $whoLabel = $applicantEmail !== '' ? $applicantEmail : 'Участник';
        notify_contest_moderators(
            $pdo,
            $contestId,
            'Новая заявка на участие',
            $whoLabel . ' подал заявку на участие в конкурсе «' . (string) $contest['title'] . '» (согласие и документы к заявке).',
            '/moderator/consents?contest_id=' . $contestId
        );
        $msg = 'Заявка на участие отправлена на проверку модератору конкурса (согласие'
            . ($attachmentFields !== [] ? ' и документы к заявке' : '')
            . ').';
        flash_set('success', $msg);
        redirect('/contest?id=' . $contestId);
    }

    $existingConsentPathForUi = $existingConsent !== null
        ? trim((string) ($existingConsent['consent_file_path'] ?? ''))
        : '';
    $consentFileInputRequired = !$consentFileOptional && $existingConsentPathForUi === '';

    $applyPageTitle = $consentFileOptional ? 'Документы к заявке' : 'Стать участником';
    render('user/upload_consent', [
        'title' => $applyPageTitle,
        'contest' => $contest,
        'user' => $user,
        'flash' => $flash,
        'attachment_fields' => $attachmentFields,
        'existing_attachments' => $existingByField,
        'existing_consent' => $existingConsent,
        'consent_file_optional' => $consentFileOptional,
        'consent_file_input_required' => $consentFileInputRequired,
        'breadcrumbs' => array_merge(
            iro_breadcrumbs_contest_nav((string) $contest['title'], (int) $contest['id']),
            [['label' => $applyPageTitle, 'url' => null]]
        ),
    ]);
    exit;
}

