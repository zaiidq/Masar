<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session-config.php';
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
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/system-alerts.php';

$error = '';
$email = '';
$notice = '';

if (($_GET['deleted'] ?? '') === '1') {
    $notice = t('auth_account_deleted');
}

if (($_GET['reset'] ?? '') === '1') {
    $notice = t('auth_reset_done');
    $email = (string) ($_SESSION['registered_email'] ?? '');
    unset($_SESSION['registered_email']);
}

if (($_GET['registered'] ?? '') === '1') {
    $notice = t('auth_register_success_login');
    $email = (string) ($_SESSION['registered_email'] ?? '');
    unset($_SESSION['registered_email']);
}

$maxAttempts = max(3, (int) env('LOGIN_MAX_ATTEMPTS', '5'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $ipAddress = getClientIpAddress();

    if (!verifyCsrf()) {
        $error = t('auth_error_session_expired');
    } elseif (recentFailedLogins($pdo, $ipAddress) >= $maxAttempts) {
        /* Brute-force protection: too many failures from this address. */
        $error = t('auth_error_too_many_attempts');

        logActivity($pdo, 'LOGIN_BLOCKED', null, 'email=' . mb_substr($email, 0, 150));

        raiseSystemAlert(
            $pdo,
            'LOGIN_BLOCKED',
            'warning',
            sprintf('Repeated failed logins from %s (last email tried: %s).', $ipAddress ?? 'unknown IP', mb_substr($email, 0, 150)),
            null,
            15
        );
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, full_name, email, password, role, preferred_language,
                    password_changed_at
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
            $_SESSION['language'] = $user['preferred_language'];
            $_SESSION['password_changed_at'] = (string) ($user['password_changed_at'] ?? '');
            unset($_SESSION['csrf_token']);

            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                $pdo->prepare('UPDATE users SET password = :password WHERE id = :id')
                    ->execute([
                        'password' => password_hash($password, PASSWORD_DEFAULT),
                        'id' => $user['id'],
                    ]);
            }

            logActivity(
                $pdo,
                'LOGIN_SUCCESS',
                (int) $user['id'],
                'role=' . $user['role']
            );

            header(
                'Location: '
                . ($user['role'] === 'admin'
                    ? '/masar/admin/dashboard.php'
                    : '/masar/student/dashboard.php')
            );
            exit;
        }

        logActivity(
            $pdo,
            'LOGIN_FAILED',
            $user ? (int) $user['id'] : null,
            $user ? 'invalid_password' : 'unknown_email'
        );

        $error = t('auth_login_error');
    }
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
    <?php require __DIR__ . '/../includes/theme-head.php'; ?>
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
                <img class="brand-logo-light" src="/masar/assets/brand/masar-logo-horizontal.svg" alt="Masar">
                <img class="brand-logo-dark" src="/masar/assets/brand/masar-logo-horizontal-white.svg" alt="Masar">
            </a>

            <div class="auth-toolbar-new__actions">
                <?php require __DIR__ . '/../includes/theme-toggle.php'; ?>

                <a class="auth-language-new" href="<?= htmlspecialchars($languageSwitchUrl, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars(t('switch_language')) ?>
                </a>
            </div>
        </div>

        <div class="auth-form-wrap-new">
            <header class="auth-form-heading-new">
                <p class="auth-form-heading-new__eyebrow"><?= htmlspecialchars(t('auth_sign_in_eyebrow')) ?></p>
                <h2><?= htmlspecialchars(t('auth_login_title')) ?></h2>
                <p><?= htmlspecialchars(t('auth_login_intro')) ?></p>
            </header>

            <?php if ($notice !== ''): ?>
                <div class="auth-alert-new auth-alert-new--success" role="status">
                    <?= htmlspecialchars($notice) ?>
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="auth-alert-new auth-alert-new--error" role="alert">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form class="auth-form-new" method="POST" action="">
                <?= csrfField() ?>
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

                    <div class="password-field-wrapper">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            autocomplete="current-password"
                            required
                        >

                        <button
                            type="button"
                            class="password-toggle"
                            id="passwordToggle"
                            aria-label="<?= htmlspecialchars(t('auth_show_password'), ENT_QUOTES) ?>"
                            aria-pressed="false"
                            data-show-label="<?= htmlspecialchars(t('auth_show'), ENT_QUOTES) ?>"
                            data-hide-label="<?= htmlspecialchars(t('auth_hide'), ENT_QUOTES) ?>"
                            data-show-aria="<?= htmlspecialchars(t('auth_show_password'), ENT_QUOTES) ?>"
                            data-hide-aria="<?= htmlspecialchars(t('auth_hide_password'), ENT_QUOTES) ?>"
                        >
                            <?= htmlspecialchars(t('auth_show')) ?>
                        </button>
                    </div>
                </div>

                <p class="auth-forgot-new">
                    <a href="/masar/auth/forgot-password.php"><?= htmlspecialchars(t('auth_forgot_link')) ?></a>
                </p>

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
<script>
    const passwordInput = document.getElementById('password');
    const passwordToggle = document.getElementById('passwordToggle');

    if (passwordInput && passwordToggle) {
        passwordToggle.addEventListener('click', function () {
            const isHidden = passwordInput.type === 'password';

            const data = passwordToggle.dataset;

            passwordInput.type = isHidden ? 'text' : 'password';
            passwordToggle.textContent = isHidden ? data.hideLabel : data.showLabel;
            passwordToggle.setAttribute(
                'aria-pressed',
                isHidden ? 'true' : 'false'
            );
            passwordToggle.setAttribute(
                'aria-label',
                isHidden ? data.hideAria : data.showAria
            );
        });
    }
</script>
</body>
</html>
