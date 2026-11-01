<?php

declare(strict_types=1);

/**
 * Create the first administrator, or promote an existing account.
 *
 *   php tools/create-admin.php
 *
 * Registration through the website always creates students, so this
 * command-line tool is the only way to obtain the first admin account.
 * Later admins can be promoted from the admin Users page.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';

function prompt(string $label): string
{
    fwrite(STDOUT, $label);

    return trim((string) fgets(STDIN));
}

function promptHidden(string $label): string
{
    fwrite(STDOUT, $label);

    $canHide = DIRECTORY_SEPARATOR === '/' && stream_isatty(STDIN);

    if ($canHide) {
        shell_exec('stty -echo');
    }

    $value = trim((string) fgets(STDIN));

    if ($canHide) {
        shell_exec('stty echo');
        fwrite(STDOUT, "\n");
    }

    return $value;
}

echo "Masar — create administrator\n\n";

$email = strtolower(prompt('Email: '));

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
    fwrite(STDERR, "Invalid email address.\n");
    exit(1);
}

$statement = $pdo->prepare('SELECT id, full_name, role FROM users WHERE email = :email');
$statement->execute(['email' => $email]);
$existing = $statement->fetch();

if ($existing) {
    if ($existing['role'] === 'admin') {
        echo "{$existing['full_name']} is already an administrator.\n";
        exit(0);
    }

    $answer = strtolower(prompt(
        "{$existing['full_name']} exists as a student. Promote to admin? [y/N] "
    ));

    if ($answer !== 'y') {
        echo "Nothing changed.\n";
        exit(0);
    }

    $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = :id")
        ->execute(['id' => $existing['id']]);

    echo "Promoted to administrator.\n";
    exit(0);
}

$fullName = prompt('Full name: ');

if ($fullName === '' || mb_strlen($fullName) > 100) {
    fwrite(STDERR, "Full name is required (max 100 characters).\n");
    exit(1);
}

$password = promptHidden('Password (min 8 characters): ');

if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(1);
}

if (promptHidden('Repeat password: ') !== $password) {
    fwrite(STDERR, "Passwords do not match.\n");
    exit(1);
}

$pdo->prepare(
    "INSERT INTO users (full_name, email, password, role)
     VALUES (:full_name, :email, :password, 'admin')"
)->execute([
    'full_name' => $fullName,
    'email' => $email,
    'password' => password_hash($password, PASSWORD_DEFAULT),
]);

echo "Administrator created. Sign in at /masar/auth/login.php\n";
