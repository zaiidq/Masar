<?php

declare(strict_types=1);

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

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwarded = explode(
            ',',
            (string) $_SERVER['HTTP_X_FORWARDED_FOR']
        );

        foreach ($forwarded as $ip) {
            $candidates[] = trim($ip);
        }
    }

    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $candidates[] = trim(
            (string) $_SERVER['HTTP_X_REAL_IP']
        );
    }

    if (!empty($_SERVER['REMOTE_ADDR'])) {
        $candidates[] = trim(
            (string) $_SERVER['REMOTE_ADDR']
        );
    }

    foreach ($candidates as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return null;
}