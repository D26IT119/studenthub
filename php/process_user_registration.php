<?php
/** Practical 9: validated MySQLi account registration. */
declare(strict_types=1);

session_start();

function redirectWithResult(string $status, array $messages = [], array $old = []): never
{
    $_SESSION['registration_result'] = ['status' => $status, 'messages' => $messages, 'old' => $old];
    header('Location: ../pages/register.php', true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/register.php', true, 303);
    exit;
}

$name = trim((string) ($_POST['name'] ?? ''));
$username = trim((string) ($_POST['username'] ?? ''));
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$password = (string) ($_POST['password'] ?? '');
$confirmPassword = (string) ($_POST['confirm_password'] ?? '');
$old = ['name' => $name, 'username' => $username, 'email' => $email];
$errors = [];

// Match these rules in pages/register.php's browser validation.
if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 80
    || !preg_match("/^[\p{L}]+(?:[ .'-][\p{L}]+)*$/u", $name)) {
    $errors[] = 'Enter a name of 2 to 80 characters using letters, spaces, apostrophes, periods, or hyphens.';
}
if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
    $errors[] = 'Username must be 3 to 30 characters using letters, numbers, or underscores.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
    $errors[] = 'Enter a valid email address (maximum 150 characters).';
}
if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $password)) {
    $errors[] = 'Password must be at least 8 characters and include uppercase, lowercase, a number, and a symbol.';
}
if ($password !== $confirmPassword) {
    $errors[] = 'The passwords do not match.';
}
if (!isset($_POST['terms'])) {
    $errors[] = 'Accept the terms and privacy policy to continue.';
}
$sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
$submittedToken = (string) ($_POST['csrf_token'] ?? '');
if ($sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    $errors[] = 'This form expired or could not be verified. Please try again.';
}
if ($errors) {
    redirectWithResult('error', $errors, $old);
}

try {
    require __DIR__ . '/db_connect_mysqli.php';
    $conn->begin_transaction();

    // The SELECT gives a friendly response; UNIQUE keys handle concurrent requests.
    $check = $conn->prepare('SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1');
    $check->bind_param('ss', $email, $username);
    $check->execute();
    $check->store_result();
    $duplicate = $check->num_rows > 0;
    $check->close();
    if ($duplicate) {
        $conn->rollback();
        $conn->close();
        redirectWithResult('error', ['That email address or username is already registered. Try logging in or choose another.'], $old);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $insert = $conn->prepare('INSERT INTO users (name, username, email, password) VALUES (?, ?, ?, ?)');
    $insert->bind_param('ssss', $name, $username, $email, $passwordHash);
    $insert->execute();
    $newUserId = (int) $conn->insert_id;
    $insert->close();

    // Audit only successful account creation; never log submitted credentials.
    $action = 'user_registered';
    $audit = $conn->prepare('INSERT INTO audit_logs (user_id, action) VALUES (?, ?)');
    $audit->bind_param('is', $newUserId, $action);
    $audit->execute();
    $audit->close();
    $conn->commit();
    $conn->close();

    redirectWithResult('success', ['Account created successfully. Your password is stored as a secure hash.']);
} catch (mysqli_sql_exception $exception) {
    if (isset($conn) && $conn instanceof mysqli) {
        $errorCode = (int) $exception->getCode();
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        $conn->close();
    } else {
        $errorCode = (int) $exception->getCode();
    }
    if ($errorCode === 1062) {
        redirectWithResult('error', ['That email address or username is already registered. Try logging in or choose another.'], $old);
    }
    error_log('StudentHub Practical 9 registration failed: ' . $exception->getMessage());
    redirectWithResult('error', ['We could not create your account right now. Check the database setup and try again.'], $old);
} catch (Throwable $exception) {
    if (isset($conn) && $conn instanceof mysqli) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        $conn->close();
    }
    error_log('StudentHub Practical 9 registration failed: ' . $exception->getMessage());
    redirectWithResult('error', ['We could not create your account right now. Check the database setup and try again.'], $old);
}
