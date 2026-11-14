<?php

declare(strict_types=1);

/*
 * Choose a new password from a reset link.
 * The link works once and only until it expires.
 */

require_once __DIR__ . '/../includes/session-config.php';
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../includes/activity-logger.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/password-reset.php';
require_once __DIR__ . '/../includes/auth-shell.php';

/* The token must never be cached or sent on as a referrer. */
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$reset = findValidPasswordReset($pdo, $token);
$errors = [];

if ($reset && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (!verifyCsrf()) {
        $errors[] = t('auth_error_session_expired');
    }

    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = t('auth_error_password_length');
    }

    if ($password !== $confirmPassword) {
        $errors[] = t('auth_error_password_match');
    }

    if (!$errors) {
        /* Claim the link first, so it can never be used twice. */
        $claim = $pdo->prepare(
            'UPDATE password_resets
             SET used_at = NOW()
             WHERE id = :id AND used_at IS NULL AND expires_at > NOW()'
        );
        $claim->execute(['id' => $reset['id']]);

        if ($claim->rowCount() === 1) {
            setUserPassword($pdo, (int) $reset['user_id'], $password);

            logActivity($pdo, 'PASSWORD_RESET_COMPLETED', (int) $reset['user_id']);

            /* Whoever was signed in on this browser is signed out too. */
            $language = currentLanguage();
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['language'] = $language;
            $_SESSION['registered_email'] = $reset['email'];

            header('Location: /masar/auth/login.php?reset=1');
            exit;
        }

        $reset = null;
    }
}

authShellStart(t('auth_reset_title'), '/masar/auth/login.php');
?>
            <header class="auth-form-heading-new">
                <p class="auth-form-heading-new__eyebrow"><?= htmlspecialchars(t('auth_forgot_eyebrow')) ?></p>
                <h2><?= htmlspecialchars(t('auth_reset_title')) ?></h2>
                <?php if ($reset): ?>
                    <p>
                        <?= htmlspecialchars(t('auth_reset_intro')) ?>
                        <bdi dir="ltr"><?= htmlspecialchars($reset['email']) ?></bdi>
                    </p>
                <?php endif; ?>
            </header>

            <?php if (!$reset): ?>
                <div class="auth-alert-new auth-alert-new--error" role="alert">
                    <?= htmlspecialchars(t('auth_reset_invalid')) ?>
                </div>

                <p class="auth-switch-copy-new">
                    <a href="/masar/auth/forgot-password.php"><?= htmlspecialchars(t('auth_reset_request_new')) ?></a>
                </p>
            <?php else: ?>
                <?php if ($errors): ?>
                    <div class="auth-alert-new auth-alert-new--error" role="alert">
                        <?php foreach ($errors as $error): ?>
                            <div><?= htmlspecialchars($error) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form class="auth-form-new" method="POST" action="/masar/auth/reset-password.php">
                    <?= csrfField() ?>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="auth-field-new">
                        <label for="password"><?= htmlspecialchars(t('auth_new_password')) ?></label>
                        <input type="password" id="password" name="password" autocomplete="new-password" minlength="8" required>
                        <small><?= htmlspecialchars(t('auth_password_hint')) ?></small>
                    </div>

                    <div class="auth-field-new">
                        <label for="confirm_password"><?= htmlspecialchars(t('auth_confirm_new_password')) ?></label>
                        <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" minlength="8" required>
                    </div>

                    <button type="submit" class="auth-submit-new">
                        <?= htmlspecialchars(t('auth_reset_action')) ?>
                        <span aria-hidden="true">→</span>
                    </button>
                </form>
            <?php endif; ?>
<?php
authShellEnd();
