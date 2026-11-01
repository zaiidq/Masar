<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../includes/academic-record-analyzer.php';
require_once __DIR__ . '/../includes/activity-logger.php';
require_once __DIR__ . '/../includes/system-alerts.php';

header('Content-Type: application/json; charset=UTF-8');

/*
 * The analysis continues even if the student leaves the page:
 * Gemini analysis can take more than a minute.
 */
ignore_user_abort(true);
set_time_limit(300);

function respond(int $statusCode, array $body): never
{
    http_response_code($statusCode);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SESSION['role'] ?? '') !== 'student') {
    respond(403, [
        'success' => false,
        'message' => t('record_api_unauthorized'),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, [
        'success' => false,
        'message' => t('record_api_post_only'),
    ]);
}

$submittedToken = $_POST['csrf_token'] ?? '';

if (
    !is_string($submittedToken)
    || empty($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $submittedToken)
) {
    respond(403, [
        'success' => false,
        'message' => t('record_api_invalid_token'),
    ]);
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$recordId = (int) ($_POST['record_id'] ?? 0);
$isReplan = ($_POST['replan'] ?? '') === '1';

$targetSemester = $_POST['target_semester'] ?? 'regular';

if (!in_array($targetSemester, ['regular', 'summer'], true)) {
    $targetSemester = 'regular';
}

/*
 * Release the PHP session lock before the long AI request so the
 * student can keep browsing Masar while the record is analysed.
 */
session_write_close();

if ($recordId <= 0) {
    respond(422, [
        'success' => false,
        'message' => t('record_api_invalid_record'),
    ]);
}

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
    respond(404, [
        'success' => false,
        'message' => t('record_api_not_found'),
    ]);
}

$pdfPath =
    dirname(__DIR__)
    . DIRECTORY_SEPARATOR
    . str_replace('/', DIRECTORY_SEPARATOR, $record['file_path']);

if (!is_file($pdfPath)) {
    if ($record['status'] !== 'analyzed') {
        markAnalysisFailed(
            $pdo,
            $recordId,
            'The uploaded academic record file could not be found.'
        );
    }

    respond(404, [
        'success' => false,
        'message' => t('record_api_file_missing'),
    ]);
}

/*
 * Atomic claim: only one request can move the record to processing.
 * A re-plan may claim an analysed record; a normal request may not.
 */
$previousStatus = claimRecordForAnalysis(
    $pdo,
    $recordId,
    $userId,
    $targetSemester,
    $isReplan
);

if ($previousStatus === null) {
    respond(409, [
        'success' => false,
        'message' => t('record_api_cannot_analyze'),
        'status' => $record['status'],
    ]);
}

logActivity(
    $pdo,
    'ANALYSIS_STARTED',
    $userId,
    sprintf('record=%d semester=%s%s', $recordId, $targetSemester, $isReplan ? ' replan' : '')
);

try {
    analyzeAcademicRecord(
        $pdo,
        $recordId,
        $userId,
        $pdfPath,
        $targetSemester
    );

    logActivity(
        $pdo,
        'ANALYSIS_SUCCESS',
        $userId,
        'record=' . $recordId
    );

    respond(200, [
        'success' => true,
        'status' => 'analyzed',
        'message' => t('record_api_success'),
    ]);
} catch (Throwable $exception) {
    markAnalysisFailed(
        $pdo,
        $recordId,
        $exception->getMessage(),
        $previousStatus
    );

    logActivity(
        $pdo,
        'ANALYSIS_FAILED',
        $userId,
        'record=' . $recordId . ': ' . $exception->getMessage()
    );

    $messageKey = analysisFailureMessageKey($exception);

    raiseSystemAlert(
        $pdo,
        $messageKey === 'record_error_ai_unavailable' || $messageKey === 'record_error_ai_busy'
            ? 'AI_SERVICE_ERROR'
            : 'ANALYSIS_FAILED',
        $messageKey === 'record_error_ai_unavailable' ? 'critical' : 'warning',
        'Record #' . $recordId . ': ' . $exception->getMessage(),
        $messageKey === 'record_error_ai_unavailable' || $messageKey === 'record_error_ai_busy'
            ? null
            : $userId
    );

    respond(500, [
        'success' => false,
        'status' => $previousStatus === 'analyzed' ? 'analyzed' : 'failed',
        'message' => t($messageKey),
    ]);
}
