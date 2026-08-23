<?php

declare(strict_types=1);

/*
 * My Path — the student home screen.
 *
 * Answers one question first: how much is left to graduate. Everything
 * else on the page supports that answer or points at the page that
 * carries the detail.
 *
 * All figures come from the analysed record. Nothing here is computed
 * beyond adding up credit hours that are already stored.
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/language.php';

if ($_SESSION['role'] !== 'student') {
    header('Location: ../admin/dashboard.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/* The current record is the newest one that analysed successfully. */
$recordStatement = $pdo->prepare(
    'SELECT *
     FROM academic_records
     WHERE user_id = :user_id
       AND is_current = 1
       AND status = "analyzed"
     LIMIT 1'
);

$recordStatement->execute(['user_id' => $userId]);

$record = $recordStatement->fetch();

/*
 * A failed upload only matters if it happened after the record on
 * display, otherwise the student has already recovered from it.
 */
$failedStatement = $pdo->prepare(
    'SELECT id, original_name
     FROM academic_records
     WHERE user_id = :user_id
       AND status = "failed"
     ORDER BY created_at DESC
     LIMIT 1'
);

$failedStatement->execute(['user_id' => $userId]);

$failedUpload = $failedStatement->fetch();

$recommendations = [];
$failedCourses = [];
$inProgressHours = 0;

if ($record) {
    $recommendationStatement = $pdo->prepare(
        'SELECT course_code, course_name, credit_hours
         FROM record_recommendations
         WHERE record_id = :record_id
           AND is_accepted = 1
         ORDER BY priority'
    );

    $recommendationStatement->execute(['record_id' => $record['id']]);

    $recommendations = $recommendationStatement->fetchAll();

    /*
     * Failed courses are surfaced as a fact, not as a required retake.
     * The record does not say whether a failed elective must be repeated.
     */
    $failedStatement = $pdo->prepare(
        'SELECT course_code
         FROM record_courses
         WHERE record_id = :record_id
           AND completion_state = "failed"'
    );

    $failedStatement->execute(['record_id' => $record['id']]);

    $failedCourses = $failedStatement->fetchAll(PDO::FETCH_COLUMN);

    $hoursStatement = $pdo->prepare(
        'SELECT SUM(credit_hours) AS total
         FROM record_courses
         WHERE record_id = :record_id
           AND completion_state = "in_progress"'
    );

    $hoursStatement->execute(['record_id' => $record['id']]);

    $inProgressHours = (int) ($hoursStatement->fetchColumn() ?: 0);
}

/*
 * Official figures, kept exactly as the record states them. Remaining
 * hours are the university's graduation figure and are never derived
 * from the course list.
 */
$planHours = (int) ($record['plan_hours'] ?? 0);
$earnedHours = (int) ($record['earned_hours'] ?? 0);
$remainingHours = (int) ($record['remaining_hours'] ?? 0);

$gpa = $record && $record['gpa'] !== null
    ? number_format((float) $record['gpa'], 2)
    : null;

/* Bar widths are shares of the plan; the plan is the only denominator. */
$earnedShare = 0.0;
$inProgressShare = 0.0;
$markerShare = 0.0;

if ($planHours > 0) {
    $earnedShare = min(100, $earnedHours / $planHours * 100);
    $inProgressShare = min(100 - $earnedShare, $inProgressHours / $planHours * 100);
    $markerShare = $earnedShare + $inProgressShare;
}

$recommendedHours = array_sum(array_column($recommendations, 'credit_hours'));

$failedCodes = array_flip($failedCourses);

$previewRecommendations = array_slice($recommendations, 0, 5);

/* Eyebrow line: whatever the record actually holds, nothing invented. */
$eyebrowParts = array_filter([
    trim((string) ($_SESSION['full_name'] ?? '')),
    trim((string) ($record['major_name'] ?? '')),
    trim((string) ($record['student_level'] ?? '')),
]);

$analysedOn = null;

if ($record && !empty($record['analyzed_at'])) {
    $analysedOn = date('d M Y', strtotime((string) $record['analyzed_at']));
}

$pageTitle = t('my_path');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content path-page">

<?php if (!$record): ?>

    <?php if ($failedUpload): ?>
        <div class="path-note path-note--attention">
            <div class="path-note__label"><?= e(t('path_needs_attention')) ?></div>

            <p class="path-note__body">
                <?= e(t('path_last_upload_failed')) ?>
            </p>

            <a
                class="path-note__link"
                href="/masar/student/academic-record.php"
            >
                <?= e(t('path_open_record_link')) ?>
            </a>
        </div>
    <?php endif; ?>

    <section class="path-empty">
        <img
            class="path-empty__mark"
            src="/masar/assets/brand/masar-mark.svg"
            alt=""
        >

        <div class="path-empty__content">
            <span class="path-eyebrow">
                <span class="path-eyebrow__tick"></span>
                <?= e(t('my_path')) ?>
            </span>

            <h1 class="path-empty__headline">
                <?= e(t('path_empty_headline')) ?>
            </h1>

            <p class="path-empty__body">
                <?= e(t('path_empty_body')) ?>
            </p>

            <a
                class="path-button"
                href="/masar/student/academic-record.php"
            >
                <?= e(t('path_upload_record')) ?>
            </a>
        </div>
    </section>

<?php else: ?>

    <section class="path-position">
        <img
            class="path-position__mark"
            src="/masar/assets/brand/masar-mark.svg"
            alt=""
        >

        <div class="path-position__main">
            <div class="path-position__lead">
                <?php if ($eyebrowParts): ?>
                    <span class="path-eyebrow">
                        <span class="path-eyebrow__tick"></span>
                        <?= e(implode(' · ', $eyebrowParts)) ?>
                    </span>
                <?php endif; ?>

                <h1 class="path-headline">
                    <?= e(sprintf(t('path_hours_remaining'), $remainingHours)) ?>
                </h1>
            </div>

            <div class="path-figures">
                <?php if ($gpa !== null): ?>
                    <div class="path-figure">
                        <div class="path-figure__label">
                            <?= e(t('path_gpa')) ?>
                        </div>

                        <div class="path-figure__value">
                            <?= e($gpa) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="path-figure">
                    <div class="path-figure__label">
                        <?= e(t('path_earned')) ?>
                    </div>

                    <div class="path-figure__value">
                        <?= $earnedHours ?><span
                            class="path-figure__of"
                        >/<?= $planHours ?></span>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($planHours > 0): ?>
            <div class="path-progress">
                <div
                    class="path-progress__track"
                    role="img"
                    aria-label="<?= e(sprintf(
                        t('path_progress_summary'),
                        $earnedHours,
                        $inProgressHours,
                        $remainingHours,
                        $planHours
                    )) ?>"
                >
                    <span
                        class="path-progress__earned"
                        style="inline-size: <?= round($earnedShare, 2) ?>%"
                    ></span>

                    <span
                        class="path-progress__active"
                        style="inline-size: <?= round($inProgressShare, 2) ?>%"
                    ></span>
                </div>

                <span
                    class="path-progress__marker"
                    style="inset-inline-start: <?= round($markerShare, 2) ?>%"
                ></span>

                <ul class="path-legend">
                    <li class="path-legend__item">
                        <span class="path-legend__swatch path-legend__swatch--earned"></span>
                        <?= e(sprintf(t('path_legend_passed'), $earnedHours)) ?>
                    </li>

                    <li class="path-legend__item">
                        <span class="path-legend__swatch path-legend__swatch--active"></span>
                        <?= e(sprintf(t('path_legend_in_progress'), $inProgressHours)) ?>
                    </li>

                    <li class="path-legend__item">
                        <span class="path-legend__swatch path-legend__swatch--rest"></span>
                        <?= e(sprintf(
                            t('path_legend_remaining'),
                            $remainingHours,
                            $planHours
                        )) ?>
                    </li>
                </ul>
            </div>
        <?php endif; ?>
    </section>

    <section class="path-next">

        <div class="path-next__main">
            <div class="path-section-head">
                <h2 class="path-section-title">
                    <?= e(t('path_recommended_next')) ?>
                </h2>

                <?php if ($previewRecommendations): ?>
                    <span class="path-section-meta">
                        <?= e(sprintf(t('path_credit_hours'), $recommendedHours)) ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($previewRecommendations): ?>

                <ul class="path-course-list">
                    <?php foreach ($previewRecommendations as $item): ?>
                        <li class="path-course">
                            <span
                                class="path-course__code<?=
                                    isset($failedCodes[$item['course_code']])
                                        ? ' path-course__code--failed'
                                        : '' ?>"
                            >
                                <?= e($item['course_code']) ?>
                            </span>

                            <span class="path-course__name">
                                <?= e($item['course_name']) ?>
                            </span>

                            <span class="path-course__hours">
                                <?= e(sprintf(
                                    t('path_hours_short'),
                                    (int) $item['credit_hours']
                                )) ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="path-next__actions">
                    <a
                        class="path-button"
                        href="/masar/student/recommendations.php"
                    >
                        <?= e(t('path_view_all')) ?>
                    </a>

                    <p class="path-next__hint">
                        <?= e(t('path_reasons_elsewhere')) ?>
                    </p>
                </div>

            <?php else: ?>

                <p class="path-next__hint">
                    <?= e(t('path_no_recommendations')) ?>
                </p>

            <?php endif; ?>
        </div>

        <div class="path-next__aside">

            <?php if ($failedUpload && (int) $failedUpload['id'] > (int) $record['id']): ?>
                <div class="path-note path-note--attention">
                    <div class="path-note__label">
                        <?= e(t('path_needs_attention')) ?>
                    </div>

                    <p class="path-note__body">
                        <?= e(t('path_last_upload_failed')) ?>
                    </p>

                    <a
                        class="path-note__link"
                        href="/masar/student/academic-record.php"
                    >
                        <?= e(t('path_open_record_link')) ?>
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($failedCourses): ?>
                <div class="path-note path-note--attention">
                    <div class="path-note__label">
                        <?= e(t('path_needs_attention')) ?>
                    </div>

                    <p class="path-note__body">
                        <?= e(sprintf(
                            t('path_failed_courses'),
                            count($failedCourses)
                        )) ?>
                    </p>

                    <a
                        class="path-note__link"
                        href="/masar/student/academic-record.php"
                    >
                        <?= e(t('path_see_in_record')) ?>
                    </a>
                </div>
            <?php endif; ?>

            <div class="path-note">
                <div class="path-note__label path-note__label--brand">
                    <?= e(t('path_record_on_file')) ?>
                </div>

                <p class="path-note__body">
                    <?php if (!empty($record['original_name'])): ?>
                        <span class="path-note__file">
                            <?= e($record['original_name']) ?>
                        </span>
                    <?php endif; ?>

                    <span class="path-note__meta">
                        <?php if ($analysedOn !== null): ?>
                            <?= e(sprintf(t('path_analysed_on'), $analysedOn)) ?>
                        <?php endif; ?>

                        <?php if (!empty($record['academic_semester'])): ?>
                            ·
                            <?= e(sprintf(
                                t('path_last_semester'),
                                $record['academic_semester']
                            )) ?>
                        <?php endif; ?>
                    </span>
                </p>

                <a
                    class="path-note__link path-note__link--brand"
                    href="/masar/student/academic-record.php"
                >
                    <?= e(t('path_open_record_link')) ?>
                </a>
            </div>

            <p class="path-disclaimer">
                <?= e(t('path_disclaimer')) ?>
            </p>

        </div>

    </section>

<?php endif; ?>

</main>

<?php

require_once __DIR__ . '/../includes/footer.php';
