<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/session-config.php';
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/csrf.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /masar/auth/login.php');
    exit;
}

/*
 * Re-read the account on every request so a deleted account is logged
 * out immediately and a role change made by an administrator applies
 * without the user signing in again.
 */
$authStatement = $pdo->prepare(
    'SELECT role, full_name, preferred_language, password_changed_at
     FROM users WHERE id = :id'
);
$authStatement->execute(['id' => (int) $_SESSION['user_id']]);
$authUser = $authStatement->fetch();

/*
 * A password change or reset signs out every other session: only the
 * session that knows the current change time stays valid.
 */
if (
    $authUser
    && (string) ($authUser['password_changed_at'] ?? '')
        !== (string) ($_SESSION['password_changed_at'] ?? '')
) {
    $authUser = false;
}

if (!$authUser) {
    $_SESSION = [];
    session_destroy();
    header('Location: /masar/auth/login.php');
    exit;
}

$_SESSION['role'] = $authUser['role'];
$_SESSION['full_name'] = $authUser['full_name'];

if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = $authUser['preferred_language'];
}

unset($authStatement, $authUser);
