<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

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

$totalUsers = (int) ($userStats['total_users'] ?? 0);
$totalStudents = (int) ($userStats['total_students'] ?? 0);
$totalAdmins = (int) ($userStats['total_admins'] ?? 0);

$totalLinks = (int) ($linkStats['total_links'] ?? 0);
$activeLinks = (int) ($linkStats['active_links'] ?? 0);
$hiddenLinks = (int) ($linkStats['hidden_links'] ?? 0);

$pageTitle = 'Admin Dashboard';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<main class="main-content">

    <header class="page-header">
        <h1>Admin Dashboard</h1>

        <p>
            Manage Masar content and monitor basic system activity.
        </p>
    </header>

    <section class="dashboard-grid">

        <article class="dashboard-card">
            <p>Registered Users</p>
            <h2><?= $totalUsers ?></h2>
        </article>

        <article class="dashboard-card">
            <p>Students</p>
            <h2><?= $totalStudents ?></h2>
        </article>

        <article class="dashboard-card">
            <p>Administrators</p>
            <h2><?= $totalAdmins ?></h2>
        </article>

        <article class="dashboard-card">
            <p>University Links</p>
            <h2><?= $totalLinks ?></h2>
        </article>

        <article class="dashboard-card">
            <p>Active Links</p>
            <h2><?= $activeLinks ?></h2>
        </article>

        <article class="dashboard-card">
            <p>Hidden Links</p>
            <h2><?= $hiddenLinks ?></h2>
        </article>

    </section>

</main>

<?php

require_once __DIR__ . '/../includes/footer.php';