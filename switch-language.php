<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/language.php';

$requestedLanguage = $_GET['lang'] ?? '';

if (
    is_string($requestedLanguage)
    && in_array($requestedLanguage, ['en', 'ar'], true)
) {
    $_SESSION['language'] = $requestedLanguage;

    /* FR4: remember the choice on the account for the next sign-in. */
    if (isset($_SESSION['user_id'])) {
        require_once __DIR__ . '/config/database.php';

        $pdo->prepare(
            'UPDATE users SET preferred_language = :language WHERE id = :id'
        )->execute([
            'language' => $requestedLanguage,
            'id' => (int) $_SESSION['user_id'],
        ]);
    }
}

$returnPath = $_GET['return'] ?? '/masar/index.php';

/*
 * Only allow redirects inside Masar (no "//evil.example" or schemes).
 */
if (
    !is_string($returnPath)
    || !str_starts_with($returnPath, '/masar/')
    || str_contains($returnPath, '//')
    || preg_match('/[\r\n]/', $returnPath)
) {
    $returnPath = '/masar/index.php';
}

header('Location: ' . $returnPath);
exit;
