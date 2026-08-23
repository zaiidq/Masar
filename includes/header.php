<?php

require_once __DIR__ . '/language.php';

$pageTitle = $pageTitle ?? 'Masar';

$dashboardUrl = (
    ($_SESSION['role'] ?? 'student') === 'admin'
)
    ? '/masar/admin/dashboard.php'
    : '/masar/student/dashboard.php';

$currentLanguage = currentLanguage();
$currentDirection = currentDirection();

$switchToLanguage =
    $currentLanguage === 'en'
        ? 'ar'
        : 'en';

$currentRequestUri =
    $_SERVER['REQUEST_URI']
    ?? '/masar/student/dashboard.php';

$languageSwitchUrl =
    '/masar/switch-language.php?lang='
    . urlencode($switchToLanguage)
    . '&return='
    . urlencode($currentRequestUri);
?>

<!DOCTYPE html>
<html
    lang="<?= htmlspecialchars($currentLanguage, ENT_QUOTES, 'UTF-8') ?>"
    dir="<?= htmlspecialchars($currentDirection, ENT_QUOTES, 'UTF-8') ?>"
>
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title><?= htmlspecialchars($pageTitle) ?> | Masar</title>

    <link
    rel="stylesheet"
    href="/masar/assets/css/style.css?v=8"
>
    
    <script
    src="/masar/assets/js/main.js?v=2"
    defer
></script>

    <script
    src="/masar/assets/js/major-selector.js?v=1"
    defer
></script>
</head>

<body>

<header class="mobile-topbar">
    <button
        type="button"
        class="menu-toggle"
        id="menuToggle"
        aria-label="Open navigation menu"
        aria-expanded="false"
    >
        ☰
    </button>

    <a
        href="<?= htmlspecialchars($dashboardUrl) ?>"
        class="mobile-logo"
    >
        Masar
    </a>

    <a
        href="<?= htmlspecialchars(
            $languageSwitchUrl,
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
        class="language-switch mobile-language-switch"
    >
        <?= htmlspecialchars(
            t('switch_language'),
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </a>
</header>

<div
    class="sidebar-overlay"
    id="sidebarOverlay"
></div>

<div class="app-layout">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link
    href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Tajawal:wght@400;500;700&display=swap"
    rel="stylesheet"
>
