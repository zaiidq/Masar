-- ============================================================
--  Masar — Migration: bilingual university links
--  Date: 2026-11-02
--
--  Adds separate Arabic title / description / category to
--  university_links, then splits the four standard links that were
--  written as "English - عربي" in one field. Links you edited to other
--  values are left untouched (edit them from the admin page).
--
--  Run after 2026_11_01_masar_v2_upgrade.sql:
--    sudo mysqldump masar_db > ~/masar_backup_before_links.sql
--    sudo mysql masar_db < database/migrations/2026_11_02_bilingual_links.sql
--
--  Safe to run more than once. Requires MariaDB 10.4+.
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE `university_links`
    ADD COLUMN IF NOT EXISTS `title_ar` VARCHAR(120)
        NULL DEFAULT NULL AFTER `category`,
    ADD COLUMN IF NOT EXISTS `description_ar` VARCHAR(255)
        NULL DEFAULT NULL AFTER `title_ar`,
    ADD COLUMN IF NOT EXISTS `category_ar` VARCHAR(80)
        NULL DEFAULT NULL AFTER `description_ar`;

-- Category: "University Services - خدمات جامعية" → both languages.
UPDATE `university_links`
SET `category` = 'University Services',
    `category_ar` = 'خدمات جامعية'
WHERE `category` IN ('University Services - خدمات جامعية', 'University Services')
  AND (`category_ar` IS NULL OR `category_ar` = '');

UPDATE `university_links`
SET `title_ar` = 'جامعة الشرق الأوسط',
    `description_ar` = 'زيارة الموقع الرسمي للجامعة.'
WHERE `title` = 'Middle East University'
  AND (`title_ar` IS NULL OR `title_ar` = '');

UPDATE `university_links`
SET `title` = 'Edu Gate',
    `title_ar` = 'بوابة الطالب',
    `description_ar` = `description`,
    `description` = 'Student portal for academic services, registration, grades and university records.'
WHERE `title` = 'Edu Gate - بوابة الطالب'
  AND (`title_ar` IS NULL OR `title_ar` = '');

UPDATE `university_links`
SET `title` = 'E-learning',
    `title_ar` = 'التعلم الإلكتروني',
    `description_ar` = `description`,
    `description` = 'Course materials, assignments, quizzes and files shared by instructors.'
WHERE `title` IN ('E-learning - التعلم الإلكتروني', 'E-learning - التعلم الألكتروني')
  AND (`title_ar` IS NULL OR `title_ar` = '');

UPDATE `university_links`
SET `title` = 'Microsoft Teams',
    `title_ar` = 'مايكروسوفت تيمز',
    `description_ar` = `description`,
    `description` = 'Online lectures and communication with instructors and classmates.'
WHERE `title` = 'Microsoft Teams - مايكروسوفت تيمز'
  AND (`title_ar` IS NULL OR `title_ar` = '');
