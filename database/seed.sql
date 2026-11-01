-- ============================================================
--  Masar — reference data
--
--  Run after schema.sql:
--    mysql -u root -p masar_db < database/seed.sql
--
--  Safe to run more than once: existing rows keep their data, and
--  the Arabic faculty/major names are filled in or refreshed.
--  Contains no personal data.
-- ============================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------
--  Faculties
-- ------------------------------------------------------------

INSERT INTO `faculties` (`id`, `name`, `name_ar`) VALUES
(1, 'Faculty of Arts and Educational Sciences', 'كلية الآداب والعلوم التربوية'),
(2, 'Faculty of Law', 'كلية الحقوق'),
(3, 'Faculty of Business', 'كلية الأعمال'),
(4, 'Faculty of Information Technology', 'كلية تكنولوجيا المعلومات'),
(5, 'Faculty of Media', 'كلية الإعلام'),
(6, 'Faculty of Engineering and Design', 'كلية الهندسة والتصميم'),
(7, 'Faculty of Pharmacy', 'كلية الصيدلة'),
(8, 'Faculty of Allied Medical Sciences', 'كلية العلوم الطبية المساندة'),
(9, 'Faculty of Nursing', 'كلية التمريض')
ON DUPLICATE KEY UPDATE `name_ar` = VALUES(`name_ar`);

-- ------------------------------------------------------------
--  Majors
-- ------------------------------------------------------------

INSERT INTO `majors` (`id`, `faculty_id`, `name`, `name_ar`) VALUES
(1, 1, 'English Language and Literature', 'اللغة الإنجليزية وآدابها'),
(2, 1, 'Arabic Language and Literature', 'اللغة العربية وآدابها'),
(3, 1, 'Applied Translation', 'الترجمة التطبيقية'),
(4, 1, 'Education Technology', 'تكنولوجيا التعليم'),
(5, 1, 'Sports Management and Training', 'الإدارة والتدريب الرياضي'),
(6, 2, 'Law', 'القانون'),
(7, 3, 'Business Administration', 'إدارة الأعمال'),
(8, 3, 'Accounting', 'المحاسبة'),
(9, 3, 'E-Marketing and Social Media', 'التسويق الإلكتروني ووسائل التواصل الاجتماعي'),
(10, 3, 'E-Business and E-Commerce', 'الأعمال الإلكترونية والتجارة الإلكترونية'),
(11, 3, 'Business Intelligence', 'ذكاء الأعمال'),
(12, 3, 'Human Resource Management', 'إدارة الموارد البشرية'),
(13, 3, 'Financial Technology (FinTech)', 'التكنولوجيا المالية'),
(14, 3, 'Supply Chain & Digital Logistics Management', 'إدارة سلاسل التوريد واللوجستيات الرقمية'),
(15, 3, 'Business and Psychology', 'الأعمال وعلم النفس'),
(16, 4, 'Computer Science', 'علم الحاسوب'),
(17, 4, 'Software Engineering', 'هندسة البرمجيات'),
(18, 4, 'Cyber Security', 'الأمن السيبراني'),
(19, 4, 'Artificial Intelligence', 'الذكاء الاصطناعي'),
(20, 5, 'Journalism and Media', 'الصحافة والإعلام'),
(21, 5, 'Radio and Television', 'الإذاعة والتلفزيون'),
(22, 5, 'Digital Media', 'الإعلام الرقمي'),
(23, 6, 'Renewable Energy Engineering', 'هندسة الطاقة المتجددة'),
(24, 6, 'Intelligence Systems Engineering', 'هندسة الأنظمة الذكية'),
(25, 6, 'Architecture', 'العمارة'),
(26, 6, 'Biomedical Engineering', 'الهندسة الطبية الحيوية'),
(27, 6, 'Graphic Design', 'التصميم الجرافيكي'),
(28, 6, 'Interior Design', 'التصميم الداخلي'),
(29, 7, 'Pharmacy', 'الصيدلة'),
(30, 8, 'Physiotherapy', 'العلاج الطبيعي'),
(31, 8, 'Medical Analysis and Laboratory Sciences', 'التحاليل الطبية وعلوم المختبرات'),
(32, 8, 'Nutrition and Integrative Health', 'التغذية والصحة المتكاملة'),
(33, 8, 'Genetic Engineering and Biotechnology', 'الهندسة الوراثية والتكنولوجيا الحيوية'),
(34, 8, 'Paramedic and Emergency Science', 'علوم الإسعاف والطوارئ'),
(35, 9, 'Nursing', 'التمريض')
ON DUPLICATE KEY UPDATE `name_ar` = VALUES(`name_ar`);

-- ------------------------------------------------------------
--  University links
-- ------------------------------------------------------------

INSERT IGNORE INTO `university_links`
    (`id`, `title`, `url`, `description`, `category`,
     `title_ar`, `description_ar`, `category_ar`, `is_active`, `sort_order`)
VALUES
(1, 'Middle East University', 'https://www.meu.edu.jo/',
    'Visit the official university website.',
    'University Services',
    'جامعة الشرق الأوسط',
    'زيارة الموقع الرسمي للجامعة.',
    'خدمات جامعية', 1, 1),
(2, 'Edu Gate', 'https://reg.meu.edu.jo/',
    'Student portal for academic services, registration, grades and university records.',
    'University Services',
    'بوابة الطالب',
    'بوابة الطالب للوصول إلى الخدمات الأكاديمية، التسجيل، العلامات، والبيانات الجامعية.',
    'خدمات جامعية', 1, 2),
(3, 'E-learning', 'https://elearning.meu.edu.jo/',
    'Course materials, assignments, quizzes and files shared by instructors.',
    'University Services',
    'التعلم الإلكتروني',
    'الوصول إلى المواد التعليمية والواجبات والاختبارات والملفات المرفقة من الكادر التعليمي عبر منصة التعلم الإلكتروني.',
    'خدمات جامعية', 1, 3),
(4, 'Microsoft Teams', 'https://teams.microsoft.com/v2/',
    'Online lectures and communication with instructors and classmates.',
    'University Services',
    'مايكروسوفت تيمز',
    'الوصول إلى محاضرات الأونلاين والتواصل مع المدرسين والطلاب عبر Microsoft Teams.',
    'خدمات جامعية', 1, 4);
