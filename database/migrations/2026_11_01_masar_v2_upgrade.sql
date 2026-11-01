-- ============================================================
--  Masar — Migration: v2 upgrade for an EXISTING database
--  Date: 2026-11-01
--
--  Brings a database created before November 2026 up to the
--  structure in database/schema.sql without losing any data.
--  Requires MariaDB 10.4+ (uses ADD/DROP COLUMN IF EXISTS).
--
--  BACK UP FIRST:
--    sudo mysqldump masar_db > ~/masar_backup_before_v2.sql
--  Then run:
--    sudo mysql masar_db < database/migrations/2026_11_01_masar_v2_upgrade.sql
--
--  Safe to run more than once.
-- ============================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------
--  users: persisted language preference (FR4)
-- ------------------------------------------------------------

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `preferred_language` ENUM('en', 'ar')
        NOT NULL DEFAULT 'en' AFTER `role`;

-- ------------------------------------------------------------
--  academic_records: planning context, analysis timing,
--  and removal of never-used personal-data columns
-- ------------------------------------------------------------

ALTER TABLE `academic_records`
    ADD COLUMN IF NOT EXISTS `target_semester` ENUM('regular', 'summer')
        NOT NULL DEFAULT 'regular' AFTER `is_current`,
    ADD COLUMN IF NOT EXISTS `is_graduating` TINYINT(1)
        NOT NULL DEFAULT 0 AFTER `target_semester`,
    ADD COLUMN IF NOT EXISTS `credit_limit` TINYINT UNSIGNED
        NULL DEFAULT NULL AFTER `is_graduating`,
    ADD COLUMN IF NOT EXISTS `analysis_started_at` DATETIME
        NULL DEFAULT NULL AFTER `analysis_error`,
    DROP COLUMN IF EXISTS `university_printed_at`,
    DROP COLUMN IF EXISTS `student_number`,
    DROP COLUMN IF EXISTS `student_name`,
    DROP COLUMN IF EXISTS `advisor_name`;

-- ------------------------------------------------------------
--  Data-quality warnings on courses and recommendations
-- ------------------------------------------------------------

ALTER TABLE `record_courses`
    ADD COLUMN IF NOT EXISTS `has_data_warning` TINYINT(1)
        NOT NULL DEFAULT 0 AFTER `completion_state`;

ALTER TABLE `record_recommendations`
    ADD COLUMN IF NOT EXISTS `has_data_warning` TINYINT(1)
        NOT NULL DEFAULT 0 AFTER `rejection_reason`;

-- ------------------------------------------------------------
--  activity_logs: match users.id type, index for login limiting
-- ------------------------------------------------------------

ALTER TABLE `activity_logs`
    MODIFY COLUMN `user_id` INT UNSIGNED NULL DEFAULT NULL;

ALTER TABLE `activity_logs`
    ADD INDEX IF NOT EXISTS `idx_activity_ip_event`
        (`ip_address`, `event_type`, `created_at`);

-- ------------------------------------------------------------
--  New tables
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `chat_sessions` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `title`      VARCHAR(150) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                     ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_chat_sessions_user` (`user_id`, `updated_at`),

    CONSTRAINT `fk_chat_sessions_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_messages` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` INT UNSIGNED    NOT NULL,
    `role`       ENUM('user', 'assistant') NOT NULL,
    `content`    TEXT            NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_chat_messages_session` (`session_id`, `id`),
    KEY `idx_chat_messages_created` (`created_at`),

    CONSTRAINT `fk_chat_messages_session`
        FOREIGN KEY (`session_id`) REFERENCES `chat_sessions` (`id`)
        ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `system_alerts` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `alert_type`  VARCHAR(50)  NOT NULL,
    `severity`    ENUM('info', 'warning', 'critical') NOT NULL DEFAULT 'warning',
    `message`     VARCHAR(500) NOT NULL,
    `user_id`     INT UNSIGNED NULL DEFAULT NULL,
    `is_resolved` TINYINT(1)   NOT NULL DEFAULT 0,
    `resolved_by` INT UNSIGNED NULL DEFAULT NULL,
    `resolved_at` DATETIME     NULL DEFAULT NULL,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_alerts_open` (`is_resolved`, `created_at`),
    KEY `idx_alerts_type` (`alert_type`, `created_at`),

    CONSTRAINT `fk_alerts_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_alerts_resolver`
        FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Fix the typos in the seeded Arabic link texts
-- ------------------------------------------------------------

UPDATE `university_links`
SET `title` = REPLACE(`title`, 'الألكتروني', 'الإلكتروني'),
    `description` = REPLACE(
        REPLACE(`description`, 'المرفقه', 'المرفقة'),
        'الاونلاين', 'الأونلاين'
    );
