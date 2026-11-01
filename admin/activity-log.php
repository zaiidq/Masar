<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/admin-common.php';

/*
|--------------------------------------------------------------------------
| Filters and pagination
|--------------------------------------------------------------------------
*/

$eventFilter = trim((string) ($_GET['event'] ?? ''));
$userFilter = trim((string) ($_GET['user'] ?? ''));
$perPage = 50;
$page = max(1, (int) ($_GET['page'] ?? 1));

$where = ['1 = 1'];
$params = [];

if ($eventFilter !== '') {
    $where[] = 'al.event_type = :event_type';
    $params['event_type'] = $eventFilter;
}

if ($userFilter !== '') {
    $where[] = '(
        CAST(al.user_id AS CHAR) = :user_id
        OR u.full_name LIKE :user_name_search
        OR u.email LIKE :user_email_search
        OR al.ip_address = :ip_search
    )';

    $params['user_id'] = $userFilter;
    $params['user_name_search'] = '%' . $userFilter . '%';
    $params['user_email_search'] = '%' . $userFilter . '%';
    $params['ip_search'] = $userFilter;
}

$whereSql = implode(' AND ', $where);

$summaryStatement = $pdo->prepare(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(al.event_type = 'LOGIN_SUCCESS'), 0) AS successful_logins,
        COALESCE(SUM(al.event_type = 'LOGIN_FAILED'), 0) AS failed_logins
     FROM activity_logs al
     LEFT JOIN users u ON u.id = al.user_id
     WHERE {$whereSql}"
);
$summaryStatement->execute($params);
$summary = $summaryStatement->fetch();

$totalLogs = (int) $summary['total'];
$totalPages = max(1, (int) ceil($totalLogs / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$statement = $pdo->prepare(
    "SELECT
        al.id, al.user_id, al.event_type, al.ip_address,
        al.user_agent, al.details, al.created_at,
        u.full_name, u.email
     FROM activity_logs al
     LEFT JOIN users u ON u.id = al.user_id
     WHERE {$whereSql}
     ORDER BY al.created_at DESC, al.id DESC
     LIMIT {$perPage} OFFSET {$offset}"
);
$statement->execute($params);
$logs = $statement->fetchAll();

$eventTypes = $pdo->query(
    'SELECT DISTINCT event_type FROM activity_logs ORDER BY event_type'
)->fetchAll(PDO::FETCH_COLUMN);

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function activityBadgeClass(string $eventType): string
{
    return match (true) {
        in_array($eventType, ['LOGIN_SUCCESS', 'ANALYSIS_SUCCESS'], true)
            => 'activity-badge activity-badge--success',
        in_array($eventType, ['LOGIN_FAILED', 'LOGIN_BLOCKED', 'ANALYSIS_FAILED', 'ACCOUNT_DELETED', 'ADMIN_USER_DELETED'], true)
            => 'activity-badge activity-badge--danger',
        in_array($eventType, ['REGISTER', 'RECORD_UPLOAD', 'CHAT_SESSION_STARTED'], true)
            => 'activity-badge activity-badge--info',
        $eventType === 'ANALYSIS_STARTED'
            => 'activity-badge activity-badge--warning',
        $eventType === 'LOGOUT'
            => 'activity-badge activity-badge--neutral',
        default => 'activity-badge',
    };
}

function activityEventLabel(string $eventType): string
{
    $key = 'event_' . strtolower($eventType);
    $label = t($key);

    return $label === $key
        ? ucwords(strtolower(str_replace('_', ' ', $eventType)))
        : $label;
}

function shortUserAgent(?string $userAgent): string
{
    if (!$userAgent) {
        return t('activity_device_unknown');
    }

    foreach (
        ['iPhone' => 'iPhone', 'iPad' => 'iPad', 'Android' => 'Android',
         'Windows' => 'Windows', 'Macintosh' => 'Mac', 'Linux' => 'Linux'] as $needle => $label
    ) {
        if (stripos($userAgent, $needle) !== false) {
            return $label;
        }
    }

    return t('activity_device_other');
}

function activityPageUrl(int $page, string $event, string $user): string
{
    return '/masar/admin/activity-log.php?' . http_build_query(array_filter([
        'event' => $event,
        'user' => $user,
        'page' => $page,
    ]));
}

$pageTitle = t('admin_activity_title');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<main class="main-content admin-page admin-activity-page">

    <section class="admin-hero-new">
        <div class="admin-hero-new__copy">
            <span class="admin-eyebrow-new">
                <span class="admin-eyebrow-new__tick"></span>
                <?= adminEscape(t('admin_monitoring')) ?>
            </span>

            <h1><?= adminEscape(t('admin_activity_headline')) ?></h1>
            <p><?= adminEscape(t('admin_activity_intro')) ?></p>
        </div>

        <img class="admin-hero-new__mark" src="/masar/assets/brand/masar-mark.svg" alt="">
    </section>

    <section class="activity-summary-grid">
        <article class="activity-summary-card">
            <span class="activity-summary-card__label"><?= adminEscape(t('admin_activity_matching')) ?></span>
            <strong><?= number_format($totalLogs) ?></strong>
        </article>

        <article class="activity-summary-card activity-summary-card--success">
            <span class="activity-summary-card__label"><?= adminEscape(t('admin_successful_logins')) ?></span>
            <strong><?= number_format((int) $summary['successful_logins']) ?></strong>
        </article>

        <article class="activity-summary-card activity-summary-card--danger">
            <span class="activity-summary-card__label"><?= adminEscape(t('admin_failed_logins')) ?></span>
            <strong><?= number_format((int) $summary['failed_logins']) ?></strong>
        </article>
    </section>

    <section class="activity-panel">
        <div class="activity-panel__heading">
            <div>
                <span class="activity-panel__eyebrow"><?= adminEscape(t('admin_monitoring')) ?></span>
                <h2><?= adminEscape(t('admin_activity_nav')) ?></h2>
                <p><?= adminEscape(t('admin_activity_filter_intro')) ?></p>
            </div>

            <div class="activity-count">
                <?= adminEscape(sprintf(t('admin_page_of'), $page, $totalPages)) ?>
            </div>
        </div>

        <form class="activity-filters" method="GET" action="/masar/admin/activity-log.php">
            <select name="event" aria-label="<?= adminEscape(t('admin_activity_filter_event')) ?>">
                <option value=""><?= adminEscape(t('admin_activity_all_events')) ?></option>

                <?php foreach ($eventTypes as $eventType): ?>
                    <option
                        value="<?= adminEscape((string) $eventType) ?>"
                        <?= $eventFilter === $eventType ? 'selected' : '' ?>
                    >
                        <?= adminEscape(activityEventLabel((string) $eventType)) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input
                type="search"
                name="user"
                value="<?= adminEscape($userFilter) ?>"
                placeholder="<?= adminEscape(t('admin_activity_search')) ?>"
                aria-label="<?= adminEscape(t('admin_activity_search')) ?>"
            >

            <button type="submit" class="activity-filter-button"><?= adminEscape(t('admin_filter')) ?></button>
            <a class="activity-reset" href="/masar/admin/activity-log.php"><?= adminEscape(t('admin_reset')) ?></a>
        </form>

        <div class="activity-table-wrap">
            <?php if (!$logs): ?>
                <div class="activity-empty"><?= adminEscape(t('admin_activity_empty')) ?></div>
            <?php else: ?>
                <table class="activity-table">
                    <thead>
                        <tr>
                            <th><?= adminEscape(t('admin_activity_time')) ?></th>
                            <th><?= adminEscape(t('admin_users_user')) ?></th>
                            <th><?= adminEscape(t('admin_activity_event')) ?></th>
                            <th><?= adminEscape(t('admin_activity_ip')) ?></th>
                            <th><?= adminEscape(t('admin_activity_device')) ?></th>
                            <th><?= adminEscape(t('admin_activity_details')) ?></th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="activity-time">
                                    <?= adminEscape(adminFormatDateTime((string) $log['created_at'])) ?>
                                </td>

                                <td>
                                    <?php if ($log['user_id'] !== null): ?>
                                        <div class="activity-user-name">
                                            <bdi><?= adminEscape($log['full_name'] ?: sprintf(t('admin_activity_deleted_user'), $log['user_id'])) ?></bdi>
                                        </div>
                                        <div class="activity-user-meta" dir="ltr">
                                            #<?= (int) $log['user_id'] ?>
                                            <?php if (!empty($log['email'])): ?>
                                                · <?= adminEscape((string) $log['email']) ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="activity-user-meta"><?= adminEscape(t('admin_activity_unknown_user')) ?></span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="<?= adminEscape(activityBadgeClass((string) $log['event_type'])) ?>">
                                        <?= adminEscape(activityEventLabel((string) $log['event_type'])) ?>
                                    </span>
                                </td>

                                <td class="activity-ip" dir="ltr"><?= adminEscape($log['ip_address'] ?: '—') ?></td>

                                <td class="activity-device"><?= adminEscape(shortUserAgent($log['user_agent'])) ?></td>

                                <td class="activity-details" dir="ltr"><?= adminEscape($log['details'] ?: '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="admin-pagination" aria-label="<?= adminEscape(t('admin_pagination')) ?>">
                <?php if ($page > 1): ?>
                    <a href="<?= adminEscape(activityPageUrl($page - 1, $eventFilter, $userFilter)) ?>"><?= adminEscape(t('admin_previous')) ?></a>
                <?php endif; ?>
                <span><?= adminEscape(sprintf(t('admin_page_of'), $page, $totalPages)) ?></span>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= adminEscape(activityPageUrl($page + 1, $eventFilter, $userFilter)) ?>"><?= adminEscape(t('admin_next')) ?></a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
