<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/language.php';

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare(
        'SELECT id, full_name, email, password, role
         FROM users
         WHERE email = :email
         LIMIT 1'
    );

    $stmt->execute([
        'email' => $email,
    ]);

    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];

        if ($user['role'] === 'admin') {
            header('Location: ../admin/dashboard.php');
        } else {
            header('Location: ../student/dashboard.php');
        }

        exit;
    }

    $error = t('auth_login_error');
}

$currentLanguage = currentLanguage();
$currentDirection = currentDirection();
$switchToLanguage = $currentLanguage === 'en' ? 'ar' : 'en';
$currentRequestUri = $_SERVER['REQUEST_URI'] ?? '/masar/auth/login.php';
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
    <title><?= htmlspecialchars(t('auth_login_page_title')) ?> | Masar</title>

    <link rel="icon" type="image/svg+xml" href="/masar/assets/brand/masar-favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Tajawal:wght@400;500;700&display=swap"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="/masar/assets/css/style.css?v=14">
</head>
<body class="auth-body-new">
<main class="auth-shell-new">
    <aside class="auth-brand-new">
        <a class="auth-brand-new__logo" href="/masar/auth/login.php" aria-label="Masar">
            <img src="/masar/assets/brand/masar-logo-horizontal-white.svg" alt="Masar">
        </a>

        <div class="auth-brand-new__content">
            <p class="auth-brand-new__eyebrow"><?= htmlspecialchars(t('auth_brand_eyebrow')) ?></p>
            <h1><?= htmlspecialchars(t('auth_brand_title')) ?></h1>
            <p class="auth-brand-new__intro"><?= htmlspecialchars(t('auth_brand_body')) ?></p>

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

        <div class="auth-form-wrap-new">
            <header class="auth-form-heading-new">
                <p class="auth-form-heading-new__eyebrow"><?= htmlspecialchars(t('auth_sign_in_eyebrow')) ?></p>
                <h2><?= htmlspecialchars(t('auth_login_title')) ?></h2>
                <p><?= htmlspecialchars(t('auth_login_intro')) ?></p>
            </header>

            <?php if ($error !== ''): ?>
                <div class="auth-alert-new auth-alert-new--error" role="alert">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form class="auth-form-new" method="POST" action="">
                <div class="auth-field-new">
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
                        autocomplete="current-password"
                        required
                    >
                </div>

                <button type="submit" class="auth-submit-new">
                    <?= htmlspecialchars(t('auth_login_action')) ?>
                    <span aria-hidden="true">→</span>
                </button>
            </form>

            <p class="auth-switch-copy-new">
                <?= htmlspecialchars(t('auth_no_account')) ?>
                <a href="/masar/auth/register.php"><?= htmlspecialchars(t('auth_create_account')) ?></a>
            </p>
        </div>

        <p class="auth-pane-footer-new"><?= htmlspecialchars(t('auth_footer_note')) ?></p>
    </section>
</main>
</body>
</html>
