-- ============================================================
--  Masar — full database schema
--
--  Fresh installation:
--    mysql -u root -p -e "CREATE DATABASE masar_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
--    mysql -u root -p masar_db < database/schema.sql
--    mysql -u root -p masar_db < database/seed.sql
--
--  Safe to run more than once: every table uses IF NOT EXISTS and
--  nothing is dropped. To upgrade an existing (pre-v2) database use
--  database/migrations/2026_11_01_masar_v2_upgrade.sql instead.
--
--  Works on MySQL 8+ and MariaDB 10.4+.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
--  faculties / majors — lookup lists for registration and profile
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `faculties` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(150) NOT NULL,
    `name_ar`    VARCHAR(150) NULL DEFAULT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `name` (`name`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `majors` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `faculty_id` INT UNSIGNED NOT NULL,
    `name`       VARCHAR(150) NOT NULL,
    `name_ar`    VARCHAR(150) NULL DEFAULT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_major_per_faculty` (`faculty_id`, `name`),

    CONSTRAINT `fk_majors_faculty`
        FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`id`)
        ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  users — students and administrators
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `users` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `full_name`          VARCHAR(100) NOT NULL,
    `email`              VARCHAR(150) NOT NULL,
    `password`           VARCHAR(255) NOT NULL,
    `university_id`      VARCHAR(30)  NULL DEFAULT NULL,
    `faculty_id`         INT UNSIGNED NULL DEFAULT NULL,
    `major_id`           INT UNSIGNED NULL DEFAULT NULL,
    `role`               ENUM('student', 'admin') NOT NULL DEFAULT 'student',
    `preferred_language` ENUM('en', 'ar')         NOT NULL DEFAULT 'en',
    `created_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`),
    KEY `fk_users_faculty` (`faculty_id`),
    KEY `fk_users_major` (`major_id`),

    CONSTRAINT `fk_users_faculty`
        FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_users_major`
        FOREIGN KEY (`major_id`) REFERENCES `majors` (`id`)
        ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  academic_records — one row per uploaded academic record PDF
--
--  No personal identifiers (student name, number, advisor) are
--  stored: they are redacted before analysis and never needed.
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `academic_records` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`           INT UNSIGNED NOT NULL,

    `original_name`     VARCHAR(255) NOT NULL,
    `stored_name`       VARCHAR(255) NOT NULL,
    `file_path`         VARCHAR(500) NOT NULL,
    `file_hash`         CHAR(64)     NOT NULL,
    `mime_type`         VARCHAR(100) NOT NULL,
    `file_size`         INT UNSIGNED NOT NULL,
    `record_language`   ENUM('en', 'ar') NOT NULL DEFAULT 'en',

    `status`            ENUM('uploaded', 'processing', 'analyzed', 'failed')
                            NOT NULL DEFAULT 'uploaded',
    `is_current`        TINYINT(1)   NOT NULL DEFAULT 0,

    /* Next-semester planning context chosen by the student. */
    `target_semester`   ENUM('regular', 'summer') NOT NULL DEFAULT 'regular',
    `is_graduating`     TINYINT(1)       NOT NULL DEFAULT 0,
    `credit_limit`      TINYINT UNSIGNED NULL DEFAULT NULL,

    /* Official summary, as printed on the record. */
    `academic_semester` VARCHAR(50)  NULL DEFAULT NULL,
    `faculty_name`      VARCHAR(150) NULL DEFAULT NULL,
    `major_name`        VARCHAR(150) NULL DEFAULT NULL,
    `degree_name`       VARCHAR(100) NULL DEFAULT NULL,
    `student_level`     VARCHAR(100) NULL DEFAULT NULL,
    `plan_hours`        SMALLINT UNSIGNED NULL DEFAULT NULL,
    `graded_hours`      SMALLINT UNSIGNED NULL DEFAULT NULL,
    `earned_hours`      SMALLINT UNSIGNED NULL DEFAULT NULL,
    `attempted_hours`   SMALLINT UNSIGNED NULL DEFAULT NULL,
    `remaining_hours`   SMALLINT UNSIGNED NULL DEFAULT NULL,
    `gpa`               DECIMAL(4, 2) NULL DEFAULT NULL,
    `academic_grade`    VARCHAR(50)  NULL DEFAULT NULL,

    /* Analysis bookkeeping. analysis_error holds the technical
       detail for administrators; students see a generic message. */
    `analysis_error`    VARCHAR(500) NULL DEFAULT NULL,
    `analysis_started_at` DATETIME   NULL DEFAULT NULL,
    `analyzed_at`       DATETIME     NULL DEFAULT NULL,
    `ai_model`          VARCHAR(100) NULL DEFAULT NULL,
    `prompt_version`    VARCHAR(20)  NULL DEFAULT NULL,
    `analysis_json`     LONGTEXT     NULL DEFAULT NULL,

    `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                            ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_record_file_per_user` (`user_id`, `file_hash`),
    KEY `idx_academic_records_user` (`user_id`),
    KEY `idx_academic_records_current` (`user_id`, `is_current`),
    KEY `idx_academic_records_status` (`status`),
    KEY `idx_academic_records_created` (`created_at`),

    CONSTRAINT `fk_academic_records_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  record_courses — one row per course line in an analysed record
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `record_courses` (
    `id`                  INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `record_id`           INT UNSIGNED     NOT NULL,

    `requirement_type`    VARCHAR(120)     NOT NULL,
    `course_code`         VARCHAR(20)      NOT NULL,
    `course_name`         VARCHAR(255)     NOT NULL,
    `prerequisite_codes`  VARCHAR(255)     NULL DEFAULT NULL,
    `credit_hours`        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `mark`                VARCHAR(5)       NULL DEFAULT NULL,
    `result`              ENUM('pass', 'fail', 'exempted', 'incomplete', 'none')
                              NOT NULL DEFAULT 'none',
    `registration_status` VARCHAR(30)      NULL DEFAULT NULL,
    `semester_code`       VARCHAR(10)      NULL DEFAULT NULL,
    `is_current_semester` TINYINT(1)       NOT NULL DEFAULT 0,
    `completion_state`    ENUM('completed', 'in_progress', 'failed', 'remaining')
                              NOT NULL DEFAULT 'remaining',

    /* Set when the PDF itself is ambiguous about this course. */
    `has_data_warning`    TINYINT(1)       NOT NULL DEFAULT 0,

    `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_record_course` (`record_id`, `course_code`),
    KEY `idx_record_courses_state` (`record_id`, `completion_state`),
    KEY `idx_record_courses_code` (`course_code`),

    CONSTRAINT `fk_record_courses_record`
        FOREIGN KEY (`record_id`) REFERENCES `academic_records` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  record_recommendations — AI suggestions after PHP validation.
--  Rejected suggestions are kept for the evaluation statistics.
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `record_recommendations` (
    `id`               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `record_id`        INT UNSIGNED     NOT NULL,

    `course_code`      VARCHAR(20)      NOT NULL,
    `course_name`      VARCHAR(255)     NOT NULL,
    `credit_hours`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `priority`         TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `reason`           VARCHAR(500)     NULL DEFAULT NULL,
    `reason_ar`        VARCHAR(500)     NULL DEFAULT NULL,
    `is_accepted`      TINYINT(1)       NOT NULL DEFAULT 1,
    `rejection_reason` VARCHAR(255)     NULL DEFAULT NULL,
    `has_data_warning` TINYINT(1)       NOT NULL DEFAULT 0,

    `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_record_recommendation` (`record_id`, `course_code`),
    KEY `idx_recommendations_accepted` (`record_id`, `is_accepted`, `priority`),

    CONSTRAINT `fk_recommendations_record`
        FOREIGN KEY (`record_id`) REFERENCES `academic_records` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  chat_sessions / chat_messages — Ask Masar conversations
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

-- ------------------------------------------------------------
--  university_links — resources hub managed by administrators
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `university_links` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(120) NOT NULL,
    `url`         VARCHAR(500) NOT NULL,
    `description` VARCHAR(255) NULL DEFAULT NULL,
    `category`    VARCHAR(80)  NULL DEFAULT NULL,
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
    `sort_order`  INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                      ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  activity_logs — security and usage events.
--  No foreign key on user_id on purpose: the trail is kept after
--  an account is deleted.
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED    NULL DEFAULT NULL,
    `event_type` VARCHAR(50)     NOT NULL,
    `ip_address` VARCHAR(45)     NULL DEFAULT NULL,
    `user_agent` VARCHAR(255)    NULL DEFAULT NULL,
    `details`    VARCHAR(500)    NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_activity_user` (`user_id`),
    KEY `idx_activity_event` (`event_type`),
    KEY `idx_activity_created` (`created_at`),
    KEY `idx_activity_ip_event` (`ip_address`, `event_type`, `created_at`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  system_alerts — issues raised automatically for administrators
-- ------------------------------------------------------------

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

SET FOREIGN_KEY_CHECKS = 1;
