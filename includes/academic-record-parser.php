<?php

declare(strict_types=1);

use Smalot\PdfParser\Parser;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Extract the raw text of an academic record PDF.
 *
 * Note: getText() concatenates the PDF's text objects in drawing order,
 * which destroys the table row alignment. Use extractAcademicRecordRows()
 * for anything that depends on which mark belongs to which course.
 */
function extractAcademicRecordPdfText(string $pdfPath): string
{
    if (!is_file($pdfPath)) {
        throw new RuntimeException(
            'The academic record file was not found.'
        );
    }

    $parser = new Parser();
    $pdf = $parser->parseFile($pdfPath);

    $text = trim($pdf->getText());

    if ($text === '') {
        throw new RuntimeException(
            'No readable text could be extracted from the PDF.'
        );
    }

    return str_replace(["\r\n", "\r"], "\n", $text);
}

/**
 * Rebuild the academic record as aligned text rows.
 *
 * Every text fragment in a PDF carries a transformation matrix holding its
 * position on the page. Fragments sharing (almost) the same vertical
 * position belong to the same visual row, so grouping by Y and then sorting
 * each group by X restores the original table layout.
 *
 * @return string One reconstructed row per line, columns separated by " | ".
 */
function extractAcademicRecordRows(string $pdfPath): string
{
    if (!is_file($pdfPath)) {
        throw new RuntimeException(
            'The academic record file was not found.'
        );
    }

    $parser = new Parser();
    $pdf = $parser->parseFile($pdfPath);

    /* Fragments closer than this vertically are treated as one row. */
    $rowTolerance = 3.0;

    $lines = [];

    foreach ($pdf->getPages() as $page) {
        $rows = [];

        foreach ($page->getDataTm() as $fragment) {
            $matrix = $fragment[0] ?? null;
            $text = $fragment[1] ?? '';

            if (!is_array($matrix) || count($matrix) < 6) {
                continue;
            }

            $text = trim((string) $text);

            if ($text === '') {
                continue;
            }

            $x = (float) $matrix[4];
            $y = (float) $matrix[5];

            /* Round Y into buckets so near-identical baselines merge. */
            $rowKey = (string) round($y / $rowTolerance);

            $rows[$rowKey][] = [
                'x' => $x,
                'text' => $text,
            ];
        }

        /* PDF Y grows upwards, so sort descending to read top to bottom. */
        krsort($rows, SORT_NUMERIC);

        foreach ($rows as $fragments) {
            usort(
                $fragments,
                static fn (array $a, array $b): int => $a['x'] <=> $b['x']
            );

            $columns = array_map(
                static fn (array $fragment): string => $fragment['text'],
                $fragments
            );

            $line = trim(implode(' | ', $columns));

            if ($line !== '') {
                $lines[] = $line;
            }
        }
    }

    if ($lines === []) {
        throw new RuntimeException(
            'No readable text could be extracted from the PDF.'
        );
    }

    return implode("\n", $lines);
}

/**
 * Remove direct personal identifiers before the text leaves the server.
 *
 * The analysis only needs the plan, the courses and the totals. The name,
 * university ID and advisor add nothing to a recommendation, so they are
 * stripped from the payload sent to the AI provider.
 */
function redactAcademicRecordText(string $text): string
{
    $lines = preg_split('/\R/u', $text) ?: [];
    $redactedLines = [];

    /*
     * Some PDF fields have their value on the following visual row.
     * When that happens, redact the next non-empty line as well.
     */
    $redactNextValue = false;

    $sensitiveLabelPattern =
        '/\b(Student\s*Id|Student\s*Name|Advisor\s*Name|'
        . 'User\s*Name|Nationality)\b/i';

    /*
     * If the next line is clearly another academic field or a course row,
     * it is not the value of the previous sensitive field.
     */
    $structuredLinePattern =
        '/(?:'
        . 'Student\s*Id|Student\s*Name|Advisor\s*Name|User\s*Name|'
        . 'Nationality|Join\s*Semester|Faculty|Major|Degree|Plan\s*Type|'
        . 'Edition|Status|Study\s*Type|Thesis|Equivalent\s*Hrs|'
        . 'University\s+Requirement|Faculty\s+Requirement|'
        . 'Major\s+Requirement|Supportive\s+Requirement|'
        . 'Orientation\s+Requirement|Course\s+(?:Code|Name)'
        . ')|^\d{7}\s*\|/i';

    $replacements = [
        '/(Student\s*Id\s*\|?\s*:?).*/i' =>
            '$1 [REDACTED]',

        '/(Student\s*Name\s*\|?\s*:?).*/i' =>
            '$1 [REDACTED]',

        '/(Advisor\s*Name\s*\|?\s*:?).*/i' =>
            '$1 [REDACTED]',

        '/(User\s*Name\s*\|?\s*:?).*/i' =>
            '$1 [REDACTED]',

        '/(Nationality\s*\|?\s*:?).*/i' =>
            '$1 [REDACTED]',
    ];

    foreach ($lines as $line) {
        $trimmedLine = trim($line);

        /*
         * Redact a value that was placed on the row after its label.
         */
        if ($redactNextValue && $trimmedLine !== '') {
            if (
                preg_match(
                    $structuredLinePattern,
                    $trimmedLine
                ) !== 1
            ) {
                $redactedLines[] = '[REDACTED]';
                $redactNextValue = false;
                continue;
            }

            /*
             * The next row is another known field, so the previous field
             * did not have a separate value row.
             */
            $redactNextValue = false;
        }

        /*
         * Before replacing the line, determine whether the sensitive field
         * actually had a value after its label on this same row.
         */
        if (
            preg_match(
                $sensitiveLabelPattern,
                $line,
                $match,
                PREG_OFFSET_CAPTURE
            ) === 1
        ) {
            $label = $match[0][0];
            $labelOffset = $match[0][1];

            $afterLabel = substr(
                $line,
                $labelOffset + strlen($label)
            );

            $afterLabel = (string) preg_replace(
                '/^[\s|:]*/',
                '',
                $afterLabel
            );

            if (trim($afterLabel) === '') {
                $redactNextValue = true;
            }
        }

        $line = (string) preg_replace(
            array_keys($replacements),
            array_values($replacements),
            $line
        );

        $redactedLines[] = $line;
    }

    return implode("\n", $redactedLines);
}

/**
 * Validate that the extracted text belongs to an English MEU academic record.
 */
function validateEnglishMeuAcademicRecord(string $text): array
{
    $markers = [
        'Middle East University',
        'Student Id',
        'Student Name',
        'Course Code',
        'Course Name',
        'Prerequisite',
        'Crd',
        'Status',
        'Plan Hrs',
        'Remain Hrs',
        'GPA',
    ];

    /* Column headers are often split across fragments, so compare against
       a copy of the text with all whitespace and separators normalised. */
    $haystack = (string) preg_replace(
        '/[\s|]+/',
        ' ',
        $text
    );

    $foundMarkers = [];
    $missingMarkers = [];

    foreach ($markers as $marker) {
        if (stripos($haystack, $marker) !== false) {
            $foundMarkers[] = $marker;
        } else {
            $missingMarkers[] = $marker;
        }
    }

    return [
        'valid' => count($foundMarkers) >= 7,
        'found_markers' => $foundMarkers,
        'missing_markers' => $missingMarkers,
    ];
}
