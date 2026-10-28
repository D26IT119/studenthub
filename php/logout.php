<?php
declare(strict_types=1);
require __DIR__ . '/auth_session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/index.html', true, 303);
    exit;
}
$token = (string) ($_COOKIE['studenthub_remember'] ?? '');
if ($token !== '' && ctype_xdigit($token) && strlen($token) === 64) {
    try {
        require __DIR__ . '/db_connect_mysqli.php';
        $hash = hash('sha256', $token);
        $remove = $conn->prepare('DELETE FROM auth_tokens WHERE token_hash = ?');
        $remove->bind_param('s', $hash);
        $remove->execute();
        $remove->close();
        $conn->close();
    } catch (Throwable $exception) {
        error_log('StudentHub remember-me cleanup failed: ' . $exception->getMessage());
    }
}
$_SESSION = [];
if (isset($_COOKIE['studenthub_remember'])) {
    $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    setcookie('studenthub_remember', '', [
        'expires' => time() - 42000, 'path' => '/', 'secure' => $https,
        'httponly' => true, 'samesite' => 'Lax',
    ]);
}
auth_clear_session();
header('Location: ../pages/login.html?status=logout', true, 303);
exit;
