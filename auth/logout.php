<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session-config.php';
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/activity-logger.php';
require_once __DIR__ . '/../includes/csrf.php';

/*
 * Logout only via POST with a valid token, so another site cannot sign
 * a user out with a hidden link or image.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf()) {
    header('Location: /masar/index.php');
    exit;
}

$userId = isset($_SESSION['user_id'])
    ? (int) $_SESSION['user_id']
    : null;

if ($userId !== null) {
    logActivity(
        $pdo,
        'LOGOUT',
        $userId,
        'User logged out'
    );
}

$language = $_SESSION['language'] ?? null;

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

/* Keep the interface language on the login page. */
if ($language !== null) {
    session_start();
    $_SESSION['language'] = $language;
}

header('Location: /masar/auth/login.php');
exit;
