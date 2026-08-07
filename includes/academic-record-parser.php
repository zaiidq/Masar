<?php

declare(strict_types=1);

use Smalot\PdfParser\Parser;

require_once __DIR__ . '/../vendor/autoload.php';

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

    $foundMarkers = [];
    $missingMarkers = [];

    foreach ($markers as $marker) {
        if (stripos($text, $marker) !== false) {
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