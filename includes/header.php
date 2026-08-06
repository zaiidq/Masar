<?php

$pageTitle = $pageTitle ?? 'Masar';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title><?= htmlspecialchars($pageTitle) ?> | Masar</title>

    <link
        rel="stylesheet"
        href="/masar/assets/css/style.css?v=2"
    >
    
    <script
    src="/masar/assets/js/main.js?v=2"
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
        href="/masar/student/dashboard.php"
        class="mobile-logo"
    >
        Masar
    </a>
</header>

<div
    class="sidebar-overlay"
    id="sidebarOverlay"
></div>

<div class="app-layout">