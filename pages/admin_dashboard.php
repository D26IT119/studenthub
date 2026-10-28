<?php
declare(strict_types=1);
require __DIR__ . '/../php/auth_session.php';
auth_require_role('admin');

$metrics = ['accounts' => 0, 'admins' => 0, 'students' => 0, 'student_profiles' => 0, 'events' => 0, 'registrations' => 0];
$recentAccounts = $latestRegistrations = $recentAudit = [];
$dashboardError = false;
try {
    require __DIR__ . '/../php/db_connect_mysqli.php';
    $metricsResult = $conn->query("SELECT (SELECT COUNT(*) FROM users) AS accounts, (SELECT COUNT(*) FROM users WHERE role = 'admin') AS admins, (SELECT COUNT(*) FROM users WHERE role = 'student') AS students, (SELECT COUNT(*) FROM students WHERE status = 'active') AS student_profiles, (SELECT COUNT(*) FROM events) AS events, (SELECT COUNT(*) FROM registrations) AS registrations");
    $metrics = array_map('intval', $metricsResult->fetch_assoc());
    $metricsResult->free();
    $accountsResult = $conn->query('SELECT name, email, role, created_at FROM users ORDER BY created_at DESC, id DESC LIMIT 5');
    $recentAccounts = $accountsResult->fetch_all(MYSQLI_ASSOC);
    $accountsResult->free();
    $registrationsResult = $conn->query('SELECT s.name AS student_name, s.email, e.title AS event_title, r.registered_on FROM registrations r INNER JOIN students s ON s.id = r.student_id INNER JOIN events e ON e.event_id = r.event_id ORDER BY r.registered_on DESC, r.reg_id DESC LIMIT 6');
    $latestRegistrations = $registrationsResult->fetch_all(MYSQLI_ASSOC);
    $registrationsResult->free();
    $auditResult = $conn->query('SELECT a.action, a.created_at, u.name AS actor_name, u.role AS actor_role FROM audit_logs a INNER JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC, a.log_id DESC LIMIT 8');
    $recentAudit = $auditResult->fetch_all(MYSQLI_ASSOC);
    $auditResult->free();
    $conn->close();
} catch (Throwable $exception) {
    if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
    error_log('StudentHub admin dashboard data failed: ' . $exception->getMessage());
    $dashboardError = true;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin Dashboard | StudentHub</title><link rel="stylesheet" href="../css/style.css">
    <style>
        .p14-admin{max-width:1240px;margin:32px auto;padding:24px}.p14-head{display:flex;justify-content:space-between;gap:20px;align-items:end;flex-wrap:wrap}.p14-head h1{margin:.25em 0}.p14-role{display:inline-flex;padding:7px 12px;border-radius:999px;background:#f0eaff;color:#6238b5;font-weight:750}.p14-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin:26px 0}.p14-stat,.p14-panel{padding:22px;border:1px solid var(--line);border-radius:18px;background:var(--surface);box-shadow:var(--shadow)}.p14-stat strong{display:block;font-size:2rem}.p14-stat span,.p14-muted{color:var(--muted)}.p14-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px}.p14-panel h2{margin-top:0}.p14-table-wrap{overflow-x:auto}.p14-table{width:100%;border-collapse:collapse;text-align:left}.p14-table th,.p14-table td{padding:12px 10px;border-bottom:1px solid var(--line);overflow-wrap:anywhere}.p14-table th{font-size:.88rem;color:var(--muted)}.p14-table tr:last-child td{border:0}.p14-error{padding:14px 18px;border-radius:12px;background:#fff0ee;color:#8f1d18}.p14-nav{display:flex;gap:18px;align-items:center;flex-wrap:wrap}.p14-nav button{border:0;background:none;color:var(--ink);font:inherit;cursor:pointer}.p14-badge{display:inline-flex;padding:4px 9px;border-radius:999px;background:#f0eaff;color:#6238b5;font-size:.82rem;font-weight:700}.p14-action{overflow-wrap:anywhere}@media(max-width:820px){.p14-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.p14-grid{grid-template-columns:1fr}}@media(max-width:520px){.p14-stats{grid-template-columns:1fr}.p14-admin{padding:16px}.p14-table th,.p14-table td{padding:9px 7px;font-size:.9rem}}
    </style>
</head>
<body>
<header class="site-header"><div class="header-row"><a class="brand" href="index.html"><img class="brand-logo" src="../images/logo.jpeg" alt=""><span class="site-name">StudentHub</span></a><nav class="p14-nav" aria-label="Administrator navigation">
<?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?><a href="admin_dashboard.php" aria-current="page">Overview</a><a href="student_management.php">Manage Students</a><a href="../php/view_users.php">Accounts</a><a href="event_management.php">Manage Events</a><a href="events.html">Public Events</a><?php else: ?><a href="student_dashboard.php">My Dashboard</a><a href="events.html">Browse Events</a><?php endif; ?>
<form action="../php/logout.php" method="post"><button type="submit">Sign out</button></form></nav></div></header>
<main class="p14-admin"><div class="p14-head"><div><p class="eyebrow">StudentHub · Administration</p><h1>Welcome, <?= auth_e((string) $_SESSION['user_name']) ?>.</h1><p class="p14-muted"><?= auth_e((string) $_SESSION['user_email']) ?></p></div><span class="p14-role">Administrator</span></div>
<?php if ($dashboardError): ?><p class="p14-error" role="alert">We could not load the admin overview. Check that MySQL is running and the StudentHub database is available.</p><?php endif; ?>
<section class="p14-stats" aria-label="Live database statistics"><article class="p14-stat"><strong><?= $metrics['accounts'] ?></strong><span>Total accounts · <?= $metrics['admins'] ?> admins, <?= $metrics['students'] ?> students</span></article><article class="p14-stat"><strong><?= $metrics['student_profiles'] ?></strong><span>Active student records</span></article><article class="p14-stat"><strong><?= $metrics['events'] ?></strong><span>Total campus events</span></article><article class="p14-stat"><strong><?= $metrics['registrations'] ?></strong><span>Event registrations</span></article></section>
<div class="p14-grid"><section class="p14-panel"><h2>Latest registrations</h2><?php if ($latestRegistrations): ?><div class="p14-table-wrap"><table class="p14-table"><thead><tr><th>Student</th><th>Event</th><th>Registered</th></tr></thead><tbody><?php foreach ($latestRegistrations as $registration): ?><tr><td><?= auth_e((string) $registration['student_name']) ?><br><small class="p14-muted"><?= auth_e((string) $registration['email']) ?></small></td><td><?= auth_e((string) $registration['event_title']) ?></td><td><time><?= auth_e(date('M j, Y · g:i a', strtotime((string) $registration['registered_on']))) ?></time></td></tr><?php endforeach; ?></tbody></table></div><?php elseif (!$dashboardError): ?><p class="p14-muted">No registrations have been recorded yet.</p><?php endif; ?></section>
<section class="p14-panel"><h2>Recent accounts</h2><?php if ($recentAccounts): ?><div class="p14-table-wrap"><table class="p14-table"><thead><tr><th>Name</th><th>Email</th><th>Role</th></tr></thead><tbody><?php foreach ($recentAccounts as $account): ?><tr><td><?= auth_e((string) $account['name']) ?></td><td><?= auth_e((string) $account['email']) ?></td><td><span class="p14-badge"><?= auth_e(ucfirst((string) $account['role'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php elseif (!$dashboardError): ?><p class="p14-muted">There are no accounts yet.</p><?php endif; ?><p><a class="button" href="../php/view_users.php">View all accounts</a></p></section></div>
<section class="p14-panel"><h2>Recent audit activity</h2><p class="p14-muted">Successful account and admin actions recorded in <code>audit_logs</code>.</p><?php if ($recentAudit): ?><div class="p14-table-wrap"><table class="p14-table"><thead><tr><th>Action</th><th>Performed by</th><th>Time</th></tr></thead><tbody><?php foreach ($recentAudit as $entry): ?><tr><td class="p14-action"><?= auth_e(str_replace('_', ' ', ucfirst((string) $entry['action']))) ?></td><td><?= auth_e((string) $entry['actor_name']) ?> <span class="p14-badge"><?= auth_e(ucfirst((string) $entry['actor_role'])) ?></span></td><td><time><?= auth_e(date('M j, Y · g:i a', strtotime((string) $entry['created_at']))) ?></time></td></tr><?php endforeach; ?></tbody></table></div><?php elseif (!$dashboardError): ?><p class="p14-muted">No audit activity has been recorded yet.</p><?php endif; ?></section>
</main><footer class="site-footer"><div class="home-footer-inner"><small>Administrator access is verified from your account role in the StudentHub database.</small></div></footer><script src="../js/script.js?v=practical14"></script>
</body></html>
