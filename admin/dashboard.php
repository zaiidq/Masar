<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/language.php';

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../student/dashboard.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Users statistics
|--------------------------------------------------------------------------
*/

$userStats = $pdo->query(
    "SELECT
        COUNT(*) AS total_users,
        SUM(role = 'student') AS total_students,
        SUM(role = 'admin') AS total_admins
     FROM users"
)->fetch();

/*
|--------------------------------------------------------------------------
| University links statistics
|--------------------------------------------------------------------------
*/

$linkStats = $pdo->query(
    'SELECT
        COUNT(*) AS total_links,
        SUM(is_active = 1) AS active_links,
        SUM(is_active = 0) AS hidden_links
     FROM university_links'
)->fetch();

/*
|--------------------------------------------------------------------------
| Activity log statistics
|--------------------------------------------------------------------------
*/

$activityStats = $pdo->query(
    "SELECT
        COUNT(*) AS total_activity,
        SUM(event_type = 'LOGIN_SUCCESS') AS successful_logins,
        SUM(event_type = 'LOGIN_FAILED') AS failed_logins
     FROM activity_logs"
)->fetch();

$totalUsers = (int) ($userStats['total_users'] ?? 0);
$totalStudents = (int) ($userStats['total_students'] ?? 0);
$totalAdmins = (int) ($userStats['total_admins'] ?? 0);

$totalLinks = (int) ($linkStats['total_links'] ?? 0);
$activeLinks = (int) ($linkStats['active_links'] ?? 0);
$hiddenLinks = (int) ($linkStats['hidden_links'] ?? 0);

$totalActivity = (int) ($activityStats['total_activity'] ?? 0);
$successfulLogins = (int) ($activityStats['successful_logins'] ?? 0);
$failedLogins = (int) ($activityStats['failed_logins'] ?? 0);

$activeLinkShare = $totalLinks > 0
    ? min(100, ($activeLinks / $totalLinks) * 100)
    : 0;

$pageTitle = t('admin_dashboard_page_title');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<main class="main-content admin-page admin-dashboard-page">

    <section class="admin-hero-new">
        <div class="admin-hero-new__copy">
            <span class="admin-eyebrow-new">
                <span class="admin-eyebrow-new__tick"></span>
                <?= htmlspecialchars(t('admin_overview_eyebrow')) ?>
            </span>

            <h1><?= htmlspecialchars(t('admin_overview_headline')) ?></h1>

            <p><?= htmlspecialchars(t('admin_overview_intro')) ?></p>
        </div>

        <img
            class="admin-hero-new__mark"
            src="/masar/assets/brand/masar-mark.svg"
            alt=""
        >
    </section>

    <section class="admin-overview-grid-new">

        <article class="admin-overview-panel-new admin-overview-panel-new--users">
            <div class="admin-overview-panel-new__topline">
                <span><?= htmlspecialchars(t('admin_users_label')) ?></span>
                <span><?= htmlspecialchars(t('admin_registered_label')) ?></span>
            </div>

            <div class="admin-overview-number-new">
                <?= number_format($totalUsers) ?>
            </div>

            <div class="admin-overview-split-new">
                <div>
                    <span><?= htmlspecialchars(t('admin_students_label')) ?></span>
                    <strong><?= number_format($totalStudents) ?></strong>
                </div>

                <div>
                    <span><?= htmlspecialchars(t('admin_administrators_label')) ?></span>
                    <strong><?= number_format($totalAdmins) ?></strong>
                </div>
            </div>
        </article>

        <article class="admin-overview-panel-new admin-overview-panel-new--links">
            <div class="admin-overview-panel-new__topline">
                <span><?= htmlspecialchars(t('admin_resources_label')) ?></span>
                <span><?= htmlspecialchars(t('admin_total_links_label')) ?></span>
            </div>

            <div class="admin-overview-number-new">
                <?= number_format($totalLinks) ?>
            </div>

            <div
                class="admin-link-health-new"
                role="img"
                aria-label="<?= htmlspecialchars(
                    sprintf(
                        t('admin_links_status_summary'),
                        $activeLinks,
                        $hiddenLinks
                    )
                ) ?>"
            >
                <span
                    class="admin-link-health-new__active"
                    style="inline-size: <?= number_format($activeLinkShare, 2, '.', '') ?>%"
                ></span>
            </div>

            <div class="admin-link-legend-new">
                <span>
                    <i class="admin-link-legend-new__dot admin-link-legend-new__dot--active"></i>
                    <?= htmlspecialchars(
                        sprintf(
                            t('admin_active_links_count'),
                            $activeLinks
                        )
                    ) ?>
                </span>

                <span>
                    <i class="admin-link-legend-new__dot admin-link-legend-new__dot--hidden"></i>
                    <?= htmlspecialchars(
                        sprintf(
                            t('admin_hidden_links_count'),
                            $hiddenLinks
                        )
                    ) ?>
                </span>
            </div>
        </article>

    </section>

    <section class="admin-manage-strip-new">
        <div>
            <span class="admin-manage-strip-new__label">
                <?= htmlspecialchars(t('admin_manage_links_eyebrow')) ?>
            </span>

            <h2><?= htmlspecialchars(t('admin_manage_links_title')) ?></h2>

            <p><?= htmlspecialchars(t('admin_manage_links_body')) ?></p>
        </div>

        <a
            href="/masar/admin/university-links.php"
            class="admin-primary-action-new"
        >
            <span><?= htmlspecialchars(t('admin_manage_links_action')) ?></span>
            <span aria-hidden="true">→</span>
        </a>
    </section>

    <!-- Activity Log -->
    <section
        class="admin-manage-strip-new"
        style="margin-top: 18px;"
    >
        <div>
            <span class="admin-manage-strip-new__label">
                Monitoring
            </span>

            <h2>Activity Log</h2>

            <p>
                Monitor important Masar activity including successful
                logins, failed login attempts, user IP addresses,
                uploads and analysis events.
            </p>

            <div
                style="
                    display: flex;
                    flex-wrap: wrap;
                    gap: 18px;
                    margin-top: 14px;
                    font-size: 14px;
                "
            >
                <span>
                    <strong><?= number_format($totalActivity) ?></strong>
                    total events
                </span>

                <span>
                    <strong><?= number_format($successfulLogins) ?></strong>
                    successful logins
                </span>

                <span>
                    <strong><?= number_format($failedLogins) ?></strong>
                    failed logins
                </span>
            </div>
        </div>

        <a
            href="/masar/admin/activity-log.php"
            class="admin-primary-action-new"
        >
            <span>View Activity Log</span>
            <span aria-hidden="true">→</span>
        </a>
    </section>

    <p class="admin-footnote-new">
        <?= htmlspecialchars(t('admin_dashboard_note')) ?>
    </p>

</main>

<?php

require_once __DIR__ . '/../includes/footer.php';