<?php

declare(strict_types=1);

/**
 * Command-line check for the analysis pipeline.
 *
 *   php tools/test-analysis.php path/to/record.pdf          (parse only)
 *   php tools/test-analysis.php path/to/record.pdf --gemini (parse + analyse)
 *
 * Runs outside the web app so parsing problems can be separated from
 * database and session problems.
 */

require_once __DIR__ . '/../includes/academic-record-parser.php';
require_once __DIR__ . '/../includes/academic-record-analyzer.php';

if (PHP_SAPI !== 'cli') {
    exit('This script must be run from the command line.');
}

$pdfPath = $argv[1] ?? '';
$callGemini = in_array('--gemini', $argv, true);

if ($pdfPath === '' || !is_file($pdfPath)) {
    exit("Usage: php tools/test-analysis.php <record.pdf> [--gemini]\n");
}

echo "=== Row reconstruction ===\n\n";

$rows = extractAcademicRecordRows($pdfPath);
$redacted = redactAcademicRecordText($rows);

$lines = explode("\n", $redacted);

foreach (array_slice($lines, 0, 40) as $line) {
    echo $line, "\n";
}

echo "\n... (", count($lines), " rows total)\n\n";

$validation = validateEnglishMeuAcademicRecord($rows);

echo '=== Validation: ',
     $validation['valid'] ? 'PASS' : 'FAIL',
     ' (', count($validation['found_markers']), '/11 markers) ===',
     "\n";

if ($validation['missing_markers'] !== []) {
    echo 'Missing: ',
         implode(', ', $validation['missing_markers']),
         "\n";
}
echo "\n=== Deterministic course facts ===\n\n";

$deterministicFacts = extractDeterministicCourseFacts($rows);

echo 'Courses detected: ', count($deterministicFacts), "\n\n";

$stateCounts = [
    'completed' => 0,
    'in_progress' => 0,
    'failed' => 0,
    'remaining' => 0,
];

$conflictCount = 0;

foreach ($deterministicFacts as $fact) {
    $state = $fact['completion_state'];

    if (isset($stateCounts[$state])) {
        $stateCounts[$state]++;
    }

    if (!empty($fact['source_conflict'])) {
        $conflictCount++;
    }
}

foreach ($stateCounts as $state => $count) {
    echo str_pad($state, 14), $count, "\n";
}

echo 'conflicts     ', $conflictCount, "\n";

echo "\n=== Failed / conflict courses ===\n\n";

foreach ($deterministicFacts as $fact) {
    if (
        $fact['completion_state'] !== 'failed'
        && empty($fact['source_conflict'])
    ) {
        continue;
    }

    echo $fact['course_code'];
    echo ' | state=', $fact['completion_state'];
    echo ' | mark=', $fact['mark'] ?? '-';
    echo ' | hours=', $fact['credit_hours'] ?? '-';
    echo ' | source=', $fact['source'];

    if (!empty($fact['source_conflict'])) {
        echo ' | CONFLICT';
    }

    if ($fact['prerequisite_codes'] !== []) {
        echo ' | prereq=',
             implode(',', $fact['prerequisite_codes']);
    }

    echo "\n";
}

if (!$callGemini) {
    echo "\nPass --gemini to also run the AI analysis.\n";
    exit(0);
}

echo "\n=== Gemini analysis ===\n\n";

if (env('GEMINI_API_KEY') === null) {
    exit("GEMINI_API_KEY is not set in .env\n");
}

$start = microtime(true);

try {
    $result = callGeminiJson(
        academicRecordSystemInstruction(),
        "MEU Academic Record:\n\n" . $redacted,
        academicRecordResponseSchema()
    );
} catch (Throwable $exception) {
    exit('FAILED: ' . $exception->getMessage() . "\n");
}

$elapsed = round(microtime(true) - $start, 1);

$courses = (array) ($result['courses'] ?? []);
$summary = (array) ($result['summary'] ?? []);

echo "Completed in {$elapsed}s\n";
echo 'Courses extracted: ', count($courses), "\n";
echo 'GPA: ', $summary['gpa'] ?? '?', "\n";
echo 'Remaining hours: ', $summary['remaining_hours'] ?? '?', "\n\n";

$states = [];

foreach ($courses as $course) {
    $state = $course['completion_state'] ?? 'unknown';
    $states[$state] = ($states[$state] ?? 0) + 1;
}

foreach ($states as $state => $count) {
    echo str_pad($state, 14), $count, "\n";
}

/* Run the same deterministic checks the web app applies. */
$coursesByCode = [];

foreach ($courses as $course) {
    $normalised = normaliseCourse((array) $course);

    if ($normalised !== null) {
        $coursesByCode[$normalised['course_code']] = $normalised;
    }
}

$recommendations = validateRecommendations(
    (array) ($result['recommended_courses'] ?? []),
    $deterministicFacts,
    $coursesByCode
);

echo "\n=== Recommendations ===\n\n";

foreach ($recommendations as $recommendation) {
    echo $recommendation['is_accepted'] ? '[OK]     ' : '[REJECT] ';
    echo $recommendation['course_code'], ' ';
    echo $recommendation['course_name'], "\n";
    echo '         ', $recommendation['reason'], "\n";

    if ($recommendation['rejection_reason'] !== null) {
        echo '         -> ', $recommendation['rejection_reason'], "\n";
    }

    echo "\n";
}
