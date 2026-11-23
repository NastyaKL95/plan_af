<?php

/**
 * Маршруты разнесены по доменам в app/http/routes/:
 * — публичный сайт, заявки, профиль;
 * — модератор согласий/файлов, подача работ, эксперт, уведомления;
 * — админ-панель;
 * — этапы конкурса, отчёты, награды, выход и финальный 404.
 */
require __DIR__ . '/routes/routes_public_apply.php';
require __DIR__ . '/routes/routes_moderator_submissions_expert.php';
require __DIR__ . '/routes/routes_admin.php';
require __DIR__ . '/routes/routes_moderator_stages_finale.php';
