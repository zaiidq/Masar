<?php

declare(strict_types=1);

/**
 * Password reset links.
 *
 * A link carries a random token; only its SHA-256 hash is stored. A link
 * works once, expires after PASSWORD_RESET_MINUTES, and creating a new
 * one cancels the user's older unused links.
 */

require_once __DIR__ . '/mailer.php';

const PASSWORD_RESET_MINUTES = 60;
const PASSWORD_RESET_MAX_PER_HOUR = 3;
const PASSWORD_MIN_LENGTH = 8;

/**
 * Absolute URL of the site, for links sent by email.
 * APP_URL in .env wins (needed behind a proxy such as Tailscale Funnel).
 */
function appBaseUrl(): string
{
    $configured = env('APP_URL');

    if ($configured !== null) {
        return rtrim($configured, '/');
    }

    $isHttps =
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;

    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));

    return ($isHttps ? 'https://' : 'http://') . $host . '/masar';
}

/**
 * Create a reset link for the user and return it.
 *
 * @param int|null $createdBy Administrator who issued it, null when the
 *                            user asked for it themselves.
 */
function createPasswordResetLink(PDO $pdo, int $userId, ?int $createdBy = null): string
{
    $token = bin2hex(random_bytes(32));

    /* Older unused links stop working. */
    $pdo->prepare(
        'UPDATE password_resets
         SET used_at = NOW()
         WHERE user_id = :user_id AND used_at IS NULL'
    )->execute(['user_id' => $userId]);

    $pdo->prepare(
        'INSERT INTO password_resets (user_id, token_hash, expires_at, created_by)
         VALUES (:user_id, :token_hash, NOW() + INTERVAL ' . PASSWORD_RESET_MINUTES . ' MINUTE, :created_by)'
    )->execute([
        'user_id' => $userId,
        'token_hash' => hash('sha256', $token),
        'created_by' => $createdBy,
    ]);

    return appBaseUrl() . '/auth/reset-password.php?token=' . $token;
}

/**
 * Links the user asked for themselves in the last hour.
 */
function recentPasswordResetRequests(PDO $pdo, int $userId): int
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM password_resets
         WHERE user_id = :user_id
           AND created_by IS NULL
           AND created_at > NOW() - INTERVAL 1 HOUR'
    );
    $statement->execute(['user_id' => $userId]);

    return (int) $statement->fetchColumn();
}

/**
 * The reset row and its user for a token that is still valid.
 *
 * @return array<string, mixed>|null
 */
function findValidPasswordReset(PDO $pdo, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $statement = $pdo->prepare(
        'SELECT r.id, r.user_id, u.email, u.full_name
         FROM password_resets r
         JOIN users u ON u.id = r.user_id
         WHERE r.token_hash = :token_hash
           AND r.used_at IS NULL
           AND r.expires_at > NOW()'
    );
    $statement->execute(['token_hash' => hash('sha256', $token)]);
    $row = $statement->fetch();

    return $row ?: null;
}

/**
 * Store a new password and sign out the user's other sessions.
 *
 * @return string The new password_changed_at value, for the session
 *                that should stay signed in.
 */
function setUserPassword(PDO $pdo, int $userId, string $newPassword): string
{
    $changedAt = date('Y-m-d H:i:s');

    $pdo->prepare(
        'UPDATE users
         SET password = :password, password_changed_at = :changed_at
         WHERE id = :id'
    )->execute([
        'password' => password_hash($newPassword, PASSWORD_DEFAULT),
        'changed_at' => $changedAt,
        'id' => $userId,
    ]);

    /* Any reset link still open for this account is now pointless. */
    $pdo->prepare(
        'UPDATE password_resets
         SET used_at = NOW()
         WHERE user_id = :user_id AND used_at IS NULL'
    )->execute(['user_id' => $userId]);

    return $changedAt;
}

/**
 * Email the reset link in the user's language.
 *
 * @throws MailException
 */
function sendPasswordResetEmail(string $email, string $fullName, string $link, string $language): void
{
    if ($language === 'ar') {
        $subject = 'إعادة تعيين كلمة المرور - مسار';
        $body = "مرحباً {$fullName}،\n\n"
            . "وصلنا طلب لإعادة تعيين كلمة المرور لحسابك في مسار.\n"
            . "افتح الرابط التالي لاختيار كلمة مرور جديدة (صالح لمدة " . PASSWORD_RESET_MINUTES . " دقيقة ولمرة واحدة):\n\n"
            . $link . "\n\n"
            . "إذا لم تطلب ذلك، تجاهل هذه الرسالة وستبقى كلمة مرورك كما هي.\n\n"
            . "مسار";
    } else {
        $subject = 'Reset your Masar password';
        $body = "Hello {$fullName},\n\n"
            . "We received a request to reset the password of your Masar account.\n"
            . "Open this link to choose a new password (valid for " . PASSWORD_RESET_MINUTES . " minutes, one use only):\n\n"
            . $link . "\n\n"
            . "If you did not ask for this, ignore this email and your password stays the same.\n\n"
            . "Masar";
    }

    sendMail($email, $subject, $body);
}
