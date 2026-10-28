<?php
declare(strict_types=1);
$users = [];
$loadError = false;
try {
    require __DIR__ . '/db_connect_mysqli.php';
    $result = $conn->query('SELECT id, name, username, email, role, created_at FROM users ORDER BY id DESC');
    $users = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
    $conn->close();
} catch (Throwable $exception) {
    error_log('StudentHub users list failed: ' . $exception->getMessage());
    $loadError = true;
}
function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Registered accounts | StudentHub</title><link rel="stylesheet" href="../css/style.css">
<style>.users-wrap{max-width:1100px;margin:36px auto;padding:24px}.users-table-wrap{overflow-x:auto}.users-table{width:100%;border-collapse:collapse;background:var(--surface)}.users-table th,.users-table td{text-align:left;padding:13px;border-bottom:1px solid var(--line)}.users-table th{background:#315fe0;color:#fff}.users-note{color:var(--muted)}.users-error{padding:18px;border-radius:12px;background:#fff0ee;color:#8f1d18}</style></head><body>
<header><div class="header-row"><div class="brand"><img class="brand-logo" src="../images/logo.jpeg" alt="StudentHub logo"><p class="site-name">StudentHub</p></div><div class="header-actions"><button class="icon-button" type="button" data-theme-toggle aria-label="Switch color theme">☾</button></div></div><nav class="site-nav" aria-label="Main navigation"><ul><li><a href="../pages/index.html">Home</a></li><li><a href="../pages/register.php">Register</a></li><li><a href="view_users.php" aria-current="page">Registered users</a></li></ul></nav></header>
<main class="users-wrap"><a href="../pages/register.php">← Back to registration</a><h1>Registered accounts</h1><p class="users-note">Account details are shown for the Practical 9 database demonstration. Passwords are never displayed.</p>
<?php if ($loadError): ?><p class="users-error" role="alert">Could not load accounts. Check that MySQL is running and Practical 9 tables have been created.</p>
<?php elseif (!$users): ?><p>No accounts are registered yet.</p>
<?php else: ?><div class="users-table-wrap"><table class="users-table"><thead><tr><th>ID</th><th>Full name</th><th>Username</th><th>Email</th><th>Role</th><th>Created</th></tr></thead><tbody><?php foreach ($users as $user): ?><tr><td><?= (int) $user['id'] ?></td><td><?= e((string) $user['name']) ?></td><td><?= e((string) $user['username']) ?></td><td><?= e((string) $user['email']) ?></td><td><?= e((string) $user['role']) ?></td><td><?= e((string) $user['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></main><footer>&copy; 2026 StudentHub</footer><script src="../js/script.js"></script></body></html>
