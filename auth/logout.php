<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session-config.php';
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/activity-logger.php';

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

header('Location: login.php');
exit;