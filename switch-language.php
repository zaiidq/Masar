<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/language.php';

$requestedLanguage = $_GET['lang'] ?? '';

if (
    is_string($requestedLanguage)
    && in_array($requestedLanguage, ['en', 'ar'], true)
) {
    $_SESSION['language'] = $requestedLanguage;
}

$returnPath = $_GET['return'] ?? '/masar/student/dashboard.php';

/*
 * Only allow redirects inside Masar.
 */
if (
    !is_string($returnPath)
    || !str_starts_with($returnPath, '/masar/')
) {
    $returnPath = '/masar/student/dashboard.php';
}

header('Location: ' . $returnPath);
exit;