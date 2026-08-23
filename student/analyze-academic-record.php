<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/language.php';
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
        'message' => t('record_api_unauthorized'),
    ]);

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => t('record_api_post_only'),
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
        'message' => t('record_api_invalid_token'),
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
        'message' => t('record_api_invalid_record'),
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
        'message' => t('record_api_not_found'),
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
        'message' => t('record_api_cannot_analyze'),
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
        'message' => t('record_api_file_missing'),
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
        'message' => t('record_api_success'),
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
        'message' => t('record_error_analysis_failed'),
    ]);
}