-- ============================================================
--  Masar — reference data
--
--  Run after schema.sql:
--    mysql -u root -p masar_db < database/seed.sql
--
--  Safe to run more than once (INSERT IGNORE on primary keys).
--  Contains no personal data.
-- ============================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------
--  Faculties
-- ------------------------------------------------------------

INSERT IGNORE INTO `faculties` (`id`, `name`) VALUES
(1, 'Faculty of Arts and Educational Sciences'),
(2, 'Faculty of Law'),
(3, 'Faculty of Business'),
(4, 'Faculty of Information Technology'),
(5, 'Faculty of Media'),
(6, 'Faculty of Engineering and Design'),
(7, 'Faculty of Pharmacy'),
(8, 'Faculty of Allied Medical Sciences'),
(9, 'Faculty of Nursing');

-- ------------------------------------------------------------
--  Majors
-- ------------------------------------------------------------

INSERT IGNORE INTO `majors` (`id`, `faculty_id`, `name`) VALUES
(1, 1, 'English Language and Literature'),
(2, 1, 'Arabic Language and Literature'),
(3, 1, 'Applied Translation'),
(4, 1, 'Education Technology'),
(5, 1, 'Sports Management and Training'),
(6, 2, 'Law'),
(7, 3, 'Business Administration'),
(8, 3, 'Accounting'),
(9, 3, 'E-Marketing and Social Media'),
(10, 3, 'E-Business and E-Commerce'),
(11, 3, 'Business Intelligence'),
(12, 3, 'Human Resource Management'),
(13, 3, 'Financial Technology (FinTech)'),
(14, 3, 'Supply Chain & Digital Logistics Management'),
(15, 3, 'Business and Psychology'),
(16, 4, 'Computer Science'),
(17, 4, 'Software Engineering'),
(18, 4, 'Cyber Security'),
(19, 4, 'Artificial Intelligence'),
(20, 5, 'Journalism and Media'),
(21, 5, 'Radio and Television'),
(22, 5, 'Digital Media'),
(23, 6, 'Renewable Energy Engineering'),
(24, 6, 'Intelligence Systems Engineering'),
(25, 6, 'Architecture'),
(26, 6, 'Biomedical Engineering'),
(27, 6, 'Graphic Design'),
(28, 6, 'Interior Design'),
(29, 7, 'Pharmacy'),
(30, 8, 'Physiotherapy'),
(31, 8, 'Medical Analysis and Laboratory Sciences'),
(32, 8, 'Nutrition and Integrative Health'),
(33, 8, 'Genetic Engineering and Biotechnology'),
(34, 8, 'Paramedic and Emergency Science'),
(35, 9, 'Nursing');

-- ------------------------------------------------------------
--  University links
-- ------------------------------------------------------------

INSERT IGNORE INTO `university_links`
    (`id`, `title`, `url`, `description`, `category`, `is_active`, `sort_order`)
VALUES
(1, 'Middle East University', 'https://www.meu.edu.jo/',
    'Visit the official university website.',
    'University Services', 1, 1),
(2, 'Edu Gate - بوابة الطالب', 'https://reg.meu.edu.jo/',
    'بوابة الطالب للوصول إلى الخدمات الأكاديمية، التسجيل، العلامات، والبيانات الجامعية.',
    'University Services - خدمات جامعية', 1, 2),
(3, 'E-learning - التعلم الإلكتروني', 'https://elearning.meu.edu.jo/',
    'الوصول إلى المواد التعليمية والواجبات والاختبارات والملفات المرفقة من الكادر التعليمي عبر منصة التعلم الإلكتروني.',
    'University Services - خدمات جامعية', 1, 3),
(4, 'Microsoft Teams - مايكروسوفت تيمز', 'https://teams.microsoft.com/v2/',
    'الوصول إلى محاضرات الأونلاين والتواصل مع المدرسين والطلاب عبر Microsoft Teams.',
    'University Services - خدمات جامعية', 1, 4);
