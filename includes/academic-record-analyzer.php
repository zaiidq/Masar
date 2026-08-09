<?php

declare(strict_types=1);

require_once __DIR__ . '/academic-record-parser.php';
require_once __DIR__ . '/gemini-client.php';

/**
 * Bump this whenever the prompt or schema below changes.
 * Stored per record so past analyses stay explainable.
 */
const MASAR_PROMPT_VERSION = 'v1';

/**
 * Instructions given to the model on every analysis.
 */
function academicRecordSystemInstruction(): string
{
    return <<<'PROMPT'
You are an academic record analyser for Middle East University (MEU).

You receive the text of an MEU Academic Record that has been rebuilt from
the PDF, one table row per line, with columns separated by " | ".

Extract the data exactly as written. Never invent a course, a code, a mark
or an hour count that does not appear in the input.

Reading the course tables:
- Each course row is: course code, course name, prerequisite codes,
  credit hours, mark, result, registration status, semester, and an
  optional "X" marking current-semester registration.
- Course codes are 7 digits. Prerequisites appear in parentheses and are
  separated by "&".
- A row with no mark and no status is a course the student has not taken.
- "Exempted" means the requirement is satisfied without a mark.
- A course listed under "Incomplete Courses" is not completed.
- Section headers such as "Major Requirement Compulsory : ( 46 ) Hour"
  define the requirement type for every course row beneath them, until
  the next header.

Assign completion_state as follows:
- "completed"   : result is Pass or Exempted.
- "in_progress" : marked X for the current semester and not yet passed.
- "failed"      : result is Fail, or the course appears as incomplete.
- "remaining"   : everything else.

For recommendations, choose courses for the NEXT semester only:
- The course must be "remaining" or "failed".
- Every prerequisite of the course must be "completed".
- Prefer courses that unlock the most later courses, courses the student
  failed and should retake, and courses required for graduation.
- Suggest between 4 and 6 courses, not exceeding 18 credit hours total.
- Give each one a short, factual reason in English referring to the
  student's actual record.

Return only JSON matching the provided schema.
PROMPT;
}

/**
 * JSON schema the model output must conform to.
 *
 * @return array<string, mixed>
 */
function academicRecordResponseSchema(): array
{
    return [
        'type' => 'OBJECT',
        'required' => ['summary', 'courses', 'recommended_courses'],
        'properties' => [
            'summary' => [
                'type' => 'OBJECT',
                'properties' => [
                    'academic_semester' => ['type' => 'STRING'],
                    'faculty_name' => ['type' => 'STRING'],
                    'major_name' => ['type' => 'STRING'],
                    'degree_name' => ['type' => 'STRING'],
                    'student_level' => ['type' => 'STRING'],
                    'academic_grade' => ['type' => 'STRING'],
                    'gpa' => ['type' => 'NUMBER'],
                    'plan_hours' => ['type' => 'INTEGER'],
                    'graded_hours' => ['type' => 'INTEGER'],
                    'earned_hours' => ['type' => 'INTEGER'],
                    'attempted_hours' => ['type' => 'INTEGER'],
                    'remaining_hours' => ['type' => 'INTEGER'],
                ],
            ],

            'courses' => [
                'type' => 'ARRAY',
                'items' => [
                    'type' => 'OBJECT',
                    'required' => [
                        'course_code',
                        'course_name',
                        'credit_hours',
                        'completion_state',
                    ],
                    'properties' => [
                        'requirement_type' => ['type' => 'STRING'],
                        'course_code' => ['type' => 'STRING'],
                        'course_name' => ['type' => 'STRING'],
                        'prerequisite_codes' => [
                            'type' => 'ARRAY',
                            'items' => ['type' => 'STRING'],
                        ],
                        'credit_hours' => ['type' => 'INTEGER'],
                        'mark' => ['type' => 'STRING'],
                        'result' => [
                            'type' => 'STRING',
                            'enum' => [
                                'pass',
                                'fail',
                                'exempted',
                                'incomplete',
                                'none',
                            ],
                        ],
                        'registration_status' => ['type' => 'STRING'],
                        'semester_code' => ['type' => 'STRING'],
                        'is_current_semester' => ['type' => 'BOOLEAN'],
                        'completion_state' => [
                            'type' => 'STRING',
                            'enum' => [
                                'completed',
                                'in_progress',
                                'failed',
                                'remaining',
                            ],
                        ],
                    ],
                ],
            ],

            'recommended_courses' => [
                'type' => 'ARRAY',
                'items' => [
                    'type' => 'OBJECT',
                    'required' => ['course_code', 'reason'],
                    'properties' => [
                        'course_code' => ['type' => 'STRING'],
                        'reason' => ['type' => 'STRING'],
                    ],
                ],
            ],
        ],
    ];
}

/**
 * Deterministic checks applied to the model's suggestions.
 *
 * This is not a recommendation engine. It only rejects suggestions that
 * contradict the record, so a hallucinated course can never reach the
 * student as though it were verified advice.
 *
 * @param  array<int, array<string, mixed>>  $recommendations
 * @param  array<string, array<string, mixed>>  $coursesByCode
 * @return array<int, array<string, mixed>>
 */
function validateRecommendations(
    array $recommendations,
    array $coursesByCode
): array {
    $validated = [];
    $priority = 0;

    foreach ($recommendations as $recommendation) {
        $code = trim((string) ($recommendation['course_code'] ?? ''));

        if ($code === '') {
            continue;
        }

        $priority++;

        $rejection = null;

        /* Rule 1: the course must exist in this student's record. */
        if (!isset($coursesByCode[$code])) {
            $rejection = 'Course is not part of the uploaded academic record.';
        }

        $course = $coursesByCode[$code] ?? null;

        /* Rule 2: the student must not have already completed it. */
        if ($rejection === null && $course !== null) {
            if ($course['completion_state'] === 'completed') {
                $rejection = 'Course has already been completed.';
            } elseif ($course['completion_state'] === 'in_progress') {
                $rejection = 'Course is already registered this semester.';
            }
        }

        /* Rule 3: every prerequisite must be completed. */
        if ($rejection === null && $course !== null) {
            $unmet = [];

            foreach ($course['prerequisite_codes'] as $prerequisiteCode) {
                $prerequisite = $coursesByCode[$prerequisiteCode] ?? null;

                if (
                    $prerequisite === null
                    || $prerequisite['completion_state'] !== 'completed'
                ) {
                    $unmet[] = $prerequisiteCode;
                }
            }

            if ($unmet !== []) {
                $rejection = 'Unmet prerequisite: ' . implode(', ', $unmet);
            }
        }

        $validated[] = [
            'course_code' => $code,
            'course_name' => $course['course_name'] ?? $code,
            'credit_hours' => (int) ($course['credit_hours'] ?? 0),
            'priority' => $priority,
            'reason' => mb_substr(
                trim((string) ($recommendation['reason'] ?? '')),
                0,
                500
            ),
            'is_accepted' => $rejection === null ? 1 : 0,
            'rejection_reason' => $rejection,
        ];
    }

    return $validated;
}

/**
 * Normalise one course entry returned by the model.
 *
 * @param  array<string, mixed>  $raw
 * @return array<string, mixed>|null
 */
function normaliseCourse(array $raw): ?array
{
    $code = trim((string) ($raw['course_code'] ?? ''));
    $name = trim((string) ($raw['course_name'] ?? ''));

    if ($code === '' || $name === '') {
        return null;
    }

    $prerequisites = [];

    foreach ((array) ($raw['prerequisite_codes'] ?? []) as $prerequisite) {
        $prerequisite = trim((string) $prerequisite);

        if ($prerequisite !== '') {
            $prerequisites[] = $prerequisite;
        }
    }

    $allowedStates = ['completed', 'in_progress', 'failed', 'remaining'];
    $state = (string) ($raw['completion_state'] ?? 'remaining');

    if (!in_array($state, $allowedStates, true)) {
        $state = 'remaining';
    }

    $allowedResults = ['pass', 'fail', 'exempted', 'incomplete', 'none'];
    $result = strtolower((string) ($raw['result'] ?? 'none'));

    if (!in_array($result, $allowedResults, true)) {
        $result = 'none';
    }

    return [
        'requirement_type' => mb_substr(
            trim((string) ($raw['requirement_type'] ?? 'Unspecified')),
            0,
            120
        ),
        'course_code' => mb_substr($code, 0, 20),
        'course_name' => mb_substr($name, 0, 255),
        'prerequisite_codes' => $prerequisites,
        'credit_hours' => max(0, min(255, (int) ($raw['credit_hours'] ?? 0))),
        'mark' => mb_substr(trim((string) ($raw['mark'] ?? '')), 0, 5) ?: null,
        'result' => $result,
        'registration_status' => mb_substr(
            trim((string) ($raw['registration_status'] ?? '')),
            0,
            30
        ) ?: null,
        'semester_code' => mb_substr(
            trim((string) ($raw['semester_code'] ?? '')),
            0,
            10
        ) ?: null,
        'is_current_semester' => !empty($raw['is_current_semester']) ? 1 : 0,
        'completion_state' => $state,
    ];
}

/**
 * Run the full analysis pipeline for one uploaded record.
 *
 * Extract rows -> redact -> ask the model -> validate -> store.
 * Marks the record as the student's current one on success.
 *
 * @throws GeminiException|RuntimeException
 */
function analyzeAcademicRecord(
    PDO $pdo,
    int $recordId,
    int $userId,
    string $pdfPath
): void {
    $pdo->prepare(
        'UPDATE academic_records
         SET status = "processing",
             analysis_error = NULL
         WHERE id = :id'
    )->execute(['id' => $recordId]);

    $rows = extractAcademicRecordRows($pdfPath);
    $redacted = redactAcademicRecordText($rows);

    $model = env('GEMINI_MODEL', 'gemini-2.5-flash');

    $result = callGeminiJson(
        academicRecordSystemInstruction(),
        "MEU Academic Record:\n\n" . $redacted,
        academicRecordResponseSchema()
    );

    $summary = (array) ($result['summary'] ?? []);
    $rawCourses = (array) ($result['courses'] ?? []);

    if ($rawCourses === []) {
        throw new RuntimeException(
            'The analysis returned no courses.'
        );
    }

    $courses = [];
    $coursesByCode = [];

    foreach ($rawCourses as $rawCourse) {
        if (!is_array($rawCourse)) {
            continue;
        }

        $course = normaliseCourse($rawCourse);

        if ($course === null) {
            continue;
        }

        /* Duplicate codes would break the unique key; keep the first. */
        if (isset($coursesByCode[$course['course_code']])) {
            continue;
        }

        $courses[] = $course;
        $coursesByCode[$course['course_code']] = $course;
    }

    $recommendations = validateRecommendations(
        (array) ($result['recommended_courses'] ?? []),
        $coursesByCode
    );

    $pdo->beginTransaction();

    try {
        /* Replace any previous analysis of this same record. */
        $pdo->prepare(
            'DELETE FROM record_courses WHERE record_id = :id'
        )->execute(['id' => $recordId]);

        $pdo->prepare(
            'DELETE FROM record_recommendations WHERE record_id = :id'
        )->execute(['id' => $recordId]);

        $courseStatement = $pdo->prepare(
            'INSERT INTO record_courses (
                record_id, requirement_type, course_code, course_name,
                prerequisite_codes, credit_hours, mark, result,
                registration_status, semester_code, is_current_semester,
                completion_state
            ) VALUES (
                :record_id, :requirement_type, :course_code, :course_name,
                :prerequisite_codes, :credit_hours, :mark, :result,
                :registration_status, :semester_code, :is_current_semester,
                :completion_state
            )'
        );

        foreach ($courses as $course) {
            $courseStatement->execute([
                'record_id' => $recordId,
                'requirement_type' => $course['requirement_type'],
                'course_code' => $course['course_code'],
                'course_name' => $course['course_name'],
                'prerequisite_codes' => $course['prerequisite_codes'] === []
                    ? null
                    : implode(',', $course['prerequisite_codes']),
                'credit_hours' => $course['credit_hours'],
                'mark' => $course['mark'],
                'result' => $course['result'],
                'registration_status' => $course['registration_status'],
                'semester_code' => $course['semester_code'],
                'is_current_semester' => $course['is_current_semester'],
                'completion_state' => $course['completion_state'],
            ]);
        }

        $recommendationStatement = $pdo->prepare(
            'INSERT INTO record_recommendations (
                record_id, course_code, course_name, credit_hours,
                priority, reason, is_accepted, rejection_reason
            ) VALUES (
                :record_id, :course_code, :course_name, :credit_hours,
                :priority, :reason, :is_accepted, :rejection_reason
            )'
        );

        foreach ($recommendations as $recommendation) {
            $recommendationStatement->execute([
                'record_id' => $recordId,
                'course_code' => $recommendation['course_code'],
                'course_name' => $recommendation['course_name'],
                'credit_hours' => $recommendation['credit_hours'],
                'priority' => $recommendation['priority'],
                'reason' => $recommendation['reason'],
                'is_accepted' => $recommendation['is_accepted'],
                'rejection_reason' => $recommendation['rejection_reason'],
            ]);
        }

        /* Only one record per student may be the current one. */
        $pdo->prepare(
            'UPDATE academic_records
             SET is_current = 0
             WHERE user_id = :user_id'
        )->execute(['user_id' => $userId]);

        $pdo->prepare(
            'UPDATE academic_records
             SET status = "analyzed",
                 is_current = 1,
                 analyzed_at = NOW(),
                 analysis_error = NULL,
                 ai_model = :ai_model,
                 prompt_version = :prompt_version,
                 analysis_json = :analysis_json,
                 academic_semester = :academic_semester,
                 faculty_name = :faculty_name,
                 major_name = :major_name,
                 degree_name = :degree_name,
                 student_level = :student_level,
                 academic_grade = :academic_grade,
                 gpa = :gpa,
                 plan_hours = :plan_hours,
                 graded_hours = :graded_hours,
                 earned_hours = :earned_hours,
                 attempted_hours = :attempted_hours,
                 remaining_hours = :remaining_hours
             WHERE id = :id'
        )->execute([
            'ai_model' => $model,
            'prompt_version' => MASAR_PROMPT_VERSION,
            'analysis_json' => json_encode(
                $result,
                JSON_UNESCAPED_UNICODE
            ),
            'academic_semester' => $summary['academic_semester'] ?? null,
            'faculty_name' => $summary['faculty_name'] ?? null,
            'major_name' => $summary['major_name'] ?? null,
            'degree_name' => $summary['degree_name'] ?? null,
            'student_level' => $summary['student_level'] ?? null,
            'academic_grade' => $summary['academic_grade'] ?? null,
            'gpa' => isset($summary['gpa']) ? (float) $summary['gpa'] : null,
            'plan_hours' => $summary['plan_hours'] ?? null,
            'graded_hours' => $summary['graded_hours'] ?? null,
            'earned_hours' => $summary['earned_hours'] ?? null,
            'attempted_hours' => $summary['attempted_hours'] ?? null,
            'remaining_hours' => $summary['remaining_hours'] ?? null,
            'id' => $recordId,
        ]);

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

/**
 * Record a failed analysis without losing the uploaded file.
 */
function markAnalysisFailed(
    PDO $pdo,
    int $recordId,
    string $message
): void {
    $pdo->prepare(
        'UPDATE academic_records
         SET status = "failed",
             analysis_error = :message
         WHERE id = :id'
    )->execute([
        'message' => mb_substr($message, 0, 500),
        'id' => $recordId,
    ]);
}
