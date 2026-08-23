<?php

require_once __DIR__ . '/language.php';

$pageTitle = $pageTitle ?? 'Masar';

$isAdmin =
    ($_SESSION['role'] ?? 'student') === 'admin';

$dashboardUrl = $isAdmin
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
    ?? $dashboardUrl;

$languageSwitchUrl =
    '/masar/switch-language.php?lang='
    . urlencode($switchToLanguage)
    . '&return='
    . urlencode($currentRequestUri);

$bodyClass = $isAdmin
    ? 'role-admin'
    : 'role-student';
?>

<!DOCTYPE html>
<html
    lang="<?= htmlspecialchars(
        $currentLanguage,
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
    dir="<?= htmlspecialchars(
        $currentDirection,
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
>
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= htmlspecialchars(
            $pageTitle,
            ENT_QUOTES,
            'UTF-8'
        ) ?> | Masar
    </title>

    <link
        rel="icon"
        type="image/svg+xml"
        href="/masar/assets/brand/masar-favicon.svg"
    >

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Tajawal:wght@400;500;700&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="/masar/assets/css/style.css?v=14"
    >

    <script
        src="/masar/assets/js/main.js?v=4"
        defer
    ></script>

    <script
        src="/masar/assets/js/major-selector.js?v=2"
        defer
    ></script>
</head>

<body class="<?= htmlspecialchars($bodyClass) ?>">



<div class="app-layout">