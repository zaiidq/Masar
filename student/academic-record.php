<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/academic-record-parser.php';
require_once __DIR__ . '/../includes/academic-record-analyzer.php';

if (($_SESSION['role'] ?? '') !== 'student') {
    header('Location: /masar/admin/dashboard.php');
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Prevent HTML injection when displaying database values.
 */
function escape(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}

/**
 * Convert file size from bytes to a readable format.
 */
function formatFileSize(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 2) . ' MB';
    }

    return number_format($bytes / 1024, 2) . ' KB';
}

/**
 * Return a readable record status.
 */
function getStatusLabel(string $status): string
{
    return match ($status) {
        'uploaded' => 'Uploaded',
        'processing' => 'Processing',
        'analyzed' => 'Analyzed',
        'failed' => 'Failed',
        default => 'Unknown',
    };
}

$errors = [];

$successMessage = $_SESSION['academic_record_success'] ?? null;
unset($_SESSION['academic_record_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken)
        || !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        $errors[] = 'Invalid request. Please refresh the page and try again.';
    }
    $action = $_POST['action'] ?? 'upload_record';

if (!$errors && $action === 'retry_analysis') {
    $recordId = (int) ($_POST['record_id'] ?? 0);

    $retryStatement = $pdo->prepare(
        'SELECT id, user_id, file_path, status
         FROM academic_records
         WHERE id = :id
           AND user_id = :user_id
         LIMIT 1'
    );

    $retryStatement->execute([
        'id' => $recordId,
        'user_id' => $userId,
    ]);

    $retryRecord = $retryStatement->fetch();

    if (!$retryRecord) {
        $errors[] = 'The academic record could not be found.';
    } elseif ($retryRecord['status'] !== 'failed') {
        $errors[] = 'Only failed analyses can be retried.';
    } else {
        $pdfPath =
            dirname(__DIR__)
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $retryRecord['file_path']
            );

        if (!is_file($pdfPath)) {
            $errors[] = 'The uploaded academic record file could not be found.';
        } else {
            try {
                set_time_limit(120);
                analyzeAcademicRecord(
                    $pdo,
                    $recordId,
                    $userId,
                    $pdfPath
                );

                $_SESSION['academic_record_success'] =
                    'Your academic record was analyzed successfully.';

                header(
                    'Location: /masar/student/academic-record.php'
                );
                exit;
            } catch (Throwable $exception) {
                markAnalysisFailed(
                    $pdo,
                    $recordId,
                    $exception->getMessage()
                );

                $errors[] =
                    'The analysis failed again. Please try again later.';
            }
        }
    }
}

    $uploadedFile = $_FILES['academic_record'] ?? null;

if (!$errors && $action === 'upload_record') {
    if (!is_array($uploadedFile)) {
        $errors[] = 'Please select an academic record PDF.';
    } else {
        $uploadError = (int) ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($uploadError !== UPLOAD_ERR_OK) {
            $errors[] = match ($uploadError) {
                UPLOAD_ERR_INI_SIZE,
                UPLOAD_ERR_FORM_SIZE =>
                    'The selected file is larger than the allowed size.',

                UPLOAD_ERR_PARTIAL =>
                    'The file was only partially uploaded. Please try again.',

                UPLOAD_ERR_NO_FILE =>
                    'Please select an academic record PDF.',

                default =>
                    'The file could not be uploaded. Please try again.',
            };
        }
    }
}

    if (
    !$errors
    && $action === 'upload_record'
    && is_array($uploadedFile)) {
        $originalName = basename((string) $uploadedFile['name']);
        $temporaryPath = (string) $uploadedFile['tmp_name'];
        $fileSize = (int) $uploadedFile['size'];

        $maximumFileSize = 10 * 1024 * 1024;

        if ($fileSize <= 0) {
            $errors[] = 'The selected file is empty.';
        }

        if ($fileSize > $maximumFileSize) {
            $errors[] = 'The academic record must not exceed 10 MB.';
        }

        if (!is_uploaded_file($temporaryPath)) {
            $errors[] = 'The uploaded file could not be verified.';
        }

        $extension = strtolower(
            pathinfo($originalName, PATHINFO_EXTENSION)
        );

        if ($extension !== 'pdf') {
            $errors[] = 'Only PDF files are allowed.';
        }

        if (!$errors) {
            if (!class_exists('finfo')) {
                $errors[] = 'The server cannot verify the uploaded file type.';
            } else {
                $fileInfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $fileInfo->file($temporaryPath);

                $allowedMimeTypes = [
                    'application/pdf',
                    'application/x-pdf',
                ];

                if (
                    !is_string($mimeType)
                    || !in_array($mimeType, $allowedMimeTypes, true)
                ) {
                    $errors[] = 'The selected file is not a valid PDF.';
                }
            }
        }

        if (!$errors) {
            $fileHandle = fopen($temporaryPath, 'rb');

            if ($fileHandle === false) {
                $errors[] = 'The uploaded PDF could not be read.';
            } else {
                $fileSignature = fread($fileHandle, 5);
                fclose($fileHandle);

                if ($fileSignature !== '%PDF-') {
                    $errors[] = 'The selected file does not contain valid PDF data.';
                }
            }
        }
        if (!$errors) {
              try {
                   $recordText = extractAcademicRecordPdfText($temporaryPath);

          $recordValidation =
            validateEnglishMeuAcademicRecord($recordText);

        if (!$recordValidation['valid']) {
            $errors[] =
                'The uploaded file could not be recognized as an English '
                . 'MEU Academic Record. Please download the English version '
                . 'directly from the university system.';
        }
    } catch (Throwable $exception) {
        $errors[] =
            'The academic record could not be read. '
            . 'Please upload the original English PDF from the university system.';
    }
}

        if (!$errors) {
            $fileHash = hash_file('sha256', $temporaryPath);

            if ($fileHash === false) {
                $errors[] = 'The uploaded file could not be processed.';
            } else {
                $duplicateStatement = $pdo->prepare(
                    'SELECT id
                     FROM academic_records
                     WHERE user_id = :user_id
                       AND file_hash = :file_hash
                     LIMIT 1'
                );

                $duplicateStatement->execute([
                    'user_id' => $userId,
                    'file_hash' => $fileHash,
                ]);

                if ($duplicateStatement->fetch()) {
                    $errors[] = 'This academic record has already been uploaded.';
                }
            }
        }

        if (!$errors) {
            $storedName = bin2hex(random_bytes(16)) . '.pdf';

            $storageDirectory =
                dirname(__DIR__)
                . DIRECTORY_SEPARATOR
                . 'storage'
                . DIRECTORY_SEPARATOR
                . 'academic-records';

            if (
                !is_dir($storageDirectory)
                && !mkdir($storageDirectory, 0755, true)
                && !is_dir($storageDirectory)
            ) {
                $errors[] = 'The academic record storage folder could not be created.';
            }

            if (!$errors && !is_writable($storageDirectory)) {
                $errors[] = 'The academic record storage folder is not writable.';
            }

            $destinationPath =
                $storageDirectory
                . DIRECTORY_SEPARATOR
                . $storedName;

            if (
                !$errors
                && !move_uploaded_file($temporaryPath, $destinationPath)
            ) {
                $errors[] = 'The academic record could not be saved.';
            }

            if (!$errors) {
                $relativePath =
                    'storage/academic-records/' . $storedName;

                try {
                    $insertStatement = $pdo->prepare(
                        'INSERT INTO academic_records (
                            user_id,
                            original_name,
                            stored_name,
                            file_path,
                            file_hash,
                            mime_type,
                            file_size,
                            record_language,
                            status,
                            is_current
                        ) VALUES (
                            :user_id,
                            :original_name,
                            :stored_name,
                            :file_path,
                            :file_hash,
                            :mime_type,
                            :file_size,
                            :record_language,
                            :status,
                            :is_current
                        )'
                    );

                    $insertStatement->execute([
                        'user_id' => $userId,
                        'original_name' => $originalName,
                        'stored_name' => $storedName,
                        'file_path' => $relativePath,
                        'file_hash' => $fileHash,
                        'mime_type' => $mimeType,
                        'file_size' => $fileSize,
                        'record_language' => 'en',
                        'status' => 'uploaded',
                        'is_current' => 0,
                    ]);

                    $recordId = (int) $pdo->lastInsertId();

try {
    /*
     * Gemini currently takes around one minute on our test record,
     * so allow enough time for the synchronous analysis.
     */
    set_time_limit(120);

    analyzeAcademicRecord(
        $pdo,
        $recordId,
        $userId,
        $destinationPath
    );

    $_SESSION['academic_record_success'] =
        'Your academic record was uploaded and analyzed successfully.';
} catch (Throwable $analysisException) {
    markAnalysisFailed(
        $pdo,
        $recordId,
        $analysisException->getMessage()
    );

    $_SESSION['academic_record_success'] =
        'Your academic record was uploaded, but the analysis failed. '
        . 'Use Retry Analysis from the record history to try again.';
}

header('Location: /masar/student/academic-record.php');
exit;
                } catch (PDOException $exception) {
                    if (is_file($destinationPath)) {
                        unlink($destinationPath);
                    }

                    if ($exception->getCode() === '23000') {
                        $errors[] =
                            'This academic record has already been uploaded.';
                    } else {
                        $errors[] =
                            'The academic record could not be saved in the database.';
                    }
                }
            }
        }
    }
}

$currentStatement = $pdo->prepare(
    'SELECT *
     FROM academic_records
     WHERE user_id = :user_id
       AND is_current = 1
       AND status = "analyzed"
     ORDER BY analyzed_at DESC, created_at DESC
     LIMIT 1'
);

$currentStatement->execute([
    'user_id' => $userId,
]);

$currentRecord = $currentStatement->fetch();

$historyStatement = $pdo->prepare(
    'SELECT *
     FROM academic_records
     WHERE user_id = :user_id
     ORDER BY created_at DESC'
);

$historyStatement->execute([
    'user_id' => $userId,
]);

$recordHistory = $historyStatement->fetchAll();

$pageTitle = 'Academic Record';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content academic-record-page">
    <div class="page-heading">
        <div>
            <h1>Academic Record Management</h1>
            <p>
                Upload and manage your Middle East University academic records.
            </p>
        </div>
    </div>

    <?php if ($successMessage): ?>
        <div class="alert alert-success">
            <?= escape($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= escape($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="academic-record-grid">
        <section class="academic-record-card">
            <h2>Current Record</h2>

            <?php if ($currentRecord): ?>
                <div class="record-summary">
                    <p>
                        <strong>File:</strong>
                        <?= escape($currentRecord['original_name']) ?>
                    </p>

                    <p>
                        <strong>Academic Semester:</strong>
                        <?= escape(
                            $currentRecord['academic_semester']
                            ?? 'Not available'
                        ) ?>
                    </p>

                    <p>
                        <strong>Uploaded:</strong>
                        <?= escape(
                            date(
                                'd M Y, h:i A',
                                strtotime($currentRecord['created_at'])
                            )
                        ) ?>
                    </p>

                    <p>
                        <strong>Status:</strong>
                        <?= escape(
                            getStatusLabel($currentRecord['status'])
                        ) ?>
                    </p>

                    <p>
                        <strong>GPA:</strong>
                        <?= escape(
                            $currentRecord['gpa'] !== null
                                ? (string) $currentRecord['gpa']
                                : 'Not available'
                        ) ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>
                        You do not have an analyzed academic record yet.
                    </p>
                </div>
            <?php endif; ?>
        </section>

        <section class="academic-record-card">
            <h2>Upload New Record</h2>

            <p class="upload-instructions">
                Download your complete Academic Record in English directly
                from the university system and upload it here as a PDF.
            </p>

            <form
                method="POST"
                enctype="multipart/form-data"
                class="academic-record-form"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escape($_SESSION['csrf_token']) ?>"
                >
                <input
                    type="hidden"
                    name="action"
                    value="upload_record"
                >   
                

                <div class="form-group">
                    <label for="academic_record">
                        Academic Record PDF
                    </label>

                    <input
                        type="file"
                        id="academic_record"
                        name="academic_record"
                        accept=".pdf,application/pdf"
                        required
                    >

                    <small>
                        English MEU Academic Record only. Maximum size: 10 MB.
                    </small>
                </div>

                <button type="submit" class="btn btn-primary">
                    Upload Academic Record
                </button>
            </form>
        </section>
    </div>

    <section class="academic-record-card record-history-card">
        <h2>Record History</h2>

        <?php if (!$recordHistory): ?>
            <div class="empty-state">
                <p>No academic records have been uploaded yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="record-history-table">
                    <thead>
                        <tr>
                            <th>File Name</th>
                            <th>Size</th>
                            <th>Status</th>
                            <th>Current</th>
                            <th>Uploaded At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($recordHistory as $record): ?>
                            <tr>
                                <td>
                                    <?= escape($record['original_name']) ?>
                                </td>

                                <td>
                                    <?= escape(
                                        formatFileSize(
                                            (int) $record['file_size']
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <span
                                        class="record-status
                                            record-status-<?= escape(
                                                $record['status']
                                            ) ?>"
                                    >
                                        <?= escape(
                                            getStatusLabel($record['status'])
                                        ) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= (int) $record['is_current'] === 1
                                        ? 'Yes'
                                        : 'No' ?>
                                </td>

                                <td>
                                    <?= escape(
                                        date(
                                            'd M Y, h:i A',
                                            strtotime($record['created_at'])
                                        )
                                    ) ?>
                                </td>
                                <td>
    <?php if ($record['status'] === 'failed'): ?>
        <form method="POST">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= escape($_SESSION['csrf_token']) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="retry_analysis"
            >

            <input
                type="hidden"
                name="record_id"
                value="<?= (int) $record['id'] ?>"
            >

            <button
                type="submit"
                class="btn-retry-analysis"
            >
                Retry Analysis
            </button>
        </form>
    <?php else: ?>
        —
    <?php endif; ?>
</td>
                            </tr>

                            <?php if (
                                $record['status'] === 'failed'
                                && !empty($record['analysis_error'])
                            ): ?>
                                <tr>
                                    <td colspan="6">
                                        <strong>Analysis error:</strong>
                                        <?= escape($record['analysis_error']) ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>