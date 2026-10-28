<?php
/** Shared secure session setup and authentication guards for Practical 10. */
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function auth_dashboard_path(string $role): string
{
    return $role === 'admin' ? 'admin_dashboard.php' : 'student_dashboard.php';
}

function auth_clear_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}

function auth_require_role(string $role): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    if (!isset($_SESSION['user_id'], $_SESSION['user_role'])) {
        header('Location: login.html', true, 303);
        exit;
    }
    $timeoutSeconds = 900;
    if (!isset($_SESSION['last_activity']) || time() - (int) $_SESSION['last_activity'] > $timeoutSeconds) {
        auth_clear_session();
        header('Location: login.html?status=timeout', true, 303);
        exit;
    }
    $_SESSION['last_activity'] = time();
    if ($_SESSION['user_role'] !== $role) {
        header('Location: ' . auth_dashboard_path((string) $_SESSION['user_role']), true, 303);
        exit;
    }
}

/** Restore a verified remember-me cookie, rotating it immediately after use. */
function auth_restore_remember(): void
{
    if (isset($_SESSION['user_id']) || !isset($_COOKIE['studenthub_remember'])) {
        return;
    }
    $token = (string) $_COOKIE['studenthub_remember'];
    if (strlen($token) !== 64 || !ctype_xdigit($token)) {
        return;
    }
    try {
        require __DIR__ . '/db_connect_mysqli.php';
        $hash = hash('sha256', $token);
        $query = $conn->prepare('SELECT t.token_id, u.id, u.name, u.email, u.role FROM auth_tokens t JOIN users u ON u.id = t.user_id WHERE t.token_hash = ? AND t.expires_at > CURRENT_TIMESTAMP LIMIT 1');
        $query->bind_param('s', $hash);
        $query->execute();
        $user = $query->get_result()->fetch_assoc();
        $query->close();
        if (!$user || !in_array($user['role'], ['admin', 'student'], true)) {
            $conn->close();
            return;
        }
        $remove = $conn->prepare('DELETE FROM auth_tokens WHERE token_id = ?');
        $tokenId = (int) $user['token_id'];
        $remove->bind_param('i', $tokenId);
        $remove->execute();
        $remove->close();
        $newToken = bin2hex(random_bytes(32));
        $newHash = hash('sha256', $newToken);
        $insert = $conn->prepare('INSERT INTO auth_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 30 DAY))');
        $userId = (int) $user['id'];
        $insert->bind_param('is', $userId, $newHash);
        $insert->execute();
        $insert->close();
        $conn->close();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = (string) $user['name'];
        $_SESSION['user_email'] = (string) $user['email'];
        $_SESSION['user_role'] = (string) $user['role'];
        $_SESSION['last_activity'] = time();
        $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off';
        setcookie('studenthub_remember', $newToken, [
            'expires' => time() + 2592000, 'path' => '/', 'secure' => $https,
            'httponly' => true, 'samesite' => 'Lax',
        ]);
    } catch (Throwable $exception) {
        if (isset($conn) && $conn instanceof mysqli) {
            $conn->close();
        }
        error_log('StudentHub remember-me restore failed: ' . $exception->getMessage());
    }
}

function auth_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
