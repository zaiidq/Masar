<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

if (($_SESSION['role'] ?? '') !== 'student') {
    header('Location: /masar/admin/dashboard.php');
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);

function escapeValue(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
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

$recommendations = [];
$progress = [];

if ($record) {
    $recommendationStatement = $pdo->prepare(
        'SELECT *
         FROM record_recommendations
         WHERE record_id = :record_id
           AND is_accepted = 1
         ORDER BY priority'
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
}

$stateLabels = [
    'completed' => 'Completed',
    'in_progress' => 'In Progress',
    'failed' => 'Needs Retake',
    'remaining' => 'Remaining',
];

$totalRecommendedHours = array_sum(
    array_column($recommendations, 'credit_hours')
);

$pageTitle = 'Recommendations';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">

    <header class="page-header">
        <h1>Course Recommendations</h1>

        <p>
            Suggested courses for your next semester, based on your
            academic record.
        </p>
    </header>

    <?php if (!$record): ?>

        <div class="empty-state">
            <p>
                No analyzed academic record was found.
                Upload your MEU academic record to see your progress and
                recommended courses.
            </p>

            <a
                class="btn-primary"
                href="/masar/student/academic-record.php"
            >
                Upload Academic Record
            </a>
        </div>

    <?php else: ?>

        <section class="dashboard-grid">

            <?php foreach ($stateLabels as $state => $label): ?>
                <article class="dashboard-card">
                    <h3><?= escapeValue($label) ?></h3>

                    <p>
                        <?= (int) ($progress[$state]['course_count'] ?? 0) ?>
                        courses
                        &middot;
                        <?= (int) ($progress[$state]['credit_hours'] ?? 0) ?>
                        credit hours
                    </p>
                </article>
            <?php endforeach; ?>

        </section>

        <section class="content-card">
            <h2>Academic Summary</h2>

            <div class="record-summary">
                <p>
                    <strong>Major:</strong>
                    <?= escapeValue($record['major_name'] ?? 'Not available') ?>
                </p>

                <p>
                    <strong>Level:</strong>
                    <?= escapeValue($record['student_level'] ?? 'Not available') ?>
                </p>

                <p>
                    <strong>GPA:</strong>
                    <?= escapeValue((string) ($record['gpa'] ?? 'Not available')) ?>
                </p>

                <p>
                    <strong>Earned Hours:</strong>
                    <?= (int) ($record['earned_hours'] ?? 0) ?>
                    of
                    <?= (int) ($record['plan_hours'] ?? 0) ?>
                </p>

                <p>
                    <strong>Remaining Hours:</strong>
                    <?= (int) ($record['remaining_hours'] ?? 0) ?>
                </p>
            </div>
        </section>

        <section class="content-card">
            <h2>
                Recommended Courses
                <?php if ($totalRecommendedHours > 0): ?>
                    (<?= (int) $totalRecommendedHours ?> credit hours)
                <?php endif; ?>
            </h2>

            <?php if (!$recommendations): ?>

                <p class="empty-state">
                    No courses could be recommended from this record.
                </p>

            <?php else: ?>

                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Course</th>
                                <th>Hours</th>
                                <th>Why this course</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($recommendations as $item): ?>
                                <tr>
                                    <td>
                                        <?= escapeValue($item['course_code']) ?>
                                    </td>

                                    <td>
                                        <?= escapeValue($item['course_name']) ?>
                                    </td>

                                    <td>
                                        <?= (int) $item['credit_hours'] ?>
                                    </td>

                                    <td>
                                        <?= escapeValue($item['reason']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>

            <p class="form-help">
                These recommendations are generated automatically to support
                academic planning. They do not replace official university
                advising.
            </p>
        </section>

    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
