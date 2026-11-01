<?php

declare(strict_types=1);

/**
 * Shared bootstrap for administrator pages.
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/language.php';
require_once __DIR__ . '/activity-logger.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /masar/student/dashboard.php');
    exit;
}

csrfToken();

function adminEscape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * One-time message shown after a redirect (Post/Redirect/Get), so a page
 * refresh never repeats the action.
 */
function adminFlash(string $type, string $message): void
{
    $_SESSION['admin_flash'] = ['type' => $type, 'message' => $message];
}

/**
 * @return array{type: string, message: string}|null
 */
function adminTakeFlash(): ?array
{
    $flash = $_SESSION['admin_flash'] ?? null;
    unset($_SESSION['admin_flash']);

    return is_array($flash) ? $flash : null;
}

function adminRedirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/**
 * Date/time in the interface language.
 */
function adminFormatDateTime(?string $value): string
{
    if (!$value) {
        return '—';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return '—';
    }

    return formatLocalizedDate($timestamp, true);
}

function adminOpenAlertCount(PDO $pdo): int
{
    return (int) $pdo->query(
        'SELECT COUNT(*) FROM system_alerts WHERE is_resolved = 0'
    )->fetchColumn();
}
