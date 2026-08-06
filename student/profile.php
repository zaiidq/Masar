<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

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

$stmt->execute([
    'id' => $_SESSION['user_id'],
]);

$user = $stmt->fetch();

if (!$user) {
    session_destroy();

    header('Location: ../auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $errors[] = 'Invalid request. Please try again.';
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
        $errors[] = 'Full name is required.';
    } elseif (strlen($fullName) > 100) {
        $errors[] = 'Full name is too long.';
    }

    if (strlen($universityId) > 30) {
        $errors[] = 'University ID is too long.';
    }

    if ($facultyId <= 0) {
        $errors[] = 'Please select your faculty.';
    }

    if ($majorId <= 0) {
        $errors[] = 'Please select your major.';
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
            $errors[] = 'The selected major does not belong to this faculty.';
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

        $success = 'Profile updated successfully.';
    }
}

$pageTitle = 'My Profile';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">

    <header class="page-header">
        <h1>My Profile</h1>

        <p>
            View and update your personal and academic information.
        </p>
    </header>

    <section class="content-card profile-card">

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

        <form method="POST" action="">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
            >

            <div class="profile-form-grid">

                <div class="form-group">
                    <label for="full_name">Full Name</label>

                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        value="<?= htmlspecialchars($user['full_name']) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>

                    <input
                        type="email"
                        id="email"
                        value="<?= htmlspecialchars($user['email']) ?>"
                        disabled
                    >
                </div>

                <div class="form-group">
                    <label for="university_id">
                        University ID
                    </label>

                    <input
                        type="text"
                        id="university_id"
                        name="university_id"
                        value="<?= htmlspecialchars(
                            $user['university_id'] ?? ''
                        ) ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="faculty_id">Faculty</label>

                    <select
                        id="faculty_id"
                        name="faculty_id"
                        required
                    >
                        <option value="">Select Faculty</option>

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

                <div class="form-group">
                    <label for="major_id">Major</label>

                    <select
                        id="major_id"
                        name="major_id"
                        data-selected-major="<?=
                            (int) ($user['major_id'] ?? 0)
                        ?>"
                        disabled
                        required
                    >
                        <option value="">
                            Select Faculty First
                        </option>
                    </select>
                </div>

            </div>

            <button
                type="submit"
                class="btn-primary profile-save-button"
            >
                Save Changes
            </button>

        </form>

    </section>

</main>

<script
    type="application/json"
    id="majorsData"
>
<?= json_encode(
    $majors,
    JSON_UNESCAPED_UNICODE
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
) ?>
</script>

<?php

require_once __DIR__ . '/../includes/footer.php';