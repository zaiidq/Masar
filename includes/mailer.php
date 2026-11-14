<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';

/**
 * Outgoing email (used for password reset links).
 *
 * A small SMTP client, so Masar needs no extra package and no local mail
 * server. Configure it in .env:
 *
 *   SMTP_HOST=smtp.gmail.com
 *   SMTP_PORT=587            (587 = STARTTLS, 465 = TLS from the start)
 *   SMTP_USER=you@example.com
 *   SMTP_PASSWORD=app-password
 *   MAIL_FROM=you@example.com
 *   MAIL_FROM_NAME=Masar
 *
 * When SMTP_HOST is empty, email is switched off and administrators hand
 * out reset links from the Users page instead.
 */

class MailException extends RuntimeException
{
}

function mailIsConfigured(): bool
{
    return env('SMTP_HOST') !== null && env('MAIL_FROM', env('SMTP_USER')) !== null;
}

/**
 * Send one plain-text email.
 *
 * @throws MailException when email is not configured or the server
 *                       refuses the message.
 */
function sendMail(string $toAddress, string $subject, string $body): void
{
    if (!mailIsConfigured()) {
        throw new MailException('Email is not configured (SMTP_HOST is empty).');
    }

    if (
        !filter_var($toAddress, FILTER_VALIDATE_EMAIL)
        || preg_match('/[\r\n]/', $toAddress . $subject)
    ) {
        throw new MailException('Invalid recipient or subject.');
    }

    $host = (string) env('SMTP_HOST');
    $port = (int) env('SMTP_PORT', '587');
    $user = env('SMTP_USER');
    $password = env('SMTP_PASSWORD');
    $from = (string) env('MAIL_FROM', $user);
    $fromName = (string) env('MAIL_FROM_NAME', 'Masar');

    /* SMTP_SECURE: tls (STARTTLS), ssl (TLS from the start) or none. */
    $secure = strtolower((string) env('SMTP_SECURE', $port === 465 ? 'ssl' : ($port === 25 ? 'none' : 'tls')));

    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        throw new MailException('MAIL_FROM is not a valid email address.');
    }

    $socket = @stream_socket_client(
        ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port,
        $errorNumber,
        $errorMessage,
        15
    );

    if ($socket === false) {
        throw new MailException("Could not connect to the mail server: {$errorMessage}");
    }

    stream_set_timeout($socket, 20);

    /* Read one (possibly multi-line) reply and require an expected code. */
    $expect = static function (array $codes) use ($socket): string {
        $reply = '';

        while (($line = fgets($socket, 1024)) !== false) {
            $reply .= $line;

            /* "250-..." continues, "250 ..." is the last line. */
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }

        $code = (int) substr($reply, 0, 3);

        if (!in_array($code, $codes, true)) {
            throw new MailException('Mail server replied: ' . trim($reply !== '' ? $reply : 'no response'));
        }

        return $reply;
    };

    $send = static function (string $command) use ($socket): void {
        fwrite($socket, $command . "\r\n");
    };

    $clientName = preg_replace('/[^A-Za-z0-9.\-]/', '', (string) ($_SERVER['SERVER_NAME'] ?? '')) ?: 'localhost';

    try {
        $expect([220]);

        $send('EHLO ' . $clientName);
        $expect([250]);

        if ($secure === 'tls') {
            $send('STARTTLS');
            $expect([220]);

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new MailException('Could not start an encrypted connection.');
            }

            $send('EHLO ' . $clientName);
            $expect([250]);
        }

        if ($user !== null && $password !== null) {
            $send('AUTH LOGIN');
            $expect([334]);
            $send(base64_encode($user));
            $expect([334]);
            $send(base64_encode($password));
            $expect([235]);
        }

        $send('MAIL FROM:<' . $from . '>');
        $expect([250]);
        $send('RCPT TO:<' . $toAddress . '>');
        $expect([250, 251]);
        $send('DATA');
        $expect([354]);

        $encodeHeader = static fn (string $text): string =>
            preg_match('/[^\x20-\x7E]/', $text)
                ? '=?UTF-8?B?' . base64_encode($text) . '?='
                : $text;

        $headers = [
            'Date: ' . date('r'),
            'From: ' . $encodeHeader(str_replace(['"', "\r", "\n"], '', $fromName)) . ' <' . $from . '>',
            'To: <' . $toAddress . '>',
            'Subject: ' . $encodeHeader($subject),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . substr(strrchr($from, '@'), 1) . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];

        $send(
            implode("\r\n", $headers)
            . "\r\n\r\n"
            . rtrim(chunk_split(base64_encode($body), 76, "\r\n"))
            . "\r\n."
        );
        $expect([250]);

        $send('QUIT');
    } finally {
        fclose($socket);
    }
}
