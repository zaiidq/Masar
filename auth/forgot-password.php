<?php

declare(strict_types=1);

/*
 * Forgot password: the user enters their email and receives a link to
 * choose a new password.
 *
 * The page answers the same way whether or not the email belongs to an
 * account, so it cannot be used to find out who is registered.
 */

require_once __DIR__ . '/../includes/session-config.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: /masar/index.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../includes/activity-logger.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/system-alerts.php';
require_once __DIR__ . '/../includes/password-reset.php';
require_once __DIR__ . '/../includes/auth-shell.php';

$error = '';
$email = '';
$sent = ($_GET['sent'] ?? '') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));

    if (!verifyCsrf()) {
        $error = t('auth_error_session_expired');
    } elseif ($email === '' || mb_strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = t('auth_error_email_invalid');
    } else {
        $statement = $pdo->prepare(
            'SELECT id, full_name, email, preferred_language FROM users WHERE email = :email LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        if ($user && recentPasswordResetRequests($pdo, (int) $user['id']) < PASSWORD_RESET_MAX_PER_HOUR) {
            $userId = (int) $user['id'];

            if (mailIsConfigured()) {
                $link = createPasswordResetLink($pdo, $userId);

                try {
                    sendPasswordResetEmail(
                        $user['email'],
                        $user['full_name'],
                        $link,
                        $user['preferred_language']
                    );

                    logActivity($pdo, 'PASSWORD_RESET_REQUESTED', $userId, 'email sent');
                } catch (Throwable $exception) {
                    error_log('Masar reset email failed: ' . $exception->getMessage());
                    logActivity($pdo, 'PASSWORD_RESET_REQUESTED', $userId, 'email failed');

                    raiseSystemAlert(
                        $pdo,
                        'MAIL_ERROR',
                        'warning',
                        'Password reset email could not be sent to ' . $user['email']
                            . ': ' . $exception->getMessage()
                            . ' — issue a reset link from the Users page.',
                        $userId
                    );
                }
            } else {
                /*
                 * No mail server: an administrator hands out the link
                 * from the Users page.
                 */
                logActivity($pdo, 'PASSWORD_RESET_REQUESTED', $userId, 'email not configured');

                raiseSystemAlert(
                    $pdo,
                    'PASSWORD_RESET_REQUEST',
                    'info',
                    $user['email'] . ' asked to reset their password. Email is not configured:'
                        . ' issue a reset link from the Users page and pass it to the student.',
                    $userId
                );
            }
        } else {
            logActivity(
                $pdo,
                'PASSWORD_RESET_REQUESTED',
                $user ? (int) $user['id'] : null,
                $user ? 'too many requests' : 'unknown email'
            );
        }

        /* Post/Redirect/Get, and the same answer in every case. */
        header('Location: /masar/auth/forgot-password.php?sent=1');
        exit;
    }
}

authShellStart(t('auth_forgot_title'), '/masar/auth/forgot-password.php');
?>
            <header class="auth-form-heading-new">
                <p class="auth-form-heading-new__eyebrow"><?= htmlspecialchars(t('auth_forgot_eyebrow')) ?></p>
                <h2><?= htmlspecialchars(t('auth_forgot_title')) ?></h2>
                <p><?= htmlspecialchars(t('auth_forgot_intro')) ?></p>
            </header>

            <?php if ($sent): ?>
                <div class="auth-alert-new auth-alert-new--success" role="status">
                    <?= htmlspecialchars(t(mailIsConfigured() ? 'auth_forgot_sent' : 'auth_forgot_sent_admin')) ?>
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="auth-alert-new auth-alert-new--error" role="alert">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (!$sent): ?>
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

                    <button type="submit" class="auth-submit-new">
                        <?= htmlspecialchars(t('auth_forgot_action')) ?>
                        <span aria-hidden="true">→</span>
                    </button>
                </form>
            <?php endif; ?>
<?php
authShellEnd();
