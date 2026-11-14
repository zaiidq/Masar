-- ============================================================
--  Masar — Migration: password change and password reset
--  Date: 2026-11-13
--
--  1. users.password_changed_at — when the password last changed.
--     Sessions opened before that moment are signed out.
--  2. password_resets — single-use, expiring reset links.
--     Only a hash of each link's token is stored.
--
--  REQUIRED before using the updated code (every page fails
--  without it):
--    sudo mysqldump masar_db > ~/masar_backup_before_account_features.sql
--    sudo mysql masar_db < database/migrations/2026_11_13_account_features.sql
--
--  Safe to run more than once. Requires MariaDB 10.4+.
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `password_changed_at` DATETIME
        NULL DEFAULT NULL AFTER `password`;

CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `token_hash` CHAR(64)     NOT NULL,
    `expires_at` DATETIME     NOT NULL,
    `used_at`    DATETIME     NULL DEFAULT NULL,
    `created_by` INT UNSIGNED NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_password_resets_token` (`token_hash`),
    KEY `idx_password_resets_user` (`user_id`, `created_at`),

    CONSTRAINT `fk_password_resets_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
