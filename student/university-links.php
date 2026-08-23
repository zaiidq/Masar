<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/language.php';

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

$linksByCategory = [];

foreach ($links as $link) {
    $category = trim((string) ($link['category'] ?? ''));

    if ($category === '') {
        $category = t('resources_default_category');
    }

    $linksByCategory[$category][] = $link;
}

$pageTitle = t('resources');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content resources-page-new">
    <section class="resources-hero-new">
        <img
            src="/masar/assets/brand/masar-mark.svg"
            alt=""
            class="resources-hero-new__mark"
        >

        <div>
            <span class="path-eyebrow">
                <span class="path-eyebrow__tick"></span>
                <?= htmlspecialchars(t('resources_eyebrow')) ?>
            </span>

            <h1><?= htmlspecialchars(t('resources_headline')) ?></h1>
            <p><?= htmlspecialchars(t('resources_intro')) ?></p>
        </div>

        <span class="resources-count-new">
            <?= htmlspecialchars(sprintf(t('resources_count'), count($links))) ?>
        </span>
    </section>

    <?php if (empty($links)): ?>
        <section class="resources-empty-new">
            <h2><?= htmlspecialchars(t('resources_empty_title')) ?></h2>
            <p><?= htmlspecialchars(t('resources_empty_body')) ?></p>
        </section>
    <?php else: ?>
        <div class="resources-groups-new">
            <?php foreach ($linksByCategory as $category => $categoryLinks): ?>
                <section class="resources-group-new">
                    <div class="resources-group-new__heading">
                        <h2 dir="auto"><?= htmlspecialchars($category) ?></h2>
                        <span><?= count($categoryLinks) ?></span>
                    </div>

                    <div class="resources-list-new">
                        <?php foreach ($categoryLinks as $link): ?>
                            <article class="resource-row-new">
                                <div class="resource-row-new__copy">
                                    <h3 dir="auto">
                                        <?= htmlspecialchars($link['title']) ?>
                                    </h3>

                                    <?php if (!empty($link['description'])): ?>
                                        <p dir="auto">
                                            <?= htmlspecialchars($link['description']) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <a
                                    href="<?= htmlspecialchars($link['url']) ?>"
                                    class="resource-row-new__link"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <?= htmlspecialchars(t('resources_open')) ?>
                                </a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
