<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/admin-common.php';

$adminId = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Actions: change role, delete user (Post/Redirect/Get)
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $returnQuery = (string) ($_POST['return_query'] ?? '');
    $returnUrl = '/masar/admin/users.php'
        . (preg_match('/^[\w=&%+.\-]*$/', $returnQuery) && $returnQuery !== '' ? '?' . $returnQuery : '');

    if (!verifyCsrf()) {
        adminFlash('error', t('admin_links_error_invalid_request'));
        adminRedirect($returnUrl);
    }

    $action = (string) ($_POST['action'] ?? '');
    $targetId = (int) ($_POST['user_id'] ?? 0);

    $targetStatement = $pdo->prepare('SELECT id, full_name, email, role FROM users WHERE id = :id');
    $targetStatement->execute(['id' => $targetId]);
    $target = $targetStatement->fetch();

    if (!$target) {
        adminFlash('error', t('admin_users_not_found'));
        adminRedirect($returnUrl);
    }

    if ($targetId === $adminId) {
        adminFlash('error', t('admin_users_not_self'));
        adminRedirect($returnUrl);
    }

    if ($action === 'set_role') {
        $newRole = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'student';

        $pdo->prepare('UPDATE users SET role = :role WHERE id = :id')
            ->execute(['role' => $newRole, 'id' => $targetId]);

        logActivity(
            $pdo,
            'ADMIN_ROLE_CHANGED',
            $adminId,
            sprintf('user=%d (%s) role=%s', $targetId, $target['email'], $newRole)
        );

        adminFlash('success', sprintf(t('admin_users_role_changed'), $target['full_name']));
        adminRedirect($returnUrl);
    }

    if ($action === 'delete_user') {
        $fileStatement = $pdo->prepare(
            'SELECT file_path FROM academic_records WHERE user_id = :user_id'
        );
        $fileStatement->execute(['user_id' => $targetId]);
        $filePaths = $fileStatement->fetchAll(PDO::FETCH_COLUMN);

        $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $targetId]);

        $storageRoot = realpath(dirname(__DIR__) . '/storage/academic-records');

        foreach ($filePaths as $relativePath) {
            $absolutePath = realpath(dirname(__DIR__) . '/' . $relativePath);

            if (
                $absolutePath !== false
                && $storageRoot !== false
                && str_starts_with($absolutePath, $storageRoot . DIRECTORY_SEPARATOR)
                && is_file($absolutePath)
            ) {
                @unlink($absolutePath);
            }
        }

        logActivity(
            $pdo,
            'ADMIN_USER_DELETED',
            $adminId,
            sprintf('user=%d (%s), %d record file(s) removed', $targetId, $target['email'], count($filePaths))
        );

        adminFlash('success', sprintf(t('admin_users_deleted'), $target['full_name']));
        adminRedirect($returnUrl);
    }

    adminRedirect($returnUrl);
}

/*
|--------------------------------------------------------------------------
| Listing with search, role filter and pagination
|--------------------------------------------------------------------------
*/

$search = trim((string) ($_GET['q'] ?? ''));
$roleFilter = in_array($_GET['role'] ?? '', ['student', 'admin'], true) ? $_GET['role'] : '';
$perPage = 25;
$page = max(1, (int) ($_GET['page'] ?? 1));

$where = ['1 = 1'];
$params = [];

if ($search !== '') {
    $where[] = '(u.full_name LIKE :search_name OR u.email LIKE :search_email OR u.university_id LIKE :search_id)';
    $params['search_name'] = '%' . $search . '%';
    $params['search_email'] = '%' . $search . '%';
    $params['search_id'] = '%' . $search . '%';
}

if ($roleFilter !== '') {
    $where[] = 'u.role = :role';
    $params['role'] = $roleFilter;
}

$whereSql = implode(' AND ', $where);

$countStatement = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE {$whereSql}");
$countStatement->execute($params);
$totalUsers = (int) $countStatement->fetchColumn();
$totalPages = max(1, (int) ceil($totalUsers / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listStatement = $pdo->prepare(
    "SELECT
        u.id, u.full_name, u.email, u.university_id, u.role, u.created_at,
        " . localizedNameSql('m') . " AS major_name,
        (SELECT COUNT(*) FROM academic_records ar WHERE ar.user_id = u.id) AS record_count,
        (SELECT ar.status FROM academic_records ar WHERE ar.user_id = u.id
            ORDER BY ar.created_at DESC, ar.id DESC LIMIT 1) AS latest_status,
        (SELECT ar.analysis_error FROM academic_records ar WHERE ar.user_id = u.id
            ORDER BY ar.created_at DESC, ar.id DESC LIMIT 1) AS latest_error,
        (SELECT COUNT(*) FROM chat_sessions cs WHERE cs.user_id = u.id) AS chat_count
     FROM users u
     LEFT JOIN majors m ON m.id = u.major_id
     WHERE {$whereSql}
     ORDER BY u.created_at DESC, u.id DESC
     LIMIT {$perPage} OFFSET {$offset}"
);
$listStatement->execute($params);
$users = $listStatement->fetchAll();

$flash = adminTakeFlash();

$currentQuery = http_build_query(array_filter([
    'q' => $search,
    'role' => $roleFilter,
    'page' => $page > 1 ? $page : null,
]));

function usersPageUrl(int $page, string $search, string $role): string
{
    return '/masar/admin/users.php?' . http_build_query(array_filter([
        'q' => $search,
        'role' => $role,
        'page' => $page,
    ]));
}

function recordStatusLabel(?string $status): string
{
    return match ($status) {
        'uploaded' => t('record_status_uploaded'),
        'processing' => t('record_status_processing'),
        'analyzed' => t('record_status_analyzed'),
        'failed' => t('record_status_failed'),
        default => t('admin_users_no_record'),
    };
}

$pageTitle = t('admin_users_title');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<main class="main-content admin-page admin-users-page">

    <section class="admin-hero-new">
        <div class="admin-hero-new__copy">
            <span class="admin-eyebrow-new">
                <span class="admin-eyebrow-new__tick"></span>
                <?= adminEscape(t('admin_users_eyebrow')) ?>
            </span>

            <h1><?= adminEscape(t('admin_users_headline')) ?></h1>
            <p><?= adminEscape(t('admin_users_intro')) ?></p>
        </div>

        <img class="admin-hero-new__mark" src="/masar/assets/brand/masar-mark.svg" alt="">
    </section>

    <?php if ($flash): ?>
        <div class="admin-notice-new admin-notice-new--<?= $flash['type'] === 'success' ? 'success' : 'error' ?>" role="status">
            <?= adminEscape($flash['message']) ?>
        </div>
    <?php endif; ?>

    <form class="admin-filter-bar" method="GET" action="/masar/admin/users.php">
        <label class="visually-hidden" for="users-search"><?= adminEscape(t('admin_users_search')) ?></label>
        <input
            type="search"
            id="users-search"
            name="q"
            value="<?= adminEscape($search) ?>"
            placeholder="<?= adminEscape(t('admin_users_search')) ?>"
        >

        <label class="visually-hidden" for="users-role"><?= adminEscape(t('admin_users_role')) ?></label>
        <select id="users-role" name="role">
            <option value=""><?= adminEscape(t('admin_users_all_roles')) ?></option>
            <option value="student" <?= $roleFilter === 'student' ? 'selected' : '' ?>><?= adminEscape(t('admin_users_role_student')) ?></option>
            <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>><?= adminEscape(t('admin_users_role_admin')) ?></option>
        </select>

        <button type="submit" class="admin-filter-bar__submit"><?= adminEscape(t('admin_filter')) ?></button>
        <a class="admin-filter-bar__reset" href="/masar/admin/users.php"><?= adminEscape(t('admin_reset')) ?></a>
    </form>

    <p class="admin-result-count"><?= adminEscape(sprintf(t('admin_users_count'), $totalUsers)) ?></p>

    <?php if (!$users): ?>
        <div class="admin-links-empty-new">
            <h3><?= adminEscape(t('admin_users_empty')) ?></h3>
        </div>
    <?php else: ?>
        <div class="admin-table-wrap-new">
            <table class="admin-links-table-new admin-users-table">
                <thead>
                    <tr>
                        <th><?= adminEscape(t('admin_users_user')) ?></th>
                        <th><?= adminEscape(t('admin_users_role')) ?></th>
                        <th><?= adminEscape(t('admin_users_major')) ?></th>
                        <th><?= adminEscape(t('admin_users_analysis')) ?></th>
                        <th><?= adminEscape(t('admin_users_joined')) ?></th>
                        <th><?= adminEscape(t('admin_links_actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php $isSelf = (int) $user['id'] === $adminId; ?>
                        <tr>
                            <td data-label="<?= adminEscape(t('admin_users_user')) ?>">
                                <div class="admin-link-title-cell-new">
                                    <strong><bdi><?= adminEscape($user['full_name']) ?></bdi></strong>
                                    <small dir="ltr"><?= adminEscape($user['email']) ?></small>
                                    <?php if (!empty($user['university_id'])): ?>
                                        <small dir="ltr"><?= adminEscape($user['university_id']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td data-label="<?= adminEscape(t('admin_users_role')) ?>">
                                <span class="admin-status-new <?= $user['role'] === 'admin' ? 'admin-status-new--hidden' : 'admin-status-new--active' ?>">
                                    <?= adminEscape($user['role'] === 'admin' ? t('admin_users_role_admin') : t('admin_users_role_student')) ?>
                                </span>
                            </td>
                            <td data-label="<?= adminEscape(t('admin_users_major')) ?>">
                                <?= adminEscape($user['major_name'] ?? '—') ?>
                            </td>
                            <td data-label="<?= adminEscape(t('admin_users_analysis')) ?>">
                                <?php if ($user['role'] === 'student'): ?>
                                    <span class="record-pill record-pill--<?= adminEscape($user['latest_status'] ?? 'none') ?>">
                                        <?= adminEscape(recordStatusLabel($user['latest_status'])) ?>
                                    </span>
                                    <small class="admin-users-meta">
                                        <?= adminEscape(sprintf(
                                            t('admin_users_records_chats'),
                                            (int) $user['record_count'],
                                            (int) $user['chat_count']
                                        )) ?>
                                    </small>
                                    <?php if ($user['latest_status'] === 'failed' && !empty($user['latest_error'])): ?>
                                        <details class="admin-users-error-details">
                                            <summary><?= adminEscape(t('admin_users_error_details')) ?></summary>
                                            <small class="admin-users-error" dir="ltr">
                                                <?= adminEscape($user['latest_error']) ?>
                                            </small>
                                        </details>
                                    <?php endif; ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td data-label="<?= adminEscape(t('admin_users_joined')) ?>" class="admin-users-date">
                                <?= adminEscape(adminFormatDateTime($user['created_at'])) ?>
                            </td>
                            <td data-label="<?= adminEscape(t('admin_links_actions')) ?>" class="admin-table-actions-new">
                                <?php if ($isSelf): ?>
                                    <span class="admin-users-meta"><?= adminEscape(t('admin_users_you')) ?></span>
                                <?php else: ?>
                                    <form method="POST" action="/masar/admin/users.php">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="set_role">
                                        <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                        <input type="hidden" name="role" value="<?= $user['role'] === 'admin' ? 'student' : 'admin' ?>">
                                        <input type="hidden" name="return_query" value="<?= adminEscape($currentQuery) ?>">
                                        <button
                                            type="submit"
                                            class="admin-row-action-new"
                                            onclick="return confirm(<?= adminEscape(json_encode(
                                                $user['role'] === 'admin' ? t('admin_users_demote_confirm') : t('admin_users_promote_confirm'),
                                                JSON_UNESCAPED_UNICODE
                                            )) ?>);"
                                        >
                                            <?= adminEscape($user['role'] === 'admin' ? t('admin_users_demote') : t('admin_users_promote')) ?>
                                        </button>
                                    </form>

                                    <form method="POST" action="/masar/admin/users.php">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                        <input type="hidden" name="return_query" value="<?= adminEscape($currentQuery) ?>">
                                        <button
                                            type="submit"
                                            class="admin-row-action-new admin-row-action-new--danger"
                                            onclick="return confirm(<?= adminEscape(json_encode(
                                                sprintf(t('admin_users_delete_confirm'), $user['full_name']),
                                                JSON_UNESCAPED_UNICODE
                                            )) ?>);"
                                        >
                                            <?= adminEscape(t('admin_links_delete_action')) ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="admin-pagination" aria-label="<?= adminEscape(t('admin_pagination')) ?>">
                <?php if ($page > 1): ?>
                    <a href="<?= adminEscape(usersPageUrl($page - 1, $search, $roleFilter)) ?>"><?= adminEscape(t('admin_previous')) ?></a>
                <?php endif; ?>
                <span><?= adminEscape(sprintf(t('admin_page_of'), $page, $totalPages)) ?></span>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= adminEscape(usersPageUrl($page + 1, $search, $roleFilter)) ?>"><?= adminEscape(t('admin_next')) ?></a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
