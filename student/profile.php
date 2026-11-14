<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../includes/activity-logger.php';
require_once __DIR__ . '/../includes/password-reset.php';

if (($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ../admin/dashboard.php');
    exit;
}

csrfToken();

$errors = [];
$passwordErrors = [];
$passwordSuccess = '';
$deleteErrors = [];
$success = '';

$faculties = $pdo->query(
    'SELECT id, ' . localizedNameSql() . ' AS name
     FROM faculties
     ORDER BY name'
)->fetchAll();

$majors = $pdo->query(
    'SELECT id, faculty_id, ' . localizedNameSql() . ' AS name
     FROM majors
     ORDER BY name'
)->fetchAll();

$stmt = $pdo->prepare(
    'SELECT
        id,
        full_name,
        email,
        university_id,
        faculty_id,
        major_id
     FROM users
     WHERE id = :id
     LIMIT 1'
);

$stmt->execute(['id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: ../auth/login.php');
    exit;
}

$action = (string) ($_POST['action'] ?? 'update_profile');

/*
 * FR3 / UC-02: delete the account after password confirmation.
 * Removes the user, every uploaded PDF, analyses and conversations.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete_account') {
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    $passwordStatement = $pdo->prepare('SELECT password FROM users WHERE id = :id');
    $passwordStatement->execute(['id' => $_SESSION['user_id']]);
    $passwordHash = (string) $passwordStatement->fetchColumn();

    if (!verifyCsrf()) {
        $deleteErrors[] = t('profile_error_invalid_request');
    } elseif (!password_verify($confirmPassword, $passwordHash)) {
        $deleteErrors[] = t('profile_delete_wrong_password');
    } else {
        $userId = (int) $_SESSION['user_id'];

        $fileStatement = $pdo->prepare(
            'SELECT file_path FROM academic_records WHERE user_id = :user_id'
        );
        $fileStatement->execute(['user_id' => $userId]);
        $filePaths = $fileStatement->fetchAll(PDO::FETCH_COLUMN);

        /* Records, courses, recommendations and chats cascade. */
        $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);

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
            'ACCOUNT_DELETED',
            $userId,
            sprintf('Deleted by the student; %d record file(s) removed', count($filePaths))
        );

        $language = currentLanguage();

        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['language'] = $language;

        header('Location: /masar/auth/login.php?deleted=1');
        exit;
    }
}

/*
 * Change password: needs the current password, and signs out every
 * other device that is logged in to this account.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'change_password') {
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmNewPassword = (string) ($_POST['confirm_new_password'] ?? '');

    $passwordStatement = $pdo->prepare('SELECT password FROM users WHERE id = :id');
    $passwordStatement->execute(['id' => $_SESSION['user_id']]);
    $passwordHash = (string) $passwordStatement->fetchColumn();

    if (!verifyCsrf()) {
        $passwordErrors[] = t('profile_error_invalid_request');
    } elseif (!password_verify($currentPassword, $passwordHash)) {
        $passwordErrors[] = t('profile_password_wrong_current');

        logActivity($pdo, 'PASSWORD_CHANGE_FAILED', (int) $_SESSION['user_id'], 'wrong current password');
    } else {
        if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            $passwordErrors[] = t('auth_error_password_length');
        }

        if ($newPassword !== $confirmNewPassword) {
            $passwordErrors[] = t('auth_error_password_match');
        }

        if (!$passwordErrors && password_verify($newPassword, $passwordHash)) {
            $passwordErrors[] = t('profile_password_same');
        }

        if (!$passwordErrors) {
            /* This session stays signed in; all others are signed out. */
            $_SESSION['password_changed_at'] = setUserPassword(
                $pdo,
                (int) $_SESSION['user_id'],
                $newPassword
            );
            session_regenerate_id(true);

            logActivity($pdo, 'PASSWORD_CHANGED', (int) $_SESSION['user_id']);

            $passwordSuccess = t('profile_password_changed');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_profile') {
    if (!verifyCsrf()) {
        $errors[] = t('profile_error_invalid_request');
    }

    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $universityId = trim((string) ($_POST['university_id'] ?? ''));

    $facultyId = filter_var(
        $_POST['faculty_id'] ?? null,
        FILTER_VALIDATE_INT
    ) ?: 0;

    $majorId = filter_var(
        $_POST['major_id'] ?? null,
        FILTER_VALIDATE_INT
    ) ?: 0;

    if ($fullName === '') {
        $errors[] = t('profile_error_name_required');
    } elseif (mb_strlen($fullName) > 100) {
        $errors[] = t('profile_error_name_long');
    }

    if (mb_strlen($universityId) > 30) {
        $errors[] = t('profile_error_id_long');
    }

    if ($facultyId <= 0) {
        $errors[] = t('profile_error_faculty_required');
    }

    if ($majorId <= 0) {
        $errors[] = t('profile_error_major_required');
    }

    if ($facultyId > 0 && $majorId > 0) {
        $stmt = $pdo->prepare(
            'SELECT id
             FROM majors
             WHERE id = :major_id
               AND faculty_id = :faculty_id
             LIMIT 1'
        );

        $stmt->execute([
            'major_id' => $majorId,
            'faculty_id' => $facultyId,
        ]);

        if (!$stmt->fetch()) {
            $errors[] = t('profile_error_major_faculty');
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'UPDATE users
             SET full_name = :full_name,
                 university_id = :university_id,
                 faculty_id = :faculty_id,
                 major_id = :major_id
             WHERE id = :id'
        );

        $stmt->execute([
            'full_name' => $fullName,
            'university_id' => $universityId !== ''
                ? $universityId
                : null,
            'faculty_id' => $facultyId,
            'major_id' => $majorId,
            'id' => $_SESSION['user_id'],
        ]);

        $_SESSION['full_name'] = $fullName;

        $user['full_name'] = $fullName;
        $user['university_id'] = $universityId;
        $user['faculty_id'] = $facultyId;
        $user['major_id'] = $majorId;

        $success = t('profile_success');
    }
}

$pageTitle = t('profile');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content profile-page-new">
    <section class="profile-hero-new">
        <img
            src="/masar/assets/brand/masar-mark.svg"
            alt=""
            class="profile-hero-new__mark"
        >

        <span class="path-eyebrow">
            <span class="path-eyebrow__tick"></span>
            <?= htmlspecialchars(t('profile_eyebrow')) ?>
        </span>

        <h1><?= htmlspecialchars(t('profile_headline')) ?></h1>
        <p><?= htmlspecialchars(t('profile_intro')) ?></p>
    </section>

    <?php if (!empty($errors)): ?>
        <div class="record-alert record-alert--error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="record-alert record-alert--success">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <div class="profile-layout-new">
        <section class="profile-form-panel-new">
            <div class="record-section-heading">
                <h2><?= htmlspecialchars(t('profile_form_title')) ?></h2>
            </div>

            <form method="POST" action="" class="profile-form-new">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update_profile">

                <div class="profile-form-grid-new">
                    <div class="profile-field-new profile-field-new--wide">
                        <label for="full_name">
                            <?= htmlspecialchars(t('profile_full_name')) ?>
                        </label>
                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            value="<?= htmlspecialchars($user['full_name']) ?>"
                            required
                        >
                    </div>

                    <div class="profile-field-new">
                        <label for="university_id">
                            <?= htmlspecialchars(t('profile_university_id')) ?>
                        </label>
                        <input
                            type="text"
                            id="university_id"
                            name="university_id"
                            value="<?= htmlspecialchars($user['university_id'] ?? '') ?>"
                        >
                    </div>

                    <div class="profile-field-new">
                        <label for="faculty_id">
                            <?= htmlspecialchars(t('profile_faculty')) ?>
                        </label>
                        <select id="faculty_id" name="faculty_id" required>
                            <option value="">
                                <?= htmlspecialchars(t('profile_select_faculty')) ?>
                            </option>

                            <?php foreach ($faculties as $faculty): ?>
                                <option
                                    value="<?= (int) $faculty['id'] ?>"
                                    <?= (int) $faculty['id']
                                        === (int) ($user['faculty_id'] ?? 0)
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= htmlspecialchars($faculty['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="profile-field-new profile-field-new--wide">
                        <label for="major_id">
                            <?= htmlspecialchars(t('profile_major')) ?>
                        </label>
                        <select
                            id="major_id"
                            name="major_id"
                            data-selected-major="<?= (int) ($user['major_id'] ?? 0) ?>"
                            data-placeholder-major="<?= htmlspecialchars(t('profile_select_major'), ENT_QUOTES) ?>"
                            data-placeholder-faculty-first="<?= htmlspecialchars(t('profile_select_faculty_first'), ENT_QUOTES) ?>"
                            disabled
                            required
                        >
                            <option value="">
                                <?= htmlspecialchars(t('profile_select_faculty_first')) ?>
                            </option>
                        </select>
                    </div>
                </div>

                <div class="profile-actions-new">
                    <button type="submit" class="path-button profile-save-new">
                        <?= htmlspecialchars(t('profile_save')) ?>
                    </button>
                </div>
            </form>
        </section>

        <aside class="profile-account-panel-new">
            <div class="record-kicker">
                <?= htmlspecialchars(t('profile_account_title')) ?>
            </div>

            <div class="profile-account-row-new">
                <span><?= htmlspecialchars(t('profile_email')) ?></span>
                <strong dir="ltr"><?= htmlspecialchars($user['email']) ?></strong>
            </div>

            <p><?= htmlspecialchars(t('profile_account_note')) ?></p>
        </aside>
    </div>

    <section class="profile-form-panel-new profile-password-panel" id="change-password">
        <div class="record-section-heading">
            <h2><?= htmlspecialchars(t('profile_password_title')) ?></h2>
        </div>

        <p class="profile-password-panel__note"><?= htmlspecialchars(t('profile_password_note')) ?></p>

        <?php if ($passwordErrors): ?>
            <div class="record-alert record-alert--error" role="alert">
                <ul>
                    <?php foreach ($passwordErrors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($passwordSuccess !== ''): ?>
            <div class="record-alert record-alert--success" role="status">
                <?= htmlspecialchars($passwordSuccess) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="#change-password" class="profile-form-new">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="change_password">

            <div class="profile-form-grid-new">
                <div class="profile-field-new profile-field-new--wide">
                    <label for="current_password"><?= htmlspecialchars(t('profile_password_current')) ?></label>
                    <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
                </div>

                <div class="profile-field-new">
                    <label for="new_password"><?= htmlspecialchars(t('auth_new_password')) ?></label>
                    <input type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="8" required>
                </div>

                <div class="profile-field-new">
                    <label for="confirm_new_password"><?= htmlspecialchars(t('auth_confirm_new_password')) ?></label>
                    <input type="password" id="confirm_new_password" name="confirm_new_password" autocomplete="new-password" minlength="8" required>
                </div>
            </div>

            <div class="profile-actions-new">
                <button type="submit" class="path-button profile-save-new">
                    <?= htmlspecialchars(t('profile_password_action')) ?>
                </button>
            </div>
        </form>
    </section>

    <section class="profile-danger-zone" id="delete-account">
        <div class="profile-danger-zone__copy">
            <h2><?= htmlspecialchars(t('profile_delete_title')) ?></h2>
            <p><?= htmlspecialchars(t('profile_delete_body')) ?></p>
        </div>

        <?php if ($deleteErrors): ?>
            <div class="record-alert record-alert--error" role="alert">
                <ul>
                    <?php foreach ($deleteErrors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form
            method="POST"
            action="#delete-account"
            class="profile-danger-zone__form"
            onsubmit="return confirm(<?= htmlspecialchars(json_encode(t('profile_delete_confirm'), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>);"
        >
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete_account">

            <label for="confirm_password"><?= htmlspecialchars(t('profile_delete_password')) ?></label>

            <div class="profile-danger-zone__row">
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    autocomplete="current-password"
                    required
                >

                <button type="submit" class="profile-delete-button">
                    <?= htmlspecialchars(t('profile_delete_action')) ?>
                </button>
            </div>
        </form>
    </section>
</main>

<script type="application/json" id="majorsData">
<?= json_encode(
    $majors,
    JSON_UNESCAPED_UNICODE
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
) ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
