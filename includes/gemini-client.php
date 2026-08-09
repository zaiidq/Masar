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
    $apiKey = env('GEMINI_API_KEY');

    if ($apiKey === null) {
        throw new GeminiException(
            'GEMINI_API_KEY is not configured.'
        );
    }

    $model = env('GEMINI_MODEL', 'gemini-2.5-flash');
    $timeout = (int) env('GEMINI_TIMEOUT', '120');

    $endpoint = sprintf(
        'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
        rawurlencode((string) $model)
    );

    $payload = [
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
    ];

    $encodedPayload = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if ($encodedPayload === false) {
        throw new GeminiException(
            'The request payload could not be encoded.'
        );
    }

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
    $curlError = curl_error($curl);

    curl_close($curl);

    if ($responseBody === false) {
        throw new GeminiException(
            'The AI service could not be reached: ' . $curlError
        );
    }

    if ($statusCode !== 200) {
        /* Surface the provider's message, but never the API key. */
        $decodedError = json_decode((string) $responseBody, true);

        $message = is_array($decodedError)
            ? ($decodedError['error']['message'] ?? 'Unknown error')
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

    $finishReason = $decoded['candidates'][0]['finishReason'] ?? null;

    if ($finishReason !== null && $finishReason !== 'STOP') {
        throw new GeminiException(
            "The AI response was incomplete (reason: {$finishReason})."
        );
    }

    /* A candidate may be split across several text parts. */
    $parts = $decoded['candidates'][0]['content']['parts'] ?? [];

    $text = '';

    foreach ($parts as $part) {
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
