<?php

declare(strict_types=1);

/**
 * Raise an alert for administrators (FR14).
 *
 * Repeated alerts of the same type for the same user within the
 * de-duplication window are merged into the open one, so a student
 * retrying a broken upload ten times creates one alert, not ten.
 */
function raiseSystemAlert(
    PDO $pdo,
    string $alertType,
    string $severity,
    string $message,
    ?int $userId = null,
    int $dedupeMinutes = 60
): void {
    if (!in_array($severity, ['info', 'warning', 'critical'], true)) {
        $severity = 'warning';
    }

    try {
        $existing = $pdo->prepare(
            'SELECT id FROM system_alerts
             WHERE alert_type = :alert_type
               AND is_resolved = 0
               AND (user_id <=> :user_id)
               AND created_at > NOW() - INTERVAL ' . max(1, $dedupeMinutes) . ' MINUTE
             LIMIT 1'
        );

        $existing->execute([
            'alert_type' => $alertType,
            'user_id' => $userId,
        ]);

        if ($existing->fetchColumn() !== false) {
            return;
        }

        $pdo->prepare(
            'INSERT INTO system_alerts (alert_type, severity, message, user_id)
             VALUES (:alert_type, :severity, :message, :user_id)'
        )->execute([
            'alert_type' => mb_substr($alertType, 0, 50),
            'severity' => $severity,
            'message' => mb_substr($message, 0, 500),
            'user_id' => $userId,
        ]);
    } catch (Throwable $exception) {
        error_log('Masar alert failed: ' . $exception->getMessage());
    }
}
