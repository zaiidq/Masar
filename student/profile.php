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

$stmt = $pdo->prepare(
    'SELECT id, full_name, email, university_id, major
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
    $major = trim($_POST['major'] ?? '');

    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    } elseif (strlen($fullName) > 100) {
        $errors[] = 'Full name is too long.';
    }

    if (strlen($universityId) > 30) {
        $errors[] = 'University ID is too long.';
    }

    if (strlen($major) > 100) {
        $errors[] = 'Major name is too long.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'UPDATE users
             SET full_name = :full_name,
                 university_id = :university_id,
                 major = :major
             WHERE id = :id'
        );

        $stmt->execute([
            'full_name' => $fullName,
            'university_id' => $universityId !== ''
                ? $universityId
                : null,
            'major' => $major !== ''
                ? $major
                : null,
            'id' => $_SESSION['user_id'],
        ]);

        $_SESSION['full_name'] = $fullName;

        $user['full_name'] = $fullName;
        $user['university_id'] = $universityId;
        $user['major'] = $major;

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
                    <label for="full_name">
                        Full Name
                    </label>

                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        value="<?= htmlspecialchars($user['full_name']) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        value="<?= htmlspecialchars($user['email']) ?>"
                        disabled
                    >

                    <small class="form-help">
                        Email cannot be changed from this page.
                    </small>
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
                    <label for="major">
                        Major
                    </label>

                    <input
                        type="text"
                        id="major"
                        name="major"
                        value="<?= htmlspecialchars(
                            $user['major'] ?? ''
                        ) ?>"
                        placeholder="Computer Science"
                    >
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

<?php

require_once __DIR__ . '/../includes/footer.php';