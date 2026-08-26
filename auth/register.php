<?php

declare(strict_types=1);

session_start();

if (isset($_SESSION['user_id'])) {
    if (($_SESSION['role'] ?? '') === 'admin') {
        header('Location: ../admin/dashboard.php');
    } else {
        header('Location: ../student/dashboard.php');
    }

    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../includes/activity-logger.php';

$errors = [];
$success = '';

$fullName = '';
$universityId = '';
$email = '';
$selectedFacultyId = 0;
$selectedMajorId = 0;

$faculties = $pdo->query(
    'SELECT id, name
     FROM faculties
     ORDER BY name'
)->fetchAll();

$majors = $pdo->query(
    'SELECT id, faculty_id, name
     FROM majors
     ORDER BY name'
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $universityId = trim($_POST['university_id'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $selectedFacultyId = filter_var(
        $_POST['faculty_id'] ?? null,
        FILTER_VALIDATE_INT
    ) ?: 0;

    $selectedMajorId = filter_var(
        $_POST['major_id'] ?? null,
        FILTER_VALIDATE_INT
    ) ?: 0;

    if ($fullName === '') {
        $errors[] = t('auth_error_name_required');
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = t('auth_error_email_invalid');
    }

    if ($selectedFacultyId <= 0) {
        $errors[] = t('auth_error_faculty_required');
    }

    if ($selectedMajorId <= 0) {
        $errors[] = t('auth_error_major_required');
    }

    if (strlen($password) < 8) {
        $errors[] = t('auth_error_password_length');
    }

    if ($password !== $confirmPassword) {
        $errors[] = t('auth_error_password_match');
    }

    if ($selectedFacultyId > 0 && $selectedMajorId > 0) {
        $stmt = $pdo->prepare(
            'SELECT id
             FROM majors
             WHERE id = :major_id
               AND faculty_id = :faculty_id
             LIMIT 1'
        );

        $stmt->execute([
            'major_id' => $selectedMajorId,
            'faculty_id' => $selectedFacultyId,
        ]);

        if (!$stmt->fetch()) {
            $errors[] = t('auth_error_major_faculty');
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'SELECT id
             FROM users
             WHERE email = :email
             LIMIT 1'
        );

        $stmt->execute([
            'email' => $email,
        ]);

        if ($stmt->fetch()) {
            $errors[] = t('auth_error_email_registered');
        } else {
            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $pdo->prepare(
                'INSERT INTO users (
                    full_name,
                    email,
                    password,
                    university_id,
                    faculty_id,
                    major_id
                ) VALUES (
                    :full_name,
                    :email,
                    :password,
                    :university_id,
                    :faculty_id,
                    :major_id
                )'
            );

            $stmt->execute([
    'full_name' => $fullName,
    'email' => $email,
    'password' => $hashedPassword,
    'university_id' => $universityId !== ''
        ? $universityId
        : null,
    'faculty_id' => $selectedFacultyId,
    'major_id' => $selectedMajorId,
]);

$newUserId = (int) $pdo->lastInsertId();

logActivity(
    $pdo,
    'REGISTER',
    $newUserId,
    'role=student'
);

$success = t('auth_register_success');

            $fullName = '';
            $universityId = '';
            $email = '';
            $selectedFacultyId = 0;
            $selectedMajorId = 0;
        }
    }
}

$currentLanguage = currentLanguage();
$currentDirection = currentDirection();
$switchToLanguage = $currentLanguage === 'en' ? 'ar' : 'en';
$currentRequestUri = $_SERVER['REQUEST_URI'] ?? '/masar/auth/register.php';
$languageSwitchUrl = '/masar/switch-language.php?lang='
    . urlencode($switchToLanguage)
    . '&return='
    . urlencode($currentRequestUri);
?>
<!DOCTYPE html>
<html
    lang="<?= htmlspecialchars($currentLanguage, ENT_QUOTES, 'UTF-8') ?>"
    dir="<?= htmlspecialchars($currentDirection, ENT_QUOTES, 'UTF-8') ?>"
>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(t('auth_register_page_title')) ?> | Masar</title>

    <link rel="icon" type="image/svg+xml" href="/masar/assets/brand/masar-favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Tajawal:wght@400;500;700&display=swap"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="/masar/assets/css/style.css?v=14">
    <script src="/masar/assets/js/major-selector.js?v=2" defer></script>
</head>
<body class="auth-body-new">
<main class="auth-shell-new auth-shell-new--register">
    <aside class="auth-brand-new">
        <a class="auth-brand-new__logo" href="/masar/auth/login.php" aria-label="Masar">
            <img src="/masar/assets/brand/masar-logo-horizontal-white.svg" alt="Masar">
        </a>

        <div class="auth-brand-new__content">
            <p class="auth-brand-new__eyebrow"><?= htmlspecialchars(t('auth_brand_eyebrow')) ?></p>
            <h1><?= htmlspecialchars(t('auth_register_brand_title')) ?></h1>
            <p class="auth-brand-new__intro"><?= htmlspecialchars(t('auth_register_brand_body')) ?></p>

            <ol class="auth-brand-new__steps">
                <li>
                    <span>01</span>
                    <p><?= htmlspecialchars(t('auth_brand_step_record')) ?></p>
                </li>
                <li>
                    <span>02</span>
                    <p><?= htmlspecialchars(t('auth_brand_step_progress')) ?></p>
                </li>
                <li>
                    <span>03</span>
                    <p><?= htmlspecialchars(t('auth_brand_step_next')) ?></p>
                </li>
            </ol>
        </div>

        <p class="auth-brand-new__note"><?= htmlspecialchars(t('auth_brand_note')) ?></p>
    </aside>

    <section class="auth-form-pane-new">
        <div class="auth-toolbar-new">
            <a class="auth-mobile-logo-new" href="/masar/auth/login.php" aria-label="Masar">
                <img src="/masar/assets/brand/masar-logo-horizontal.svg" alt="Masar">
            </a>

            <a class="auth-language-new" href="<?= htmlspecialchars($languageSwitchUrl, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars(t('switch_language')) ?>
            </a>
        </div>

        <div class="auth-form-wrap-new auth-form-wrap-new--register">
            <header class="auth-form-heading-new">
                <p class="auth-form-heading-new__eyebrow"><?= htmlspecialchars(t('auth_create_eyebrow')) ?></p>
                <h2><?= htmlspecialchars(t('auth_register_title')) ?></h2>
                <p><?= htmlspecialchars(t('auth_register_intro')) ?></p>
            </header>

            <?php if (!empty($errors)): ?>
                <div class="auth-alert-new auth-alert-new--error" role="alert">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success !== ''): ?>
                <div class="auth-alert-new auth-alert-new--success" role="status">
                    <strong><?= htmlspecialchars($success) ?></strong>
                    <a href="/masar/auth/login.php"><?= htmlspecialchars(t('auth_login_action')) ?> →</a>
                </div>
            <?php endif; ?>

            <form class="auth-form-new" method="POST" action="">
                <div class="auth-register-grid-new">
                    <div class="auth-field-new">
                        <label for="full_name"><?= htmlspecialchars(t('auth_full_name')) ?></label>
                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            value="<?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>"
                            autocomplete="name"
                            required
                        >
                    </div>

                    <div class="auth-field-new">
                        <label for="university_id"><?= htmlspecialchars(t('auth_university_id_optional')) ?></label>
                        <input
                            type="text"
                            id="university_id"
                            name="university_id"
                            value="<?= htmlspecialchars($universityId, ENT_QUOTES, 'UTF-8') ?>"
                            autocomplete="off"
                        >
                    </div>

                    <div class="auth-field-new">
                        <label for="faculty_id"><?= htmlspecialchars(t('auth_faculty')) ?></label>
                        <select id="faculty_id" name="faculty_id" required>
                            <option value=""><?= htmlspecialchars(t('auth_select_faculty')) ?></option>
                            <?php foreach ($faculties as $faculty): ?>
                                <option
                                    value="<?= (int) $faculty['id'] ?>"
                                    <?= (int) $faculty['id'] === $selectedFacultyId ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($faculty['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="auth-field-new">
                        <label for="major_id"><?= htmlspecialchars(t('auth_major')) ?></label>
                        <select
                            id="major_id"
                            name="major_id"
                            data-selected-major="<?= $selectedMajorId ?>"
                            disabled
                            required
                        >
                            <option value=""><?= htmlspecialchars(t('auth_select_faculty_first')) ?></option>
                        </select>
                    </div>

                    <div class="auth-field-new auth-field-new--wide">
                        <label for="email"><?= htmlspecialchars(t('auth_email')) ?></label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                            autocomplete="email"
                            placeholder="name@example.com"
                            required
                        >
                    </div>

                    <div class="auth-field-new">
                        <label for="password"><?= htmlspecialchars(t('auth_password')) ?></label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            autocomplete="new-password"
                            aria-describedby="passwordHint"
                            required
                        >
                        <small id="passwordHint"><?= htmlspecialchars(t('auth_password_hint')) ?></small>
                    </div>

                    <div class="auth-field-new">
                        <label for="confirm_password"><?= htmlspecialchars(t('auth_confirm_password')) ?></label>
                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            autocomplete="new-password"
                            required
                        >
                    </div>
                </div>

                <button type="submit" class="auth-submit-new">
                    <?= htmlspecialchars(t('auth_create_action')) ?>
                    <span aria-hidden="true">→</span>
                </button>
            </form>

            <p class="auth-switch-copy-new">
                <?= htmlspecialchars(t('auth_have_account')) ?>
                <a href="/masar/auth/login.php"><?= htmlspecialchars(t('auth_login_action')) ?></a>
            </p>
        </div>

        <p class="auth-pane-footer-new"><?= htmlspecialchars(t('auth_footer_note')) ?></p>
    </section>
</main>

<script type="application/json" id="majorsData">
<?= json_encode(
    $majors,
    JSON_UNESCAPED_UNICODE
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
) ?>
</script>
</body>
</html>
