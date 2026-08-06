<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

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
            $errors[] = 'The selected link was not found.';
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
        $errors[] = 'Invalid request. Please try again.';
    }

    $action = $_POST['action'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | Create or update
    |--------------------------------------------------------------------------
    */

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
                $errors[] = 'Invalid link selected for editing.';
            }
        }

        if ($title === '') {
            $errors[] = 'Link title is required.';
        } elseif (strlen($title) > 120) {
            $errors[] = 'Link title is too long.';
        }

        if (
            $url === ''
            || !filter_var($url, FILTER_VALIDATE_URL)
        ) {
            $errors[] = 'A valid URL is required.';
        } else {
            $urlScheme = strtolower(
                (string) parse_url($url, PHP_URL_SCHEME)
            );

            if (!in_array($urlScheme, ['http', 'https'], true)) {
                $errors[] = 'The URL must use http or https.';
            }
        }

        if (strlen($description) > 255) {
            $errors[] = 'Description is too long.';
        }

        if (strlen($category) > 80) {
            $errors[] = 'Category is too long.';
        }

        if (
            $sortOrderResult === false
            || $sortOrderResult < 0
        ) {
            $errors[] = 'Sort order must be zero or greater.';
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

                $success = 'Link added successfully.';
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

                $success = 'Link updated successfully.';
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

    /*
    |--------------------------------------------------------------------------
    | Activate or hide
    |--------------------------------------------------------------------------
    */

    if ($action === 'toggle' && empty($errors)) {
        $linkId = filter_var(
            $_POST['link_id'] ?? null,
            FILTER_VALIDATE_INT
        ) ?: 0;

        if ($linkId <= 0) {
            $errors[] = 'Invalid link selected.';
        } else {
            $stmt = $pdo->prepare(
                'UPDATE university_links
                 SET is_active = NOT is_active
                 WHERE id = :id'
            );

            $stmt->execute([
                'id' => $linkId,
            ]);

            $success = 'Link status updated.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    if ($action === 'delete' && empty($errors)) {
        $linkId = filter_var(
            $_POST['link_id'] ?? null,
            FILTER_VALIDATE_INT
        ) ?: 0;

        if ($linkId <= 0) {
            $errors[] = 'Invalid link selected.';
        } else {
            $stmt = $pdo->prepare(
                'DELETE FROM university_links
                 WHERE id = :id'
            );

            $stmt->execute([
                'id' => $linkId,
            ]);

            $success = 'Link deleted successfully.';
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

$pageTitle = 'Manage University Links';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<main class="main-content">

    <header class="page-header">
        <h1>Manage University Links</h1>

        <p>
            Add, edit and manage the links available to students.
        </p>
    </header>

    <?php if (!empty($errors)): ?>
        <div class="alert-error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li>
                        <?= htmlspecialchars($error) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="alert-success">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <section class="content-card admin-form-card">

        <div class="admin-form-heading">

            <h2>
                <?= $isEditing
                    ? 'Edit Link'
                    : 'Add New Link' ?>
            </h2>

            <?php if ($isEditing): ?>
                <a
                    href="/masar/admin/university-links.php"
                    class="cancel-edit-link"
                >
                    Cancel Edit
                </a>
            <?php endif; ?>

        </div>

        <form
            method="POST"
            action="/masar/admin/university-links.php"
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

            <div class="admin-form-grid">

                <div class="form-group">
                    <label for="title">Title</label>

                    <input
                        type="text"
                        id="title"
                        name="title"
                        value="<?= htmlspecialchars($title) ?>"
                        maxlength="120"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="category">Category</label>

                    <input
                        type="text"
                        id="category"
                        name="category"
                        value="<?= htmlspecialchars($category) ?>"
                        maxlength="80"
                        placeholder="University Services"
                    >
                </div>

                <div class="form-group admin-full-width">
                    <label for="url">URL</label>

                    <input
                        type="url"
                        id="url"
                        name="url"
                        value="<?= htmlspecialchars($url) ?>"
                        required
                    >
                </div>

                <div class="form-group admin-full-width">
                    <label for="description">Description</label>

                    <input
                        type="text"
                        id="description"
                        name="description"
                        value="<?= htmlspecialchars($description) ?>"
                        maxlength="255"
                    >
                </div>

                <div class="form-group">
                    <label for="sort_order">Sort Order</label>

                    <input
                        type="number"
                        id="sort_order"
                        name="sort_order"
                        value="<?= (int) $sortOrder ?>"
                        min="0"
                    >
                </div>

            </div>

            <button
                type="submit"
                class="btn-primary admin-submit-button"
            >
                <?= $isEditing
                    ? 'Save Changes'
                    : 'Add Link' ?>
            </button>

        </form>

    </section>

    <section class="content-card admin-list-card">

        <h2>Existing Links</h2>

        <?php if (empty($links)): ?>

            <p>No university links have been added.</p>

        <?php else: ?>

            <div class="table-wrapper">

                <table class="admin-table">

                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Order</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($links as $link): ?>

                            <tr>

                                <td>
                                    <a
                                        href="<?= htmlspecialchars($link['url']) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <?= htmlspecialchars($link['title']) ?>
                                    </a>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $link['category'] ?? '—'
                                    ) ?>
                                </td>

                                <td>
                                    <?= (int) $link['sort_order'] ?>
                                </td>

                                <td>
                                    <?php if ((int) $link['is_active'] === 1): ?>
                                        <span class="status-active">
                                            Active
                                        </span>
                                    <?php else: ?>
                                        <span class="status-inactive">
                                            Hidden
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="table-actions">

                                    <a
                                        href="/masar/admin/university-links.php?edit=<?= (int) $link['id'] ?>"
                                        class="action-button edit-button"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        method="POST"
                                        action="/masar/admin/university-links.php"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= htmlspecialchars(
                                                $_SESSION['csrf_token']
                                            ) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="toggle"
                                        >

                                        <input
                                            type="hidden"
                                            name="link_id"
                                            value="<?= (int) $link['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="action-button"
                                        >
                                            <?= (int) $link['is_active'] === 1
                                                ? 'Hide'
                                                : 'Activate' ?>
                                        </button>

                                    </form>

                                    <form
                                        method="POST"
                                        action="/masar/admin/university-links.php"
                                        onsubmit="return confirm(
                                            'Delete this link?'
                                        );"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= htmlspecialchars(
                                                $_SESSION['csrf_token']
                                            ) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete"
                                        >

                                        <input
                                            type="hidden"
                                            name="link_id"
                                            value="<?= (int) $link['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="action-button danger-button"
                                        >
                                            Delete
                                        </button>

                                    </form>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

</main>

<?php

require_once __DIR__ . '/../includes/footer.php';