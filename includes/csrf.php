<?php

declare(strict_types=1);

/**
 * CSRF protection shared by every form and JSON endpoint.
 * Requires an active session.
 */

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8')
        . '">';
}

/**
 * True when the submitted token matches the session token.
 * Non-string input (e.g. csrf_token[]=x) is rejected, not a crash.
 */
function verifyCsrf(): bool
{
    $submitted = $_POST['csrf_token'] ?? '';

    return is_string($submitted)
        && $submitted !== ''
        && !empty($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submitted);
}
