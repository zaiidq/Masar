<?php

$currentStudentPage = basename(
    (string) parse_url(
        $_SERVER['REQUEST_URI'] ?? '',
        PHP_URL_PATH
    )
);

$fullName = trim(
    (string) ($_SESSION['full_name'] ?? 'Student')
);

$userInitial = 'S';

if (
    $fullName !== ''
    && preg_match('/^\X/u', $fullName, $initialMatch) === 1
) {
    $userInitial = $initialMatch[0];
}
?>

<header class="student-site-header">

    <div class="student-desktop-nav">

        <a
            href="/masar/student/dashboard.php"
            class="student-brand"
            aria-label="Masar"
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
        </a>

        <nav
            class="student-primary-nav"
            aria-label="<?= htmlspecialchars(t('nav_student_label'), ENT_QUOTES) ?>"
        >
            <a
                href="/masar/student/dashboard.php"
                class="<?= $currentStudentPage === 'dashboard.php'
                    ? 'is-active'
                    : '' ?>"
            >
                <?= htmlspecialchars(t('my_path')) ?>
            </a>

            <a
                href="/masar/student/academic-record.php"
                class="<?= $currentStudentPage === 'academic-record.php'
                    ? 'is-active'
                    : '' ?>"
            >
                <?= htmlspecialchars(t('academic_record')) ?>
            </a>

            <a
                href="/masar/student/recommendations.php"
                class="<?= $currentStudentPage === 'recommendations.php'
                    ? 'is-active'
                    : '' ?>"
            >
                <?= htmlspecialchars(t('recommendations')) ?>
            </a>

            <a
                href="/masar/student/university-links.php"
                class="<?= $currentStudentPage === 'university-links.php'
                    ? 'is-active'
                    : '' ?>"
            >
                <?= htmlspecialchars(t('resources')) ?>
            </a>
        </nav>

        <div class="student-nav-actions">

            <a
                href="<?= htmlspecialchars(
                    $languageSwitchUrl,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                class="student-language-switch"
            >
                <?= htmlspecialchars(t('switch_language')) ?>
            </a>

            <?php require __DIR__ . '/theme-toggle.php'; ?>

            <a
                href="/masar/student/ask-masar.php"
                class="student-ask-masar <?= $currentStudentPage === 'ask-masar.php'
                    ? 'is-active'
                    : '' ?>"
            >
                <img
                    src="/masar/assets/brand/masar-mark.svg"
                    alt=""
                    aria-hidden="true"
                >

                <?= htmlspecialchars(t('ask_masar')) ?>
            </a>

            <div
                class="student-user-menu"
                data-user-menu
            >
                <button
                    type="button"
                    class="student-user-chip <?= $currentStudentPage === 'profile.php'
                        ? 'is-active'
                        : '' ?>"
                    aria-label="<?= htmlspecialchars(t('profile')) ?>"
                    aria-haspopup="menu"
                    aria-expanded="false"
                    title="<?= htmlspecialchars($fullName) ?>"
                    data-user-menu-toggle
                >
                    <?= htmlspecialchars($userInitial) ?>
                </button>

                <div
                    class="student-user-dropdown"
                    role="menu"
                    data-user-menu-panel
                    hidden
                >
                    <a
                        href="/masar/student/profile.php"
                        role="menuitem"
                        class="<?= $currentStudentPage === 'profile.php'
                            ? 'is-active'
                            : '' ?>"
                    >
                        <?= htmlspecialchars(t('profile')) ?>
                    </a>

                    <form method="POST" action="/masar/auth/logout.php" class="user-menu-logout-form">
                        <?= csrfField() ?>
                        <button type="submit" role="menuitem" class="student-user-dropdown__logout">
                            <?= htmlspecialchars(t('logout')) ?>
                        </button>
                    </form>
                </div>
            </div>

        </div>

    </div>

    <div class="student-mobile-header">

        <a
            href="/masar/student/dashboard.php"
            class="student-mobile-brand"
            aria-label="Masar"
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
        </a>

        <div class="student-mobile-actions">

            <a
                href="<?= htmlspecialchars(
                    $languageSwitchUrl,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                class="student-language-switch"
            >
                <?= htmlspecialchars(t('switch_language')) ?>
            </a>

            <?php require __DIR__ . '/theme-toggle.php'; ?>

            <div
                class="student-user-menu"
                data-user-menu
            >
                <button
                    type="button"
                    class="student-user-chip <?= $currentStudentPage === 'profile.php'
                        ? 'is-active'
                        : '' ?>"
                    aria-label="<?= htmlspecialchars(t('profile')) ?>"
                    aria-haspopup="menu"
                    aria-expanded="false"
                    data-user-menu-toggle
                >
                    <?= htmlspecialchars($userInitial) ?>
                </button>

                <div
                    class="student-user-dropdown student-user-dropdown--mobile"
                    role="menu"
                    data-user-menu-panel
                    hidden
                >
                    <a
                        href="/masar/student/profile.php"
                        role="menuitem"
                        class="<?= $currentStudentPage === 'profile.php'
                            ? 'is-active'
                            : '' ?>"
                    >
                        <?= htmlspecialchars(t('profile')) ?>
                    </a>

                    <form method="POST" action="/masar/auth/logout.php" class="user-menu-logout-form">
                        <?= csrfField() ?>
                        <button type="submit" role="menuitem" class="student-user-dropdown__logout">
                            <?= htmlspecialchars(t('logout')) ?>
                        </button>
                    </form>
                </div>
            </div>

        </div>

    </div>

</header>

<nav
    class="student-mobile-bottom-nav"
    aria-label="<?= htmlspecialchars(t('nav_student_mobile_label'), ENT_QUOTES) ?>"
>
    <a
        href="/masar/student/dashboard.php"
        class="<?= $currentStudentPage === 'dashboard.php'
            ? 'is-active'
            : '' ?>"
    >
        <?= htmlspecialchars(t('path_short')) ?>
    </a>

    <a
        href="/masar/student/academic-record.php"
        class="<?= $currentStudentPage === 'academic-record.php'
            ? 'is-active'
            : '' ?>"
    >
        <?= htmlspecialchars(t('record_short')) ?>
    </a>

    <a
        href="/masar/student/recommendations.php"
        class="<?= $currentStudentPage === 'recommendations.php'
            ? 'is-active'
            : '' ?>"
    >
        <?= htmlspecialchars(t('next_short')) ?>
    </a>

    <a
        href="/masar/student/ask-masar.php"
        class="<?= $currentStudentPage === 'ask-masar.php'
            ? 'is-active'
            : '' ?>"
    >
        <?= htmlspecialchars(t('ask_short')) ?>
    </a>

    <a
        href="/masar/student/university-links.php"
        class="<?= $currentStudentPage === 'university-links.php'
            ? 'is-active'
            : '' ?>"
    >
        <?= htmlspecialchars(t('links_short')) ?>
    </a>
</nav>
