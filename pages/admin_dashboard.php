<?php
declare(strict_types=1);
require __DIR__ . '/../php/auth_session.php';
auth_require_role('admin');

$metrics = ['accounts' => 0, 'admins' => 0, 'students' => 0, 'student_profiles' => 0, 'upcoming_events' => 0, 'registrations' => 0];
$recentAccounts = [];
$upcomingEvents = [];
$dashboardError = false;
try {
    require __DIR__ . '/../php/db_connect_mysqli.php';
    $metricsResult = $conn->query("SELECT (SELECT COUNT(*) FROM users) AS accounts, (SELECT COUNT(*) FROM users WHERE role = 'admin') AS admins, (SELECT COUNT(*) FROM users WHERE role = 'student') AS students, (SELECT COUNT(*) FROM students) AS student_profiles, (SELECT COUNT(*) FROM events WHERE event_date >= CURRENT_DATE) AS upcoming_events, (SELECT COUNT(*) FROM registrations) AS registrations");
    $metrics = array_map('intval', $metricsResult->fetch_assoc());
    $metricsResult->free();
    $accountsResult = $conn->query('SELECT name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5');
    $recentAccounts = $accountsResult->fetch_all(MYSQLI_ASSOC);
    $accountsResult->free();
    $eventsResult = $conn->query('SELECT title, event_date, location FROM events WHERE event_date >= CURRENT_DATE ORDER BY event_date ASC LIMIT 5');
    $upcomingEvents = $eventsResult->fetch_all(MYSQLI_ASSOC);
    $eventsResult->free();
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
        .p10-admin{max-width:1240px;margin:32px auto;padding:24px}.p10-admin-head{display:flex;justify-content:space-between;gap:20px;align-items:end;flex-wrap:wrap}.p10-admin-head h1{margin:.25em 0}.p10-admin-role{display:inline-flex;padding:7px 12px;border-radius:999px;background:#f0eaff;color:#6238b5;font-weight:750}.p10-admin-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin:26px 0}.p10-admin-stat,.p10-admin-panel{padding:22px;border:1px solid var(--line);border-radius:18px;background:var(--surface);box-shadow:var(--shadow)}.p10-admin-stat strong{display:block;font-size:2rem}.p10-admin-stat span,.p10-admin-muted{color:var(--muted)}.p10-admin-grid{display:grid;grid-template-columns:1.2fr .8fr;gap:18px}.p10-admin-panel h2{margin-top:0}.p10-admin-row{display:grid;grid-template-columns:1.2fr 1.4fr .7fr;gap:12px;padding:12px 0;border-bottom:1px solid var(--line);overflow-wrap:anywhere}.p10-admin-row:last-child{border:0}.p10-admin-event{display:flex;justify-content:space-between;gap:16px;padding:13px 0;border-bottom:1px solid var(--line)}.p10-admin-event:last-child{border:0}.p10-admin-error{padding:14px 18px;border-radius:12px;background:#fff0ee;color:#8f1d18}.p10-admin-nav{display:flex;gap:18px;align-items:center;flex-wrap:wrap}.p10-admin-nav button{border:0;background:none;color:var(--ink);font:inherit;cursor:pointer}@media(max-width:760px){.p10-admin-stats{grid-template-columns:1fr}.p10-admin-grid{grid-template-columns:1fr}.p10-admin-row{grid-template-columns:1fr}}
    </style>
</head>
<body>
<header class="site-header"><div class="header-row"><a class="brand" href="index.html"><img class="brand-logo" src="../images/logo.jpeg" alt=""><span class="site-name">StudentHub</span></a><nav class="p10-admin-nav" aria-label="Administrator navigation"><a href="../php/view_users.php">Accounts</a><a href="events.html">Events</a><form action="../php/logout.php" method="post"><button type="submit">Sign out</button></form></nav></div></header>
<main class="p10-admin"><div class="p10-admin-head"><div><p class="eyebrow">StudentHub · Administration</p><h1>Welcome, <?= auth_e((string) $_SESSION['user_name']) ?>.</h1><p class="p10-admin-muted"><?= auth_e((string) $_SESSION['user_email']) ?></p></div><span class="p10-admin-role">Administrator</span></div>
    <?php if ($dashboardError): ?><p class="p10-admin-error" role="alert">We could not load the admin overview. Check that MySQL is running and the StudentHub database is available.</p><?php endif; ?>
    <section class="p10-admin-stats" aria-label="Database overview"><article class="p10-admin-stat"><strong><?= $metrics['accounts'] ?></strong><span>Accounts · <?= $metrics['admins'] ?> admins, <?= $metrics['students'] ?> students</span></article><article class="p10-admin-stat"><strong><?= $metrics['student_profiles'] ?></strong><span>Student profiles in database</span></article><article class="p10-admin-stat"><strong><?= $metrics['upcoming_events'] ?></strong><span>Upcoming events · <?= $metrics['registrations'] ?> total registrations</span></article></section>
    <div class="p10-admin-grid"><section class="p10-admin-panel"><h2>Recently created accounts</h2><div class="p10-admin-row"><strong>Name</strong><strong>Email</strong><strong>Role</strong></div><?php foreach ($recentAccounts as $account): ?><div class="p10-admin-row"><span><?= auth_e((string) $account['name']) ?></span><span><?= auth_e((string) $account['email']) ?></span><span><?= auth_e(ucfirst((string) $account['role'])) ?></span></div><?php endforeach; ?><?php if (!$dashboardError && !$recentAccounts): ?><p class="p10-admin-muted">There are no accounts yet.</p><?php endif; ?><p><a class="button" href="../php/view_users.php">View all accounts</a></p></section>
        <section class="p10-admin-panel"><h2>Upcoming events</h2><?php foreach ($upcomingEvents as $event): ?><article class="p10-admin-event"><div><strong><?= auth_e((string) $event['title']) ?></strong><p class="p10-admin-muted"><?= auth_e((string) $event['location']) ?></p></div><time datetime="<?= auth_e((string) $event['event_date']) ?>"><?= auth_e(date('M j, Y', strtotime((string) $event['event_date']))) ?></time></article><?php endforeach; ?><?php if (!$dashboardError && !$upcomingEvents): ?><p class="p10-admin-muted">There are no future events in the database.</p><?php endif; ?></section></div>
</main><footer class="site-footer"><div class="home-footer-inner"><small>Administrator access is verified from your account role in the StudentHub database.</small></div></footer><script src="../js/script.js?v=practical10"></script>
</body></html>
