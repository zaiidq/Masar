-- ============================================================
--  Masar — Migration: plan accuracy and chat limit
--  Date: 2026-11-12
--
--  1. record_recommendations.conditional_on — prerequisites that are
--     still in progress; the recommendation depends on passing them.
--  2. chat_usage — Ask Masar questions per student per day, so that
--     deleting a conversation no longer resets the daily limit.
--     Today's questions are carried over.
--
--  REQUIRED before using the updated code (analysis and Ask Masar
--  fail without it):
--    sudo mysqldump masar_db > ~/masar_backup_before_plan_accuracy.sql
--    sudo mysql masar_db < database/migrations/2026_11_12_plan_accuracy.sql
--
--  Safe to run more than once. Requires MariaDB 10.4+.
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE `record_recommendations`
    ADD COLUMN IF NOT EXISTS `conditional_on` VARCHAR(255)
        NULL DEFAULT NULL AFTER `has_data_warning`;

CREATE TABLE IF NOT EXISTS `chat_usage` (
    `user_id`    INT UNSIGNED      NOT NULL,
    `usage_date` DATE              NOT NULL,
    `questions`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    PRIMARY KEY (`user_id`, `usage_date`),

    CONSTRAINT `fk_chat_usage_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Carry over the questions already asked today.
INSERT IGNORE INTO `chat_usage` (`user_id`, `usage_date`, `questions`)
SELECT s.`user_id`, CURDATE(), COUNT(*)
FROM `chat_messages` m
JOIN `chat_sessions` s ON s.`id` = m.`session_id`
WHERE m.`role` = 'user'
  AND m.`created_at` >= CURDATE()
GROUP BY s.`user_id`;
