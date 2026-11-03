<?php

declare(strict_types=1);

/*
 * Lightweight status check used by the Academic Record page while an
 * analysis is running, so the page can refresh itself as soon as the
 * analysis finishes instead of reloading on a timer.
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/academic-record-analyzer.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if (($_SESSION['role'] ?? '') !== 'student') {
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}

$userId = (int) $_SESSION['user_id'];

/* Never hold the session lock while polling. */
session_write_close();

/* A worker that died mid-analysis must not keep the page waiting forever. */
expireStaleAnalyses($pdo, $userId);

$statement = $pdo->prepare(
    'SELECT id, status
     FROM academic_records
     WHERE user_id = :user_id
     ORDER BY created_at DESC, id DESC
     LIMIT 50'
);
$statement->execute(['user_id' => $userId]);

$records = [];
$processing = false;

foreach ($statement->fetchAll() as $row) {
    $records[(string) $row['id']] = $row['status'];

    if ($row['status'] === 'processing') {
        $processing = true;
    }
}

echo json_encode([
    'success' => true,
    'processing' => $processing,
    'records' => $records,
]);
