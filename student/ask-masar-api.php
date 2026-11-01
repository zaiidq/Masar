<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../includes/ask-masar.php';
require_once __DIR__ . '/../includes/activity-logger.php';
require_once __DIR__ . '/../includes/system-alerts.php';

header('Content-Type: application/json; charset=UTF-8');
set_time_limit(180);

function chatRespond(int $statusCode, array $body): never
{
    http_response_code($statusCode);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SESSION['role'] ?? '') !== 'student') {
    chatRespond(403, ['success' => false, 'message' => t('record_api_unauthorized')]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    chatRespond(405, ['success' => false, 'message' => t('record_api_post_only')]);
}

$submittedToken = $_POST['csrf_token'] ?? '';

if (
    !is_string($submittedToken)
    || empty($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $submittedToken)
) {
    chatRespond(403, ['success' => false, 'message' => t('record_api_invalid_token')]);
}

$userId = (int) $_SESSION['user_id'];
$interfaceLanguage = currentLanguage();

/* Release the session lock before the AI call. */
session_write_close();

$action = (string) ($_POST['action'] ?? 'send');
$sessionId = (int) ($_POST['session_id'] ?? 0);

if ($sessionId > 0 && findChatSession($pdo, $sessionId, $userId) === null) {
    chatRespond(404, ['success' => false, 'message' => t('chat_error_not_found')]);
}

if ($action === 'delete_session') {
    if ($sessionId <= 0) {
        chatRespond(422, ['success' => false, 'message' => t('chat_error_not_found')]);
    }

    $pdo->prepare('DELETE FROM chat_sessions WHERE id = :id AND user_id = :user_id')
        ->execute(['id' => $sessionId, 'user_id' => $userId]);

    chatRespond(200, ['success' => true]);
}

if ($action !== 'send') {
    chatRespond(400, ['success' => false, 'message' => t('chat_error_generic')]);
}

$question = trim((string) ($_POST['message'] ?? ''));

/* UC-06 alternative flow 1: empty question. */
if ($question === '') {
    chatRespond(422, ['success' => false, 'message' => t('chat_error_empty')]);
}

if (mb_strlen($question) > ASK_MASAR_MAX_MESSAGE_LENGTH) {
    chatRespond(422, [
        'success' => false,
        'message' => sprintf(t('chat_error_too_long'), ASK_MASAR_MAX_MESSAGE_LENGTH),
    ]);
}

if (askMasarQuestionsToday($pdo, $userId) >= askMasarDailyLimit()) {
    chatRespond(429, [
        'success' => false,
        'message' => sprintf(t('chat_error_daily_limit'), askMasarDailyLimit()),
    ]);
}

try {
    $result = askMasar(
        $pdo,
        $userId,
        $sessionId > 0 ? $sessionId : null,
        $question,
        $interfaceLanguage
    );
} catch (Throwable $exception) {
    error_log('Ask Masar failed: ' . $exception->getMessage());

    $isConfiguration = str_contains($exception->getMessage(), 'not configured');

    raiseSystemAlert(
        $pdo,
        'AI_SERVICE_ERROR',
        $isConfiguration ? 'critical' : 'warning',
        'Ask Masar: ' . $exception->getMessage()
    );

    /* UC-06 alternative flow 2: response unavailable. */
    chatRespond(503, ['success' => false, 'message' => t('chat_error_unavailable')]);
}

if ($sessionId <= 0) {
    logActivity($pdo, 'CHAT_SESSION_STARTED', $userId, 'session=' . $result['session_id']);
}

chatRespond(200, [
    'success' => true,
    'session_id' => $result['session_id'],
    'title' => $result['title'],
    'reply_html' => renderChatText($result['reply']),
    'remaining_today' => max(0, askMasarDailyLimit() - askMasarQuestionsToday($pdo, $userId)),
]);
