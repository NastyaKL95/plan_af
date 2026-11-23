START TRANSACTION;

CREATE TABLE system_roles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(32) NOT NULL UNIQUE,
    name VARCHAR(64) NOT NULL
);

CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    oauth_provider VARCHAR(32) NULL,
    oauth_subject VARCHAR(191) NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    middle_name VARCHAR(100),
    date_of_birth DATE,
    school VARCHAR(255),
    region VARCHAR(100),
    municipality VARCHAR(255),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_staff TINYINT(1) NOT NULL DEFAULT 0,
    is_superuser TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_oauth (oauth_provider, oauth_subject)
);

CREATE TABLE password_reset_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_prt_token_hash (token_hash),
    KEY idx_prt_user (user_id),
    KEY idx_prt_expires (expires_at)
);

CREATE TABLE user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, role_id)
);

CREATE TABLE contest_series (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(280) NOT NULL UNIQUE,
    description TEXT NOT NULL DEFAULT '',
    cover_image_path VARCHAR(500),
    sort_order INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE contests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    description TEXT NOT NULL,
    status SMALLINT NOT NULL DEFAULT 1,
    document_intake_closed TINYINT(1) NOT NULL DEFAULT 0,
    require_explicit_stage_admission TINYINT(1) NOT NULL DEFAULT 0,
    stage_conduct_mode TINYINT(1) NOT NULL DEFAULT 0,
    image_path VARCHAR(500),
    regulations_path VARCHAR(500) NULL,
    documents_link VARCHAR(500) NOT NULL DEFAULT '',
    series_id BIGINT UNSIGNED NULL,
    is_external TINYINT(1) NOT NULL DEFAULT 0,
    external_url VARCHAR(500) NOT NULL DEFAULT '',
    is_archived TINYINT(1) NOT NULL DEFAULT 0,
    archived_at TIMESTAMP NULL,
    archive_report_path VARCHAR(500) NULL,
    CONSTRAINT contests_dates_check CHECK (end_date > start_date)
);

CREATE TABLE contest_moderators (
    contest_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (contest_id, user_id)
);

CREATE TABLE contest_expert_pool (
    contest_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (contest_id, user_id)
);

CREATE TABLE contest_attachment_fields (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    contest_id BIGINT UNSIGNED NOT NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    title VARCHAR(255) NOT NULL,
    requirements TEXT NOT NULL DEFAULT ''
);

CREATE TABLE participant_attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    contest_id BIGINT UNSIGNED NOT NULL,
    field_id BIGINT UNSIGNED NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    UNIQUE (user_id, contest_id, field_id)
);

CREATE TABLE consent_documents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE user_consents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    contest_id BIGINT UNSIGNED NOT NULL,
    consent_file_path VARCHAR(500) NOT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_approved TINYINT(1) NOT NULL DEFAULT 0,
    rejection_reason TEXT NOT NULL DEFAULT '',
    reviewed_at TIMESTAMP NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    UNIQUE (user_id, contest_id)
);

CREATE TABLE stages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    contest_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    instruction_file_path VARCHAR(500),
    external_link VARCHAR(500) NOT NULL DEFAULT '',
    external_link_enabled TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INTEGER NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    submission_deadline DATE,
    evaluation_mode VARCHAR(20) NOT NULL DEFAULT 'all',
    experts_per_submission SMALLINT NOT NULL DEFAULT 2,
    include_scores_in_final_ranking TINYINT(1) NOT NULL DEFAULT 0,
    phase_status TINYINT NOT NULL DEFAULT 1,
    stage_format TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT stages_dates_check CHECK (end_date > start_date)
);

CREATE TABLE stage_experts (
    stage_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (stage_id, user_id)
);

CREATE TABLE stage_attachment_fields (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    stage_id BIGINT UNSIGNED NOT NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    title VARCHAR(255) NOT NULL,
    requirements TEXT NOT NULL DEFAULT ''
);

CREATE TABLE criterion_groups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    stage_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0
);

CREATE TABLE criteria (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    stage_id BIGINT UNSIGNED NOT NULL,
    group_id BIGINT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    min_points INTEGER NOT NULL DEFAULT 0,
    max_points INTEGER NOT NULL,
    weight INTEGER NOT NULL DEFAULT 100,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    CONSTRAINT fk_criteria_criterion_group FOREIGN KEY (group_id) REFERENCES criterion_groups(id) ON DELETE SET NULL
);

CREATE INDEX idx_criteria_stage_group_sort ON criteria (stage_id, group_id, sort_order, id);
CREATE INDEX idx_criterion_groups_stage_sort ON criterion_groups (stage_id, sort_order, id);

CREATE TABLE submissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    contest_id BIGINT UNSIGNED NOT NULL,
    stage_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    work_link VARCHAR(500) NOT NULL DEFAULT '',
    optional_attachments_status VARCHAR(16) NOT NULL DEFAULT 'na',
    optional_attachments_rejection_reason TEXT NOT NULL DEFAULT '',
    optional_attachments_reviewed_at TIMESTAMP NULL,
    optional_attachments_reviewed_by BIGINT UNSIGNED NULL,
    submission_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, stage_id)
);

CREATE TABLE submission_assigned_experts (
    submission_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (submission_id, user_id)
);

CREATE TABLE submission_attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    submission_id BIGINT UNSIGNED NOT NULL,
    field_id BIGINT UNSIGNED NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    UNIQUE (submission_id, field_id)
);

CREATE TABLE contest_participants (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    contest_id BIGINT UNSIGNED NOT NULL,
    admitted_max_stage_order INTEGER NOT NULL DEFAULT 1,
    UNIQUE uk_contest_participants_user_contest (user_id, contest_id)
);

CREATE TABLE evaluations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    expert_id BIGINT UNSIGNED NOT NULL,
    submission_id BIGINT UNSIGNED NOT NULL,
    criterion_id BIGINT UNSIGNED NOT NULL,
    points INTEGER NOT NULL,
    evaluated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    comment TEXT,
    UNIQUE (expert_id, submission_id, criterion_id)
);

CREATE TABLE awards (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    participant_id BIGINT UNSIGNED NOT NULL,
    contest_id BIGINT UNSIGNED NOT NULL,
    place INTEGER NOT NULL,
    award_description TEXT,
    is_manual_override TINYINT(1) NOT NULL DEFAULT 0
);

CREATE TABLE site_announcements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(280) NOT NULL UNIQUE,
    summary VARCHAR(500) NOT NULL DEFAULT '',
    body TEXT NOT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    is_pinned TINYINT(1) NOT NULL DEFAULT 0,
    published_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE in_app_notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    body TEXT NOT NULL DEFAULT '',
    link VARCHAR(500) NOT NULL DEFAULT '/',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE site_partners (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    website_url VARCHAR(500) NOT NULL DEFAULT '',
    logo_path VARCHAR(500) NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_contests_series_id ON contests(series_id);
CREATE INDEX idx_stages_contest_order ON stages(contest_id, sort_order);
CREATE INDEX idx_submissions_contest_stage ON submissions(contest_id, stage_id);
CREATE INDEX idx_notifications_user_created ON in_app_notifications(user_id, created_at DESC);
CREATE INDEX idx_notifications_user_read ON in_app_notifications(user_id, is_read);

COMMIT;
