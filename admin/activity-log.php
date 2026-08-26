<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/language.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../student/dashboard.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$eventFilter = trim($_GET['event'] ?? '');
$userFilter = trim($_GET['user'] ?? '');

/*
|--------------------------------------------------------------------------
| Activity logs
|--------------------------------------------------------------------------
*/

$sql = '
    SELECT
        al.id,
        al.user_id,
        al.event_type,
        al.ip_address,
        al.user_agent,
        al.details,
        al.created_at,
        u.full_name,
        u.email
    FROM activity_logs al
    LEFT JOIN users u ON u.id = al.user_id
    WHERE 1 = 1
';

$params = [];

if ($eventFilter !== '') {
    $sql .= ' AND al.event_type = :event_type';
    $params['event_type'] = $eventFilter;
}

if ($userFilter !== '') {
    $sql .= '
        AND (
            CAST(al.user_id AS CHAR) = :user_id
            OR u.full_name LIKE :user_name_search
            OR u.email LIKE :user_email_search
        )
    ';

    $searchValue = '%' . $userFilter . '%';

    $params['user_id'] = $userFilter;
    $params['user_name_search'] = $searchValue;
    $params['user_email_search'] = $searchValue;
}

$sql .= ' ORDER BY al.created_at DESC, al.id DESC LIMIT 300';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$logs = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Available event types
|--------------------------------------------------------------------------
*/

$eventStmt = $pdo->query(
    'SELECT DISTINCT event_type
     FROM activity_logs
     ORDER BY event_type ASC'
);

$eventTypes = $eventStmt->fetchAll(PDO::FETCH_COLUMN);

/*
|--------------------------------------------------------------------------
| Page statistics
|--------------------------------------------------------------------------
*/

$totalShown = count($logs);

$successCount = 0;
$failedCount = 0;

foreach ($logs as $log) {
    if ($log['event_type'] === 'LOGIN_SUCCESS') {
        $successCount++;
    }

    if ($log['event_type'] === 'LOGIN_FAILED') {
        $failedCount++;
    }
}

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function activityBadgeClass(string $eventType): string
{
    return match ($eventType) {
        'LOGIN_SUCCESS' => 'activity-badge activity-badge--success',
        'LOGIN_FAILED' => 'activity-badge activity-badge--danger',
        'REGISTER' => 'activity-badge activity-badge--info',
        'LOGOUT' => 'activity-badge activity-badge--neutral',
        'RECORD_UPLOAD' => 'activity-badge activity-badge--info',
        'ANALYSIS_STARTED' => 'activity-badge activity-badge--warning',
        'ANALYSIS_SUCCESS' => 'activity-badge activity-badge--success',
        'ANALYSIS_FAILED' => 'activity-badge activity-badge--danger',
        default => 'activity-badge',
    };
}

function shortUserAgent(?string $userAgent): string
{
    if (!$userAgent) {
        return 'Unknown';
    }

    if (stripos($userAgent, 'iPhone') !== false) {
        return 'iPhone';
    }

    if (stripos($userAgent, 'iPad') !== false) {
        return 'iPad';
    }

    if (stripos($userAgent, 'Android') !== false) {
        return 'Android';
    }

    if (stripos($userAgent, 'Windows') !== false) {
        return 'Windows';
    }

    if (stripos($userAgent, 'Macintosh') !== false) {
        return 'Mac';
    }

    if (stripos($userAgent, 'Linux') !== false) {
        return 'Linux';
    }

    return 'Other';
}

function activityEventLabel(string $eventType): string
{
    return match ($eventType) {
        'LOGIN_SUCCESS' => 'Login Success',
        'LOGIN_FAILED' => 'Login Failed',
        'REGISTER' => 'Registered',
        'LOGOUT' => 'Logout',
        'RECORD_UPLOAD' => 'Record Upload',
        'ANALYSIS_STARTED' => 'Analysis Started',
        'ANALYSIS_SUCCESS' => 'Analysis Success',
        'ANALYSIS_FAILED' => 'Analysis Failed',
        default => ucwords(
            strtolower(
                str_replace('_', ' ', $eventType)
            )
        ),
    };
}

$pageTitle = 'Activity Log';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<style>
    .admin-activity-page {
        padding-bottom: 64px;
    }

    .activity-summary-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1px;
        margin: 36px 0 28px;
        border: 1px solid #dddcd6;
        background: #dddcd6;
    }

    .activity-summary-card {
        min-height: 118px;
        padding: 24px;
        background: #fbfaf7;
    }

    .activity-summary-card__label {
        display: block;
        margin-bottom: 12px;
        color: #5f6864;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }

    .activity-summary-card strong {
        display: block;
        color: #222222;
        font-size: 38px;
        font-weight: 500;
        line-height: 1;
    }

    .activity-summary-card--success strong {
        color: #28624f;
    }

    .activity-summary-card--danger strong {
        color: #9e3e3e;
    }

    .activity-panel {
        margin-top: 28px;
        border-top: 1px solid #deddd7;
        padding-top: 28px;
    }

    .activity-panel__heading {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 24px;
        margin-bottom: 18px;
    }

    .activity-panel__eyebrow {
        display: block;
        margin-bottom: 7px;
        color: #28624f;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }

    .activity-panel__heading h2 {
        margin: 0;
        color: #202020;
        font-size: 25px;
        font-weight: 500;
    }

    .activity-panel__heading p {
        margin: 7px 0 0;
        color: #6c706e;
        font-size: 14px;
    }

    .activity-count {
        flex: 0 0 auto;
        color: #6c706e;
        font-size: 13px;
        white-space: nowrap;
    }

    .activity-filters {
        display: grid;
        grid-template-columns: 220px minmax(240px, 1fr) auto auto;
        gap: 10px;
        margin-bottom: 18px;
        padding: 16px;
        border: 1px solid #dfded8;
        background: #fbfaf7;
    }

    .activity-filters select,
    .activity-filters input {
        width: 100%;
        min-height: 44px;
        padding: 10px 13px;
        border: 1px solid #d7d6d0;
        border-radius: 0;
        outline: none;
        background: #ffffff;
        color: #222222;
        font: inherit;
    }

    .activity-filters select:focus,
    .activity-filters input:focus {
        border-color: #28624f;
    }

    .activity-filter-button,
    .activity-reset {
        min-height: 44px;
        padding: 10px 18px;
        border-radius: 0;
        font: inherit;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
    }

    .activity-filter-button {
        border: 1px solid #28624f;
        background: #28624f;
        color: #ffffff;
    }

    .activity-filter-button:hover {
        background: #1f5140;
    }

    .activity-reset {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #d7d6d0;
        background: #ffffff;
        color: #333333;
    }

    .activity-reset:hover {
        border-color: #28624f;
        color: #28624f;
    }

    .activity-table-wrap {
        overflow-x: auto;
        border: 1px solid #dfded8;
        background: #ffffff;
    }

    .activity-table {
        width: 100%;
        min-width: 1000px;
        border-collapse: collapse;
    }

    .activity-table th,
    .activity-table td {
        padding: 15px 16px;
        border-bottom: 1px solid #ecebe6;
        text-align: left;
        vertical-align: middle;
    }

    .activity-table th {
        background: #f5f4ef;
        color: #626865;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .activity-table tbody tr {
        transition: background-color 0.15s ease;
    }

    .activity-table tbody tr:hover {
        background: #faf9f5;
    }

    .activity-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .activity-time {
        color: #464b49;
        font-size: 13px;
        white-space: nowrap;
    }

    .activity-user-name {
        margin-bottom: 4px;
        color: #242424;
        font-weight: 600;
    }

    .activity-user-meta {
        color: #808582;
        font-size: 12px;
    }

    .activity-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 5px 9px;
        border-radius: 999px;
        background: #eeeeeb;
        color: #565b59;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.04em;
        white-space: nowrap;
    }

    .activity-badge--success {
        background: #e5f2eb;
        color: #246147;
    }

    .activity-badge--danger {
        background: #f8e8e8;
        color: #a13c3c;
    }

    .activity-badge--warning {
        background: #faf0d9;
        color: #8b671d;
    }

    .activity-badge--info {
        background: #e8eef3;
        color: #3f6179;
    }

    .activity-badge--neutral {
        background: #eeeeeb;
        color: #606563;
    }

    .activity-ip {
        color: #4f5653;
        font-family: monospace;
        font-size: 13px;
        white-space: nowrap;
    }

    .activity-device {
        color: #424846;
        font-size: 13px;
    }

    .activity-details {
        max-width: 300px;
        color: #555b58;
        font-size: 13px;
        word-break: break-word;
    }

    .activity-empty {
        padding: 60px 24px;
        text-align: center;
        color: #747976;
    }

    @media (max-width: 900px) {
        .activity-summary-grid {
            grid-template-columns: 1fr;
        }

        .activity-filters {
            grid-template-columns: 1fr;
        }

        .activity-panel__heading {
            align-items: flex-start;
            flex-direction: column;
            gap: 10px;
        }
    }

    @media (max-width: 700px) {
        .activity-summary-card {
            min-height: auto;
            padding: 20px;
        }

        .activity-summary-card strong {
            font-size: 32px;
        }

        .activity-table th,
        .activity-table td {
            padding: 13px 12px;
        }
    }
</style>

<main class="main-content admin-page admin-activity-page">

    <section class="admin-hero-new">
        <div class="admin-hero-new__copy">

            <span class="admin-eyebrow-new">
                <span class="admin-eyebrow-new__tick"></span>
                Monitoring
            </span>

            <h1>Activity across Masar</h1>

            <p>
                Review important user activity including successful logins,
                failed login attempts, registrations and system events.
            </p>
        </div>

        <img
            class="admin-hero-new__mark"
            src="/masar/assets/brand/masar-mark.svg"
            alt=""
        >
    </section>

    <section class="activity-summary-grid">

        <article class="activity-summary-card">
            <span class="activity-summary-card__label">
                Events shown
            </span>

            <strong>
                <?= number_format($totalShown) ?>
            </strong>
        </article>

        <article class="activity-summary-card activity-summary-card--success">
            <span class="activity-summary-card__label">
                Successful logins
            </span>

            <strong>
                <?= number_format($successCount) ?>
            </strong>
        </article>

        <article class="activity-summary-card activity-summary-card--danger">
            <span class="activity-summary-card__label">
                Failed logins
            </span>

            <strong>
                <?= number_format($failedCount) ?>
            </strong>
        </article>

    </section>

    <section class="activity-panel">

        <div class="activity-panel__heading">
            <div>
                <span class="activity-panel__eyebrow">
                    Monitoring
                </span>

                <h2>Activity Log</h2>

                <p>
                    Search and filter the latest recorded activity
                    across the Masar platform.
                </p>
            </div>

            <div class="activity-count">
                Showing <?= number_format($totalShown) ?> record(s)
            </div>
        </div>

        <form
            class="activity-filters"
            method="GET"
            action=""
        >

            <select
                name="event"
                aria-label="Filter by event"
            >
                <option value="">
                    All events
                </option>

                <?php foreach ($eventTypes as $eventType): ?>

                    <option
                        value="<?= htmlspecialchars(
                            (string) $eventType,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        <?= $eventFilter === $eventType
                            ? 'selected'
                            : '' ?>
                    >
                        <?= htmlspecialchars(
                            activityEventLabel((string) $eventType)
                        ) ?>
                    </option>

                <?php endforeach; ?>
            </select>

            <input
                type="search"
                name="user"
                value="<?= htmlspecialchars(
                    $userFilter,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                placeholder="Search by user ID, name or email"
                aria-label="Search users"
            >

            <button
                type="submit"
                class="activity-filter-button"
            >
                Filter
            </button>

            <a
                class="activity-reset"
                href="/masar/admin/activity-log.php"
            >
                Reset
            </a>

        </form>

        <div class="activity-table-wrap">

            <?php if (!$logs): ?>

                <div class="activity-empty">
                    No activity records found.
                </div>

            <?php else: ?>

                <table class="activity-table">

                    <thead>
                    <tr>
                        <th>Time</th>
                        <th>User</th>
                        <th>Event</th>
                        <th>IP Address</th>
                        <th>Device</th>
                        <th>Details</th>
                    </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($logs as $log): ?>

                        <tr>

                            <td class="activity-time">
                                <?= htmlspecialchars(
                                    date(
                                        'Y-m-d H:i:s',
                                        strtotime(
                                            (string) $log['created_at']
                                        )
                                    )
                                ) ?>
                            </td>

                            <td>

                                <?php if ($log['user_id'] !== null): ?>

                                    <div class="activity-user-name">
                                        <?= htmlspecialchars(
                                            $log['full_name']
                                                ?: 'User #' . $log['user_id']
                                        ) ?>
                                    </div>

                                    <div class="activity-user-meta">

                                        ID:
                                        <?= htmlspecialchars(
                                            (string) $log['user_id']
                                        ) ?>

                                        <?php if (!empty($log['email'])): ?>

                                            ·
                                            <?= htmlspecialchars(
                                                (string) $log['email']
                                            ) ?>

                                        <?php endif; ?>

                                    </div>

                                <?php else: ?>

                                    <span class="activity-user-meta">
                                        Unknown user
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>
                                <span
                                    class="<?= htmlspecialchars(
                                        activityBadgeClass(
                                            (string) $log['event_type']
                                        )
                                    ) ?>"
                                >
                                    <?= htmlspecialchars(
                                        activityEventLabel(
                                            (string) $log['event_type']
                                        )
                                    ) ?>
                                </span>
                            </td>

                            <td class="activity-ip">
                                <?= htmlspecialchars(
                                    $log['ip_address'] ?: '—'
                                ) ?>
                            </td>

                            <td class="activity-device">
                                <?= htmlspecialchars(
                                    shortUserAgent(
                                        $log['user_agent']
                                    )
                                ) ?>
                            </td>

                            <td class="activity-details">
                                <?= htmlspecialchars(
                                    $log['details'] ?: '—'
                                ) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            <?php endif; ?>

        </div>

    </section>

</main>

<?php

require_once __DIR__ . '/../includes/footer.php';