<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

/*
 * Connection settings come from .env (see .env.example).
 * The defaults match a local XAMPP installation.
 */
$host = env('DB_HOST', 'localhost');
$dbName = env('DB_NAME', 'masar_db');
$username = env('DB_USER', 'root');
$password = env('DB_PASSWORD', '') ?? '';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbName};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $exception) {
    error_log('Masar database connection failed: ' . $exception->getMessage());

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Database connection failed. Check DB_* in .env.\n");
        exit(1);
    }

    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');

    exit(
        '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<title>Masar</title></head><body style="font-family:sans-serif;'
        . 'padding:48px;text-align:center"><h1>Masar is temporarily unavailable</h1>'
        . '<p>Please try again in a few minutes.</p>'
        . '<p dir="rtl">مسار غير متاح مؤقتاً. يرجى المحاولة بعد قليل.</p>'
        . '</body></html>'
    );
}
