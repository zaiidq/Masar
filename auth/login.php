<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare(
        'SELECT id, full_name, email, password, role
         FROM users
         WHERE email = :email
         LIMIT 1'
    );

    $stmt->execute([
        'email' => $email,
    ]);

    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];

        if ($user['role'] === 'admin') {
            header('Location: ../admin/dashboard.php');
        } else {
            header('Location: ../student/dashboard.php');
        }

        exit;
    }

    $error = 'Invalid email or password.';
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
    <title>Login | Masar</title>
</head>
<body>

    <h1>Login</h1>

    <?php if ($error !== ''): ?>
        <p><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST" action="">
        <div>
            <label for="email">Email</label>
            <input
                type="email"
                id="email"
                name="email"
                required
            >
        </div>

        <div>
            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                required
            >
        </div>

        <button type="submit">Login</button>
    </form>

</body>
</html>