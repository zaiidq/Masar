<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';

if ($_SESSION['role'] !== 'student') {
    header('Location: ../admin/dashboard.php');
    exit;
}

$pageTitle = 'Dashboard';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">

    <header class="page-header">
        <h1>
            Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?>
        </h1>

        <p>
            Here is an overview of your academic progress.
        </p>
    </header>

    <section class="dashboard-grid">

        <article class="dashboard-card">
            <p>Completed Hours</p>
            <h2>72</h2>
        </article>

        <article class="dashboard-card">
            <p>Remaining Hours</p>
            <h2>60</h2>
        </article>

        <article class="dashboard-card">
            <p>Completed Courses</p>
            <h2>24</h2>
        </article>

        <article class="dashboard-card">
            <p>Current GPA</p>
            <h2>3.15</h2>
        </article>

    </section>

</main>

<?php

require_once __DIR__ . '/../includes/footer.php';