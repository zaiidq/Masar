<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$language = $_SESSION['language'] ?? 'en';

if (!in_array($language, ['en', 'ar'], true)) {
    $language = 'en';
}

$translations = require __DIR__ . '/../lang/' . $language . '.php';

function t(string $key): string
{
    global $translations;

    return $translations[$key] ?? $key;
}

function currentLanguage(): string
{
    global $language;

    return $language;
}

function currentDirection(): string
{
    global $translations;

    return $translations['direction'] ?? 'ltr';
}