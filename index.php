<?php

declare(strict_types=1);

/*
 * Entry point: send visitors to the right place.
 */

require_once __DIR__ . '/includes/session-config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /masar/auth/login.php');
} elseif (($_SESSION['role'] ?? '') === 'admin') {
    header('Location: /masar/admin/dashboard.php');
} else {
    header('Location: /masar/student/dashboard.php');
}

exit;
