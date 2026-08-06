<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../student/dashboard.php');
    exit;
}

$pageTitle = 'Admin Dashboard';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<main class="main-content">

    <header class="page-header">
        <h1>Admin Dashboard</h1>

        <p>
            Manage Masar content and system services.
        </p>
    </header>

    <section class="dashboard-grid">

        <article class="dashboard-card">
            <p>Registered Users</p>
            <h2>—</h2>
        </article>

        <article class="dashboard-card">
            <p>University Links</p>
            <h2>—</h2>
        </article>

        <article class="dashboard-card">
            <p>Marketplace Posts</p>
            <h2>—</h2>
        </article>

        <article class="dashboard-card">
            <p>Pending Reviews</p>
            <h2>—</h2>
        </article>

    </section>

</main>

<?php

require_once __DIR__ . '/../includes/footer.php';