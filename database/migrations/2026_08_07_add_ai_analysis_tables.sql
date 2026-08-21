-- ============================================================
--  Masar — Migration: AI analysis storage
--  Date: 2026-08-07
--
--  Adds the structured tables required by the report
--  ("Academic data such as courses, prerequisites, credit hours,
--   and recommendations should be stored in structured database
--   tables") plus reproducibility columns on academic_records.
--
--  Run once:
--    mysql -u root masar_db < database/migrations/2026_08_07_add_ai_analysis_tables.sql
-- ============================================================

USE `masar_db`;

-- ------------------------------------------------------------
--  academic_records: AI reproducibility columns
-- ------------------------------------------------------------

ALTER TABLE `academic_records`
    ADD COLUMN `ai_model` VARCHAR(100) NULL DEFAULT NULL
        AFTER `analyzed_at`,
    ADD COLUMN `prompt_version` VARCHAR(20) NULL DEFAULT NULL
        AFTER `ai_model`,
    ADD COLUMN `analysis_json` LONGTEXT NULL DEFAULT NULL
        AFTER `prompt_version`;

-- ------------------------------------------------------------
--  record_courses
--  One row per course line in the uploaded academic record.
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `record_courses` (
    `id`                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `record_id`           INT UNSIGNED    NOT NULL,

    `requirement_type`    VARCHAR(120)    NOT NULL,
    `course_code`         VARCHAR(20)     NOT NULL,
    `course_name`         VARCHAR(255)    NOT NULL,
    `prerequisite_codes`  VARCHAR(255)    NULL DEFAULT NULL,
    `credit_hours`        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `mark`                VARCHAR(5)      NULL DEFAULT NULL,
    `result`              ENUM('pass', 'fail', 'exempted', 'incomplete', 'none')
                                          NOT NULL DEFAULT 'none',
    `registration_status` VARCHAR(30)     NULL DEFAULT NULL,
    `semester_code`       VARCHAR(10)     NULL DEFAULT NULL,
    `is_current_semester` TINYINT(1)      NOT NULL DEFAULT 0,

    /* Derived state used by the recommendation validator. */
    `completion_state`    ENUM('completed', 'in_progress', 'failed', 'remaining')
                                          NOT NULL DEFAULT 'remaining',

    `created_at`          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_record_course` (`record_id`, `course_code`),
    KEY `idx_record_courses_state` (`record_id`, `completion_state`),
    KEY `idx_record_courses_code` (`course_code`),

    CONSTRAINT `fk_record_courses_record`
        FOREIGN KEY (`record_id`)
        REFERENCES `academic_records` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  record_recommendations
--  AI-suggested next-semester courses, after PHP validation.
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `record_recommendations` (
    `id`               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `record_id`        INT UNSIGNED     NOT NULL,

    `course_code`      VARCHAR(20)      NOT NULL,
    `course_name`      VARCHAR(255)     NOT NULL,
    `credit_hours`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `priority`         TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `reason`           VARCHAR(500)     NULL DEFAULT NULL,

    /* Set when a suggestion fails a deterministic check.
       Rejected rows are kept for the report's evaluation chapter. */
    `is_accepted`      TINYINT(1)       NOT NULL DEFAULT 1,
    `rejection_reason` VARCHAR(255)     NULL DEFAULT NULL,

    `created_at`       TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_record_recommendation` (`record_id`, `course_code`),
    KEY `idx_recommendations_accepted` (`record_id`, `is_accepted`, `priority`),

    CONSTRAINT `fk_recommendations_record`
        FOREIGN KEY (`record_id`)
        REFERENCES `academic_records` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;
