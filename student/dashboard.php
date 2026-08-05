<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';

if ($_SESSION['role'] !== 'student') {
    header('Location: ../admin/dashboard.php');
    exit;
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
    <title>Student Dashboard | Masar</title>
</head>
<body>

    <h1>
        Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?>
    </h1>

    <p>Student Dashboard</p>

    <a href="../auth/logout.php">Logout</a>

</body>
</html>