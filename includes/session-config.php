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

    $isHttps =
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;

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
