<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/academic-record-analyzer.php';

header('Content-Type: application/json; charset=UTF-8');

/*
 * The analysis may continue even if the student leaves the page.
 * This is useful for the current Masar prototype where Gemini
 * analysis can take more than one minute.
 */
ignore_user_abort(true);
set_time_limit(180);

if (($_SESSION['role'] ?? '') !== 'student') {
    http_response_code(403);

    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized request.',
    ]);

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Only POST requests are allowed.',
    ]);

    exit;
}

$submittedToken = $_POST['csrf_token'] ?? '';

if (
    !is_string($submittedToken)
    || empty($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $submittedToken)
) {
    http_response_code(403);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid request token.',
    ]);

    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$recordId = (int) ($_POST['record_id'] ?? 0);

/*
 * Release the PHP session lock before the long AI request.
 * This allows the student to continue browsing Masar
 * while the academic record is being analyzed.
 */
session_write_close();

if ($recordId <= 0) {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid academic record.',
    ]);

    exit;
}

/*
 * Make sure the record belongs to the logged-in student.
 */
$recordStatement = $pdo->prepare(
    'SELECT id, file_path, status
     FROM academic_records
     WHERE id = :id
       AND user_id = :user_id
     LIMIT 1'
);

$recordStatement->execute([
    'id' => $recordId,
    'user_id' => $userId,
]);

$record = $recordStatement->fetch();

if (!$record) {
    http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' => 'Academic record not found.',
    ]);

    exit;
}

/*
 * Only a newly uploaded record or a previously failed record
 * may start a new analysis.
 */
if (!in_array($record['status'], ['uploaded', 'failed'], true)) {
    http_response_code(409);

    echo json_encode([
        'success' => false,
        'message' => 'This academic record cannot be analyzed right now.',
        'status' => $record['status'],
    ]);

    exit;
}

$pdfPath =
    dirname(__DIR__)
    . DIRECTORY_SEPARATOR
    . str_replace(
        '/',
        DIRECTORY_SEPARATOR,
        $record['file_path']
    );

if (!is_file($pdfPath)) {
    markAnalysisFailed(
        $pdo,
        $recordId,
        'The uploaded academic record file could not be found.'
    );

    http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' => 'The uploaded academic record file could not be found.',
    ]);

    exit;
}

try {
    /*
     * Set Processing before the long Gemini request starts so the UI
     * can immediately reflect that analysis is running.
     */
    $processingStatement = $pdo->prepare(
        'UPDATE academic_records
         SET status = :status,
             analysis_error = NULL,
             updated_at = CURRENT_TIMESTAMP
         WHERE id = :id
           AND user_id = :user_id'
    );

    $processingStatement->execute([
        'status' => 'processing',
        'id' => $recordId,
        'user_id' => $userId,
    ]);

    analyzeAcademicRecord(
        $pdo,
        $recordId,
        $userId,
        $pdfPath
    );

    echo json_encode([
        'success' => true,
        'status' => 'analyzed',
        'message' => 'Academic record analyzed successfully.',
    ]);
} catch (Throwable $exception) {
    markAnalysisFailed(
        $pdo,
        $recordId,
        $exception->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'status' => 'failed',
        'message' => 'Academic record analysis failed.',
    ]);
}