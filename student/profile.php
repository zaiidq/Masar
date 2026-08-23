<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/language.php';

if ($_SESSION['role'] !== 'student') {
    header('Location: ../admin/dashboard.php');
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = '';

$faculties = $pdo->query(
    'SELECT id, name
     FROM faculties
     ORDER BY name'
)->fetchAll();

$majors = $pdo->query(
    'SELECT id, faculty_id, name
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $errors[] = t('profile_error_invalid_request');
    }

    $fullName = trim($_POST['full_name'] ?? '');
    $universityId = trim($_POST['university_id'] ?? '');

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
    } elseif (strlen($fullName) > 100) {
        $errors[] = t('profile_error_name_long');
    }

    if (strlen($universityId) > 30) {
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
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
                >

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
