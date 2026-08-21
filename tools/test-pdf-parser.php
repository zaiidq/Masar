<?php

declare(strict_types=1);

use Smalot\PdfParser\Parser;

require_once __DIR__ . '/../vendor/autoload.php';

if ($argc < 2) {
    fwrite(
        STDERR,
        "Usage: php tools/test-pdf-parser.php <pdf-path>\n"
    );

    exit(1);
}

$pdfPath = $argv[1];

if (!is_file($pdfPath)) {
    fwrite(STDERR, "PDF file was not found.\n");
    exit(1);
}

try {
    $parser = new Parser();
    $pdf = $parser->parseFile($pdfPath);
    $text = trim($pdf->getText());

    if ($text === '') {
        fwrite(STDERR, "No text could be extracted from the PDF.\n");
        exit(1);
    }

    echo "Extracted characters: " . mb_strlen($text) . PHP_EOL;
    echo str_repeat('=', 60) . PHP_EOL;
    echo mb_substr($text, 0, 5000) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        "PDF parsing failed: " . $exception->getMessage() . PHP_EOL
    );

    exit(1);
}