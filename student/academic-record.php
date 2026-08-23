<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/language.php';
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

function escape(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function formatFileSize(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 2) . ' MB';
    }

    return number_format($bytes / 1024, 2) . ' KB';
}

function formatRecordDate(?string $value): string
{
    if (!$value) {
        return t('not_available');
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return t('not_available');
    }

    if (currentLanguage() === 'ar') {
        return date('Y/m/d H:i', $timestamp);
    }

    return date('d M Y, h:i A', $timestamp);
}

function getStatusLabel(string $status): string
{
    return match ($status) {
        'uploaded' => t('record_status_uploaded'),
        'processing' => t('record_status_processing'),
        'analyzed' => t('record_status_analyzed'),
        'failed' => t('record_status_failed'),
        default => t('record_status_unknown'),
    };
}

function getCourseStateLabel(string $state): string
{
    return match ($state) {
        'completed' => t('course_state_completed'),
        'in_progress' => t('course_state_in_progress'),
        'failed' => t('course_state_failed'),
        default => t('course_state_remaining'),
    };
}

$errors = [];

$successMessage = $_SESSION['academic_record_success'] ?? null;
unset($_SESSION['academic_record_success']);

$autoAnalyzeRecordId =
    (int) ($_SESSION['academic_record_auto_analyze_id'] ?? 0);

unset($_SESSION['academic_record_auto_analyze_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken)
        || !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        $errors[] = t('record_error_invalid_request');
    }

    $action = $_POST['action'] ?? 'upload_record';
    $uploadedFile = $_FILES['academic_record'] ?? null;

    if (!$errors && $action === 'upload_record') {
        if (!is_array($uploadedFile)) {
            $errors[] = t('record_error_select_pdf');
        } else {
            $uploadError = (int) ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE);

            if ($uploadError !== UPLOAD_ERR_OK) {
                $errors[] = match ($uploadError) {
                    UPLOAD_ERR_INI_SIZE,
                    UPLOAD_ERR_FORM_SIZE => t('record_error_too_large'),
                    UPLOAD_ERR_PARTIAL => t('record_error_partial'),
                    UPLOAD_ERR_NO_FILE => t('record_error_select_pdf'),
                    default => t('record_error_upload_failed'),
                };
            }
        }
    }

    if (
        !$errors
        && $action === 'upload_record'
        && is_array($uploadedFile)
    ) {
        $originalName = basename((string) $uploadedFile['name']);
        $temporaryPath = (string) $uploadedFile['tmp_name'];
        $fileSize = (int) $uploadedFile['size'];
        $maximumFileSize = 10 * 1024 * 1024;

        if ($fileSize <= 0) {
            $errors[] = t('record_error_empty');
        }

        if ($fileSize > $maximumFileSize) {
            $errors[] = t('record_error_max_10mb');
        }

        if (!is_uploaded_file($temporaryPath)) {
            $errors[] = t('record_error_unverified_upload');
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension !== 'pdf') {
            $errors[] = t('record_error_pdf_only');
        }

        if (!$errors) {
            if (!class_exists('finfo')) {
                $errors[] = t('record_error_mime_unavailable');
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
                    $errors[] = t('record_error_invalid_pdf');
                }
            }
        }

        if (!$errors) {
            $fileHandle = fopen($temporaryPath, 'rb');

            if ($fileHandle === false) {
                $errors[] = t('record_error_pdf_unreadable');
            } else {
                $fileSignature = fread($fileHandle, 5);
                fclose($fileHandle);

                if ($fileSignature !== '%PDF-') {
                    $errors[] = t('record_error_pdf_signature');
                }
            }
        }

        if (!$errors) {
            try {
                $recordText = extractAcademicRecordPdfText($temporaryPath);
                $recordValidation = validateEnglishMeuAcademicRecord($recordText);

                if (!$recordValidation['valid']) {
                    $errors[] = t('record_error_not_meu_english');
                }
            } catch (Throwable $exception) {
                $errors[] = t('record_error_read_record');
            }
        }

        if (!$errors) {
            $fileHash = hash_file('sha256', $temporaryPath);

            if ($fileHash === false) {
                $errors[] = t('record_error_process_file');
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
                    $errors[] = t('record_error_duplicate');
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
                $errors[] = t('record_error_storage_create');
            }

            if (!$errors && !is_writable($storageDirectory)) {
                $errors[] = t('record_error_storage_write');
            }

            $destinationPath =
                $storageDirectory
                . DIRECTORY_SEPARATOR
                . $storedName;

            if (
                !$errors
                && !move_uploaded_file($temporaryPath, $destinationPath)
            ) {
                $errors[] = t('record_error_save_file');
            }

            if (!$errors) {
                $relativePath = 'storage/academic-records/' . $storedName;

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

                    $_SESSION['academic_record_auto_analyze_id'] = $recordId;
                    $_SESSION['academic_record_success'] =
                        t('record_success_uploaded_starting');

                    header('Location: /masar/student/academic-record.php');
                    exit;
                } catch (PDOException $exception) {
                    if (is_file($destinationPath)) {
                        unlink($destinationPath);
                    }

                    if ($exception->getCode() === '23000') {
                        $errors[] = t('record_error_duplicate');
                    } else {
                        $errors[] = t('record_error_save_database');
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

$currentStatement->execute(['user_id' => $userId]);
$currentRecord = $currentStatement->fetch();

$historyStatement = $pdo->prepare(
    'SELECT *
     FROM academic_records
     WHERE user_id = :user_id
     ORDER BY created_at DESC'
);

$historyStatement->execute(['user_id' => $userId]);
$recordHistory = $historyStatement->fetchAll();
$latestRecord = $recordHistory[0] ?? null;

$currentCourses = [];

if ($currentRecord) {
    $courseStatement = $pdo->prepare(
        'SELECT
            requirement_type,
            course_code,
            course_name,
            credit_hours,
            mark,
            completion_state,
            semester_code
         FROM record_courses
         WHERE record_id = :record_id
         ORDER BY id ASC'
    );

    $courseStatement->execute(['record_id' => $currentRecord['id']]);
    $currentCourses = $courseStatement->fetchAll();
}

$pageTitle = t('academic_record');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content record-page">

    <section class="record-hero">
        <div class="record-hero__copy">
            <span class="path-eyebrow">
                <span class="path-eyebrow__tick"></span>
                <?= escape(t('record_eyebrow')) ?>
            </span>

            <h1 class="record-hero__title">
                <?= escape(t('record_headline')) ?>
            </h1>

            <p class="record-hero__intro">
                <?= escape(t('record_intro')) ?>
            </p>
        </div>

        <?php if ($currentRecord): ?>
            <div class="record-hero__file">
                <span class="record-data-ltr">
                    <?= escape($currentRecord['original_name']) ?>
                    ·
                    <?= escape(formatFileSize((int) $currentRecord['file_size'])) ?>
                </span>

                <small>
                    <?= escape(formatRecordDate($currentRecord['analyzed_at'] ?? null)) ?>
                    <?php if (!empty($currentRecord['ai_model'])): ?>
                        · <span class="record-data-ltr"><?= escape($currentRecord['ai_model']) ?></span>
                    <?php endif; ?>
                </small>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($errors): ?>
        <div class="record-alert record-alert--error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= escape($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div
        id="analysis-progress-alert"
        class="record-alert record-alert--success"
        <?= $successMessage ? '' : 'hidden' ?>
    >
        <?= $successMessage ? escape($successMessage) : '' ?>
    </div>

    <div class="record-layout">

        <aside class="record-sidebar-panel">
            <section class="record-upload-panel">
                <img
                    src="/masar/assets/brand/masar-mark.svg"
                    alt=""
                    class="record-upload-panel__mark"
                >

                <div class="record-upload-panel__content">
                    <h2><?= escape(t('record_upload_newer')) ?></h2>
                    <p><?= escape(t('record_upload_help')) ?></p>

                    <form
                        method="POST"
                        enctype="multipart/form-data"
                        class="record-upload-form"
                    >
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= escape($_SESSION['csrf_token']) ?>"
                        >
                        <input type="hidden" name="action" value="upload_record">

                        <label
                            class="record-file-control"
                            for="academic_record"
                        >
                            <span><?= escape(t('record_choose_file')) ?></span>
                            <input
                                type="file"
                                id="academic_record"
                                name="academic_record"
                                accept=".pdf,application/pdf"
                                onchange="this.form.requestSubmit()"
                                required
                            >
                        </label>
                    </form>
                </div>
            </section>

            <?php if ($latestRecord): ?>
                <section class="record-status-panel">
                    <div class="record-kicker">
                        <?= escape(t('record_analysis_status')) ?>
                    </div>

                    <div class="record-status-line">
                        <span
                            class="record-status-dot record-status-dot--<?= escape($latestRecord['status']) ?>"
                        ></span>
                        <strong>
                            <?= escape(getStatusLabel((string) $latestRecord['status'])) ?>
                        </strong>
                    </div>

                    <p>
                        <?php if ($latestRecord['status'] === 'processing'): ?>
                            <?= escape(t('record_processing_message')) ?>
                        <?php elseif ($latestRecord['status'] === 'failed'): ?>
                            <?= escape(t('record_analysis_failed')) ?>
                        <?php elseif ($latestRecord['status'] === 'analyzed'): ?>
                            <?= escape(t('record_analysis_complete')) ?>
                        <?php else: ?>
                            <?= escape(t('record_success_uploaded_starting')) ?>
                        <?php endif; ?>
                    </p>
                </section>
            <?php endif; ?>

            <section class="record-current-panel">
                <div class="record-kicker">
                    <?= escape(t('record_current_analyzed')) ?>
                </div>

                <?php if ($currentRecord): ?>
                    <dl class="record-current-list">
                        <div>
                            <dt><?= escape(t('record_last_academic_semester')) ?></dt>
                            <dd>
                                <?= escape($currentRecord['academic_semester'] ?? t('not_available')) ?>
                                <small><?= escape(t('record_as_read_pdf')) ?></small>
                            </dd>
                        </div>
                        <div>
                            <dt><?= escape(t('record_level')) ?></dt>
                            <dd><?= escape($currentRecord['student_level'] ?? t('not_available')) ?></dd>
                        </div>
                        <div>
                            <dt><?= escape(t('record_gpa')) ?></dt>
                            <dd class="record-data-ltr">
                                <?= escape(
                                    $currentRecord['gpa'] !== null
                                        ? number_format((float) $currentRecord['gpa'], 2)
                                        : t('not_available')
                                ) ?>
                            </dd>
                        </div>
                        <div>
                            <dt><?= escape(t('record_hours')) ?></dt>
                            <dd>
                                <?= escape(sprintf(
                                    t('record_hours_summary'),
                                    (int) ($currentRecord['earned_hours'] ?? 0),
                                    (int) ($currentRecord['plan_hours'] ?? 0),
                                    (int) ($currentRecord['remaining_hours'] ?? 0)
                                )) ?>
                            </dd>
                        </div>
                        <div>
                            <dt><?= escape(t('record_status')) ?></dt>
                            <dd>
                                <span class="record-pill record-pill--analyzed">
                                    <?= escape(t('record_status_analyzed')) ?>
                                </span>
                            </dd>
                        </div>
                    </dl>
                <?php else: ?>
                    <p class="record-muted-copy">
                        <?= escape(t('record_no_current')) ?>
                    </p>
                <?php endif; ?>
            </section>
        </aside>

        <div class="record-data-column">
            <section class="record-table-section">
                <div class="record-section-heading">
                    <h2><?= escape(t('record_courses_title')) ?></h2>
                    <span>
                        <?= escape(sprintf(t('record_rows_shown'), count($currentCourses))) ?>
                    </span>
                </div>

                <?php if (!$currentCourses): ?>
                    <div class="record-empty-inline">
                        <?= escape(t('record_no_courses')) ?>
                    </div>
                <?php else: ?>
                    <div class="record-table-wrap">
                        <table class="record-data-table">
                            <thead>
                                <tr>
                                    <th><?= escape(t('record_code')) ?></th>
                                    <th><?= escape(t('record_course')) ?></th>
                                    <th><?= escape(t('record_semester')) ?></th>
                                    <th class="is-numeric"><?= escape(t('record_credit_hours')) ?></th>
                                    <th><?= escape(t('record_course_status')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($currentCourses as $course): ?>
                                    <tr>
                                        <td class="record-course-code record-data-ltr">
                                            <?= escape($course['course_code']) ?>
                                        </td>
                                        <td class="record-course-name" lang="en" dir="ltr">
                                            <?= escape($course['course_name']) ?>
                                        </td>
                                        <td class="record-data-ltr record-table-muted">
                                            <?= escape($course['semester_code'] ?: '—') ?>
                                        </td>
                                        <td class="is-numeric record-data-ltr">
                                            <?= (int) $course['credit_hours'] ?>
                                        </td>
                                        <td>
                                            <span
                                                class="record-pill record-pill--<?= escape($course['completion_state']) ?>"
                                            >
                                                <?= escape(getCourseStateLabel((string) $course['completion_state'])) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="record-table-section record-history-section">
                <div class="record-section-heading">
                    <h2><?= escape(t('record_history')) ?></h2>
                    <span>
                        <?= escape(sprintf(t('record_files_count'), count($recordHistory))) ?>
                    </span>
                </div>

                <?php if (!$recordHistory): ?>
                    <div class="record-empty-inline">
                        <?= escape(t('record_no_history')) ?>
                    </div>
                <?php else: ?>
                    <div class="record-table-wrap">
                        <table class="record-data-table record-history-table-new">
                            <thead>
                                <tr>
                                    <th><?= escape(t('record_file')) ?></th>
                                    <th><?= escape(t('record_size')) ?></th>
                                    <th><?= escape(t('record_uploaded_at')) ?></th>
                                    <th><?= escape(t('record_status')) ?></th>
                                    <th><?= escape(t('record_current')) ?></th>
                                    <th><?= escape(t('record_actions')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recordHistory as $record): ?>
                                    <tr>
                                        <td class="record-history-file record-data-ltr">
                                            <?= escape($record['original_name']) ?>

                                            <?php if (
                                                $record['status'] === 'failed'
                                                && !empty($record['analysis_error'])
                                            ): ?>
                                                <small class="record-history-error" dir="auto">
                                                    <?= escape(t('record_analysis_error')) ?>:
                                                    <?= escape($record['analysis_error']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="record-data-ltr record-table-muted">
                                            <?= escape(formatFileSize((int) $record['file_size'])) ?>
                                        </td>
                                        <td class="record-table-muted">
                                            <?= escape(formatRecordDate($record['created_at'] ?? null)) ?>
                                        </td>
                                        <td>
                                            <span
                                                class="record-pill record-pill--<?= escape($record['status']) ?> record-status record-status-<?= escape($record['status']) ?>"
                                            >
                                                <?= escape(getStatusLabel((string) $record['status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ((int) $record['is_current'] === 1): ?>
                                                <span class="record-pill record-pill--current">
                                                    <?= escape(t('yes')) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="record-table-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($record['status'] === 'failed'): ?>
                                                <form
                                                    method="POST"
                                                    class="retry-analysis-form"
                                                >
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
                                                        class="record-retry-button"
                                                    >
                                                        <?= escape(t('record_retry')) ?>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="record-table-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</main>

<?php if ($autoAnalyzeRecordId > 0): ?>
    <form
        id="auto-analysis-form"
        class="retry-analysis-form"
        method="POST"
        hidden
    >
        <input
            type="hidden"
            name="csrf_token"
            value="<?= escape($_SESSION['csrf_token']) ?>"
        >
        <input
            type="hidden"
            name="record_id"
            value="<?= $autoAnalyzeRecordId ?>"
        >
    </form>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const retryForms = document.querySelectorAll('.retry-analysis-form');
    const progressAlert = document.getElementById('analysis-progress-alert');

    const messages = {
        analyzing: <?= json_encode(t('record_analyzing'), JSON_UNESCAPED_UNICODE) ?>,
        retry: <?= json_encode(t('record_retry'), JSON_UNESCAPED_UNICODE) ?>,
        processing: <?= json_encode(t('record_status_processing'), JSON_UNESCAPED_UNICODE) ?>,
        failed: <?= json_encode(t('record_status_failed'), JSON_UNESCAPED_UNICODE) ?>,
        progress: <?= json_encode(t('record_processing_message'), JSON_UNESCAPED_UNICODE) ?>,
        uploadProgress: <?= json_encode(t('record_upload_processing_message'), JSON_UNESCAPED_UNICODE) ?>,
        serverResponse: <?= json_encode(t('record_error_server_response'), JSON_UNESCAPED_UNICODE) ?>,
        analysisFailed: <?= json_encode(t('record_error_analysis_failed'), JSON_UNESCAPED_UNICODE) ?>
    };

    retryForms.forEach(function (form) {
        form.addEventListener('submit', async function (event) {
            event.preventDefault();

            const button = form.querySelector('button[type="submit"]');
            const row = form.closest('tr');
            const statusBadge = row
                ? row.querySelector('.record-status')
                : null;

            if (button) {
                button.disabled = true;
                button.textContent = messages.analyzing;
            }

            if (statusBadge) {
                statusBadge.textContent = messages.processing;
                statusBadge.className =
                    'record-pill record-pill--processing record-status record-status-processing';
            }

            if (progressAlert) {
                progressAlert.hidden = false;
                progressAlert.className =
                    'record-alert record-alert--success';
                progressAlert.textContent =
                    form.id === 'auto-analysis-form'
                        ? messages.uploadProgress
                        : messages.progress;
            }

            try {
                const response = await fetch(
                    '/masar/student/analyze-academic-record.php',
                    {
                        method: 'POST',
                        body: new FormData(form),
                        credentials: 'same-origin'
                    }
                );

                let result;

                try {
                    result = await response.json();
                } catch (error) {
                    throw new Error(messages.serverResponse);
                }

                if (!response.ok || !result.success) {
                    throw new Error(
                        result.message || messages.analysisFailed
                    );
                }

                window.location.reload();
            } catch (error) {
                if (progressAlert) {
                    progressAlert.hidden = false;
                    progressAlert.className =
                        'record-alert record-alert--error';
                    progressAlert.textContent =
                        error.message || messages.analysisFailed;
                }

                if (button) {
                    button.disabled = false;
                    button.textContent = messages.retry;
                }

                if (statusBadge) {
                    statusBadge.textContent = messages.failed;
                    statusBadge.className =
                        'record-pill record-pill--failed record-status record-status-failed';
                }
            }
        });
    });

    const processingRecord = document.querySelector(
        '.record-status-processing'
    );

    if (processingRecord) {
        window.setTimeout(function () {
            window.location.reload();
        }, 5000);
    }

    const autoAnalysisForm = document.getElementById('auto-analysis-form');

    if (autoAnalysisForm) {
        autoAnalysisForm.requestSubmit();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
