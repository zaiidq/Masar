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
