<?php
/**
 * Development-server router for PHP's built-in web server.
 * Run from the project root; Apache users can continue to use .htaccess.
 */
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$normalizedPath = str_replace('\\', '/', $path);

if ($normalizedPath === '/') {
    header('Location: /pages/index.html', true, 302);
    exit;
}

// Send the old static dashboard URLs through the session-protected pages.
$legacyDashboards = [
    '/pages/dashboard.html' => '/pages/student_dashboard.php',
    '/pages/admin.html' => '/pages/admin_dashboard.php',
];
if ($normalizedPath === '/pages/login.html') {
    require __DIR__ . '/pages/login.php';
    exit;
}
if (isset($legacyDashboards[$normalizedPath])) {
    header('Location: ' . $legacyDashboards[$normalizedPath], true, 302);
    exit;
}

// The built-in server does not read .htaccess, so protect stored registrations here.
if (preg_match('~(?:^|/)Data/registrations\.(?:csv|json)$~i', $normalizedPath)) {
    http_response_code(404);
    exit('Not found');
}

$root = realpath(__DIR__);
$file = realpath(__DIR__ . $normalizedPath);
if ($root !== false && $file !== false
    && strncasecmp($file, $root . DIRECTORY_SEPARATOR, strlen($root . DIRECTORY_SEPARATOR)) === 0
    && is_file($file)) {
    return false;
}

http_response_code(404);
echo 'Not found';
