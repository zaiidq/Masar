<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';

function logActivity(
    PDO $pdo,
    string $eventType,
    ?int $userId = null,
    ?string $details = null
): void {
    try {
        $ipAddress = getClientIpAddress();

        $userAgent = isset($_SERVER['HTTP_USER_AGENT'])
            ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255)
            : null;

        $details = $details !== null
            ? substr($details, 0, 500)
            : null;

        $stmt = $pdo->prepare(
            'INSERT INTO activity_logs
                (user_id, event_type, ip_address, user_agent, details)
             VALUES
                (:user_id, :event_type, :ip_address, :user_agent, :details)'
        );

        $stmt->execute([
            ':user_id' => $userId,
            ':event_type' => strtoupper(trim($eventType)),
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent,
            ':details' => $details,
        ]);
    } catch (Throwable $exception) {
        error_log(
            'Masar activity log failed: ' . $exception->getMessage()
        );
    }
}

function getClientIpAddress(): ?string
{
    $candidates = [];

    /*
     * Forwarded headers are set by the client and can be forged, so they
     * are only trusted when Masar runs behind a reverse proxy the owner
     * controls (TRUST_PROXY=1 in .env).
     */
    if (function_exists('env') && env('TRUST_PROXY', '0') === '1') {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            /*
             * Proxies append to this header, so the last entry is the
             * one written by our own proxy; earlier entries came from
             * the visitor and could be made up.
             */
            $forwarded = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
            $candidates[] = trim((string) end($forwarded));
        }

        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $candidates[] = trim((string) $_SERVER['HTTP_X_REAL_IP']);
        }
    }

    if (!empty($_SERVER['REMOTE_ADDR'])) {
        $candidates[] = trim((string) $_SERVER['REMOTE_ADDR']);
    }

    foreach ($candidates as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return null;
}

/**
 * Failed logins from this IP in the last 15 minutes.
 */
function recentFailedLogins(PDO $pdo, ?string $ipAddress): int
{
    if ($ipAddress === null) {
        return 0;
    }

    $statement = $pdo->prepare(
        "SELECT COUNT(*)
         FROM activity_logs
         WHERE ip_address = :ip
           AND event_type = 'LOGIN_FAILED'
           AND created_at > NOW() - INTERVAL 15 MINUTE"
    );
    $statement->execute(['ip' => $ipAddress]);

    return (int) $statement->fetchColumn();
}
