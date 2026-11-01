<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/admin-common.php';

/*
|--------------------------------------------------------------------------
| Statistics (FR14: system usage)
|--------------------------------------------------------------------------
*/

$userStats = $pdo->query(
    "SELECT
        COUNT(*) AS total_users,
        COALESCE(SUM(role = 'student'), 0) AS total_students,
        COALESCE(SUM(role = 'admin'), 0) AS total_admins,
        COALESCE(SUM(created_at > NOW() - INTERVAL 7 DAY), 0) AS new_this_week
     FROM users"
)->fetch();

$linkStats = $pdo->query(
    'SELECT
        COUNT(*) AS total_links,
        COALESCE(SUM(is_active = 1), 0) AS active_links,
        COALESCE(SUM(is_active = 0), 0) AS hidden_links
     FROM university_links'
)->fetch();

$activityStats = $pdo->query(
    "SELECT
        COALESCE(SUM(event_type = 'LOGIN_SUCCESS'), 0) AS successful_logins,
        COALESCE(SUM(event_type = 'LOGIN_FAILED'), 0) AS failed_logins
     FROM activity_logs
     WHERE created_at > NOW() - INTERVAL 1 DAY"
)->fetch();

$alertStats = $pdo->query(
    "SELECT
        COUNT(*) AS open_alerts,
        COALESCE(SUM(severity = 'critical'), 0) AS critical_alerts
     FROM system_alerts
     WHERE is_resolved = 0"
)->fetch();

/*
|--------------------------------------------------------------------------
| AI evaluation: how often the deterministic validator corrected the model
|--------------------------------------------------------------------------
*/

$analysisStats = $pdo->query(
    "SELECT
        COALESCE(SUM(status = 'analyzed'), 0) AS analyzed,
        COALESCE(SUM(status = 'failed'), 0) AS failed,
        COUNT(DISTINCT CASE WHEN status = 'analyzed' THEN user_id END) AS students_with_analysis
     FROM academic_records"
)->fetch();

$recommendationStats = $pdo->query(
    'SELECT
        COUNT(*) AS total,
        COALESCE(SUM(is_accepted = 1), 0) AS accepted,
        COALESCE(SUM(is_accepted = 0), 0) AS rejected
     FROM record_recommendations'
)->fetch();

$rejectionReasons = $pdo->query(
    "SELECT
        SUBSTRING_INDEX(rejection_reason, ':', 1) AS reason,
        COUNT(*) AS total
     FROM record_recommendations
     WHERE is_accepted = 0
     GROUP BY reason
     ORDER BY total DESC
     LIMIT 5"
)->fetchAll();

$chatStats = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM chat_sessions) AS sessions,
        (SELECT COUNT(*) FROM chat_messages WHERE role = 'user') AS questions,
        (SELECT COUNT(*) FROM chat_messages
            WHERE role = 'user' AND created_at > NOW() - INTERVAL 7 DAY) AS questions_week"
)->fetch();

$totalRecommendations = (int) $recommendationStats['total'];
$acceptedShare = $totalRecommendations > 0
    ? round((int) $recommendationStats['accepted'] / $totalRecommendations * 100, 1)
    : null;

$totalAnalyses = (int) $analysisStats['analyzed'] + (int) $analysisStats['failed'];
$analysisSuccessShare = $totalAnalyses > 0
    ? round((int) $analysisStats['analyzed'] / $totalAnalyses * 100, 1)
    : null;

$totalLinks = (int) $linkStats['total_links'];
$activeLinkShare = $totalLinks > 0
    ? min(100, (int) $linkStats['active_links'] / $totalLinks * 100)
    : 0;

function rejectionReasonLabel(string $reason): string
{
    return match (true) {
        str_starts_with($reason, 'Unmet prerequisite') => t('admin_ai_reason_prerequisite'),
        str_starts_with($reason, 'Course has already been completed') => t('admin_ai_reason_completed'),
        str_starts_with($reason, 'Course is already registered') => t('admin_ai_reason_in_progress'),
        str_starts_with($reason, 'Course is not part') => t('admin_ai_reason_not_in_record'),
        str_starts_with($reason, 'Course credit hours') => t('admin_ai_reason_hours'),
        str_starts_with($reason, 'Exceeds') => t('admin_ai_reason_limit'),
        default => $reason,
    };
}

$pageTitle = t('admin_dashboard_page_title');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<main class="main-content admin-page admin-dashboard-page">

    <section class="admin-hero-new">
        <div class="admin-hero-new__copy">
            <span class="admin-eyebrow-new">
                <span class="admin-eyebrow-new__tick"></span>
                <?= adminEscape(t('admin_overview_eyebrow')) ?>
            </span>

            <h1><?= adminEscape(t('admin_overview_headline')) ?></h1>
            <p><?= adminEscape(t('admin_overview_intro')) ?></p>
        </div>

        <img class="admin-hero-new__mark" src="/masar/assets/brand/masar-mark.svg" alt="">
    </section>

    <?php if ((int) $alertStats['open_alerts'] > 0): ?>
        <a
            href="/masar/admin/alerts.php"
            class="admin-alert-banner <?= (int) $alertStats['critical_alerts'] > 0 ? 'admin-alert-banner--critical' : '' ?>"
        >
            <strong><?= adminEscape(sprintf(t('admin_open_alerts_banner'), (int) $alertStats['open_alerts'])) ?></strong>
            <span><?= adminEscape(t('admin_review_alerts')) ?> →</span>
        </a>
    <?php endif; ?>

    <section class="admin-overview-grid-new admin-overview-grid-new--three">

        <article class="admin-overview-panel-new admin-overview-panel-new--users">
            <div class="admin-overview-panel-new__topline">
                <span><?= adminEscape(t('admin_users_label')) ?></span>
                <a href="/masar/admin/users.php"><?= adminEscape(t('admin_manage')) ?></a>
            </div>

            <div class="admin-overview-number-new"><?= number_format((int) $userStats['total_users']) ?></div>

            <div class="admin-overview-split-new">
                <div>
                    <span><?= adminEscape(t('admin_students_label')) ?></span>
                    <strong><?= number_format((int) $userStats['total_students']) ?></strong>
                </div>
                <div>
                    <span><?= adminEscape(t('admin_administrators_label')) ?></span>
                    <strong><?= number_format((int) $userStats['total_admins']) ?></strong>
                </div>
                <div>
                    <span><?= adminEscape(t('admin_new_this_week')) ?></span>
                    <strong><?= number_format((int) $userStats['new_this_week']) ?></strong>
                </div>
            </div>
        </article>

        <article class="admin-overview-panel-new admin-overview-panel-new--links">
            <div class="admin-overview-panel-new__topline">
                <span><?= adminEscape(t('admin_resources_label')) ?></span>
                <a href="/masar/admin/university-links.php"><?= adminEscape(t('admin_manage')) ?></a>
            </div>

            <div class="admin-overview-number-new"><?= number_format($totalLinks) ?></div>

            <div
                class="admin-link-health-new"
                role="img"
                aria-label="<?= adminEscape(sprintf(
                    t('admin_links_status_summary'),
                    (int) $linkStats['active_links'],
                    (int) $linkStats['hidden_links']
                )) ?>"
            >
                <span
                    class="admin-link-health-new__active"
                    style="inline-size: <?= number_format($activeLinkShare, 2, '.', '') ?>%"
                ></span>
            </div>

            <div class="admin-link-legend-new">
                <span>
                    <i class="admin-link-legend-new__dot admin-link-legend-new__dot--active"></i>
                    <?= adminEscape(sprintf(t('admin_active_links_count'), (int) $linkStats['active_links'])) ?>
                </span>
                <span>
                    <i class="admin-link-legend-new__dot admin-link-legend-new__dot--hidden"></i>
                    <?= adminEscape(sprintf(t('admin_hidden_links_count'), (int) $linkStats['hidden_links'])) ?>
                </span>
            </div>
        </article>

        <article class="admin-overview-panel-new">
            <div class="admin-overview-panel-new__topline">
                <span><?= adminEscape(t('admin_activity_24h')) ?></span>
                <a href="/masar/admin/activity-log.php"><?= adminEscape(t('admin_view_log')) ?></a>
            </div>

            <div class="admin-overview-number-new"><?= number_format((int) $activityStats['successful_logins']) ?></div>

            <div class="admin-overview-split-new">
                <div>
                    <span><?= adminEscape(t('admin_successful_logins')) ?></span>
                    <strong><?= number_format((int) $activityStats['successful_logins']) ?></strong>
                </div>
                <div>
                    <span><?= adminEscape(t('admin_failed_logins')) ?></span>
                    <strong><?= number_format((int) $activityStats['failed_logins']) ?></strong>
                </div>
            </div>
        </article>
    </section>

    <section class="admin-ai-panel">
        <div class="admin-ai-panel__heading">
            <span class="admin-manage-strip-new__label"><?= adminEscape(t('admin_ai_eyebrow')) ?></span>
            <h2><?= adminEscape(t('admin_ai_title')) ?></h2>
            <p><?= adminEscape(t('admin_ai_intro')) ?></p>
        </div>

        <div class="admin-ai-metrics">
            <div class="admin-ai-metric">
                <span><?= adminEscape(t('admin_ai_acceptance')) ?></span>
                <strong><?= $acceptedShare !== null ? adminEscape($acceptedShare . '%') : '—' ?></strong>
                <small><?= adminEscape(sprintf(
                    t('admin_ai_accepted_of'),
                    (int) $recommendationStats['accepted'],
                    $totalRecommendations
                )) ?></small>
            </div>

            <div class="admin-ai-metric">
                <span><?= adminEscape(t('admin_ai_corrected')) ?></span>
                <strong><?= number_format((int) $recommendationStats['rejected']) ?></strong>
                <small><?= adminEscape(t('admin_ai_corrected_help')) ?></small>
            </div>

            <div class="admin-ai-metric">
                <span><?= adminEscape(t('admin_ai_analysis_success')) ?></span>
                <strong><?= $analysisSuccessShare !== null ? adminEscape($analysisSuccessShare . '%') : '—' ?></strong>
                <small><?= adminEscape(sprintf(
                    t('admin_ai_analyses_of'),
                    (int) $analysisStats['analyzed'],
                    $totalAnalyses,
                    (int) $analysisStats['students_with_analysis']
                )) ?></small>
            </div>

            <div class="admin-ai-metric">
                <span><?= adminEscape(t('ask_masar')) ?></span>
                <strong><?= number_format((int) $chatStats['questions']) ?></strong>
                <small><?= adminEscape(sprintf(
                    t('admin_ai_chat_usage'),
                    (int) $chatStats['sessions'],
                    (int) $chatStats['questions_week']
                )) ?></small>
            </div>
        </div>

        <?php if ($rejectionReasons): ?>
            <div class="admin-ai-reasons">
                <h3><?= adminEscape(t('admin_ai_top_reasons')) ?></h3>
                <ul>
                    <?php foreach ($rejectionReasons as $row): ?>
                        <?php $share = (int) $recommendationStats['rejected'] > 0
                            ? (int) $row['total'] / (int) $recommendationStats['rejected'] * 100
                            : 0; ?>
                        <li>
                            <span><?= adminEscape(rejectionReasonLabel((string) $row['reason'])) ?></span>
                            <span class="admin-ai-reasons__bar" aria-hidden="true">
                                <span style="inline-size: <?= number_format($share, 1, '.', '') ?>%"></span>
                            </span>
                            <strong><?= (int) $row['total'] ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </section>

    <p class="admin-footnote-new"><?= adminEscape(t('admin_dashboard_note')) ?></p>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
