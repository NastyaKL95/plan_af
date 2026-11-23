-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Хост: MySQL-8.4:3306
-- Время создания: Ноя 23 2026 г., 15:14
-- Версия сервера: 8.4.8
-- Версия PHP: 8.4.19

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `konk`
--

-- --------------------------------------------------------

--
-- Структура таблицы `awards`
--

CREATE TABLE `awards` (
  `id` bigint UNSIGNED NOT NULL,
  `participant_id` bigint NOT NULL,
  `contest_id` bigint NOT NULL,
  `place` int NOT NULL,
  `award_description` text,
  `is_manual_override` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `awards`
--

INSERT INTO `awards` (`id`, `participant_id`, `contest_id`, `place`, `award_description`, `is_manual_override`) VALUES
(1, 1, 1, 2, NULL, 1);

-- --------------------------------------------------------

--
-- Структура таблицы `consent_documents`
--

CREATE TABLE `consent_documents` (
  `id` bigint UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `contests`
--

CREATE TABLE `contests` (
  `id` bigint UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `description` text NOT NULL,
  `status` smallint NOT NULL DEFAULT '1',
  `document_intake_closed` tinyint(1) NOT NULL DEFAULT '0',
  `require_explicit_stage_admission` tinyint(1) NOT NULL DEFAULT '0',
  `stage_conduct_mode` tinyint(1) NOT NULL DEFAULT '0',
  `image_path` varchar(500) DEFAULT NULL,
  `regulations_path` varchar(500) DEFAULT NULL,
  `documents_link` varchar(500) NOT NULL DEFAULT '',
  `series_id` bigint DEFAULT NULL,
  `is_external` tinyint(1) NOT NULL DEFAULT '0',
  `external_url` varchar(500) NOT NULL DEFAULT '',
  `is_archived` tinyint(1) NOT NULL DEFAULT '0',
  `archived_at` timestamp NULL DEFAULT NULL,
  `archive_report_path` varchar(500) DEFAULT NULL
) ;

--
-- Дамп данных таблицы `contests`
--

INSERT INTO `contests` (`id`, `title`, `start_date`, `end_date`, `description`, `status`, `document_intake_closed`, `require_explicit_stage_admission`, `stage_conduct_mode`, `image_path`, `regulations_path`, `documents_link`, `series_id`, `is_external`, `external_url`, `is_archived`, `archived_at`, `archive_report_path`) VALUES
(1, 'Тест 1', '2025-12-12', '2026-12-12', 'Описание тест 1', 2, 0, 1, 1, '/uploads/contest_images/209698f54512dae8.png', '/uploads/contest_regulations/d75469aa7e0ca5a0.pdf', '', NULL, 0, '', 0, NULL, NULL),
(2, 'Каскадный этнографический диктант (эвенкийского языка)', '2026-04-20', '2026-04-29', 'Образовательная акция «Каскадный этнографический диктант (эвенкийского языка)» проводится в рамках исполнения Государственной программы Российской Федерации «Реализация государственной национальной политики», утвержденной постановлением Правительства РФ от 29 декабря 2016 г. № 1532 (ред. от 27.07.2023), подпрограммы «Устойчивое развитие коренных малочисленных народов Севера»', 2, 1, 0, 0, '/uploads/contest_images/6daa70aee9fabcd5.jpg', NULL, '', NULL, 1, 'https://dictant.zabedu.ru/index.html', 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `contest_attachment_fields`
--

CREATE TABLE `contest_attachment_fields` (
  `id` bigint UNSIGNED NOT NULL,
  `contest_id` bigint NOT NULL,
  `sort_order` smallint NOT NULL DEFAULT '0',
  `title` varchar(255) NOT NULL,
  `requirements` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `contest_attachment_fields`
--

INSERT INTO `contest_attachment_fields` (`id`, `contest_id`, `sort_order`, `title`, `requirements`) VALUES
(3, 1, 0, 'Заявка 1', 'Файл (заявка 1)');

-- --------------------------------------------------------

--
-- Структура таблицы `contest_expert_pool`
--

CREATE TABLE `contest_expert_pool` (
  `contest_id` bigint NOT NULL,
  `user_id` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `contest_expert_pool`
--

INSERT INTO `contest_expert_pool` (`contest_id`, `user_id`) VALUES
(1, 4);

-- --------------------------------------------------------

--
-- Структура таблицы `contest_moderators`
--

CREATE TABLE `contest_moderators` (
  `contest_id` bigint NOT NULL,
  `user_id` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `contest_moderators`
--

INSERT INTO `contest_moderators` (`contest_id`, `user_id`) VALUES
(1, 1),
(2, 1);

-- --------------------------------------------------------

--
-- Структура таблицы `contest_participants`
--

CREATE TABLE `contest_participants` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint NOT NULL,
  `contest_id` bigint NOT NULL,
  `admitted_max_stage_order` int NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `contest_participants`
--

INSERT INTO `contest_participants` (`id`, `user_id`, `contest_id`, `admitted_max_stage_order`) VALUES
(1, 4, 1, 1);

-- --------------------------------------------------------

--
-- Структура таблицы `contest_series`
--

CREATE TABLE `contest_series` (
  `id` bigint UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(280) NOT NULL,
  `description` text NOT NULL,
  `cover_image_path` varchar(500) DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `criteria`
--

CREATE TABLE `criteria` (
  `id` bigint UNSIGNED NOT NULL,
  `stage_id` bigint NOT NULL,
  `group_id` bigint UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `min_points` int NOT NULL DEFAULT '0',
  `max_points` int NOT NULL,
  `weight` int NOT NULL DEFAULT '100',
  `sort_order` smallint NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `criteria`
--

INSERT INTO `criteria` (`id`, `stage_id`, `group_id`, `name`, `min_points`, `max_points`, `weight`, `sort_order`) VALUES
(1, 1, 1, '12', 0, 4, 100, 1),
(2, 1, 2, '12ы', 0, 5, 100, 1);

-- --------------------------------------------------------

--
-- Структура таблицы `criterion_groups`
--

CREATE TABLE `criterion_groups` (
  `id` bigint UNSIGNED NOT NULL,
  `stage_id` bigint UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `sort_order` smallint NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `criterion_groups`
--

INSERT INTO `criterion_groups` (`id`, `stage_id`, `title`, `sort_order`) VALUES
(1, 1, 'группа 1', 1),
(2, 1, 'группа 2', 2);

-- --------------------------------------------------------

--
-- Структура таблицы `evaluations`
--

CREATE TABLE `evaluations` (
  `id` bigint UNSIGNED NOT NULL,
  `expert_id` bigint NOT NULL,
  `submission_id` bigint NOT NULL,
  `criterion_id` bigint NOT NULL,
  `points` int NOT NULL,
  `evaluated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `comment` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `evaluations`
--

INSERT INTO `evaluations` (`id`, `expert_id`, `submission_id`, `criterion_id`, `points`, `evaluated_at`, `comment`) VALUES
(1, 4, 1, 1, 2, '2026-04-26 08:04:53', ''),
(2, 4, 1, 2, 2, '2026-04-26 08:04:53', '12');

-- --------------------------------------------------------

--
-- Структура таблицы `in_app_notifications`
--

CREATE TABLE `in_app_notifications` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint NOT NULL,
  `title` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `link` varchar(500) NOT NULL DEFAULT '/',
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `in_app_notifications`
--

INSERT INTO `in_app_notifications` (`id`, `user_id`, `title`, `body`, `link`, `is_read`, `created_at`) VALUES
(2, 4, 'В согласии отказано', 'См. детали в карточке конкурса.', '/contest?id=1', 0, '2026-04-26 07:56:36'),
(4, 4, 'Согласие одобрено', 'По конкурсу «Тест 1» ваше согласие одобрено, вы зачислены участником.', '/contest?id=1', 0, '2026-04-26 08:00:17');

-- --------------------------------------------------------

--
-- Структура таблицы `participant_attachments`
--

CREATE TABLE `participant_attachments` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint NOT NULL,
  `contest_id` bigint NOT NULL,
  `field_id` bigint NOT NULL,
  `file_path` varchar(500) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `participant_attachments`
--

INSERT INTO `participant_attachments` (`id`, `user_id`, `contest_id`, `field_id`, `file_path`) VALUES
(1, 4, 1, 1, '/uploads/participant_contest_files/0cf77ebbc9a9d01a.png');

-- --------------------------------------------------------

--
-- Структура таблицы `site_announcements`
--

CREATE TABLE `site_announcements` (
  `id` bigint UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(280) NOT NULL,
  `summary` varchar(500) NOT NULL DEFAULT '',
  `body` text NOT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT '0',
  `is_pinned` tinyint(1) NOT NULL DEFAULT '0',
  `published_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `site_announcements`
--

INSERT INTO `site_announcements` (`id`, `title`, `slug`, `summary`, `body`, `is_published`, `is_pinned`, `published_at`, `created_at`, `updated_at`) VALUES
(1, 'Новость 1', 'новость-1', 'Подведены итоги заочного этапа регионального конкурса «Директория75»', 'Подведены итоги заочного этапа регионального конкурса «Директория75», в котором приняли участие 17 директоров общеобразовательных организаций из 11 муниципальных и городских округов Забайкальского края.', 1, 0, '2026-04-10 07:19:00', '2026-04-26 07:19:49', '2026-04-26 07:19:49');

-- --------------------------------------------------------

--
-- Структура таблицы `site_partners`
--

CREATE TABLE `site_partners` (
  `id` bigint UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `website_url` varchar(500) NOT NULL DEFAULT '',
  `logo_path` varchar(500) NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `site_partners`
--

INSERT INTO `site_partners` (`id`, `title`, `website_url`, `logo_path`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 'ИРО Забайкальского края', 'https://irozk.ru/', '/uploads/partner_logos/0a722fac6bc011d4.jpg', 1, 1, '2026-04-26 08:14:01'),
(2, 'https://75.ru/', 'https://75.ru/', '/uploads/partner_logos/10de78aa7fbf8a19.jpg', 2, 1, '2026-04-26 08:16:28'),
(3, 'Минпросвещения России', 'https://edu.gov.ru/', '/uploads/partner_logos/e8ffad1ce7fb4b4f.jpg', 3, 1, '2026-04-26 08:17:56');

-- --------------------------------------------------------

--
-- Структура таблицы `stages`
--

CREATE TABLE `stages` (
  `id` bigint UNSIGNED NOT NULL,
  `contest_id` bigint NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `instruction_file_path` varchar(500) DEFAULT NULL,
  `external_link` varchar(500) NOT NULL DEFAULT '',
  `external_link_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `submission_deadline` date DEFAULT NULL,
  `evaluation_mode` varchar(20) NOT NULL DEFAULT 'all',
  `experts_per_submission` smallint NOT NULL DEFAULT '2',
  `include_scores_in_final_ranking` tinyint(1) NOT NULL DEFAULT '0',
  `phase_status` tinyint NOT NULL DEFAULT '1',
  `stage_format` tinyint(1) NOT NULL DEFAULT '0'
) ;

--
-- Дамп данных таблицы `stages`
--

INSERT INTO `stages` (`id`, `contest_id`, `title`, `description`, `instruction_file_path`, `external_link`, `external_link_enabled`, `sort_order`, `start_date`, `end_date`, `submission_deadline`, `evaluation_mode`, `experts_per_submission`, `include_scores_in_final_ranking`, `phase_status`, `stage_format`) VALUES
(1, 1, 'Заочный', 'Описание этапа', NULL, '', 0, 1, '2026-04-25', '2026-04-27', '2026-04-27', 'assigned', 1, 1, 4, 0);

-- --------------------------------------------------------

--
-- Структура таблицы `stage_attachment_fields`
--

CREATE TABLE `stage_attachment_fields` (
  `id` bigint UNSIGNED NOT NULL,
  `stage_id` bigint NOT NULL,
  `sort_order` smallint NOT NULL DEFAULT '0',
  `title` varchar(255) NOT NULL,
  `requirements` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `stage_experts`
--

CREATE TABLE `stage_experts` (
  `stage_id` bigint NOT NULL,
  `user_id` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `stage_experts`
--

INSERT INTO `stage_experts` (`stage_id`, `user_id`) VALUES
(1, 4);

-- --------------------------------------------------------

--
-- Структура таблицы `submissions`
--

CREATE TABLE `submissions` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint NOT NULL,
  `contest_id` bigint NOT NULL,
  `stage_id` bigint NOT NULL,
  `title` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `work_link` varchar(500) NOT NULL DEFAULT '',
  `optional_attachments_status` varchar(16) NOT NULL DEFAULT 'na',
  `optional_attachments_rejection_reason` text NOT NULL,
  `optional_attachments_reviewed_at` timestamp NULL DEFAULT NULL,
  `optional_attachments_reviewed_by` bigint DEFAULT NULL,
  `submission_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `submissions`
--

INSERT INTO `submissions` (`id`, `user_id`, `contest_id`, `stage_id`, `title`, `file_path`, `work_link`, `optional_attachments_status`, `optional_attachments_rejection_reason`, `optional_attachments_reviewed_at`, `optional_attachments_reviewed_by`, `submission_date`) VALUES
(1, 4, 1, 1, '3', '/uploads/submissions/0778aae90aad1cf5.jpg', '', 'na', '', NULL, NULL, '2026-04-26 08:04:00');

-- --------------------------------------------------------

--
-- Структура таблицы `submission_assigned_experts`
--

CREATE TABLE `submission_assigned_experts` (
  `submission_id` bigint NOT NULL,
  `user_id` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `submission_assigned_experts`
--

INSERT INTO `submission_assigned_experts` (`submission_id`, `user_id`) VALUES
(1, 4);

-- --------------------------------------------------------

--
-- Структура таблицы `submission_attachments`
--

CREATE TABLE `submission_attachments` (
  `id` bigint UNSIGNED NOT NULL,
  `submission_id` bigint NOT NULL,
  `field_id` bigint NOT NULL,
  `file_path` varchar(500) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `system_roles`
--

CREATE TABLE `system_roles` (
  `id` bigint UNSIGNED NOT NULL,
  `slug` varchar(32) NOT NULL,
  `name` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `system_roles`
--

INSERT INTO `system_roles` (`id`, `slug`, `name`) VALUES
(1, 'admin', 'Администратор'),
(2, 'moderator', 'Модератор'),
(3, 'expert', 'Эксперт'),
(4, 'participant', 'Участник');

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `school` varchar(255) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `municipality` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_staff` tinyint(1) NOT NULL DEFAULT '0',
  `is_superuser` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `first_name`, `last_name`, `middle_name`, `date_of_birth`, `school`, `region`, `municipality`, `is_active`, `is_staff`, `is_superuser`, `created_at`, `updated_at`) VALUES
(1, 'admin@iro.local', '$2y$12$T5ZYYhmj94x94J0e2KzT0e5CAKpC6zsT00bMu45vsLbCbZCf8IFWq', 'Сайта', 'Админ', NULL, NULL, NULL, NULL, NULL, 1, 1, 1, '2026-04-22 14:48:47', '2026-04-22 14:48:47'),
(3, 'nastyaklopova.95.951@gmail.com', '$2y$12$f1nx7hc/G5iOWbEcOIW/uuB81pGQnRJBaP2ohzIdZ0jY8SqA.kbyS', 'Анастасия', 'Клопова', NULL, NULL, NULL, NULL, NULL, 1, 0, 0, '2026-04-26 05:36:14', '2026-04-26 05:36:14'),
(4, 'nastyaklopova.95.952@gmail.com', '$2y$12$TiSIsB1SsubNLP8V95SGLOGpr/eQefVh52jByvsxvnsyyJc31ipXG', NULL, 'Модератор 1', NULL, NULL, NULL, NULL, NULL, 1, 0, 0, '2026-04-26 07:23:27', '2026-04-26 07:23:27');

-- --------------------------------------------------------

--
-- Структура таблицы `user_consents`
--

CREATE TABLE `user_consents` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint NOT NULL,
  `contest_id` bigint NOT NULL,
  `consent_file_path` varchar(500) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_approved` tinyint(1) NOT NULL DEFAULT '0',
  `rejection_reason` text NOT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `user_consents`
--

INSERT INTO `user_consents` (`id`, `user_id`, `contest_id`, `consent_file_path`, `uploaded_at`, `is_approved`, `rejection_reason`, `reviewed_at`, `reviewed_by`) VALUES
(1, 4, 1, '/uploads/consents/563dd901345436a8.pdf', '2026-04-26 07:57:05', 1, '', '2026-04-26 08:00:17', 1);

-- --------------------------------------------------------

--
-- Структура таблицы `user_roles`
--

CREATE TABLE `user_roles` (
  `user_id` bigint NOT NULL,
  `role_id` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `user_roles`
--

INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES
(1, 1);

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `awards`
--
ALTER TABLE `awards`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `consent_documents`
--
ALTER TABLE `consent_documents`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `contests`
--
ALTER TABLE `contests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_contests_series_id` (`series_id`);

--
-- Индексы таблицы `contest_attachment_fields`
--
ALTER TABLE `contest_attachment_fields`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `contest_expert_pool`
--
ALTER TABLE `contest_expert_pool`
  ADD PRIMARY KEY (`contest_id`,`user_id`);

--
-- Индексы таблицы `contest_moderators`
--
ALTER TABLE `contest_moderators`
  ADD PRIMARY KEY (`contest_id`,`user_id`);

--
-- Индексы таблицы `contest_participants`
--
ALTER TABLE `contest_participants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_contest_participants_user_contest` (`user_id`,`contest_id`);

--
-- Индексы таблицы `contest_series`
--
ALTER TABLE `contest_series`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Индексы таблицы `criteria`
--
ALTER TABLE `criteria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_criteria_criterion_group` (`group_id`),
  ADD KEY `idx_criteria_stage_group_sort` (`stage_id`,`group_id`,`sort_order`,`id`);

--
-- Индексы таблицы `criterion_groups`
--
ALTER TABLE `criterion_groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_criterion_groups_stage_sort` (`stage_id`,`sort_order`,`id`);

--
-- Индексы таблицы `evaluations`
--
ALTER TABLE `evaluations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `expert_id` (`expert_id`,`submission_id`,`criterion_id`);

--
-- Индексы таблицы `in_app_notifications`
--
ALTER TABLE `in_app_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_user_created` (`user_id`,`created_at` DESC),
  ADD KEY `idx_notifications_user_read` (`user_id`,`is_read`);

--
-- Индексы таблицы `participant_attachments`
--
ALTER TABLE `participant_attachments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`contest_id`,`field_id`);

--
-- Индексы таблицы `site_announcements`
--
ALTER TABLE `site_announcements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Индексы таблицы `site_partners`
--
ALTER TABLE `site_partners`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `stages`
--
ALTER TABLE `stages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_stages_contest_order` (`contest_id`,`sort_order`);

--
-- Индексы таблицы `stage_attachment_fields`
--
ALTER TABLE `stage_attachment_fields`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `stage_experts`
--
ALTER TABLE `stage_experts`
  ADD PRIMARY KEY (`stage_id`,`user_id`);

--
-- Индексы таблицы `submissions`
--
ALTER TABLE `submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`stage_id`),
  ADD KEY `idx_submissions_contest_stage` (`contest_id`,`stage_id`);

--
-- Индексы таблицы `submission_assigned_experts`
--
ALTER TABLE `submission_assigned_experts`
  ADD PRIMARY KEY (`submission_id`,`user_id`);

--
-- Индексы таблицы `submission_attachments`
--
ALTER TABLE `submission_attachments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `submission_id` (`submission_id`,`field_id`);

--
-- Индексы таблицы `system_roles`
--
ALTER TABLE `system_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Индексы таблицы `user_consents`
--
ALTER TABLE `user_consents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`contest_id`);

--
-- Индексы таблицы `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`user_id`,`role_id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `awards`
--
ALTER TABLE `awards`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `consent_documents`
--
ALTER TABLE `consent_documents`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `contests`
--
ALTER TABLE `contests`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `contest_attachment_fields`
--
ALTER TABLE `contest_attachment_fields`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `contest_participants`
--
ALTER TABLE `contest_participants`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `contest_series`
--
ALTER TABLE `contest_series`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `criteria`
--
ALTER TABLE `criteria`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `criterion_groups`
--
ALTER TABLE `criterion_groups`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `in_app_notifications`
--
ALTER TABLE `in_app_notifications`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `participant_attachments`
--
ALTER TABLE `participant_attachments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `site_announcements`
--
ALTER TABLE `site_announcements`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `site_partners`
--
ALTER TABLE `site_partners`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `stages`
--
ALTER TABLE `stages`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `stage_attachment_fields`
--
ALTER TABLE `stage_attachment_fields`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `submissions`
--
ALTER TABLE `submissions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `submission_attachments`
--
ALTER TABLE `submission_attachments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `system_roles`
--
ALTER TABLE `system_roles`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `user_consents`
--
ALTER TABLE `user_consents`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `criteria`
--
ALTER TABLE `criteria`
  ADD CONSTRAINT `fk_criteria_criterion_group` FOREIGN KEY (`group_id`) REFERENCES `criterion_groups` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
