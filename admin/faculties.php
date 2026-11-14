<?php

declare(strict_types=1);

/*
 * Faculties and majors — the lists students choose from when they
 * register and in their profile.
 *
 * A faculty or major that students are assigned to cannot be deleted
 * (their profiles would silently lose it); rename it instead.
 */

require_once __DIR__ . '/../includes/admin-common.php';

$adminId = (int) $_SESSION['user_id'];
$pageUrl = '/masar/admin/faculties.php';

$errors = [];
$facultyForm = ['id' => 0, 'name' => '', 'name_ar' => ''];
$majorForm = ['id' => 0, 'faculty_id' => 0, 'name' => '', 'name_ar' => ''];

/**
 * Validate an English name and an optional Arabic name.
 *
 * @return string[] Error messages.
 */
function validateLookupNames(string $name, string $nameAr): array
{
    $errors = [];

    if ($name === '') {
        $errors[] = t('admin_faculties_error_name_required');
    } elseif (mb_strlen($name) > 150) {
        $errors[] = t('admin_faculties_error_name_long');
    }

    if (mb_strlen($nameAr) > 150) {
        $errors[] = t('admin_faculties_error_name_long');
    }

    return $errors;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if (!verifyCsrf()) {
        adminFlash('error', t('admin_links_error_invalid_request'));
        adminRedirect($pageUrl);
    }

    $name = trim((string) preg_replace('/\s+/u', ' ', (string) ($_POST['name'] ?? '')));
    $nameAr = trim((string) preg_replace('/\s+/u', ' ', (string) ($_POST['name_ar'] ?? '')));

    /* ---------- Faculty: create / update ---------- */

    if ($action === 'save_faculty') {
        $facultyId = (int) ($_POST['faculty_id'] ?? 0);
        $facultyForm = ['id' => $facultyId, 'name' => $name, 'name_ar' => $nameAr];
        $errors = validateLookupNames($name, $nameAr);

        if (!$errors) {
            $duplicate = $pdo->prepare('SELECT id FROM faculties WHERE name = :name AND id <> :id');
            $duplicate->execute(['name' => $name, 'id' => $facultyId]);

            if ($duplicate->fetch()) {
                $errors[] = t('admin_faculties_error_faculty_exists');
            }
        }

        if (!$errors) {
            if ($facultyId > 0) {
                $pdo->prepare('UPDATE faculties SET name = :name, name_ar = :name_ar WHERE id = :id')
                    ->execute(['name' => $name, 'name_ar' => $nameAr !== '' ? $nameAr : null, 'id' => $facultyId]);

                logActivity($pdo, 'ADMIN_FACULTY_UPDATED', $adminId, 'faculty=' . $facultyId . ' ' . $name);
                adminFlash('success', t('admin_faculties_saved'));
            } else {
                $pdo->prepare('INSERT INTO faculties (name, name_ar) VALUES (:name, :name_ar)')
                    ->execute(['name' => $name, 'name_ar' => $nameAr !== '' ? $nameAr : null]);

                logActivity($pdo, 'ADMIN_FACULTY_CREATED', $adminId, $name);
                adminFlash('success', t('admin_faculties_faculty_added'));
            }

            adminRedirect($pageUrl);
        }
    }

    /* ---------- Major: create / update ---------- */

    if ($action === 'save_major') {
        $majorId = (int) ($_POST['major_id'] ?? 0);
        $facultyId = (int) ($_POST['faculty_id'] ?? 0);
        $majorForm = ['id' => $majorId, 'faculty_id' => $facultyId, 'name' => $name, 'name_ar' => $nameAr];
        $errors = validateLookupNames($name, $nameAr);

        $facultyExists = $pdo->prepare('SELECT id FROM faculties WHERE id = :id');
        $facultyExists->execute(['id' => $facultyId]);

        if (!$facultyExists->fetch()) {
            $errors[] = t('admin_faculties_error_faculty_required');
        }

        if (!$errors) {
            $duplicate = $pdo->prepare(
                'SELECT id FROM majors WHERE faculty_id = :faculty_id AND name = :name AND id <> :id'
            );
            $duplicate->execute(['faculty_id' => $facultyId, 'name' => $name, 'id' => $majorId]);

            if ($duplicate->fetch()) {
                $errors[] = t('admin_faculties_error_major_exists');
            }
        }

        if (!$errors) {
            if ($majorId > 0) {
                $pdo->beginTransaction();

                $pdo->prepare(
                    'UPDATE majors SET faculty_id = :faculty_id, name = :name, name_ar = :name_ar WHERE id = :id'
                )->execute([
                    'faculty_id' => $facultyId,
                    'name' => $name,
                    'name_ar' => $nameAr !== '' ? $nameAr : null,
                    'id' => $majorId,
                ]);

                /* Students of a major that moved follow it to the new faculty. */
                $pdo->prepare('UPDATE users SET faculty_id = :faculty_id WHERE major_id = :major_id')
                    ->execute(['faculty_id' => $facultyId, 'major_id' => $majorId]);

                $pdo->commit();

                logActivity($pdo, 'ADMIN_MAJOR_UPDATED', $adminId, 'major=' . $majorId . ' ' . $name);
                adminFlash('success', t('admin_faculties_saved'));
            } else {
                $pdo->prepare(
                    'INSERT INTO majors (faculty_id, name, name_ar) VALUES (:faculty_id, :name, :name_ar)'
                )->execute([
                    'faculty_id' => $facultyId,
                    'name' => $name,
                    'name_ar' => $nameAr !== '' ? $nameAr : null,
                ]);

                logActivity($pdo, 'ADMIN_MAJOR_CREATED', $adminId, 'faculty=' . $facultyId . ' ' . $name);
                adminFlash('success', t('admin_faculties_major_added'));
            }

            adminRedirect($pageUrl);
        }
    }

    /* ---------- Delete ---------- */

    if ($action === 'delete_major') {
        $majorId = (int) ($_POST['major_id'] ?? 0);

        $inUse = $pdo->prepare('SELECT COUNT(*) FROM users WHERE major_id = :id');
        $inUse->execute(['id' => $majorId]);
        $students = (int) $inUse->fetchColumn();

        if ($students > 0) {
            adminFlash('error', sprintf(t('admin_faculties_error_major_in_use'), $students));
        } else {
            $pdo->prepare('DELETE FROM majors WHERE id = :id')->execute(['id' => $majorId]);

            logActivity($pdo, 'ADMIN_MAJOR_DELETED', $adminId, 'major=' . $majorId);
            adminFlash('success', t('admin_faculties_deleted'));
        }

        adminRedirect($pageUrl);
    }

    if ($action === 'delete_faculty') {
        $facultyId = (int) ($_POST['faculty_id'] ?? 0);

        $inUse = $pdo->prepare(
            'SELECT COUNT(*) FROM users
             WHERE faculty_id = :faculty_id
                OR major_id IN (SELECT id FROM majors WHERE faculty_id = :faculty_id_again)'
        );
        $inUse->execute(['faculty_id' => $facultyId, 'faculty_id_again' => $facultyId]);
        $students = (int) $inUse->fetchColumn();

        if ($students > 0) {
            adminFlash('error', sprintf(t('admin_faculties_error_faculty_in_use'), $students));
        } else {
            /* The faculty's majors are removed with it (foreign key cascade). */
            $pdo->prepare('DELETE FROM faculties WHERE id = :id')->execute(['id' => $facultyId]);

            logActivity($pdo, 'ADMIN_FACULTY_DELETED', $adminId, 'faculty=' . $facultyId);
            adminFlash('success', t('admin_faculties_deleted'));
        }

        adminRedirect($pageUrl);
    }
}

/*
 * Load the lists.
 */

$faculties = $pdo->query(
    'SELECT f.id, f.name, f.name_ar,
            (SELECT COUNT(*) FROM users u WHERE u.faculty_id = f.id) AS student_count
     FROM faculties f
     ORDER BY f.name'
)->fetchAll();

$majorsByFaculty = [];

foreach (
    $pdo->query(
        'SELECT m.id, m.faculty_id, m.name, m.name_ar,
                (SELECT COUNT(*) FROM users u WHERE u.major_id = m.id) AS student_count
         FROM majors m
         ORDER BY m.name'
    )->fetchAll() as $major
) {
    $majorsByFaculty[(int) $major['faculty_id']][] = $major;
}

/* Edit links prefill the forms (unless a failed submit already did). */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $editFacultyId = (int) ($_GET['edit_faculty'] ?? 0);
    $editMajorId = (int) ($_GET['edit_major'] ?? 0);
    $majorForm['faculty_id'] = (int) ($_GET['add_major'] ?? 0);

    foreach ($faculties as $faculty) {
        if ((int) $faculty['id'] === $editFacultyId) {
            $facultyForm = [
                'id' => $editFacultyId,
                'name' => $faculty['name'],
                'name_ar' => (string) $faculty['name_ar'],
            ];
        }

        foreach ($majorsByFaculty[(int) $faculty['id']] ?? [] as $major) {
            if ((int) $major['id'] === $editMajorId) {
                $majorForm = [
                    'id' => $editMajorId,
                    'faculty_id' => (int) $major['faculty_id'],
                    'name' => $major['name'],
                    'name_ar' => (string) $major['name_ar'],
                ];
            }
        }
    }
}

$flash = adminTakeFlash();
$majorTotal = array_sum(array_map('count', $majorsByFaculty));

$pageTitle = t('admin_faculties_title');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<main class="main-content admin-page admin-faculties-page">

    <section class="admin-hero-new">
        <div class="admin-hero-new__copy">
            <span class="admin-eyebrow-new">
                <span class="admin-eyebrow-new__tick"></span>
                <?= adminEscape(t('admin_faculties_eyebrow')) ?>
            </span>

            <h1><?= adminEscape(t('admin_faculties_headline')) ?></h1>
            <p><?= adminEscape(t('admin_faculties_intro')) ?></p>
        </div>

        <img class="admin-hero-new__mark" src="/masar/assets/brand/masar-mark.svg" alt="">
    </section>

    <?php if ($flash): ?>
        <div class="admin-notice-new admin-notice-new--<?= $flash['type'] === 'success' ? 'success' : 'error' ?>" role="status">
            <?= adminEscape($flash['message']) ?>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="admin-notice-new admin-notice-new--error" role="alert">
            <?php foreach ($errors as $error): ?>
                <div><?= adminEscape($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <section class="admin-links-workspace-new">

        <aside class="admin-link-editor-new admin-faculties-editor">

            <!-- Faculty form -->
            <div class="admin-section-kicker-new">
                <?= adminEscape(t('admin_faculties_faculty')) ?>
            </div>

            <div class="admin-link-editor-new__heading">
                <h2 id="faculty-form">
                    <?= adminEscape($facultyForm['id'] > 0 ? t('admin_faculties_edit_faculty') : t('admin_faculties_add_faculty')) ?>
                </h2>

                <?php if ($facultyForm['id'] > 0): ?>
                    <a href="<?= $pageUrl ?>"><?= adminEscape(t('admin_links_cancel_edit')) ?></a>
                <?php endif; ?>
            </div>

            <form method="POST" action="<?= $pageUrl ?>" class="admin-link-form-new">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save_faculty">
                <input type="hidden" name="faculty_id" value="<?= (int) $facultyForm['id'] ?>">

                <div class="admin-field-new">
                    <label for="faculty_name"><?= adminEscape(t('admin_faculties_name_en')) ?></label>
                    <input type="text" id="faculty_name" name="name" value="<?= adminEscape($facultyForm['name']) ?>" maxlength="150" dir="ltr" required>
                </div>

                <div class="admin-field-new">
                    <label for="faculty_name_ar"><?= adminEscape(t('admin_faculties_name_ar')) ?></label>
                    <input type="text" id="faculty_name_ar" name="name_ar" value="<?= adminEscape($facultyForm['name_ar']) ?>" maxlength="150" dir="rtl" lang="ar">
                </div>

                <button type="submit" class="admin-submit-new">
                    <?= adminEscape($facultyForm['id'] > 0 ? t('admin_faculties_save') : t('admin_faculties_add_faculty')) ?>
                </button>
            </form>

            <!-- Major form -->
            <div class="admin-section-kicker-new admin-faculties-editor__second">
                <?= adminEscape(t('admin_faculties_major')) ?>
            </div>

            <div class="admin-link-editor-new__heading">
                <h2 id="major-form">
                    <?= adminEscape($majorForm['id'] > 0 ? t('admin_faculties_edit_major') : t('admin_faculties_add_major')) ?>
                </h2>

                <?php if ($majorForm['id'] > 0): ?>
                    <a href="<?= $pageUrl ?>"><?= adminEscape(t('admin_links_cancel_edit')) ?></a>
                <?php endif; ?>
            </div>

            <?php if (!$faculties): ?>
                <p class="admin-link-editor-new__intro"><?= adminEscape(t('admin_faculties_major_needs_faculty')) ?></p>
            <?php else: ?>
                <form method="POST" action="<?= $pageUrl ?>" class="admin-link-form-new">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="save_major">
                    <input type="hidden" name="major_id" value="<?= (int) $majorForm['id'] ?>">

                    <div class="admin-field-new">
                        <label for="major_faculty"><?= adminEscape(t('admin_faculties_faculty')) ?></label>
                        <select id="major_faculty" name="faculty_id" required>
                            <option value=""><?= adminEscape(t('profile_select_faculty')) ?></option>
                            <?php foreach ($faculties as $faculty): ?>
                                <option value="<?= (int) $faculty['id'] ?>" <?= (int) $faculty['id'] === (int) $majorForm['faculty_id'] ? 'selected' : '' ?>>
                                    <?= adminEscape($faculty['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="admin-field-new">
                        <label for="major_name"><?= adminEscape(t('admin_faculties_name_en')) ?></label>
                        <input type="text" id="major_name" name="name" value="<?= adminEscape($majorForm['name']) ?>" maxlength="150" dir="ltr" required>
                    </div>

                    <div class="admin-field-new">
                        <label for="major_name_ar"><?= adminEscape(t('admin_faculties_name_ar')) ?></label>
                        <input type="text" id="major_name_ar" name="name_ar" value="<?= adminEscape($majorForm['name_ar']) ?>" maxlength="150" dir="rtl" lang="ar">
                    </div>

                    <button type="submit" class="admin-submit-new">
                        <?= adminEscape($majorForm['id'] > 0 ? t('admin_faculties_save') : t('admin_faculties_add_major')) ?>
                    </button>
                </form>
            <?php endif; ?>
        </aside>

        <div class="admin-links-list-new">
            <div class="admin-links-list-new__heading">
                <h2><?= adminEscape(t('admin_faculties_list_title')) ?></h2>
                <span class="admin-links-count-new">
                    <?= adminEscape(sprintf(t('admin_faculties_count'), count($faculties), $majorTotal)) ?>
                </span>
            </div>

            <?php if (!$faculties): ?>
                <div class="admin-links-empty-new">
                    <img src="/masar/assets/brand/masar-mark.svg" alt="">
                    <h3><?= adminEscape(t('admin_faculties_empty')) ?></h3>
                </div>
            <?php endif; ?>

            <?php foreach ($faculties as $faculty): ?>
                <?php $majors = $majorsByFaculty[(int) $faculty['id']] ?? []; ?>

                <article class="admin-faculty-card">
                    <header class="admin-faculty-card__header">
                        <div class="admin-link-title-cell-new">
                            <strong dir="ltr"><?= adminEscape($faculty['name']) ?></strong>
                            <?php if (!empty($faculty['name_ar'])): ?>
                                <small class="admin-link-ar" dir="rtl" lang="ar"><?= adminEscape($faculty['name_ar']) ?></small>
                            <?php else: ?>
                                <small class="admin-link-missing"><?= adminEscape(t('admin_faculties_no_arabic')) ?></small>
                            <?php endif; ?>
                        </div>

                        <div class="admin-table-actions-new">
                            <a class="admin-row-action-new" href="<?= $pageUrl ?>?add_major=<?= (int) $faculty['id'] ?>#major-form">
                                + <?= adminEscape(t('admin_faculties_major')) ?>
                            </a>
                            <a class="admin-row-action-new admin-row-action-new--edit" href="<?= $pageUrl ?>?edit_faculty=<?= (int) $faculty['id'] ?>#faculty-form">
                                <?= adminEscape(t('admin_links_edit_action')) ?>
                            </a>

                            <form method="POST" action="<?= $pageUrl ?>">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete_faculty">
                                <input type="hidden" name="faculty_id" value="<?= (int) $faculty['id'] ?>">
                                <button
                                    type="submit"
                                    class="admin-row-action-new admin-row-action-new--danger"
                                    onclick="return confirm(<?= adminEscape(json_encode(
                                        sprintf(t('admin_faculties_delete_faculty_confirm'), $faculty['name'], count($majors)),
                                        JSON_UNESCAPED_UNICODE
                                    )) ?>);"
                                >
                                    <?= adminEscape(t('admin_links_delete_action')) ?>
                                </button>
                            </form>
                        </div>
                    </header>

                    <?php if (!$majors): ?>
                        <p class="admin-faculty-card__empty"><?= adminEscape(t('admin_faculties_no_majors')) ?></p>
                    <?php else: ?>
                        <ul class="admin-major-list">
                            <?php foreach ($majors as $major): ?>
                                <li>
                                    <div class="admin-link-title-cell-new">
                                        <span dir="ltr"><?= adminEscape($major['name']) ?></span>
                                        <?php if (!empty($major['name_ar'])): ?>
                                            <small class="admin-link-ar" dir="rtl" lang="ar"><?= adminEscape($major['name_ar']) ?></small>
                                        <?php else: ?>
                                            <small class="admin-link-missing"><?= adminEscape(t('admin_faculties_no_arabic')) ?></small>
                                        <?php endif; ?>
                                    </div>

                                    <small class="admin-users-meta">
                                        <?= adminEscape(sprintf(t('admin_faculties_students'), (int) $major['student_count'])) ?>
                                    </small>

                                    <div class="admin-table-actions-new">
                                        <a class="admin-row-action-new admin-row-action-new--edit" href="<?= $pageUrl ?>?edit_major=<?= (int) $major['id'] ?>#major-form">
                                            <?= adminEscape(t('admin_links_edit_action')) ?>
                                        </a>

                                        <form method="POST" action="<?= $pageUrl ?>">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete_major">
                                            <input type="hidden" name="major_id" value="<?= (int) $major['id'] ?>">
                                            <button
                                                type="submit"
                                                class="admin-row-action-new admin-row-action-new--danger"
                                                onclick="return confirm(<?= adminEscape(json_encode(
                                                    sprintf(t('admin_faculties_delete_major_confirm'), $major['name']),
                                                    JSON_UNESCAPED_UNICODE
                                                )) ?>);"
                                            >
                                                <?= adminEscape(t('admin_links_delete_action')) ?>
                                            </button>
                                        </form>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
