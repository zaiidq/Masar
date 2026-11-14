<?php

declare(strict_types=1);

/**
 * Central session configuration.
 *
 * Must be required BEFORE session_start(). Every entry point that starts
 * a session does so through this file, so cookie and lifetime settings
 * are identical everywhere.
 *
 * Sessions are stored in storage/sessions rather than the system default.
 * On Ubuntu/Debian the default directory is cleaned by a cron job using
 * the php.ini lifetime (24 minutes), which silently ignores any longer
 * lifetime set here. A private directory with PHP's own garbage
 * collector makes SESSION_LIFETIME actually apply.
 */

require_once __DIR__ . '/../config/env.php';

/*
 * Browser security headers, sent with every page (every entry point
 * loads this file).
 *
 * The content policy allows only Masar's own files plus Google Fonts.
 * Inline scripts and styles stay allowed because the pages use them;
 * the policy still blocks scripts, frames and form targets from any
 * other site.
 */
if (!headers_sent() && PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header(
        "Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' 'unsafe-inline'; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com; "
        . "img-src 'self' data:; "
        . "connect-src 'self'; "
        . "object-src 'none'; "
        . "base-uri 'self'; "
        . "form-action 'self'; "
        . "frame-ancestors 'self'"
    );

    if (requestIsHttps()) {
        /* Keep browsers on HTTPS once they have used it. */
        header('Strict-Transport-Security: max-age=15552000');
    }
}

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    $sessionLifetime = max(
        900,
        (int) env('SESSION_LIFETIME', '28800')
    );

    $sessionDirectory = dirname(__DIR__) . '/storage/sessions';

    if (!is_dir($sessionDirectory)) {
        @mkdir($sessionDirectory, 0700, true);
    }

    if (is_dir($sessionDirectory) && is_writable($sessionDirectory)) {
        session_save_path($sessionDirectory);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
    }

    /* Also true behind an HTTPS proxy when TRUST_PROXY=1. */
    $isHttps = requestIsHttps();

    ini_set('session.gc_maxlifetime', (string) $sessionLifetime);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    session_name('MASARSESSID');

    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
