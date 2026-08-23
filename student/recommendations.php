<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/language.php';

if (($_SESSION['role'] ?? '') !== 'student') {
    header('Location: /masar/admin/dashboard.php');
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);

function escapeValue(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

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

$recommendations = [];
$progress = [];
$failedCount = 0;

if ($record) {
    $recommendationStatement = $pdo->prepare(
        'SELECT
            rr.*,
            rc.completion_state
         FROM record_recommendations rr
         LEFT JOIN record_courses rc
           ON rc.record_id = rr.record_id
          AND rc.course_code = rr.course_code
         WHERE rr.record_id = :record_id
           AND rr.is_accepted = 1
         ORDER BY rr.priority, rr.id'
    );

    $recommendationStatement->execute(['record_id' => $record['id']]);
    $recommendations = $recommendationStatement->fetchAll();

    $progressStatement = $pdo->prepare(
        'SELECT completion_state,
                COUNT(*) AS course_count,
                SUM(credit_hours) AS credit_hours
         FROM record_courses
         WHERE record_id = :record_id
         GROUP BY completion_state'
    );

    $progressStatement->execute(['record_id' => $record['id']]);

    foreach ($progressStatement->fetchAll() as $row) {
        $progress[$row['completion_state']] = $row;
    }

    $failedCount = (int) ($progress['failed']['course_count'] ?? 0);
}

$totalRecommendedHours = array_sum(
    array_map(
        static fn (array $row): int => (int) ($row['credit_hours'] ?? 0),
        $recommendations
    )
);

$recommendationCount = count($recommendations);
$gpa = $record && $record['gpa'] !== null
    ? number_format((float) $record['gpa'], 2)
    : null;

$pageTitle = t('recommendations');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content recs-page">

    <?php if (!$record): ?>
        <section class="recs-empty">
            <img
                src="/masar/assets/brand/masar-mark.svg"
                alt=""
                class="recs-empty__mark"
            >

            <span class="path-eyebrow">
                <span class="path-eyebrow__tick"></span>
                <?= escapeValue(t('recommendations')) ?>
            </span>

            <h1><?= escapeValue(t('recs_empty_title')) ?></h1>
            <p><?= escapeValue(t('recs_empty_body')) ?></p>

            <a
                href="/masar/student/academic-record.php"
                class="path-button"
            >
                <?= escapeValue(t('recs_upload_record')) ?>
            </a>
        </section>
    <?php else: ?>

        <section class="recs-hero">
            <img
                src="/masar/assets/brand/masar-mark.svg"
                alt=""
                class="recs-hero__mark"
            >

            <div class="recs-hero__main">
                <div class="recs-hero__copy">
                    <span class="path-eyebrow">
                        <span class="path-eyebrow__tick"></span>
                        <?= escapeValue(t('recs_eyebrow')) ?>
                    </span>

                    <h1>
                        <?= escapeValue(sprintf(
                            t('recs_headline'),
                            $recommendationCount
                        )) ?>
                    </h1>

                    <p><?= escapeValue(t('recs_intro')) ?></p>
                </div>

                <div class="recs-hero__hours">
                    <span><?= escapeValue(t('recs_suggested')) ?></span>
                    <strong><?= (int) $totalRecommendedHours ?></strong>
                    <small><?= escapeValue(t('recs_credit_hours_label')) ?></small>
                </div>
            </div>

            <div class="recs-context">
                <?php if (!empty($record['major_name'])): ?>
                    <span lang="en" dir="ltr">
                        <?= escapeValue($record['major_name']) ?>
                    </span>
                <?php endif; ?>

                <?php if ($gpa !== null): ?>
                    <span>
                        <?= escapeValue(sprintf(t('recs_context_gpa'), $gpa)) ?>
                    </span>
                <?php endif; ?>

                <span>
                    <?= escapeValue(sprintf(
                        t('recs_context_earned'),
                        (int) ($record['earned_hours'] ?? 0),
                        (int) ($record['plan_hours'] ?? 0)
                    )) ?>
                </span>

                <span>
                    <?= escapeValue(sprintf(
                        t('recs_context_remaining'),
                        (int) ($record['remaining_hours'] ?? 0)
                    )) ?>
                </span>

                <?php if ($failedCount > 0): ?>
                    <span class="recs-context__attention">
                        <?= escapeValue(sprintf(
                            t('recs_context_failed'),
                            $failedCount
                        )) ?>
                    </span>
                <?php endif; ?>
            </div>
        </section>

        <section class="recs-plan">
            <div class="recs-plan__meta">
                <?= escapeValue(sprintf(
                    t('recs_readonly_meta'),
                    $recommendationCount
                )) ?>
            </div>

            <?php if (!$recommendations): ?>
                <div class="record-empty-inline">
                    <?= escapeValue(t('recs_no_courses')) ?>
                </div>
            <?php else: ?>
                <div class="recs-table-wrap">
                    <table class="recs-table">
                        <thead>
                            <tr>
                                <th class="is-priority"><?= escapeValue(t('recs_priority')) ?></th>
                                <th><?= escapeValue(t('recs_code')) ?></th>
                                <th><?= escapeValue(t('recs_course')) ?></th>
                                <th class="is-hours"><?= escapeValue(t('recs_hours')) ?></th>
                                <th><?= escapeValue(t('recs_reason')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recommendations as $index => $item): ?>
                                <?php
                                $isFailed =
                                    ($item['completion_state'] ?? '') === 'failed';
                                $priority = (int) ($item['priority'] ?? 0);

                                if ($priority <= 0) {
                                    $priority = $index + 1;
                                }
                                ?>
                                <tr>
                                    <td class="recs-priority record-data-ltr">
                                        <?= $priority ?>
                                    </td>
                                    <td
                                        class="recs-code <?= $isFailed
                                            ? 'recs-code--failed'
                                            : '' ?>"
                                        dir="ltr"
                                    >
                                        <?= escapeValue($item['course_code']) ?>
                                    </td>
                                    <td class="recs-course" lang="en" dir="ltr">
                                        <?= escapeValue($item['course_name']) ?>
                                    </td>
                                    <td class="recs-hours record-data-ltr">
                                        <?= (int) $item['credit_hours'] ?>
                                    </td>
                                    <td class="recs-reason" lang="en" dir="ltr">
                                        <?= escapeValue($item['reason'] ?? '') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="recs-mobile-list">
                    <?php foreach ($recommendations as $index => $item): ?>
                        <?php
                        $isFailed =
                            ($item['completion_state'] ?? '') === 'failed';
                        $priority = (int) ($item['priority'] ?? 0);

                        if ($priority <= 0) {
                            $priority = $index + 1;
                        }
                        ?>
                        <article class="recs-mobile-item <?= $isFailed
                            ? 'recs-mobile-item--failed'
                            : '' ?>">
                            <div class="recs-mobile-item__top">
                                <span class="recs-priority record-data-ltr">
                                    <?= $priority ?>
                                </span>

                                <span
                                    class="recs-code <?= $isFailed
                                        ? 'recs-code--failed'
                                        : '' ?>"
                                    dir="ltr"
                                >
                                    <?= escapeValue($item['course_code']) ?>
                                </span>

                                <span class="recs-hours">
                                    <?= (int) $item['credit_hours'] ?>
                                    <?= escapeValue(t('recs_hours')) ?>
                                </span>
                            </div>

                            <h2 lang="en" dir="ltr">
                                <?= escapeValue($item['course_name']) ?>
                            </h2>

                            <?php if (!empty($item['reason'])): ?>
                                <p lang="en" dir="ltr">
                                    <?= escapeValue($item['reason']) ?>
                                </p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="recs-footnotes">
            <div class="recs-explainer">
                <strong><?= escapeValue(t('recs_how_title')) ?></strong>
                <p><?= escapeValue(t('recs_how_body')) ?></p>
            </div>

            <div class="recs-disclaimer">
                <p><?= escapeValue(t('recs_disclaimer')) ?></p>
            </div>
        </section>

    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
