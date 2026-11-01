<?php

declare(strict_types=1);

/**
 * Ask Masar — the academic planning assistant (FR11, FR12, UC-06).
 *
 * Every answer is grounded in the student's current analysed record:
 * the summary, every course with its state and prerequisites, and the
 * validated recommendations. No personal identifiers are sent.
 */

require_once __DIR__ . '/gemini-client.php';

const ASK_MASAR_MAX_MESSAGE_LENGTH = 1000;
const ASK_MASAR_HISTORY_TURNS = 20;

function askMasarSystemInstruction(string $interfaceLanguage): string
{
    $languageName = $interfaceLanguage === 'ar' ? 'Arabic' : 'English';

    return <<<PROMPT
You are "Ask Masar", the academic planning assistant inside Masar, an
advising platform for students of Middle East University (MEU) in Jordan.

SCOPE
- Help only with academic planning: the student's courses, prerequisites,
  progress toward graduation, next-semester choices, the recommendations
  Masar produced, and general study-plan concepts (credit hours, GPA,
  compulsory vs optional requirements).
- If a question is outside this scope (general knowledge, coding help,
  personal matters, other universities), politely say you can only help
  with academic planning, and suggest a related question you can answer.

GROUNDING
- The STUDENT CONTEXT below comes from the student's own uploaded academic
  record and is the only source of truth about them. Use course codes and
  names exactly as given.
- Never invent courses, prerequisites, grades, credit hours, schedules,
  instructors, or university rules that are not in the context.
- Masar's recommendations were validated by deterministic checks
  (prerequisites completed, not already passed, credit-hour limit). When
  asked why a course was recommended, explain using its reason, its
  requirement type, and its prerequisites from the context.
- If the context does not contain the answer (e.g. course timetables,
  seat availability, registration deadlines), say so and advise checking
  Edugate or the academic advisor.
- You support official advising; you do not replace it. For decisions with
  consequences (dropping courses, exceptions, graduation audits), remind
  the student to confirm with their academic advisor.

STYLE
- Reply in the language the student writes in. If unclear, use
  {$languageName}. Keep course codes and course names as they appear.
- Be concise and concrete: short paragraphs or short bullet lists.
  Plain text with optional **bold** and "- " bullets only. No tables,
  no headings, no code blocks.
PROMPT;
}

/**
 * Plain-text summary of the student's current analysed record.
 */
function buildAskMasarContext(PDO $pdo, int $userId): string
{
    $recordStatement = $pdo->prepare(
        "SELECT *
         FROM academic_records
         WHERE user_id = :user_id
           AND is_current = 1
           AND status = 'analyzed'
         LIMIT 1"
    );
    $recordStatement->execute(['user_id' => $userId]);
    $record = $recordStatement->fetch();

    if (!$record) {
        return "STUDENT CONTEXT\n"
            . "The student has not uploaded an analysed academic record yet. "
            . "You cannot discuss their specific courses. Answer general "
            . "planning questions, and suggest uploading the academic record "
            . "PDF from the Academic Record page for personal advice.\n";
    }

    $value = static fn ($v): string => $v === null || $v === '' ? 'not available' : (string) $v;

    $lines = [
        'STUDENT CONTEXT (from the uploaded academic record)',
        'Faculty: ' . $value($record['faculty_name']),
        'Major: ' . $value($record['major_name']),
        'Degree: ' . $value($record['degree_name']),
        'Level: ' . $value($record['student_level']),
        'Last academic semester on record: ' . $value($record['academic_semester']),
        'GPA: ' . $value($record['gpa']),
        'Plan hours: ' . $value($record['plan_hours'])
            . ' | Earned hours: ' . $value($record['earned_hours'])
            . ' | Official remaining hours: ' . $value($record['remaining_hours']),
        sprintf(
            'Next semester being planned: %s, credit-hour limit %s%s',
            $record['target_semester'] === 'summer' ? 'summer' : 'regular',
            $value($record['credit_limit']),
            !empty($record['is_graduating']) ? ' (graduating student)' : ''
        ),
        '',
        'COURSES (code | name | requirement | credit hours | state | mark | prerequisites)',
    ];

    $courseStatement = $pdo->prepare(
        'SELECT course_code, course_name, requirement_type, credit_hours,
                completion_state, mark, prerequisite_codes
         FROM record_courses
         WHERE record_id = :record_id
         ORDER BY requirement_type, id'
    );
    $courseStatement->execute(['record_id' => $record['id']]);

    foreach ($courseStatement->fetchAll() as $course) {
        $lines[] = implode(' | ', [
            $course['course_code'],
            $course['course_name'],
            $course['requirement_type'],
            (int) $course['credit_hours'],
            $course['completion_state'],
            $course['mark'] ?? '-',
            $course['prerequisite_codes'] ?: 'none',
        ]);
    }

    $lines[] = '';
    $lines[] = 'MASAR RECOMMENDATIONS FOR NEXT SEMESTER (priority | code | name | hours | reason)';

    $recommendationStatement = $pdo->prepare(
        'SELECT priority, course_code, course_name, credit_hours, reason
         FROM record_recommendations
         WHERE record_id = :record_id
           AND is_accepted = 1
         ORDER BY priority'
    );
    $recommendationStatement->execute(['record_id' => $record['id']]);
    $recommendations = $recommendationStatement->fetchAll();

    if ($recommendations === []) {
        $lines[] = 'No courses were recommended.';
    }

    foreach ($recommendations as $item) {
        $lines[] = implode(' | ', [
            (int) $item['priority'],
            $item['course_code'],
            $item['course_name'],
            (int) $item['credit_hours'],
            $item['reason'] ?? '',
        ]);
    }

    return implode("\n", $lines) . "\n";
}

/**
 * Number of questions the student asked today.
 */
function askMasarQuestionsToday(PDO $pdo, int $userId): int
{
    $statement = $pdo->prepare(
        "SELECT COUNT(*)
         FROM chat_messages m
         JOIN chat_sessions s ON s.id = m.session_id
         WHERE s.user_id = :user_id
           AND m.role = 'user'
           AND m.created_at >= CURDATE()"
    );
    $statement->execute(['user_id' => $userId]);

    return (int) $statement->fetchColumn();
}

function askMasarDailyLimit(): int
{
    return max(1, (int) env('CHAT_DAILY_LIMIT', '30'));
}

/**
 * The session if it belongs to the user, otherwise null.
 *
 * @return array<string, mixed>|null
 */
function findChatSession(PDO $pdo, int $sessionId, int $userId): ?array
{
    $statement = $pdo->prepare(
        'SELECT id, title, created_at, updated_at
         FROM chat_sessions
         WHERE id = :id AND user_id = :user_id'
    );
    $statement->execute(['id' => $sessionId, 'user_id' => $userId]);
    $session = $statement->fetch();

    return $session ?: null;
}

/**
 * @return array<int, array<string, mixed>>
 */
function listChatSessions(PDO $pdo, int $userId, int $limit = 50): array
{
    $statement = $pdo->prepare(
        'SELECT id, title, updated_at
         FROM chat_sessions
         WHERE user_id = :user_id
         ORDER BY updated_at DESC, id DESC
         LIMIT ' . max(1, $limit)
    );
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

/**
 * @return array<int, array<string, mixed>>
 */
function listChatMessages(PDO $pdo, int $sessionId): array
{
    $statement = $pdo->prepare(
        'SELECT id, role, content, created_at
         FROM chat_messages
         WHERE session_id = :session_id
         ORDER BY id'
    );
    $statement->execute(['session_id' => $sessionId]);

    return $statement->fetchAll();
}

/**
 * Ask a question and store the exchange.
 *
 * The question and the answer are saved together only after the AI
 * answered, so a failed request leaves no half conversation behind.
 *
 * @return array{session_id: int, title: string, reply: string}
 *
 * @throws GeminiException
 */
function askMasar(
    PDO $pdo,
    int $userId,
    ?int $sessionId,
    string $question,
    string $interfaceLanguage
): array {
    $history = [];

    if ($sessionId !== null) {
        $messages = array_slice(
            listChatMessages($pdo, $sessionId),
            -ASK_MASAR_HISTORY_TURNS
        );

        foreach ($messages as $message) {
            $history[] = [
                'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                'text' => $message['content'],
            ];
        }
    }

    $context = buildAskMasarContext($pdo, $userId);

    /*
     * The context travels with the conversation as its first turn, so it
     * reflects the latest analysis even in an older conversation.
     */
    $turns = array_merge(
        [
            ['role' => 'user', 'text' => $context],
            ['role' => 'model', 'text' => 'Understood. I will answer using only this context.'],
        ],
        $history,
        [['role' => 'user', 'text' => $question]]
    );

    $reply = callGeminiChat(
        askMasarSystemInstruction($interfaceLanguage),
        $turns
    );

    $pdo->beginTransaction();

    try {
        if ($sessionId === null) {
            $title = mb_substr(
                trim((string) preg_replace('/\s+/u', ' ', $question)),
                0,
                80
            );

            $pdo->prepare(
                'INSERT INTO chat_sessions (user_id, title) VALUES (:user_id, :title)'
            )->execute(['user_id' => $userId, 'title' => $title]);

            $sessionId = (int) $pdo->lastInsertId();
        } else {
            $pdo->prepare(
                'UPDATE chat_sessions SET updated_at = CURRENT_TIMESTAMP WHERE id = :id'
            )->execute(['id' => $sessionId]);

            $title = (string) (findChatSession($pdo, $sessionId, $userId)['title'] ?? '');
        }

        $insert = $pdo->prepare(
            'INSERT INTO chat_messages (session_id, role, content)
             VALUES (:session_id, :role, :content)'
        );

        $insert->execute([
            'session_id' => $sessionId,
            'role' => 'user',
            'content' => $question,
        ]);

        $insert->execute([
            'session_id' => $sessionId,
            'role' => 'assistant',
            'content' => $reply,
        ]);

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }

    return [
        'session_id' => $sessionId,
        'title' => $title,
        'reply' => $reply,
    ];
}

/**
 * Safe rendering of the assistant's limited Markdown: **bold** and
 * "- " / "* " / "1. " lists. Everything is escaped first.
 */
function renderChatText(string $text): string
{
    $escape = static fn (string $value): string =>
        htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

    $inline = static function (string $line) use ($escape): string {
        $html = $escape($line);

        return (string) preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $html);
    };

    $html = '';
    $listType = null;
    $paragraph = [];

    $flushParagraph = static function () use (&$paragraph, &$html, $inline): void {
        if ($paragraph !== []) {
            $html .= '<p dir="auto">' . implode('<br>', array_map($inline, $paragraph)) . '</p>';
            $paragraph = [];
        }
    };

    $closeList = static function () use (&$listType, &$html): void {
        if ($listType !== null) {
            $html .= "</{$listType}>";
            $listType = null;
        }
    };

    foreach (preg_split('/\R/u', trim($text)) ?: [] as $line) {
        $trimmed = trim($line);

        if ($trimmed === '') {
            $flushParagraph();
            $closeList();
            continue;
        }

        if (preg_match('/^(?:[-*•])\s+(.*)$/u', $trimmed, $match)) {
            $flushParagraph();

            if ($listType !== 'ul') {
                $closeList();
                $html .= '<ul dir="auto">';
                $listType = 'ul';
            }

            $html .= '<li>' . $inline($match[1]) . '</li>';
            continue;
        }

        if (preg_match('/^\d+[.)]\s+(.*)$/u', $trimmed, $match)) {
            $flushParagraph();

            if ($listType !== 'ol') {
                $closeList();
                $html .= '<ol dir="auto">';
                $listType = 'ol';
            }

            $html .= '<li>' . $inline($match[1]) . '</li>';
            continue;
        }

        $closeList();
        $paragraph[] = preg_replace('/^#{1,6}\s*/u', '', $trimmed) ?? $trimmed;
    }

    $flushParagraph();
    $closeList();

    return $html;
}
