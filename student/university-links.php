<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

if ($_SESSION['role'] !== 'student') {
    header('Location: ../admin/dashboard.php');
    exit;
}

$stmt = $pdo->query(
    'SELECT
        id,
        title,
        url,
        description,
        category
     FROM university_links
     WHERE is_active = 1
     ORDER BY sort_order ASC, title ASC'
);

$links = $stmt->fetchAll();

$pageTitle = 'University Links';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">

    <header class="page-header">
        <h1>University Links</h1>

        <p>
            Access important university platforms and services.
        </p>
    </header>

    <?php if (empty($links)): ?>

        <section class="empty-state">
            <h2>No links available</h2>

            <p>
                University links will appear here when they are added.
            </p>
        </section>

    <?php else: ?>

        <section class="links-grid">

            <?php foreach ($links as $link): ?>

                <article class="university-link-card">

                    <div class="link-card-category">
                        <?= htmlspecialchars(
                            $link['category'] ?? 'University Service'
                        ) ?>
                    </div>

                    <h2>
                        <?= htmlspecialchars($link['title']) ?>
                    </h2>

                    <?php if (!empty($link['description'])): ?>
                        <p>
                            <?= htmlspecialchars($link['description']) ?>
                        </p>
                    <?php endif; ?>

                    <a
                        href="<?= htmlspecialchars($link['url']) ?>"
                        class="link-card-button"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Open Link
                    </a>

                </article>

            <?php endforeach; ?>

        </section>

    <?php endif; ?>

</main>

<?php

require_once __DIR__ . '/../includes/footer.php';