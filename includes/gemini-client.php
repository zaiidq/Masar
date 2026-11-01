<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';

/**
 * Thrown when the AI provider cannot be reached or returns unusable output.
 */
class GeminiException extends RuntimeException
{
}

/**
 * Send one generateContent request and return the decoded API response.
 *
 * Retries rate-limit (429) and temporary server errors (5xx) with a short
 * backoff. Timeouts are not retried: a request that already used the full
 * timeout would push the page past PHP's execution limit.
 *
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function geminiGenerate(array $payload): array
{
    $apiKey = env('GEMINI_API_KEY');

    if ($apiKey === null) {
        throw new GeminiException(
            'GEMINI_API_KEY is not configured.'
        );
    }

    $model = env('GEMINI_MODEL', 'gemini-2.5-flash');
    $timeout = (int) env('GEMINI_TIMEOUT', '120');

    /* GEMINI_BASE_URL is only overridden for local testing. */
    $baseUrl = rtrim(
        (string) env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com'),
        '/'
    );

    $endpoint = sprintf(
        '%s/v1beta/models/%s:generateContent',
        $baseUrl,
        rawurlencode((string) $model)
    );

    $encodedPayload = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if ($encodedPayload === false) {
        throw new GeminiException(
            'The request payload could not be encoded.'
        );
    }

    $retryDelays = [2, 5];
    $attempt = 0;

    while (true) {
        $curl = curl_init($endpoint);

        if ($curl === false) {
            throw new GeminiException(
                'The HTTP client could not be initialised.'
            );
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_POSTFIELDS => $encodedPayload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $apiKey,
            ],
        ]);

        $responseBody = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlErrorNumber = curl_errno($curl);
        $curlError = curl_error($curl);

        curl_close($curl);

        $isTimeout = $curlErrorNumber === CURLE_OPERATION_TIMEDOUT;

        $isRetryable =
            !$isTimeout
            && (
                $responseBody === false
                || $statusCode === 429
                || $statusCode >= 500
            );

        if ($isRetryable && $attempt < count($retryDelays)) {
            sleep($retryDelays[$attempt]);
            $attempt++;
            continue;
        }

        break;
    }

    if ($responseBody === false) {
        throw new GeminiException(
            'The AI service could not be reached: ' . $curlError
        );
    }

    if ($statusCode !== 200) {
        /* Surface the provider's message, but never the API key. */
        $decodedError = json_decode((string) $responseBody, true);

        $message = is_array($decodedError)
            ? (string) ($decodedError['error']['message'] ?? 'Unknown error')
            : 'Unknown error';

        throw new GeminiException(
            "The AI service returned HTTP {$statusCode}: {$message}"
        );
    }

    $decoded = json_decode((string) $responseBody, true);

    if (!is_array($decoded)) {
        throw new GeminiException(
            'The AI service returned a malformed response.'
        );
    }

    return $decoded;
}

/**
 * Concatenate the text parts of the first candidate.
 *
 * @param  array<string, mixed>  $decoded
 */
function geminiCandidateText(array $decoded): string
{
    $finishReason = $decoded['candidates'][0]['finishReason'] ?? null;

    if ($finishReason !== null && $finishReason !== 'STOP') {
        throw new GeminiException(
            "The AI response was incomplete (reason: {$finishReason})."
        );
    }

    /* A candidate may be split across several text parts. */
    $parts = $decoded['candidates'][0]['content']['parts'] ?? [];

    $text = '';

    foreach ((array) $parts as $part) {
        if (isset($part['text']) && is_string($part['text'])) {
            $text .= $part['text'];
        }
    }

    $text = trim($text);

    if ($text === '') {
        throw new GeminiException(
            'The AI service returned an empty response.'
        );
    }

    return $text;
}

/**
 * Send a prompt to the Gemini API and return the decoded JSON response.
 *
 * The model is pinned through GEMINI_MODEL in .env rather than hard-coded,
 * so switching models never requires a code change.
 *
 * @param  array<string, mixed>  $responseSchema
 *         JSON schema the model must conform to. Passing a schema makes the
 *         provider enforce the shape instead of relying on prompt wording.
 *
 * @return array<string, mixed> The decoded model output.
 */
function callGeminiJson(
    string $systemInstruction,
    string $userPrompt,
    array $responseSchema
): array {
    $decoded = geminiGenerate([
        'systemInstruction' => [
            'parts' => [
                ['text' => $systemInstruction],
            ],
        ],
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $userPrompt],
                ],
            ],
        ],
        'generationConfig' => [
            /* Deterministic output matters more than variety here. */
            'temperature' => 0.1,
            'responseMimeType' => 'application/json',
            'responseSchema' => $responseSchema,
        ],
    ]);

    $text = geminiCandidateText($decoded);

    /* Strip markdown fences in case the schema was not honoured. */
    $text = (string) preg_replace(
        '/^```(?:json)?\s*|\s*```$/m',
        '',
        $text
    );

    $result = json_decode(trim($text), true);

    if (!is_array($result)) {
        throw new GeminiException(
            'The AI service did not return valid JSON.'
        );
    }

    return $result;
}

/**
 * Multi-turn text conversation (used by Ask Masar).
 *
 * @param  array<int, array{role: string, text: string}>  $turns
 *         Oldest first. Role is "user" or "model".
 */
function callGeminiChat(
    string $systemInstruction,
    array $turns,
    float $temperature = 0.4
): string {
    $contents = [];

    foreach ($turns as $turn) {
        $contents[] = [
            'role' => $turn['role'] === 'model' ? 'model' : 'user',
            'parts' => [
                ['text' => $turn['text']],
            ],
        ];
    }

    $decoded = geminiGenerate([
        'systemInstruction' => [
            'parts' => [
                ['text' => $systemInstruction],
            ],
        ],
        'contents' => $contents,
        'generationConfig' => [
            'temperature' => $temperature,
            'maxOutputTokens' => 8192,
        ],
    ]);

    return geminiCandidateText($decoded);
}
