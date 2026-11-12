<?php

declare(strict_types=1);

require_once __DIR__ . '/academic-record-parser.php';
require_once __DIR__ . '/gemini-client.php';

/**
 * Bump this whenever the prompt or schema below changes.
 * Stored per record so past analyses stay explainable.
 */
const MASAR_PROMPT_VERSION = 'v7';

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

Course codes use a canonical 7-digit format.

Some MEU Academic Records may prefix course codes with the letter "A",
for example A0411201. The "A" prefix is only a formatting prefix and
does not change the course identity. Treat A0411201 and 0411201 as the
same course, and always return the canonical 7-digit code without the
"A" prefix.

Prerequisites normally appear in parentheses and may contain multiple
course codes separated by "&". Prerequisite codes may also use the
optional "A" prefix. Apply the same normalization rule to prerequisite
codes and return them without the "A" prefix.

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

The recommended plan must not exceed the credit-hour limit given in the
PLANNING CONTEXT at the start of the user message. That context states the
semester being planned (regular or summer), the normal limit, and the
higher limit allowed for a graduating student.

A student is graduating when the official remaining hours, minus the hours
of courses currently in progress, fit within the graduating limit. Only
then may the plan use the graduating limit; otherwise use the normal limit.

Never recommend more hours than the student still needs: once the plan
covers the official remaining hours minus the hours currently in progress,
stop adding courses.

The limit is a MAXIMUM, not a target.

Do not add unnecessary courses merely to reach the limit.

Likewise, do not arbitrarily recommend fewer hours when additional eligible
courses are clearly needed and appropriate for graduation.

If the student's remaining graduation requirements can reasonably be
completed in one semester, and the necessary courses are academically
eligible based on the Academic Record, prefer a graduation-completing
semester plan.

If the student's official remaining hours equal the limit, this does NOT
mean you must automatically recommend exactly that many hours. Analyze the
actual requirements and prerequisites first.

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

Every prerequisite of a recommended course must be either completed or
currently in progress.

The plan is for the NEXT semester, so a prerequisite the student is
registered in this semester is expected to be finished by then. You may
recommend a course whose prerequisite is in progress, but its reason must
say that the recommendation depends on passing that prerequisite this
semester.

A failed or remaining prerequisite never counts.

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

For every recommended course, provide a short factual reason twice:
"reason" in English and "reason_ar" in clear Modern Standard Arabic with
the same meaning. Keep course codes as digits and course names exactly as
written in the record (in English) inside the Arabic sentence.

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
                    'required' => ['course_code', 'reason', 'reason_ar'],
                    'properties' => [
                        'course_code' => ['type' => 'STRING'],
                        'reason' => ['type' => 'STRING'],
                        'reason_ar' => ['type' => 'STRING'],
                    ],
                ],
            ],
        ],
    ];
}

/**
 * Convert MEU course codes to one canonical format.
 *
 * Both 0411201 and A0411201 represent the same course.
 * Internally Masar always uses the 7-digit form.
 */
function normaliseCourseCode(string $code): string
{
    $code = strtoupper(trim($code));

    if (preg_match('/^A?(\d{7})$/', $code, $matches)) {
        return $matches[1];
    }

    return $code;
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
                '/^\(\s*(A?\d{7}(?:\s*&\s*A?\d{7})*)\s*\)$/i',
                $line,
                $prerequisiteMatch
            )
        ) {
            preg_match_all(
    '/A?\d{7}/i',
    $prerequisiteMatch[1],
    $prerequisiteCodes
);

            $pendingPrerequisites =
    array_values(
        array_unique(
            array_map(
                'normaliseCourseCode',
                $prerequisiteCodes[0] ?? []
            )
        )
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
                '/^(A?\d{7})\s*\|/i',
                $line,
                $courseMatch
            )
        ) {
            continue;
        }

        $courseCode = normaliseCourseCode($courseMatch[1]);

  /*
 * Extract prerequisites that appear inside the same row.
 */
$prerequisites = [];

preg_match_all(
    '/\(([^)]+)\)/',
    $line,
    $prerequisiteGroups
);

foreach ($prerequisiteGroups[1] ?? [] as $group) {
    preg_match_all(
        '/A?\d{7}/i',
        $group,
        $codes
    );

    foreach ($codes[0] ?? [] as $code) {
        $code = normaliseCourseCode($code);

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

        /*
         * The course name is the first descriptive column after the code.
         * Used only when Gemini leaves a course out of its list.
         */
        $courseName = '';

        foreach (array_slice($columns, 1) as $column) {
            if (
                $column !== ''
                && preg_match('/\p{L}{3,}/u', $column)
                && !preg_match('/^\(.*\)$/', $column)
                && !preg_match('/^(Pass|Fail|Exempted)$/i', $column)
            ) {
                $courseName = $column;
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
            'course_name' => $courseName,
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

            if ($fact['course_name'] === '') {
                $fact['course_name'] = $existing['course_name'];
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
 * Duplicate suggestions (including A-prefixed variants of the same code)
 * are dropped, because a second row for the same course would violate the
 * unique key and make the whole analysis fail. Priorities are numbered
 * over accepted suggestions only, so the student always sees 1, 2, 3 ...
 *
 * $neededHours is what the student still has to register to graduate
 * (official remaining hours minus hours in progress), or null when the
 * record did not give a trustworthy figure.
 *
 * @param  array<int, array<string, mixed>>  $recommendations
 * @param  array<string, array<string, mixed>>  $deterministicFacts
 * @param  array<string, array<string, mixed>>  $coursesByCode
 * @return array<int, array<string, mixed>>
 */
function validateRecommendations(
    array $recommendations,
    array $deterministicFacts,
    array $coursesByCode,
    int $maximumHours = 18,
    ?int $neededHours = null
): array {
    $validated = [];
    $seenCodes = [];
    $acceptedPriority = 0;
    $acceptedHours = 0;

    foreach ($recommendations as $recommendation) {
        if (!is_array($recommendation)) {
            continue;
        }

        $code = normaliseCourseCode(
            (string) ($recommendation['course_code'] ?? '')
        );

        if ($code === '' || isset($seenCodes[$code])) {
            continue;
        }

        $seenCodes[$code] = true;
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
         * Rule 3: every prerequisite must be completed, or registered
         * this semester. The plan is for the next semester, so a course
         * in progress is expected to be finished by then; the suggestion
         * is kept but marked as conditional on passing it.
         */
        $conditionalOn = [];

        if ($rejection === null && $fact !== null) {
            $unmet = [];

            foreach (
                $fact['prerequisite_codes'] as $prerequisiteCode
            ) {
                $prerequisite =
                    $deterministicFacts[$prerequisiteCode] ?? null;

                $prerequisiteState =
                    $prerequisite['completion_state'] ?? null;

                if ($prerequisiteState === 'in_progress') {
                    $conditionalOn[] = $prerequisiteCode;
                } elseif ($prerequisiteState !== 'completed') {
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
         * Rule 5: accepted recommendations may not exceed the semester
         * credit limit. Gemini determines priority; PHP guarantees it.
         */
        if (
            $rejection === null
            && ($acceptedHours + $creditHours) > $maximumHours
        ) {
            $rejection = sprintf(
                'Exceeds the %d-credit-hour limit for this semester.',
                $maximumHours
            );
        }

        /*
         * Rule 6: stop once the plan covers what the student still needs.
         * A course that crosses the figure is allowed (5 hours left and
         * only 3-hour courses means two courses), one beyond it is not.
         */
        if (
            $rejection === null
            && $neededHours !== null
            && $acceptedHours >= $neededHours
        ) {
            $rejection = $neededHours <= 0
                ? 'Not needed: the courses in progress already cover the remaining graduation hours.'
                : sprintf(
                    'Not needed: the plan already covers the %d remaining graduation hours.',
                    $neededHours
                );
        }

        if ($rejection === null) {
            $acceptedHours += $creditHours;
            $acceptedPriority++;
        }

        $courseName = trim((string) ($aiCourse['course_name'] ?? ''));

        if ($courseName === '') {
            $courseName = trim((string) ($fact['course_name'] ?? ''));
        }

        $validated[] = [
            'course_code' => mb_substr($code, 0, 20),
            'course_name' => mb_substr(
                $courseName !== '' ? $courseName : $code,
                0,
                255
            ),
            'credit_hours' => max(0, min(255, $creditHours)),
            'priority' => $rejection === null ? $acceptedPriority : 0,
            'reason' => mb_substr(
                trim(
                    (string) ($recommendation['reason'] ?? '')
                ),
                0,
                500
            ),
            'reason_ar' => mb_substr(
                trim(
                    (string) ($recommendation['reason_ar'] ?? '')
                ),
                0,
                500
            ),
            'is_accepted' => $rejection === null ? 1 : 0,
            'rejection_reason' => $rejection !== null
                ? mb_substr($rejection, 0, 255)
                : null,
            'has_data_warning' =>
                !empty($fact['source_conflict']) ? 1 : 0,
            'conditional_on' => $rejection === null && $conditionalOn !== []
                ? mb_substr(implode(',', $conditionalOn), 0, 255)
                : null,
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
    $code = normaliseCourseCode(
    (string) ($raw['course_code'] ?? '')
);
    $name = trim((string) ($raw['course_name'] ?? ''));

    if ($code === '' || $name === '') {
        return null;
    }

    $prerequisites = [];

    foreach ((array) ($raw['prerequisite_codes'] ?? []) as $prerequisite) {
        $prerequisite = normaliseCourseCode(
    (string) $prerequisite
);

if ($prerequisite !== '') {
    $prerequisites[] = $prerequisite;
}
    }
    $prerequisites = array_values(
    array_unique($prerequisites)
);

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
 * MEU registration rules (credit limits, analysis timeout).
 *
 * @return array<string, mixed>
 */
function academicConfig(): array
{
    static $config = null;

    if ($config === null) {
        $config = require __DIR__ . '/../config/academic.php';
    }

    return $config;
}

/**
 * Decide the credit-hour limit for the semester being planned.
 *
 * Graduating = the hours still needed after the current in-progress
 * courses fit within the graduating limit for that semester type.
 *
 * @return array{limit: int, is_graduating: bool, normal: int, graduating: int}
 */
function resolveCreditLimit(
    string $targetSemester,
    ?int $remainingHours,
    int $inProgressHours
): array {
    $limits = academicConfig()['credit_limits'];
    $semesterLimits = $limits[$targetSemester] ?? $limits['regular'];

    $normal = (int) $semesterLimits['normal'];
    $graduating = (int) $semesterLimits['graduating'];

    $isGraduating = false;

    if ($remainingHours !== null) {
        $hoursAfterCurrent = $remainingHours - $inProgressHours;

        $isGraduating =
            $hoursAfterCurrent > 0
            && $hoursAfterCurrent <= $graduating;
    }

    return [
        'limit' => $isGraduating ? $graduating : $normal,
        'is_graduating' => $isGraduating,
        'normal' => $normal,
        'graduating' => $graduating,
    ];
}

/**
 * Summary values from the model, clamped to what the columns can hold.
 * A value the model did not return stays NULL so the interface can show
 * it as missing instead of as zero.
 *
 * @param  array<string, mixed>  $summary
 * @return array<string, mixed>
 */
function normaliseSummary(array $summary): array
{
    $text = static function (string $key, int $length) use ($summary): ?string {
        $value = trim((string) ($summary[$key] ?? ''));

        return $value === '' ? null : mb_substr($value, 0, $length);
    };

    $hours = static function (string $key) use ($summary): ?int {
        if (!isset($summary[$key]) || !is_numeric($summary[$key])) {
            return null;
        }

        $value = (int) $summary[$key];

        return $value >= 0 && $value <= 65535 ? $value : null;
    };

    $gpa = null;

    if (isset($summary['gpa']) && is_numeric($summary['gpa'])) {
        $value = round((float) $summary['gpa'], 2);
        $gpa = $value >= 0 && $value <= 99.99 ? $value : null;
    }

    return [
        'academic_semester' => $text('academic_semester', 50),
        'faculty_name' => $text('faculty_name', 150),
        'major_name' => $text('major_name', 150),
        'degree_name' => $text('degree_name', 100),
        'student_level' => $text('student_level', 100),
        'academic_grade' => $text('academic_grade', 50),
        'gpa' => $gpa,
        'plan_hours' => $hours('plan_hours'),
        'graded_hours' => $hours('graded_hours'),
        'earned_hours' => $hours('earned_hours'),
        'attempted_hours' => $hours('attempted_hours'),
        'remaining_hours' => $hours('remaining_hours'),
    ];
}

/**
 * Read the official summary figures straight from the reconstructed PDF
 * rows, independently from Gemini.
 *
 * Two layouts are understood:
 *   - label and value side by side:  "Remain Hrs | 12"  or  "GPA: 3.10"
 *   - a row of labels followed by a row of values in the same order
 *     (also a single label whose value sits on the next row)
 *
 * A figure that is found twice with different values is ambiguous and is
 * left out. Anything not found stays null, and the caller falls back to
 * the model's value after checking it (see reconcileSummaryWithRecord).
 *
 * @return array<string, int|float|null>
 */
function extractDeterministicSummary(string $rows): array
{
    $labels = [
        'plan_hours' => '/^Plan\s*H(?:ou)?rs?\.?$/i',
        'graded_hours' => '/^Graded\s*H(?:ou)?rs?\.?$/i',
        'earned_hours' => '/^(?:Earned|Passed|Pass)\s*H(?:ou)?rs?\.?$/i',
        'attempted_hours' => '/^Attempt(?:ed)?\s*H(?:ou)?rs?\.?$/i',
        'remaining_hours' => '/^Remain(?:ing)?\s*H(?:ou)?rs?\.?$/i',
        'gpa' => '/^(?:(?:Cumulative|Cum\.?|Acc\.?|Accumulative)\s*)?GPA$/i',
    ];

    $parseValue = static function (string $field, string $text) {
        $text = trim($text);

        if ($field === 'gpa') {
            if (!preg_match('/^\d{1,2}(?:\.\d{1,2})?$/', $text)) {
                return null;
            }

            $value = round((float) $text, 2);

            return $value <= 99.99 ? $value : null;
        }

        if (!preg_match('/^\d{1,3}$/', $text)) {
            return null;
        }

        return (int) $text;
    };

    $found = [];
    $ambiguous = [];

    $record = static function (string $field, $value) use (&$found, &$ambiguous): void {
        if (isset($ambiguous[$field])) {
            return;
        }

        if (array_key_exists($field, $found) && $found[$field] != $value) {
            unset($found[$field]);
            $ambiguous[$field] = true;

            return;
        }

        $found[$field] = $value;
    };

    $lines = array_values(array_filter(
        array_map('trim', preg_split('/\R/u', $rows) ?: []),
        static fn (string $line): bool => $line !== ''
    ));

    foreach ($lines as $lineIndex => $line) {
        /* Course rows never carry summary figures. */
        if (preg_match('/^A?\d{7}\s*\|/i', $line)) {
            continue;
        }

        $columns = array_map('trim', explode('|', $line));
        $pending = [];

        foreach ($columns as $index => $column) {
            $labelText = $column;
            $inlineValue = null;

            /* "Label: 12" or "Label 12" inside one fragment. */
            if (preg_match('/^(.*?\p{L})\s*[:=]?\s*(\d+(?:\.\d+)?)$/u', $column, $match)) {
                $labelText = trim($match[1]);
                $inlineValue = $match[2];
            }

            $labelText = rtrim($labelText, " :=\t");

            foreach ($labels as $field => $pattern) {
                if (!preg_match($pattern, $labelText)) {
                    continue;
                }

                $value = null;

                if ($inlineValue !== null) {
                    $value = $parseValue($field, $inlineValue);
                } elseif (isset($columns[$index + 1])) {
                    $value = $parseValue($field, ltrim($columns[$index + 1], ': '));
                }

                if ($value !== null) {
                    $record($field, $value);
                } else {
                    $pending[] = $field;
                }

                break;
            }
        }

        /* Labels without a value on their own row: values on the next row. */
        if ($pending !== [] && isset($lines[$lineIndex + 1])) {
            $nextColumns = array_map('trim', explode('|', $lines[$lineIndex + 1]));

            if (count($nextColumns) === count($pending)) {
                $values = [];

                foreach ($pending as $position => $field) {
                    $values[$field] = $parseValue($field, $nextColumns[$position]);
                }

                if (!in_array(null, $values, true)) {
                    foreach ($values as $field => $value) {
                        $record($field, $value);
                    }
                }
            }
        }
    }

    $summary = [];

    foreach (array_keys($labels) as $field) {
        $summary[$field] = $found[$field] ?? null;
    }

    return $summary;
}

/**
 * Make the summary figures trustworthy.
 *
 * A figure read directly from the PDF always wins over the model's.
 * A figure only the model supplied is kept only if that exact number is
 * printed somewhere in the record outside the course rows; otherwise it
 * was invented or mis-derived, and is dropped so the interface shows it
 * as missing instead of presenting a wrong official number.
 *
 * @param  array<string, mixed>  $summary  Output of normaliseSummary().
 * @return array<string, mixed>
 */
function reconcileSummaryWithRecord(array $summary, string $rows): array
{
    $deterministic = extractDeterministicSummary($rows);

    $printedNumbers = [];

    foreach (preg_split('/\R/u', $rows) ?: [] as $line) {
        if (preg_match('/^\s*A?\d{7}\s*\|/i', $line)) {
            continue;
        }

        if (preg_match_all('/(?<![\d.])\d+(?:\.\d+)?(?![\d.])/', $line, $matches)) {
            foreach ($matches[0] as $number) {
                $printedNumbers[(string) (float) $number] = true;
            }
        }
    }

    foreach ($deterministic as $field => $value) {
        if ($value !== null) {
            $summary[$field] = $value;
            continue;
        }

        if (
            $summary[$field] !== null
            && !isset($printedNumbers[(string) (float) $summary[$field]])
        ) {
            $summary[$field] = null;
        }
    }

    return $summary;
}

/**
 * Run the full analysis pipeline for one uploaded record.
 *
 * Extract rows -> redact -> ask the model -> validate -> store.
 * Marks the record as the student's current one on success.
 *
 * The caller is responsible for moving the record to "processing"
 * (atomically) before calling this function.
 *
 * @throws GeminiException|RuntimeException
 */
function analyzeAcademicRecord(
    PDO $pdo,
    int $recordId,
    int $userId,
    string $pdfPath,
    string $targetSemester = 'regular'
): void {
    if (!in_array($targetSemester, ['regular', 'summer'], true)) {
        $targetSemester = 'regular';
    }

    $rows = extractAcademicRecordRows($pdfPath);

    $deterministicFacts =
        extractDeterministicCourseFacts($rows);

    if ($deterministicFacts === []) {
        throw new RuntimeException(
            'No course rows could be read from the PDF.'
        );
    }

    $inProgressHours = 0;

    foreach ($deterministicFacts as $fact) {
        if ($fact['completion_state'] === 'in_progress') {
            $inProgressHours += (int) ($fact['credit_hours'] ?? 0);
        }
    }

    $limits = academicConfig()['credit_limits'][$targetSemester];

    $planningContext = sprintf(
        "PLANNING CONTEXT\n"
        . "- Semester being planned: %s\n"
        . "- Normal credit-hour limit: %d\n"
        . "- Graduating-student credit-hour limit: %d\n"
        . "- Credit hours currently in progress: %d\n\n",
        $targetSemester === 'summer' ? 'summer semester' : 'regular semester',
        (int) $limits['normal'],
        (int) $limits['graduating'],
        $inProgressHours
    );

    $redacted = redactAcademicRecordText($rows);

    $model = env('GEMINI_MODEL', 'gemini-2.5-flash');

    $result = callGeminiJson(
        academicRecordSystemInstruction(),
        $planningContext . "MEU Academic Record:\n\n" . $redacted,
        academicRecordResponseSchema()
    );

    $summary = reconcileSummaryWithRecord(
        normaliseSummary((array) ($result['summary'] ?? [])),
        $rows
    );
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

        /* Duplicate codes would break the unique key; keep the first. */
        if (isset($coursesByCode[$courseCode])) {
            continue;
        }

        $fact = $deterministicFacts[$courseCode] ?? null;

        $course = mergeCourseWithDeterministicFacts($course, $fact);
        $course['has_data_warning'] =
            !empty($fact['source_conflict']) ? 1 : 0;

        $courses[] = $course;
        $coursesByCode[$courseCode] = $course;
    }

    /*
     * The PDF is the source of truth for which courses exist. A course
     * the parser found but the model left out is still stored, using the
     * name read from the PDF row.
     */
    foreach ($deterministicFacts as $courseCode => $fact) {
        if (isset($coursesByCode[$courseCode])) {
            continue;
        }

        $course = [
            'requirement_type' => 'Unspecified',
            'course_code' => mb_substr($courseCode, 0, 20),
            'course_name' => mb_substr(
                $fact['course_name'] !== '' ? $fact['course_name'] : $courseCode,
                0,
                255
            ),
            'prerequisite_codes' => [],
            'credit_hours' => 0,
            'mark' => null,
            'result' => 'none',
            'registration_status' => null,
            'semester_code' => null,
            'is_current_semester' => 0,
            'completion_state' => 'remaining',
        ];

        $course = mergeCourseWithDeterministicFacts($course, $fact);
        $course['has_data_warning'] =
            !empty($fact['source_conflict']) ? 1 : 0;

        $courses[] = $course;
        $coursesByCode[$courseCode] = $course;
    }

    $creditLimit = resolveCreditLimit(
        $targetSemester,
        $summary['remaining_hours'],
        $inProgressHours
    );

    /* What the student still has to register after this semester. */
    $neededHours = $summary['remaining_hours'] !== null
        ? max(0, (int) $summary['remaining_hours'] - $inProgressHours)
        : null;

    $recommendations = validateRecommendations(
        (array) ($result['recommended_courses'] ?? []),
        $deterministicFacts,
        $coursesByCode,
        $creditLimit['limit'],
        $neededHours
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
                completion_state, has_data_warning
            ) VALUES (
                :record_id, :requirement_type, :course_code, :course_name,
                :prerequisite_codes, :credit_hours, :mark, :result,
                :registration_status, :semester_code, :is_current_semester,
                :completion_state, :has_data_warning
            )'
        );

        foreach ($courses as $course) {
            $prerequisiteList = implode(',', $course['prerequisite_codes']);

            $courseStatement->execute([
                'record_id' => $recordId,
                'requirement_type' => $course['requirement_type'],
                'course_code' => $course['course_code'],
                'course_name' => $course['course_name'],
                'prerequisite_codes' => $prerequisiteList === ''
                    ? null
                    : mb_substr($prerequisiteList, 0, 255),
                'credit_hours' => max(0, min(255, (int) $course['credit_hours'])),
                'mark' => $course['mark'] !== null
                    ? mb_substr((string) $course['mark'], 0, 5)
                    : null,
                'result' => $course['result'],
                'registration_status' => $course['registration_status'],
                'semester_code' => $course['semester_code'],
                'is_current_semester' => $course['is_current_semester'],
                'completion_state' => $course['completion_state'],
                'has_data_warning' => $course['has_data_warning'],
            ]);
        }

        $recommendationStatement = $pdo->prepare(
            'INSERT INTO record_recommendations (
                record_id, course_code, course_name, credit_hours,
                priority, reason, reason_ar, is_accepted, rejection_reason,
                has_data_warning, conditional_on
            ) VALUES (
                :record_id, :course_code, :course_name, :credit_hours,
                :priority, :reason, :reason_ar, :is_accepted, :rejection_reason,
                :has_data_warning, :conditional_on
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
                'reason_ar' => $recommendation['reason_ar'] !== ''
                    ? $recommendation['reason_ar']
                    : null,
                'is_accepted' => $recommendation['is_accepted'],
                'rejection_reason' => $recommendation['rejection_reason'],
                'has_data_warning' => $recommendation['has_data_warning'],
                'conditional_on' => $recommendation['conditional_on'],
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
                 target_semester = :target_semester,
                 is_graduating = :is_graduating,
                 credit_limit = :credit_limit,
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
            'target_semester' => $targetSemester,
            'is_graduating' => $creditLimit['is_graduating'] ? 1 : 0,
            'credit_limit' => $creditLimit['limit'],
            'ai_model' => mb_substr((string) $model, 0, 100),
            'prompt_version' => MASAR_PROMPT_VERSION,
            'analysis_json' => json_encode(
                $result,
                JSON_UNESCAPED_UNICODE
            ),
            'academic_semester' => $summary['academic_semester'],
            'faculty_name' => $summary['faculty_name'],
            'major_name' => $summary['major_name'],
            'degree_name' => $summary['degree_name'],
            'student_level' => $summary['student_level'],
            'academic_grade' => $summary['academic_grade'],
            'gpa' => $summary['gpa'],
            'plan_hours' => $summary['plan_hours'],
            'graded_hours' => $summary['graded_hours'],
            'earned_hours' => $summary['earned_hours'],
            'attempted_hours' => $summary['attempted_hours'],
            'remaining_hours' => $summary['remaining_hours'],
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
 * Atomically move a record into "processing".
 *
 * Succeeds only for a record owned by the user that is uploaded, failed,
 * stale in processing, or (when re-planning) already analysed. Two
 * simultaneous requests can therefore never analyse the same record.
 *
 * @return string|null The status the record had before, or null if it
 *                     could not be claimed.
 */
function claimRecordForAnalysis(
    PDO $pdo,
    int $recordId,
    int $userId,
    string $targetSemester,
    bool $allowAnalyzed
): ?string {
    $statement = $pdo->prepare(
        'SELECT status FROM academic_records
         WHERE id = :id AND user_id = :user_id'
    );
    $statement->execute(['id' => $recordId, 'user_id' => $userId]);
    $previousStatus = $statement->fetchColumn();

    if ($previousStatus === false) {
        return null;
    }

    $claimable = ['uploaded', 'failed'];

    if ($allowAnalyzed) {
        $claimable[] = 'analyzed';
    }

    $timeout = (int) academicConfig()['analysis_timeout_minutes'];

    $update = $pdo->prepare(
        "UPDATE academic_records
         SET status = 'processing',
             analysis_error = NULL,
             analysis_started_at = NOW()
         WHERE id = :id
           AND user_id = :user_id
           AND status = :previous_status
           AND (
                status IN ('" . implode("','", $claimable) . "')
                OR (
                    status = 'processing'
                    AND (
                        analysis_started_at IS NULL
                        OR analysis_started_at < NOW() - INTERVAL {$timeout} MINUTE
                    )
                )
           )"
    );

    /*
     * target_semester is deliberately not changed here: it is written by
     * analyzeAcademicRecord() on success, so a failed re-plan leaves the
     * stored results and their label consistent.
     */
    unset($targetSemester);

    $update->execute([
        'id' => $recordId,
        'user_id' => $userId,
        'previous_status' => $previousStatus,
    ]);

    return $update->rowCount() === 1 ? (string) $previousStatus : null;
}

/**
 * Record a failed analysis without losing the uploaded file.
 *
 * When a re-plan of an already analysed record fails, the record goes
 * back to "analyzed" so the student keeps their previous results.
 * The technical message is stored for administrators only.
 */
function markAnalysisFailed(
    PDO $pdo,
    int $recordId,
    string $message,
    string $previousStatus = 'uploaded'
): void {
    $restoredStatus = $previousStatus === 'analyzed' ? 'analyzed' : 'failed';

    $pdo->prepare(
        'UPDATE academic_records
         SET status = :status,
             analysis_error = :message
         WHERE id = :id'
    )->execute([
        'status' => $restoredStatus,
        'message' => mb_substr($message, 0, 500),
        'id' => $recordId,
    ]);
}

/**
 * Mark records stuck in "processing" (worker died, timeout, server
 * restart) as failed so the student can retry them.
 */
function expireStaleAnalyses(PDO $pdo, int $userId): void
{
    $timeout = (int) academicConfig()['analysis_timeout_minutes'];

    $pdo->prepare(
        "UPDATE academic_records
         SET status = IF(analyzed_at IS NOT NULL AND is_current = 1, 'analyzed', 'failed'),
             analysis_error = 'Analysis timed out before completing.'
         WHERE user_id = :user_id
           AND status = 'processing'
           AND (
                analysis_started_at IS NULL
                OR analysis_started_at < NOW() - INTERVAL {$timeout} MINUTE
           )"
    )->execute(['user_id' => $userId]);
}

/**
 * Translated, student-safe message for an analysis exception.
 * The raw message is never shown to students.
 */
function analysisFailureMessageKey(Throwable $exception): string
{
    $message = $exception->getMessage();

    if ($exception instanceof GeminiException) {
        if (str_contains($message, 'not configured')) {
            return 'record_error_ai_unavailable';
        }

        if (
            str_contains($message, 'HTTP 429')
            || str_contains($message, 'HTTP 500')
            || str_contains($message, 'HTTP 502')
            || str_contains($message, 'HTTP 503')
            || str_contains($message, 'HTTP 504')
            || str_contains($message, 'could not be reached')
        ) {
            return 'record_error_ai_busy';
        }
    }

    if (
        str_contains($message, 'No course rows')
        || str_contains($message, 'No readable text')
        || str_contains($message, 'returned no courses')
    ) {
        return 'record_error_unreadable_courses';
    }

    return 'record_error_analysis_failed';
}

/**
 * Fill in Arabic reasons for recommendations that only have English ones
 * (analyses made before reasons were bilingual, or a model reply that
 * left reason_ar empty). Runs once per record; results are stored.
 *
 * Never throws: on any failure the page simply keeps the English text.
 */
function ensureArabicReasons(PDO $pdo, int $recordId): void
{
    $statement = $pdo->prepare(
        "SELECT course_code, course_name, reason
         FROM record_recommendations
         WHERE record_id = :record_id
           AND is_accepted = 1
           AND reason IS NOT NULL AND reason <> ''
           AND (reason_ar IS NULL OR reason_ar = '')"
    );
    $statement->execute(['record_id' => $recordId]);
    $missing = $statement->fetchAll();

    if ($missing === []) {
        return;
    }

    $lines = [];

    foreach ($missing as $row) {
        $lines[] = $row['course_code'] . ' | ' . $row['course_name'] . ' | ' . $row['reason'];
    }

    try {
        $result = callGeminiJson(
            "Translate academic advising notes from English into clear Modern Standard Arabic.\n"
            . "Keep course codes as digits and course names exactly as written (in English).\n"
            . "Do not add or remove information. Return one translation per input line.",
            "Lines (course code | course name | English reason):\n" . implode("\n", $lines),
            [
                'type' => 'OBJECT',
                'required' => ['translations'],
                'properties' => [
                    'translations' => [
                        'type' => 'ARRAY',
                        'items' => [
                            'type' => 'OBJECT',
                            'required' => ['course_code', 'reason_ar'],
                            'properties' => [
                                'course_code' => ['type' => 'STRING'],
                                'reason_ar' => ['type' => 'STRING'],
                            ],
                        ],
                    ],
                ],
            ],
            20
        );
    } catch (Throwable $exception) {
        error_log('Masar reason translation failed: ' . $exception->getMessage());

        return;
    }

    $wanted = array_flip(array_column($missing, 'course_code'));

    $update = $pdo->prepare(
        'UPDATE record_recommendations
         SET reason_ar = :reason_ar
         WHERE record_id = :record_id AND course_code = :course_code'
    );

    foreach ((array) ($result['translations'] ?? []) as $translation) {
        if (!is_array($translation)) {
            continue;
        }

        $code = normaliseCourseCode((string) ($translation['course_code'] ?? ''));
        $text = trim((string) ($translation['reason_ar'] ?? ''));

        if ($text === '' || !isset($wanted[$code])) {
            continue;
        }

        $update->execute([
            'reason_ar' => mb_substr($text, 0, 500),
            'record_id' => $recordId,
            'course_code' => $code,
        ]);
    }
}
