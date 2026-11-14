<?php

declare(strict_types=1);

/**
 * Minimal .env loader.
 *
 * Keeps credentials out of the repository. The file lives at the project
 * root and is already listed in .gitignore.
 */
function loadEnvironment(?string $path = null): void
{
    static $loaded = false;

    if ($loaded) {
        return;
    }

    $loaded = true;

    $path = $path ?? dirname(__DIR__) . '/.env';

    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file(
        $path,
        FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
    );

    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);

        $key = trim($key);
        $value = trim($value);

        /* Allow quoted values containing spaces or '#'. */
        if (
            strlen($value) >= 2
            && (
                ($value[0] === '"' && str_ends_with($value, '"'))
                || ($value[0] === "'" && str_ends_with($value, "'"))
            )
        ) {
            $value = substr($value, 1, -1);
        }

        if ($key === '') {
            continue;
        }

        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }
}

/**
 * True when the visitor reached Masar over HTTPS.
 *
 * Behind a reverse proxy that terminates HTTPS (Tailscale Funnel, nginx,
 * Cloudflare) Apache itself only sees plain HTTP, so the proxy's
 * X-Forwarded-Proto header is used — but only with TRUST_PROXY=1,
 * because without a proxy that header can be forged by the visitor.
 */
function requestIsHttps(): bool
{
    if (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
    ) {
        return true;
    }

    if (env('TRUST_PROXY', '0') !== '1') {
        return false;
    }

    $forwardedProto = strtolower(trim(
        explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]
    ));

    return $forwardedProto === 'https';
}

/**
 * Read a configuration value, falling back to a default.
 */
function env(string $key, ?string $default = null): ?string
{
    loadEnvironment();

    $value = $_ENV[$key] ?? getenv($key);

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return (string) $value;
}
