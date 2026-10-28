<?php
declare(strict_types=1);
require __DIR__ . '/../php/auth_session.php';
auth_restore_remember();
if (isset($_SESSION['user_id'])) {
    header('Location: ' . auth_dashboard_path((string) $_SESSION['user_role']), true, 303);
    exit;
}
$csrfToken = bin2hex(random_bytes(32));
$_SESSION['login_csrf_token'] = $csrfToken;
$message = match ((string) ($_GET['status'] ?? '')) {
    'timeout' => 'Your session expired after 15 minutes of inactivity. Please sign in again.',
    'logout' => 'You have been signed out successfully.',
    'failed' => 'Invalid email or password.',
    default => '',
};
function login_e(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login | StudentHub</title><link rel="stylesheet" href="../css/style.css"><style>.login-wrap{max-width:560px;margin:clamp(32px,8vw,90px) auto;padding:28px}.login-card{padding:clamp(24px,5vw,42px);border:1px solid var(--line);border-radius:24px;background:var(--surface);box-shadow:var(--shadow)}.login-card h1{margin-top:0}.login-form{display:grid;gap:14px}.login-form label{font-weight:700}.login-form input:not([type=checkbox]){width:100%;min-height:48px;padding:11px 14px;border:1px solid var(--line);border-radius:11px;background:var(--surface);color:var(--ink);font:inherit}.login-message{padding:14px;border-radius:12px;background:#fff0ee;color:#8f1d18}.remember-row{display:flex;gap:9px;align-items:center}</style></head><body><header class="site-header"><div class="header-row"><a class="brand" href="index.html"><img class="brand-logo" src="../images/logo.jpeg" alt=""><span class="site-name">StudentHub</span></a><div class="header-actions"><button class="menu-toggle" type="button" data-menu-toggle aria-expanded="false">☰ Menu</button></div></div><nav class="site-nav" aria-label="Main navigation"><ul><li><a href="index.html">Home</a></li><li><a href="about.html">About</a></li><li><a href="register.php">Register</a></li><li><a href="login.html" aria-current="page">Sign in</a></li><li><a href="events.html">Events</a></li></ul></nav></header><main class="login-wrap"><section class="login-card"><p class="eyebrow">StudentHub account</p><h1>Welcome back</h1><p>Sign in to continue to your campus dashboard.</p><?php if ($message !== ''): ?><p class="login-message" role="alert"><?= login_e($message) ?></p><?php endif; ?><form class="login-form" action="../php/process_login.php" method="post"><input type="hidden" name="csrf_token" value="<?= login_e($csrfToken) ?>"><div><label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="username" maxlength="150" required></div><div><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required></div><label class="remember-row"><input name="remember_me" type="checkbox" value="1"> Keep me signed in for 30 days</label><button class="button" type="submit">Sign in</button></form><p>New to StudentHub? <a href="register.php">Create an account</a></p></section></main><footer class="site-footer"><div class="home-footer-inner"><small>© 2026 StudentHub. All rights reserved.</small></div></footer><script src="../js/script.js?v=practical10"></script></body></html>
