<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    require_once __DIR__ . '/session-config.php';
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
/**
 * A date (optionally with time) in the interface language.
 * Uses Latin digits in both languages, matching the rest of the UI.
 */
function formatLocalizedDate(int $timestamp, bool $withTime = false): string
{
    static $arabicMonths = [
        1 => 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
        'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر',
    ];

    $day = date('j', $timestamp);
    $month = (int) date('n', $timestamp);
    $year = date('Y', $timestamp);

    if (currentLanguage() === 'ar') {
        $text = $day . ' ' . $arabicMonths[$month] . ' ' . $year;

        if ($withTime) {
            $text .= '، ' . date('H:i', $timestamp);
        }

        return $text;
    }

    $text = date('j M Y', $timestamp);

    if ($withTime) {
        $text .= ', ' . date('H:i', $timestamp);
    }

    return $text;
}

/**
 * SQL expression for a lookup name (faculty / major) in the interface
 * language, falling back to the English name.
 */
function localizedNameSql(string $tableAlias = ''): string
{
    $prefix = $tableAlias !== '' ? $tableAlias . '.' : '';

    return currentLanguage() === 'ar'
        ? "COALESCE(NULLIF({$prefix}name_ar, ''), {$prefix}name)"
        : "{$prefix}name";
}
