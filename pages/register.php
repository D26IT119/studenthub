<?php
session_start();
$csrfToken = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;
$result = $_SESSION['registration_result'] ?? null;
unset($_SESSION['registration_result']);
$old = is_array($result['old'] ?? null) ? $result['old'] : [];
function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Secure account registration | StudentHub</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .secure-register { display:grid; grid-template-columns:minmax(260px, .9fr) minmax(320px, 1.1fr); gap:clamp(28px,6vw,88px); align-items:center; max-width:1120px; margin:40px auto; padding:24px; }
        .secure-intro { align-self:center; }
        .secure-intro .eyebrow, .secure-card .eyebrow { color:#3868df; font-size:.82rem; font-weight:800; letter-spacing:.12em; text-transform:uppercase; }
        .secure-intro h1 { font-size:clamp(2.6rem,6vw,4.8rem); line-height:1; margin:.5em 0; }
        .secure-intro p { max-width:38rem; color:var(--muted); font-size:1.1rem; line-height:1.65; }
        .secure-card { padding:clamp(24px,4vw,42px); border:1px solid var(--line); border-radius:26px; background:var(--surface); box-shadow:var(--shadow); }
        .secure-card h2 { margin:.35em 0; }
        .secure-card .lead { color:var(--muted); margin-bottom:24px; }
        .secure-form { display:grid; gap:16px; }
        .secure-form label { display:block; margin-bottom:6px; font-weight:700; }
        .secure-form input:not([type=checkbox]) { width:100%; min-height:48px; padding:11px 14px; border:1px solid var(--line); border-radius:11px; background:rgba(255,255,255,.72); color:var(--ink); font:inherit; }
        .secure-form input:focus { outline:3px solid rgba(56,104,223,.2); border-color:#3868df; }
        .secure-form .hint { margin:5px 0 0; color:var(--muted); font-size:.86rem; }
        .secure-form .field-error { min-height:1.1em; margin:4px 0 0; color:#b42318; font-size:.88rem; }
        .secure-form .input-error { border-color:#d92d20 !important; }
        .secure-form .terms-label { display:flex; align-items:flex-start; gap:9px; font-weight:500; }
        .secure-form .terms-label input { margin-top:4px; }
        .secure-form button { width:100%; }
        .secure-result { padding:13px 16px; border-radius:12px; margin:0 0 18px; }
        .secure-result.success { color:#126238; background:#e8f7ee; }
        .secure-result.error { color:#8f1d18; background:#fff0ee; }
        .secure-result ul { margin:8px 0 0; padding-left:20px; }
        .secure-links { margin-top:18px; text-align:center; }
        body.dark-theme .secure-card { background:#151d36; }
        body.dark-theme .secure-form input:not([type=checkbox]) { background:#202b4b; color:#eef2ff; }
        @media(max-width:760px) { .secure-register { grid-template-columns:1fr; margin:18px auto; } .secure-intro h1 { font-size:3rem; } }
    </style>
</head>
<body>
<header>
    <div class="header-row">
        <div class="brand"><img class="brand-logo" src="../images/logo.jpeg" alt="StudentHub logo"><p class="site-name">StudentHub</p></div>
        <div class="header-actions"><button class="icon-button" type="button" data-theme-toggle aria-label="Switch color theme">☾</button><button class="menu-toggle" type="button" data-menu-toggle aria-expanded="false">☰ Menu</button></div>
    </div>
    <nav class="site-nav" aria-label="Main navigation"><ul>
        <li><a href="index.html">Home</a></li><li><a href="register.php" aria-current="page">Register</a></li><li><a href="login.html">Login</a></li><li><a href="../php/view_users.php">Registered users</a></li>
    </ul></nav>
</header>
<main class="secure-register">
    <section class="secure-intro"><p class="eyebrow">StudentHub registration</p><h1>Create your account</h1><p>Register securely with server-side validation, duplicate checks, and password hashing.</p><p>Your account credentials are stored separately from academic student records.</p></section>
    <section class="secure-card" aria-labelledby="form-title">
        <p class="eyebrow">Student account</p><h2 id="form-title">Registration details</h2><p class="lead">Use a unique username and email address.</p>
        <?php if (is_array($result)): ?>
            <div class="secure-result <?= ($result['status'] ?? '') === 'success' ? 'success' : 'error' ?>" role="<?= ($result['status'] ?? '') === 'success' ? 'status' : 'alert' ?>">
                <?php if (($result['status'] ?? '') === 'success'): ?>
                    <?= e((string) (($result['messages'][0] ?? 'Registration successful.'))) ?>
                <?php else: ?><strong>Please review the following:</strong><ul>
                    <?php foreach (($result['messages'] ?? []) as $message): ?><li><?= e((string) $message) ?></li><?php endforeach; ?>
                </ul><?php endif; ?>
            </div>
        <?php endif; ?>
        <form id="secure-registration-form" class="secure-form" action="../php/process_user_registration.php" method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <div><label for="name">Full name *</label><input id="name" name="name" type="text" autocomplete="name" minlength="2" maxlength="80" value="<?= e((string) ($old['name'] ?? '')) ?>" required aria-describedby="name-hint name-error"><p class="hint" id="name-hint">2 to 80 characters; letters, spaces, apostrophes, periods, or hyphens.</p><p class="field-error" id="name-error" aria-live="polite"></p></div>
            <div><label for="username">Username *</label><input id="username" name="username" type="text" autocomplete="username" minlength="3" maxlength="30" pattern="[A-Za-z0-9_]{3,30}" value="<?= e((string) ($old['username'] ?? '')) ?>" required aria-describedby="username-hint username-error"><p class="hint" id="username-hint">3 to 30 letters, numbers, or underscores.</p><p class="field-error" id="username-error" aria-live="polite"></p></div>
            <div><label for="email">Email address *</label><input id="email" name="email" type="email" autocomplete="email" maxlength="150" value="<?= e((string) ($old['email'] ?? '')) ?>" required aria-describedby="email-hint email-error"><p class="hint" id="email-hint">Use an email address not already registered.</p><p class="field-error" id="email-error" aria-live="polite"></p></div>
            <div><label for="password">Password *</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required aria-describedby="password-hint password-error"><p class="hint" id="password-hint">At least 8 characters, including uppercase, lowercase, a number, and a symbol.</p><p class="field-error" id="password-error" aria-live="polite"></p></div>
            <div><label for="confirm_password">Confirm password *</label><input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required aria-describedby="confirm_password-error"><p class="field-error" id="confirm_password-error" aria-live="polite"></p></div>
            <div><label class="terms-label"><input id="terms" name="terms" type="checkbox" required> I accept the StudentHub terms and privacy policy. *</label><p class="field-error" id="terms-error" aria-live="polite"></p></div>
            <button class="button" type="submit">Create account</button>
        </form>
        <p class="secure-links"><a href="../php/view_users.php">View registered accounts</a></p>
    </section>
</main>
<footer>&copy; 2026 StudentHub</footer>
<script src="../js/script.js"></script><script src="../js/register-user.js"></script>
</body>
</html>
