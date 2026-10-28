<?php
declare(strict_types=1);
require __DIR__ . '/auth_session.php';

function login_failed(): never
{
    header('Location: ../pages/login.html?status=failed', true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/login.html', true, 303);
    exit;
}
$submittedToken = (string) ($_POST['csrf_token'] ?? '');
$sessionToken = (string) ($_SESSION['login_csrf_token'] ?? '');
if ($sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    login_failed();
}
unset($_SESSION['login_csrf_token']);
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$password = (string) ($_POST['password'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    login_failed();
}

try {
    require __DIR__ . '/db_connect_mysqli.php';
    $query = $conn->prepare('SELECT id, name, email, password, role, last_login FROM users WHERE email = ? LIMIT 1');
    $query->bind_param('s', $email);
    $query->execute();
    $user = $query->get_result()->fetch_assoc();
    $query->close();
    if (!$user || !password_verify($password, (string) $user['password'])) {
        $conn->close();
        login_failed();
    }
    if (!in_array($user['role'], ['admin', 'student'], true)) {
        $conn->close();
        login_failed();
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_name'] = (string) $user['name'];
    $_SESSION['user_email'] = (string) $user['email'];
    $_SESSION['user_role'] = (string) $user['role'];
    $_SESSION['last_activity'] = time();
    if (!empty($user['last_login'])) {
        $_SESSION['previous_login'] = (string) $user['last_login'];
    }

    $update = $conn->prepare('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?');
    $userId = (int) $user['id'];
    $update->bind_param('i', $userId);
    $update->execute();
    $update->close();

    if (!empty($_POST['remember_me'])) {
        $rememberToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rememberToken);
        $tokenInsert = $conn->prepare('INSERT INTO auth_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 30 DAY))');
        $tokenInsert->bind_param('is', $userId, $tokenHash);
        $tokenInsert->execute();
        $tokenInsert->close();
        $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off';
        setcookie('studenthub_remember', $rememberToken, [
            'expires' => time() + 2592000, 'path' => '/', 'secure' => $https,
            'httponly' => true, 'samesite' => 'Lax',
        ]);
    }

    $action = 'login_success';
    $audit = $conn->prepare('INSERT INTO audit_logs (user_id, action) VALUES (?, ?)');
    $audit->bind_param('is', $userId, $action);
    $audit->execute();
    $audit->close();
    $conn->close();
    header('Location: ../pages/' . auth_dashboard_path((string) $user['role']), true, 303);
    exit;
} catch (Throwable $exception) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    error_log('StudentHub Practical 10 login failed: ' . $exception->getMessage());
    login_failed();
}
