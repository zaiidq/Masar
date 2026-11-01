<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/admin-common.php';

$adminId = (int) $_SESSION['user_id'];
$view = ($_GET['view'] ?? 'open') === 'resolved' ? 'resolved' : 'open';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        adminFlash('error', t('admin_links_error_invalid_request'));
        adminRedirect('/masar/admin/alerts.php');
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'resolve') {
        $alertId = (int) ($_POST['alert_id'] ?? 0);

        $pdo->prepare(
            'UPDATE system_alerts
             SET is_resolved = 1, resolved_by = :admin, resolved_at = NOW()
             WHERE id = :id AND is_resolved = 0'
        )->execute(['admin' => $adminId, 'id' => $alertId]);

        logActivity($pdo, 'ADMIN_ALERT_RESOLVED', $adminId, 'alert=' . $alertId);
        adminFlash('success', t('admin_alerts_resolved_one'));
    }

    if ($action === 'resolve_all') {
        $statement = $pdo->prepare(
            'UPDATE system_alerts
             SET is_resolved = 1, resolved_by = :admin, resolved_at = NOW()
             WHERE is_resolved = 0'
        );
        $statement->execute(['admin' => $adminId]);

        logActivity($pdo, 'ADMIN_ALERT_RESOLVED', $adminId, 'all open alerts (' . $statement->rowCount() . ')');
        adminFlash('success', t('admin_alerts_resolved_all'));
    }

    adminRedirect('/masar/admin/alerts.php');
}

$statement = $pdo->prepare(
    'SELECT a.*, u.full_name AS user_name, u.email AS user_email,
            r.full_name AS resolver_name
     FROM system_alerts a
     LEFT JOIN users u ON u.id = a.user_id
     LEFT JOIN users r ON r.id = a.resolved_by
     WHERE a.is_resolved = :resolved
     ORDER BY FIELD(a.severity, \'critical\', \'warning\', \'info\'), a.created_at DESC
     LIMIT 200'
);
$statement->execute(['resolved' => $view === 'resolved' ? 1 : 0]);
$alerts = $statement->fetchAll();

$openCount = adminOpenAlertCount($pdo);
$flash = adminTakeFlash();

function alertTypeLabel(string $type): string
{
    $key = 'alert_type_' . strtolower($type);
    $label = t($key);

    return $label === $key
        ? ucwords(strtolower(str_replace('_', ' ', $type)))
        : $label;
}

$pageTitle = t('admin_alerts_title');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<main class="main-content admin-page admin-alerts-page">

    <section class="admin-hero-new">
        <div class="admin-hero-new__copy">
            <span class="admin-eyebrow-new">
                <span class="admin-eyebrow-new__tick"></span>
                <?= adminEscape(t('admin_monitoring')) ?>
            </span>

            <h1><?= adminEscape(t('admin_alerts_headline')) ?></h1>
            <p><?= adminEscape(t('admin_alerts_intro')) ?></p>
        </div>

        <img class="admin-hero-new__mark" src="/masar/assets/brand/masar-mark.svg" alt="">
    </section>

    <?php if ($flash): ?>
        <div class="admin-notice-new admin-notice-new--<?= $flash['type'] === 'success' ? 'success' : 'error' ?>" role="status">
            <?= adminEscape($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="admin-tabs">
        <a href="/masar/admin/alerts.php" class="<?= $view === 'open' ? 'is-active' : '' ?>">
            <?= adminEscape(sprintf(t('admin_alerts_open'), $openCount)) ?>
        </a>
        <a href="/masar/admin/alerts.php?view=resolved" class="<?= $view === 'resolved' ? 'is-active' : '' ?>">
            <?= adminEscape(t('admin_alerts_resolved')) ?>
        </a>

        <?php if ($view === 'open' && $openCount > 0): ?>
            <form method="POST" action="/masar/admin/alerts.php" class="admin-tabs__action">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="resolve_all">
                <button type="submit" class="admin-row-action-new"><?= adminEscape(t('admin_alerts_resolve_all')) ?></button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (!$alerts): ?>
        <div class="admin-links-empty-new">
            <img src="/masar/assets/brand/masar-mark.svg" alt="">
            <h3><?= adminEscape($view === 'open' ? t('admin_alerts_empty_open') : t('admin_alerts_empty_resolved')) ?></h3>
        </div>
    <?php else: ?>
        <ul class="admin-alert-list">
            <?php foreach ($alerts as $alert): ?>
                <li class="admin-alert admin-alert--<?= adminEscape($alert['severity']) ?>">
                    <div class="admin-alert__main">
                        <div class="admin-alert__top">
                            <span class="admin-alert__severity"><?= adminEscape(t('alert_severity_' . $alert['severity'])) ?></span>
                            <strong><?= adminEscape(alertTypeLabel($alert['alert_type'])) ?></strong>
                            <time><?= adminEscape(adminFormatDateTime($alert['created_at'])) ?></time>
                        </div>

                        <p class="admin-alert__message" dir="ltr"><?= adminEscape($alert['message']) ?></p>

                        <?php if (!empty($alert['user_email'])): ?>
                            <small>
                                <?= adminEscape(t('admin_alerts_student')) ?>:
                                <a href="/masar/admin/users.php?q=<?= urlencode((string) $alert['user_email']) ?>" dir="ltr">
                                    <?= adminEscape($alert['user_email']) ?>
                                </a>
                            </small>
                        <?php endif; ?>

                        <?php if ($view === 'resolved'): ?>
                            <small>
                                <?= adminEscape(sprintf(
                                    t('admin_alerts_resolved_by'),
                                    $alert['resolver_name'] ?? '—',
                                    adminFormatDateTime($alert['resolved_at'])
                                )) ?>
                            </small>
                        <?php endif; ?>
                    </div>

                    <?php if ($view === 'open'): ?>
                        <form method="POST" action="/masar/admin/alerts.php">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="resolve">
                            <input type="hidden" name="alert_id" value="<?= (int) $alert['id'] ?>">
                            <button type="submit" class="admin-row-action-new"><?= adminEscape(t('admin_alerts_resolve')) ?></button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
