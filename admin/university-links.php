<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/language.php';

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../student/dashboard.php');
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = '';

$isEditing = false;
$editingLinkId = 0;

$title = '';
$url = '';
$description = '';
$category = '';
$sortOrder = 0;

/*
|--------------------------------------------------------------------------
| Load selected link for editing
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $editingLinkId = filter_var(
        $_GET['edit'] ?? null,
        FILTER_VALIDATE_INT
    ) ?: 0;

    if ($editingLinkId > 0) {
        $stmt = $pdo->prepare(
            'SELECT
                id,
                title,
                url,
                description,
                category,
                sort_order
             FROM university_links
             WHERE id = :id
             LIMIT 1'
        );

        $stmt->execute([
            'id' => $editingLinkId,
        ]);

        $editingLink = $stmt->fetch();

        if ($editingLink) {
            $isEditing = true;

            $title = $editingLink['title'];
            $url = $editingLink['url'];
            $description = $editingLink['description'] ?? '';
            $category = $editingLink['category'] ?? '';
            $sortOrder = (int) $editingLink['sort_order'];
        } else {
            $errors[] = t('admin_links_error_not_found');
        }
    }
}

/*
|--------------------------------------------------------------------------
| Process create, update, toggle and delete actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $errors[] = t('admin_links_error_invalid_request');
    }

    $action = $_POST['action'] ?? '';

    if (
        empty($errors)
        && in_array($action, ['create', 'update'], true)
    ) {
        $title = trim($_POST['title'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');

        $sortOrderResult = filter_var(
            $_POST['sort_order'] ?? null,
            FILTER_VALIDATE_INT
        );

        if ($action === 'update') {
            $editingLinkId = filter_var(
                $_POST['link_id'] ?? null,
                FILTER_VALIDATE_INT
            ) ?: 0;

            $isEditing = true;

            if ($editingLinkId <= 0) {
                $errors[] = t('admin_links_error_invalid_edit');
            }
        }

        if ($title === '') {
            $errors[] = t('admin_links_error_title_required');
        } elseif (strlen($title) > 120) {
            $errors[] = t('admin_links_error_title_long');
        }

        if (
            $url === ''
            || !filter_var($url, FILTER_VALIDATE_URL)
        ) {
            $errors[] = t('admin_links_error_url_invalid');
        } else {
            $urlScheme = strtolower(
                (string) parse_url($url, PHP_URL_SCHEME)
            );

            if (!in_array($urlScheme, ['http', 'https'], true)) {
                $errors[] = t('admin_links_error_url_scheme');
            }
        }

        if (strlen($description) > 255) {
            $errors[] = t('admin_links_error_description_long');
        }

        if (strlen($category) > 80) {
            $errors[] = t('admin_links_error_category_long');
        }

        if (
            $sortOrderResult === false
            || $sortOrderResult < 0
        ) {
            $errors[] = t('admin_links_error_sort_order');
        } else {
            $sortOrder = $sortOrderResult;
        }

        if (empty($errors)) {
            if ($action === 'create') {
                $stmt = $pdo->prepare(
                    'INSERT INTO university_links (
                        title,
                        url,
                        description,
                        category,
                        sort_order,
                        is_active
                    ) VALUES (
                        :title,
                        :url,
                        :description,
                        :category,
                        :sort_order,
                        1
                    )'
                );

                $stmt->execute([
                    'title' => $title,
                    'url' => $url,
                    'description' => $description !== ''
                        ? $description
                        : null,
                    'category' => $category !== ''
                        ? $category
                        : null,
                    'sort_order' => $sortOrder,
                ]);

                $success = t('admin_links_success_added');
            }

            if ($action === 'update') {
                $stmt = $pdo->prepare(
                    'UPDATE university_links
                     SET
                        title = :title,
                        url = :url,
                        description = :description,
                        category = :category,
                        sort_order = :sort_order
                     WHERE id = :id'
                );

                $stmt->execute([
                    'title' => $title,
                    'url' => $url,
                    'description' => $description !== ''
                        ? $description
                        : null,
                    'category' => $category !== ''
                        ? $category
                        : null,
                    'sort_order' => $sortOrder,
                    'id' => $editingLinkId,
                ]);

                $success = t('admin_links_success_updated');
            }

            $isEditing = false;
            $editingLinkId = 0;

            $title = '';
            $url = '';
            $description = '';
            $category = '';
            $sortOrder = 0;
        }
    }

    if ($action === 'toggle' && empty($errors)) {
        $linkId = filter_var(
            $_POST['link_id'] ?? null,
            FILTER_VALIDATE_INT
        ) ?: 0;

        if ($linkId <= 0) {
            $errors[] = t('admin_links_error_invalid_link');
        } else {
            $stmt = $pdo->prepare(
                'UPDATE university_links
                 SET is_active = NOT is_active
                 WHERE id = :id'
            );

            $stmt->execute([
                'id' => $linkId,
            ]);

            $success = t('admin_links_success_status');
        }
    }

    if ($action === 'delete' && empty($errors)) {
        $linkId = filter_var(
            $_POST['link_id'] ?? null,
            FILTER_VALIDATE_INT
        ) ?: 0;

        if ($linkId <= 0) {
            $errors[] = t('admin_links_error_invalid_link');
        } else {
            $stmt = $pdo->prepare(
                'DELETE FROM university_links
                 WHERE id = :id'
            );

            $stmt->execute([
                'id' => $linkId,
            ]);

            $success = t('admin_links_success_deleted');
        }
    }
}

/*
|--------------------------------------------------------------------------
| Get all links
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query(
    'SELECT
        id,
        title,
        url,
        description,
        category,
        is_active,
        sort_order
     FROM university_links
     ORDER BY sort_order ASC, title ASC'
);

$links = $stmt->fetchAll();

$activeCount = 0;

foreach ($links as $link) {
    if ((int) $link['is_active'] === 1) {
        $activeCount++;
    }
}

$hiddenCount = count($links) - $activeCount;

$pageTitle = t('admin_links_page_title');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<main class="main-content admin-page admin-links-page-new">

    <section class="admin-links-hero-new">
        <div>
            <span class="admin-eyebrow-new">
                <span class="admin-eyebrow-new__tick"></span>
                <?= htmlspecialchars(t('admin_links_eyebrow')) ?>
            </span>

            <h1><?= htmlspecialchars(t('admin_links_headline')) ?></h1>

            <p><?= htmlspecialchars(t('admin_links_intro')) ?></p>
        </div>

        <div class="admin-links-count-new" aria-label="<?= htmlspecialchars(t('admin_links_count_label')) ?>">
            <strong><?= number_format(count($links)) ?></strong>
            <span><?= htmlspecialchars(t('admin_links_count_label')) ?></span>
        </div>
    </section>

    <?php if (!empty($errors)): ?>
        <div class="admin-notice-new admin-notice-new--error" role="alert">
            <strong><?= htmlspecialchars(t('admin_links_fix_errors')) ?></strong>

            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="admin-notice-new admin-notice-new--success" role="status">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <section class="admin-links-workspace-new">

        <aside class="admin-link-editor-new">
            <div class="admin-section-kicker-new">
                <?= htmlspecialchars(
                    $isEditing
                        ? t('admin_links_edit_mode')
                        : t('admin_links_add_mode')
                ) ?>
            </div>

            <div class="admin-link-editor-new__heading">
                <h2>
                    <?= htmlspecialchars(
                        $isEditing
                            ? t('admin_links_edit_title')
                            : t('admin_links_add_title')
                    ) ?>
                </h2>

                <?php if ($isEditing): ?>
                    <a href="/masar/admin/university-links.php">
                        <?= htmlspecialchars(t('admin_links_cancel_edit')) ?>
                    </a>
                <?php endif; ?>
            </div>

            <p class="admin-link-editor-new__intro">
                <?= htmlspecialchars(t('admin_links_form_intro')) ?>
            </p>

            <form
                method="POST"
                action="/masar/admin/university-links.php"
                class="admin-link-form-new"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="<?= $isEditing ? 'update' : 'create' ?>"
                >

                <?php if ($isEditing): ?>
                    <input
                        type="hidden"
                        name="link_id"
                        value="<?= (int) $editingLinkId ?>"
                    >
                <?php endif; ?>

                <div class="admin-field-new">
                    <label for="title"><?= htmlspecialchars(t('admin_links_field_title')) ?></label>

                    <input
                        type="text"
                        id="title"
                        name="title"
                        value="<?= htmlspecialchars($title) ?>"
                        maxlength="120"
                        required
                    >
                </div>

                <div class="admin-field-new">
                    <label for="category"><?= htmlspecialchars(t('admin_links_field_category')) ?></label>

                    <input
                        type="text"
                        id="category"
                        name="category"
                        value="<?= htmlspecialchars($category) ?>"
                        maxlength="80"
                        placeholder="<?= htmlspecialchars(t('admin_links_category_placeholder')) ?>"
                    >
                </div>

                <div class="admin-field-new">
                    <label for="url"><?= htmlspecialchars(t('admin_links_field_url')) ?></label>

                    <input
                        type="url"
                        id="url"
                        name="url"
                        value="<?= htmlspecialchars($url) ?>"
                        placeholder="https://"
                        dir="ltr"
                        required
                    >
                </div>

                <div class="admin-field-new">
                    <label for="description"><?= htmlspecialchars(t('admin_links_field_description')) ?></label>

                    <textarea
                        id="description"
                        name="description"
                        maxlength="255"
                        rows="3"
                    ><?= htmlspecialchars($description) ?></textarea>
                </div>

                <div class="admin-field-new admin-field-new--compact">
                    <label for="sort_order"><?= htmlspecialchars(t('admin_links_field_order')) ?></label>

                    <input
                        type="number"
                        id="sort_order"
                        name="sort_order"
                        value="<?= (int) $sortOrder ?>"
                        min="0"
                    >

                    <small><?= htmlspecialchars(t('admin_links_order_help')) ?></small>
                </div>

                <button type="submit" class="admin-submit-new">
                    <span>
                        <?= htmlspecialchars(
                            $isEditing
                                ? t('admin_links_save_changes')
                                : t('admin_links_add_action')
                        ) ?>
                    </span>
                    <span aria-hidden="true">→</span>
                </button>
            </form>
        </aside>

        <div class="admin-links-list-new">
            <div class="admin-links-list-new__heading">
                <div>
                    <span class="admin-section-kicker-new">
                        <?= htmlspecialchars(t('admin_links_existing_kicker')) ?>
                    </span>

                    <h2><?= htmlspecialchars(t('admin_links_existing_title')) ?></h2>
                </div>

                <div class="admin-links-status-summary-new">
                    <span><?= htmlspecialchars(sprintf(t('admin_active_links_count'), $activeCount)) ?></span>
                    <span><?= htmlspecialchars(sprintf(t('admin_hidden_links_count'), $hiddenCount)) ?></span>
                </div>
            </div>

            <?php if (empty($links)): ?>
                <div class="admin-links-empty-new">
                    <img src="/masar/assets/brand/masar-mark.svg" alt="">
                    <h3><?= htmlspecialchars(t('admin_links_empty_title')) ?></h3>
                    <p><?= htmlspecialchars(t('admin_links_empty_body')) ?></p>
                </div>
            <?php else: ?>
                <div class="admin-table-wrap-new">
                    <table class="admin-links-table-new">
                        <thead>
                            <tr>
                                <th><?= htmlspecialchars(t('admin_links_field_title')) ?></th>
                                <th><?= htmlspecialchars(t('admin_links_field_category')) ?></th>
                                <th><?= htmlspecialchars(t('admin_links_field_order')) ?></th>
                                <th><?= htmlspecialchars(t('admin_links_status')) ?></th>
                                <th><?= htmlspecialchars(t('admin_links_actions')) ?></th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($links as $link): ?>
                                <tr>
                                    <td data-label="<?= htmlspecialchars(t('admin_links_field_title')) ?>">
                                        <div class="admin-link-title-cell-new">
                                            <a
                                                href="<?= htmlspecialchars($link['url']) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                <?= htmlspecialchars($link['title']) ?>
                                                <span aria-hidden="true">↗</span>
                                            </a>

                                            <?php if (!empty($link['description'])): ?>
                                                <small><?= htmlspecialchars($link['description']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <td data-label="<?= htmlspecialchars(t('admin_links_field_category')) ?>">
                                        <?= htmlspecialchars($link['category'] ?? '—') ?>
                                    </td>

                                    <td
                                        data-label="<?= htmlspecialchars(t('admin_links_field_order')) ?>"
                                        class="admin-data-ltr"
                                    >
                                        <?= (int) $link['sort_order'] ?>
                                    </td>

                                    <td data-label="<?= htmlspecialchars(t('admin_links_status')) ?>">
                                        <?php if ((int) $link['is_active'] === 1): ?>
                                            <span class="admin-status-new admin-status-new--active">
                                                <?= htmlspecialchars(t('admin_links_active')) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="admin-status-new admin-status-new--hidden">
                                                <?= htmlspecialchars(t('admin_links_hidden')) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td
                                        data-label="<?= htmlspecialchars(t('admin_links_actions')) ?>"
                                        class="admin-table-actions-new"
                                    >
                                        <a
                                            href="/masar/admin/university-links.php?edit=<?= (int) $link['id'] ?>"
                                            class="admin-row-action-new admin-row-action-new--edit"
                                        >
                                            <?= htmlspecialchars(t('admin_links_edit_action')) ?>
                                        </a>

                                        <form method="POST" action="/masar/admin/university-links.php">
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
                                            >
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="link_id" value="<?= (int) $link['id'] ?>">

                                            <button type="submit" class="admin-row-action-new">
                                                <?= htmlspecialchars(
                                                    (int) $link['is_active'] === 1
                                                        ? t('admin_links_hide_action')
                                                        : t('admin_links_activate_action')
                                                ) ?>
                                            </button>
                                        </form>

                                        <form
                                            method="POST"
                                            action="/masar/admin/university-links.php"
                                            onsubmit="return confirm(<?= htmlspecialchars(
                                                json_encode(
                                                    t('admin_links_delete_confirm'),
                                                    JSON_UNESCAPED_UNICODE
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>);"
                                        >
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
                                            >
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="link_id" value="<?= (int) $link['id'] ?>">

                                            <button
                                                type="submit"
                                                class="admin-row-action-new admin-row-action-new--danger"
                                            >
                                                <?= htmlspecialchars(t('admin_links_delete_action')) ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </section>

</main>

<?php

require_once __DIR__ . '/../includes/footer.php';
