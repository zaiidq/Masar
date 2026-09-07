<?php

$currentAdminPage = basename(
    (string) parse_url(
        $_SERVER['REQUEST_URI'] ?? '',
        PHP_URL_PATH
    )
);

$adminName = trim(
    (string) ($_SESSION['full_name'] ?? t('admin_role_label'))
);

$adminInitial = 'A';

if (
    $adminName !== ''
    && preg_match('/^\X/u', $adminName, $initialMatch) === 1
) {
    $adminInitial = $initialMatch[0];
}
?>

<header class="admin-site-header">

    <div class="admin-desktop-nav">

        <a
            href="/masar/admin/dashboard.php"
            class="admin-brand"
            aria-label="Masar Admin"
        >
            <img
                class="brand-logo-light"
                src="/masar/assets/brand/masar-logo-horizontal.svg"
                alt="Masar"
            >

            <img
                class="brand-logo-dark"
                src="/masar/assets/brand/masar-logo-horizontal-white.svg"
                alt="Masar"
            >

            <span class="admin-brand__badge">
                <?= htmlspecialchars(t('admin_portal_badge')) ?>
            </span>
        </a>

        <nav
            class="admin-primary-nav"
            aria-label="<?= htmlspecialchars(t('admin_navigation_label')) ?>"
        >
            <a
                href="/masar/admin/dashboard.php"
                class="<?= $currentAdminPage === 'dashboard.php'
                    ? 'is-active'
                    : '' ?>"
            >
                <?= htmlspecialchars(t('admin_dashboard_nav')) ?>
            </a>

            <a
                href="/masar/admin/university-links.php"
                class="<?= $currentAdminPage === 'university-links.php'
                    ? 'is-active'
                    : '' ?>"
            >
                <?= htmlspecialchars(t('admin_links_nav')) ?>
            </a>

            <a
                href="/masar/admin/activity-log.php"
                class="<?= $currentAdminPage === 'activity-log.php'
                    ? 'is-active'
                    : '' ?>"
            >
                Activity Log
            </a>
        </nav>

        <div class="admin-nav-actions">

            <a
                href="<?= htmlspecialchars(
                    $languageSwitchUrl,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                class="admin-language-switch"
            >
                <?= htmlspecialchars(t('switch_language')) ?>
            </a>

            <button
                type="button"
                class="theme-toggle"
                data-theme-toggle
                aria-label="Switch to dark mode"
                aria-pressed="false"
                title="Change theme"
            >
                <span
                    class="theme-toggle__icon"
                    data-theme-icon
                    aria-hidden="true"
                >☾</span>
            </button>

            <div
                class="admin-user-menu"
                data-user-menu
            >
                <button
                    type="button"
                    class="admin-user-chip"
                    aria-label="<?= htmlspecialchars(t('admin_account_menu')) ?>"
                    aria-haspopup="menu"
                    aria-expanded="false"
                    title="<?= htmlspecialchars($adminName) ?>"
                    data-user-menu-toggle
                >
                    <?= htmlspecialchars($adminInitial) ?>
                </button>

                <div
                    class="admin-user-dropdown"
                    role="menu"
                    data-user-menu-panel
                    hidden
                >
                    <div class="admin-user-dropdown__identity">
                        <strong>
                            <?= htmlspecialchars($adminName) ?>
                        </strong>

                        <span>
                            <?= htmlspecialchars(t('admin_role_label')) ?>
                        </span>
                    </div>

                    <a
                        href="/masar/auth/logout.php"
                        role="menuitem"
                        class="admin-user-dropdown__logout"
                    >
                        <?= htmlspecialchars(t('logout')) ?>
                    </a>
                </div>
            </div>

        </div>

    </div>

    <div class="admin-mobile-header">

        <a
            href="/masar/admin/dashboard.php"
            class="admin-mobile-brand"
            aria-label="Masar Admin"
        >
            <img
                class="brand-logo-light"
                src="/masar/assets/brand/masar-logo-horizontal.svg"
                alt="Masar"
            >

            <img
                class="brand-logo-dark"
                src="/masar/assets/brand/masar-logo-horizontal-white.svg"
                alt="Masar"
            >

            <span>
                <?= htmlspecialchars(t('admin_portal_badge')) ?>
            </span>
        </a>

        <div class="admin-mobile-actions">

            <a
                href="<?= htmlspecialchars(
                    $languageSwitchUrl,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                class="admin-language-switch"
            >
                <?= htmlspecialchars(t('switch_language')) ?>
            </a>

            <button
                type="button"
                class="theme-toggle"
                data-theme-toggle
                aria-label="Switch to dark mode"
                aria-pressed="false"
                title="Change theme"
            >
                <span
                    class="theme-toggle__icon"
                    data-theme-icon
                    aria-hidden="true"
                >☾</span>
            </button>

            <div
                class="admin-user-menu"
                data-user-menu
            >
                <button
                    type="button"
                    class="admin-user-chip"
                    aria-label="<?= htmlspecialchars(t('admin_account_menu')) ?>"
                    aria-haspopup="menu"
                    aria-expanded="false"
                    data-user-menu-toggle
                >
                    <?= htmlspecialchars($adminInitial) ?>
                </button>

                <div
                    class="admin-user-dropdown admin-user-dropdown--mobile"
                    role="menu"
                    data-user-menu-panel
                    hidden
                >
                    <div class="admin-user-dropdown__identity">
                        <strong>
                            <?= htmlspecialchars($adminName) ?>
                        </strong>

                        <span>
                            <?= htmlspecialchars(t('admin_role_label')) ?>
                        </span>
                    </div>

                    <a
                        href="/masar/auth/logout.php"
                        role="menuitem"
                        class="admin-user-dropdown__logout"
                    >
                        <?= htmlspecialchars(t('logout')) ?>
                    </a>
                </div>
            </div>

        </div>

    </div>

</header>

<nav
    class="admin-mobile-bottom-nav"
    aria-label="<?= htmlspecialchars(t('admin_mobile_navigation_label')) ?>"
>
    <a
        href="/masar/admin/dashboard.php"
        class="<?= $currentAdminPage === 'dashboard.php'
            ? 'is-active'
            : '' ?>"
    >
        <?= htmlspecialchars(t('admin_dashboard_short')) ?>
    </a>

    <a
        href="/masar/admin/university-links.php"
        class="<?= $currentAdminPage === 'university-links.php'
            ? 'is-active'
            : '' ?>"
    >
        <?= htmlspecialchars(t('admin_links_short')) ?>
    </a>

    <a
        href="/masar/admin/activity-log.php"
        class="<?= $currentAdminPage === 'activity-log.php'
            ? 'is-active'
            : '' ?>"
    >
        Activity Log
    </a>
</nav>
