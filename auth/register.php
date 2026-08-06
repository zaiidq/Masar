<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $universityId = trim($_POST['university_id'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email is required.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'SELECT id FROM users WHERE email = :email LIMIT 1'
        );

        $stmt->execute([
            'email' => $email,
        ]);

        if ($stmt->fetch()) {
            $errors[] = 'This email is already registered.';
        } else {
            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $pdo->prepare(
                'INSERT INTO users
                (full_name, email, password, university_id)
                VALUES
                (:full_name, :email, :password, :university_id)'
            );

            $stmt->execute([
                'full_name' => $fullName,
                'email' => $email,
                'password' => $hashedPassword,
                'university_id' => $universityId !== '' ? $universityId : null,
            ]);

            $success = 'Account created successfully.';

            $fullName = '';
            $universityId = '';
            $email = '';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Register | Masar</title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >
</head>

<body>

    <main class="auth-page">

        <section class="auth-card">

            <div class="auth-logo">
                <h1>Masar</h1>
                <p>Create your student account</p>
            </div>

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

                <div class="form-group">
                    <label for="full_name">
                        Full Name
                    </label>

                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        value="<?= htmlspecialchars($fullName ?? '') ?>"
                        required
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
                        value="<?= htmlspecialchars($universityId ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= htmlspecialchars($email ?? '') ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="password">
                        Password
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="confirm_password">
                        Confirm Password
                    </label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        required
                    >
                </div>

                <button
                    type="submit"
                    class="btn-primary"
                >
                    Create Account
                </button>

            </form>

            <div class="auth-footer">
                Already have an account?

                <a href="login.php">
                    Login
                </a>
            </div>

        </section>

    </main>

</body>
</html>