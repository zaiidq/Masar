<?php

declare(strict_types=1);

require_once __DIR__ . '/academic-record-parser.php';
require_once __DIR__ . '/gemini-client.php';

/**
 * Bump this whenever the prompt or schema below changes.
 * Stored per record so past analyses stay explainable.
 */
const MASAR_PROMPT_VERSION = 'v3';

/**
 * Instructions given to the model on every analysis.
 */
function academicRecordSystemInstruction(): string
{
    return <<<'PROMPT'
You are the academic analysis and course recommendation component of Masar,
an AI-supported academic advising platform for Middle East University (MEU).

Your task is to analyze an English MEU Academic Record and recommend an
academically appropriate plan for the student's NEXT semester.

The input was extracted from the official MEU Academic Record PDF and
reconstructed using PDF text coordinates. Most table rows use " | " between
columns, but some PDF elements may appear on a separate line because of the
original document layout.

IMPORTANT:
Use only information that appears in the supplied Academic Record.
Never invent courses, course codes, prerequisites, grades, credit hours,
academic policies, or university requirements.

----------------------------------------------------------------------
1. READING THE ACADEMIC RECORD
----------------------------------------------------------------------

The Academic Record may contain:

- student academic summary
- faculty and major
- academic semester
- GPA
- plan hours
- earned hours
- remaining graduation hours
- requirement sections
- course codes
- course names
- prerequisite codes
- credit hours
- marks
- Pass / Fail / Exempted results
- current-semester registration markers
- semester codes
- an "Incomplete Courses" section

Course codes contain exactly 7 digits.

Prerequisites normally appear in parentheses and may contain multiple
course codes separated by "&".

Because of the PDF layout, a prerequisite may sometimes appear on the line
immediately before its course instead of inside the same reconstructed row.

Course names may also occasionally be split across nearby text fragments.
Use the academic structure and course code as the strongest identifiers.

----------------------------------------------------------------------
2. REQUIREMENT SECTIONS
----------------------------------------------------------------------

Courses belong to requirement groups such as:

- University Requirement Compulsory
- University Requirement Optional
- Faculty Requirement Compulsory
- Major Requirement Compulsory
- Major Requirement Optional
- Supportive Requirement Compulsory
- Orientation Requirement Compulsory

A requirement section continues until the next requirement section begins.

Pay special attention to the difference between compulsory and optional
requirements.

An uncompleted OPTIONAL course is only an available option unless the
student still needs credit hours from that optional requirement group.

DO NOT assume that every uncompleted optional course must be taken.

For example, if an optional requirement group requires 6 credit hours and
contains many 3-credit-hour courses, the student only needs enough eligible
courses to satisfy the remaining required hours in that group.

Therefore:

"uncompleted course"
does NOT automatically mean
"course still required for graduation".

----------------------------------------------------------------------
3. COURSE COMPLETION STATES
----------------------------------------------------------------------

Assign completion_state using these rules:

- "completed":
  The course result is Pass or Exempted.

- "in_progress":
  The course is marked as current-semester registration and does not yet
  have a Pass or Exempted result.

- "failed":
  The course result is Fail, or the course appears in the
  "Incomplete Courses" section.

- "remaining":
  The course has not been completed, is not currently in progress,
  and is not identified as failed.

If a course has both a current-semester marker and a Pass result,
treat it as completed.

A specific entry in the "Incomplete Courses" section should be treated as
more specific than the general course-plan row when the two contain
different details.

Do not silently invent a resolution when the Academic Record itself is
ambiguous.

----------------------------------------------------------------------
4. OFFICIAL ACADEMIC PROGRESS
----------------------------------------------------------------------

Use the official summary values printed in the Academic Record for:

- GPA
- Plan Hours
- Earned Hours
- Remaining Hours
- Attempted Hours
- Graded Hours

Do NOT calculate remaining graduation hours by adding the credit hours of
every course whose completion_state is "remaining".

This is especially important because optional requirement groups may contain
many course choices that the student is not required to complete.

For example:

21 uncompleted course options

does NOT mean:

21 courses are required for graduation.

The official Remaining Hours value in the Academic Record represents the
student's remaining graduation credit-hour requirement and should be used
as the primary graduation-progress value.

----------------------------------------------------------------------
5. NEXT-SEMESTER RECOMMENDATION GOAL
----------------------------------------------------------------------

Recommend an academically appropriate course plan for the student's NEXT
semester.

There is NO fixed target number of courses.

Do not recommend 4, 5, 6, or any other fixed number merely because of a
preset target.

Instead, examine the student's entire academic situation and decide what
course combination is academically appropriate.

Consider:

- official remaining graduation hours
- compulsory graduation requirements
- optional requirement hours still needed
- completed courses
- failed courses
- prerequisite chains
- course sequence
- graduation progress
- courses that unlock later required courses
- the student's ability to complete remaining requirements efficiently

The recommended plan must not exceed 18 credit hours.

18 credit hours is a MAXIMUM LIMIT, not a target.

Do not add unnecessary courses merely to reach 18 hours.

Likewise, do not arbitrarily recommend fewer hours when additional eligible
courses are clearly needed and appropriate for graduation.

If the student's remaining graduation requirements can reasonably be
completed in one semester, and the necessary courses are academically
eligible based on the Academic Record, prefer a graduation-completing
semester plan.

If the student has 18 official remaining graduation hours, this does NOT
mean you must automatically recommend exactly 18 hours. Analyze the actual
requirements and prerequisites first.

----------------------------------------------------------------------
6. FAILED COURSES
----------------------------------------------------------------------

A failed course does NOT automatically have to be retaken.

First determine what type of requirement the failed course belongs to.

If the failed course belongs to a COMPULSORY requirement:
- completing that specific course is normally important for graduation;
- recommend it when academically appropriate and its prerequisites are
  satisfied.

If the failed course belongs to an OPTIONAL requirement:
- do not automatically recommend retaking it;
- consider whether another eligible course from the same optional
  requirement group would satisfy the student's remaining requirement more
  appropriately;
- retaking the failed optional course is only one possible choice.

Do not recommend a failed optional course merely because it was failed
before.

----------------------------------------------------------------------
7. PREREQUISITES
----------------------------------------------------------------------

A recommended course must not violate prerequisite requirements shown in
the Academic Record.

Every prerequisite of a recommended course must be completed.

Do not assume that an in-progress, failed, or remaining prerequisite counts
as completed.

Never invent prerequisite relationships that are not present in the
Academic Record.

----------------------------------------------------------------------
8. RECOMMENDATION PRIORITIES
----------------------------------------------------------------------

When several eligible choices exist, use academic judgment to prioritize:

1. compulsory requirements needed for graduation;
2. prerequisite chains that affect later required courses;
3. appropriate handling of failed compulsory courses;
4. remaining required hours within optional requirement groups;
5. logical academic course sequence;
6. completing graduation requirements efficiently.

Do not prioritize an optional course simply because it appears earlier in
the document.

Do not recommend courses that the student has already completed.

Do not recommend a currently in-progress course for the next semester
unless the Academic Record clearly indicates that it must be repeated.

----------------------------------------------------------------------
9. LIMITATIONS
----------------------------------------------------------------------

The Academic Record does not necessarily contain information about:

- whether a course will actually be offered next semester
- section numbers
- class times
- instructors
- seat availability
- timetable conflicts
- special departmental approvals not printed in the record

Do NOT invent this information.

Base the recommendation only on the academic information contained in the
Academic Record.

Course offering and schedule availability may be checked by another Masar
component later.

----------------------------------------------------------------------
10. RECOMMENDATION EXPLANATIONS
----------------------------------------------------------------------

For every recommended course, provide a short factual reason in English.

The reason should refer to the student's actual academic situation, for
example:

- compulsory graduation requirement
- prerequisite chain completed
- failed compulsory course that still needs completion
- eligible choice needed to satisfy an optional requirement
- important prerequisite for a later required course
- remaining graduation requirement

Avoid vague statements such as:

"This course is good for the student."

Do not claim university rules that are not shown in the Academic Record.

----------------------------------------------------------------------
11. OUTPUT
----------------------------------------------------------------------

Extract the academic summary and all identifiable courses from the supplied
record.

Generate recommended_courses using the reasoning rules above.

Return ONLY valid JSON matching the provided response schema.

Do not include Markdown.
Do not include explanations outside the JSON.
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
 * Extract recommendation-critical facts directly from the reconstructed
 * PDF rows, independently from Gemini.
 *
 * @return array<string, array<string, mixed>>
 */
function extractDeterministicCourseFacts(string $rows): array
{
    $facts = [];
    $lines = preg_split('/\R/u', $rows) ?: [];

    $inIncompleteCourses = false;
    $pendingPrerequisites = [];

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '') {
            continue;
        }

        if (stripos($line, 'Incomplete Courses') !== false) {
            $inIncompleteCourses = true;
            $pendingPrerequisites = [];
            continue;
        }

        /*
         * Some prerequisites are rendered on a separate line immediately
         * before the course row, for example:
         *
         * (0161100)
         * 0161101 | Arabic Communication Skills | ...
         */
        if (
            preg_match(
                '/^\(\s*(\d{7}(?:\s*&\s*\d{7})*)\s*\)$/',
                $line,
                $prerequisiteMatch
            )
        ) {
            preg_match_all(
                '/\d{7}/',
                $prerequisiteMatch[1],
                $prerequisiteCodes
            );

            $pendingPrerequisites =
                array_values(
                    array_unique($prerequisiteCodes[0] ?? [])
                );

            continue;
        }

        /*
         * A real course row must START with the course code.
         * This prevents standalone prerequisite lines from being treated
         * as courses.
         */
        if (
            !preg_match(
                '/^(\d{7})\s*\|/',
                $line,
                $courseMatch
            )
        ) {
            continue;
        }

        $courseCode = $courseMatch[1];

        /*
         * Extract prerequisites that appear inside the same row.
         */
        $prerequisites = [];

        preg_match_all(
            '/\(([^)]*)\)/',
            $line,
            $parenthesizedGroups
        );

        foreach ($parenthesizedGroups[1] ?? [] as $group) {
            preg_match_all(
                '/\d{7}/',
                $group,
                $codes
            );

            foreach ($codes[0] ?? [] as $code) {
                if ($code !== $courseCode) {
                    $prerequisites[] = $code;
                }
            }
        }

        $prerequisites =
            array_values(array_unique($prerequisites));

        /*
         * If no prerequisite was reconstructed on the same line,
         * use the standalone prerequisite line immediately above it.
         */
        if (
            $prerequisites === []
            && $pendingPrerequisites !== []
        ) {
            $prerequisites = $pendingPrerequisites;
        }

        $pendingPrerequisites = [];

        $columns = array_map(
            'trim',
            explode('|', $line)
        );

        $creditHours = null;

        foreach ($columns as $column) {
            if (preg_match('/^[1-6]$/', $column)) {
                $creditHours = (int) $column;
                break;
            }
        }

        $mark = null;

        foreach ($columns as $column) {
            if (
                preg_match(
                    '/^(A[+-]?|B[+-]?|C[+-]?|D[+-]?|F|\?\?)$/i',
                    $column
                )
            ) {
                $mark = strtoupper($column);
                break;
            }
        }

        $result = 'none';

        if (preg_match('/\bExempted\b/i', $line)) {
            $result = 'exempted';
        } elseif (preg_match('/\bPass\b/i', $line)) {
            $result = 'pass';
        } elseif (preg_match('/\bFail\b/i', $line)) {
            $result = 'fail';
        }

        $isCurrentSemester =
            preg_match(
                '/(?:^|\|)\s*X\s*(?:\||$)/i',
                $line
            ) === 1;

        if ($inIncompleteCourses) {
            $result = 'incomplete';
            $completionState = 'failed';
            $source = 'incomplete_courses';
        } elseif (
            $result === 'pass'
            || $result === 'exempted'
        ) {
            $completionState = 'completed';
            $source = 'main_table';
        } elseif ($result === 'fail') {
            $completionState = 'failed';
            $source = 'main_table';
        } elseif ($isCurrentSemester) {
            $completionState = 'in_progress';
            $source = 'main_table';
        } else {
            $completionState = 'remaining';
            $source = 'main_table';
        }

        $fact = [
            'course_code' => $courseCode,
            'prerequisite_codes' => $prerequisites,
            'credit_hours' => $creditHours,
            'mark' => $mark,
            'result' => $result,
            'is_current_semester' => $isCurrentSemester,
            'completion_state' => $completionState,
            'source' => $source,
            'source_conflict' => false,
        ];

        if (!isset($facts[$courseCode])) {
            $facts[$courseCode] = $fact;
            continue;
        }

        $existing = $facts[$courseCode];

        $hasConflict =
            (
                $existing['mark'] !== null
                && $mark !== null
                && $existing['mark'] !== $mark
            )
            || (
                $existing['completion_state']
                !== $completionState
            );

        /*
         * The dedicated Incomplete Courses section is treated as the more
         * specific source when it conflicts with the general course table.
         */
        if ($source === 'incomplete_courses') {
            if ($fact['prerequisite_codes'] === []) {
                $fact['prerequisite_codes'] =
                    $existing['prerequisite_codes'];
            }

            if ($fact['credit_hours'] === null) {
                $fact['credit_hours'] =
                    $existing['credit_hours'];
            }

            $fact['source_conflict'] =
                $hasConflict
                || !empty($existing['source_conflict']);

            $facts[$courseCode] = $fact;
        } else {
            /*
             * Preserve any useful data missing from the first observation.
             */
            if (
                $facts[$courseCode]['prerequisite_codes'] === []
                && $prerequisites !== []
            ) {
                $facts[$courseCode]['prerequisite_codes'] =
                    $prerequisites;
            }

            if (
                $facts[$courseCode]['credit_hours'] === null
                && $creditHours !== null
            ) {
                $facts[$courseCode]['credit_hours'] =
                    $creditHours;
            }

            $facts[$courseCode]['source_conflict'] =
                $hasConflict
                || !empty($existing['source_conflict']);
        }
    }

    return $facts;
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
    array $deterministicFacts,
    array $coursesByCode
): array {
    $validated = [];
    $priority = 0;
    $acceptedHours = 0;
    $maximumHours = 18;

    foreach ($recommendations as $recommendation) {
        $code = trim((string) ($recommendation['course_code'] ?? ''));

        if ($code === '') {
            continue;
        }

        $priority++;
        $rejection = null;

        /*
         * Gemini data is used only for display information such as the
         * course name. Academic eligibility comes from deterministic facts.
         */
        $aiCourse = $coursesByCode[$code] ?? null;
        $fact = $deterministicFacts[$code] ?? null;

        /*
         * Rule 1: the course must really exist in the uploaded PDF.
         */
        if ($fact === null) {
            $rejection =
                'Course is not part of the uploaded academic record.';
        }

        /*
         * Rule 2: do not recommend completed or currently registered courses.
         */
        if ($rejection === null && $fact !== null) {
            if ($fact['completion_state'] === 'completed') {
                $rejection = 'Course has already been completed.';
            } elseif ($fact['completion_state'] === 'in_progress') {
                $rejection =
                    'Course is already registered this semester.';
            }
        }

        /*
         * Rule 3: every prerequisite must be deterministically completed.
         */
        if ($rejection === null && $fact !== null) {
            $unmet = [];

            foreach (
                $fact['prerequisite_codes'] as $prerequisiteCode
            ) {
                $prerequisite =
                    $deterministicFacts[$prerequisiteCode] ?? null;

                if (
                    $prerequisite === null
                    || $prerequisite['completion_state'] !== 'completed'
                ) {
                    $unmet[] = $prerequisiteCode;
                }
            }

            if ($unmet !== []) {
                $rejection =
                    'Unmet prerequisite: '
                    . implode(', ', $unmet);
            }
        }

        $creditHours = (int) ($fact['credit_hours'] ?? 0);

        /*
         * Rule 4: credit hours must be verifiable from the source PDF.
         */
        if ($rejection === null && $creditHours <= 0) {
            $rejection =
                'Course credit hours could not be verified.';
        }

        /*
         * Rule 5: accepted recommendations may not exceed 18 hours.
         * Gemini determines priority; PHP guarantees the limit.
         */
        if (
            $rejection === null
            && ($acceptedHours + $creditHours) > $maximumHours
        ) {
            $rejection =
                'Exceeds the 18-credit-hour recommendation limit.';
        }

        if ($rejection === null) {
            $acceptedHours += $creditHours;
        }

        $validated[] = [
            'course_code' => $code,
            'course_name' =>
                $aiCourse['course_name'] ?? $code,
            'credit_hours' => $creditHours,
            'priority' => $priority,
            'reason' => mb_substr(
                trim(
                    (string) ($recommendation['reason'] ?? '')
                ),
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
 * Override recommendation-critical AI fields with facts extracted
 * directly from the PDF.
 *
 * Gemini is still used for descriptive information, while academic
 * status and eligibility facts come from the source document.
 *
 * @param array<string, mixed> $course
 * @param array<string, mixed>|null $fact
 * @return array<string, mixed>
 */
function mergeCourseWithDeterministicFacts(
    array $course,
    ?array $fact
): array {
    if ($fact === null) {
        return $course;
    }

    $course['prerequisite_codes'] =
        $fact['prerequisite_codes'];

    if (
        $fact['credit_hours'] !== null
        && (int) $fact['credit_hours'] > 0
    ) {
        $course['credit_hours'] =
            (int) $fact['credit_hours'];
    }

    /*
     * A null mark from the PDF is meaningful:
     * the student has no recorded mark for that course.
     */
    $course['mark'] = $fact['mark'];

    $course['result'] =
        $fact['result'];

    $course['is_current_semester'] =
        $fact['is_current_semester'] ? 1 : 0;

    $course['completion_state'] =
        $fact['completion_state'];

    return $course;
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
    
    $deterministicFacts =
        extractDeterministicCourseFacts($rows);

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

$courseCode = $course['course_code'];

$course = mergeCourseWithDeterministicFacts(
    $course,
    $deterministicFacts[$courseCode] ?? null
);

/* Duplicate codes would break the unique key; keep the first. */
if (isset($coursesByCode[$courseCode])) {
    continue;
}

$courses[] = $course;
$coursesByCode[$courseCode] = $course;
    }

    $recommendations = validateRecommendations(
        (array) ($result['recommended_courses'] ?? []),
      $deterministicFacts,
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
