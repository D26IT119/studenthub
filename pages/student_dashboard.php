<?php
declare(strict_types=1);
require __DIR__ . '/../php/auth_session.php';
auth_require_role('student');

$studentProfile = null;
$upcomingEvents = [];
$upcomingCount = 0;
$registrationCount = 0;
$dashboardError = false;
try {
    require __DIR__ . '/../php/db_connect_mysqli.php';
    $countResult = $conn->query('SELECT COUNT(*) AS total FROM events WHERE event_date >= CURRENT_DATE');
    $upcomingCount = (int) $countResult->fetch_assoc()['total'];
    $countResult->free();

    $profileQuery = $conn->prepare('SELECT s.id, s.name, s.year, c.course_name FROM students s LEFT JOIN courses c ON c.course_id = s.course_id WHERE LOWER(s.email) = LOWER(?) LIMIT 1');
    $profileQuery->bind_param('s', $_SESSION['user_email']);
    $profileQuery->execute();
    $studentProfile = $profileQuery->get_result()->fetch_assoc() ?: null;
    $profileQuery->close();

    if ($studentProfile) {
        $registrationQuery = $conn->prepare('SELECT COUNT(*) AS total FROM registrations r JOIN students s ON s.id = r.student_id WHERE s.id = ?');
        $profileId = (int) $studentProfile['id'];
        $registrationQuery->bind_param('i', $profileId);
        $registrationQuery->execute();
        $registrationCount = (int) $registrationQuery->get_result()->fetch_assoc()['total'];
        $registrationQuery->close();
    }

    $eventsResult = $conn->query('SELECT title, event_date, location, category FROM events WHERE event_date >= CURRENT_DATE ORDER BY event_date ASC LIMIT 4');
    $upcomingEvents = $eventsResult->fetch_all(MYSQLI_ASSOC);
    $eventsResult->free();
    $conn->close();
} catch (Throwable $exception) {
    if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
    error_log('StudentHub student dashboard data failed: ' . $exception->getMessage());
    $dashboardError = true;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Student Dashboard | StudentHub</title><link rel="stylesheet" href="../css/style.css">
    <style>
        .p10-main{max-width:1180px;margin:32px auto;padding:24px}.p10-heading{display:flex;justify-content:space-between;gap:20px;align-items:end;flex-wrap:wrap}.p10-heading h1{margin:.25em 0}.p10-role{display:inline-flex;padding:7px 12px;border-radius:999px;background:#e9efff;color:#244fc4;font-weight:750}.p10-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin:26px 0}.p10-stat,.p10-panel{padding:22px;border:1px solid var(--line);border-radius:18px;background:var(--surface);box-shadow:var(--shadow)}.p10-stat strong{display:block;font-size:2rem}.p10-stat span,.p10-muted{color:var(--muted)}.p10-grid{display:grid;grid-template-columns:1.3fr .7fr;gap:18px}.p10-panel h2{margin-top:0}.p10-event{display:flex;justify-content:space-between;gap:16px;padding:14px 0;border-bottom:1px solid var(--line)}.p10-event:last-child{border:0}.p10-event p{margin:4px 0}.p10-notice{padding:14px 18px;border-radius:12px;background:#fff4df;color:#754c00}.p10-error{padding:14px 18px;border-radius:12px;background:#fff0ee;color:#8f1d18}.p10-logout button{border:0;background:none;color:var(--ink);font:inherit;cursor:pointer}.p10-topnav{display:flex;gap:18px;align-items:center;flex-wrap:wrap}@media(max-width:760px){.p10-stats{grid-template-columns:1fr}.p10-grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
<header class="site-header"><div class="header-row"><a class="brand" href="index.html"><img class="brand-logo" src="../images/logo.jpeg" alt=""><span class="site-name">StudentHub</span></a><nav class="p10-topnav" aria-label="Student navigation"><a href="events.html">Events</a><a href="profile.html">Profile</a><form class="p10-logout" action="../php/logout.php" method="post"><button type="submit">Sign out</button></form></nav></div></header>
<main class="p10-main">
    <div class="p10-heading"><div><p class="eyebrow">Student account · Dashboard</p><h1>Welcome, <?= auth_e((string) $_SESSION['user_name']) ?>.</h1><p class="p10-muted"><?= auth_e((string) $_SESSION['user_email']) ?></p></div><span class="p10-role">Student</span></div>
    <?php if ($dashboardError): ?><p class="p10-error" role="alert">We could not load your campus information. Check that MySQL is running and the StudentHub database is available.</p><?php endif; ?>
    <section class="p10-stats" aria-label="Your StudentHub information">
        <article class="p10-stat"><strong><?= $upcomingCount ?></strong><span>Upcoming campus events</span></article>
        <article class="p10-stat"><strong><?= $studentProfile ? $registrationCount : '—' ?></strong><span>Events you registered for</span></article>
        <article class="p10-stat"><strong><?= $studentProfile ? 'Linked' : 'Not linked' ?></strong><span>Student record</span></article>
    </section>
    <div class="p10-grid"><section class="p10-panel"><h2>Upcoming campus events</h2>
        <?php if (!$dashboardError && !$upcomingEvents): ?><p class="p10-muted">There are no upcoming events in the database right now.</p><?php endif; ?>
        <?php foreach ($upcomingEvents as $event): ?><article class="p10-event"><div><strong><?= auth_e((string) $event['title']) ?></strong><p class="p10-muted"><?= auth_e((string) $event['category']) ?> · <?= auth_e((string) $event['location']) ?></p></div><time datetime="<?= auth_e((string) $event['event_date']) ?>"><?= auth_e(date('M j, Y', strtotime((string) $event['event_date']))) ?></time></article><?php endforeach; ?>
        <p><a class="button" href="events.html">Explore events</a></p>
    </section><aside class="p10-panel"><h2>Your profile</h2>
        <?php if ($studentProfile): ?><p><strong><?= auth_e((string) $studentProfile['name']) ?></strong></p><p class="p10-muted"><?= auth_e((string) ($studentProfile['course_name'] ?? 'Course not set')) ?> · Year <?= (int) $studentProfile['year'] ?></p><p>Your login email is linked to your student record.</p>
        <?php else: ?><p>Your account is signed in, but its email does not match a record in the <code>students</code> table yet.</p><p class="p10-muted">Current login: <?= auth_e((string) $_SESSION['user_email']) ?></p><?php endif; ?>
    </aside></div>
    <?php if (isset($_SESSION['previous_login'])): ?><p class="p10-muted">Previous sign in: <?= auth_e((string) $_SESSION['previous_login']) ?></p><?php endif; ?>
</main><footer class="site-footer"><div class="home-footer-inner"><small>StudentHub · Your session expires after 15 minutes of inactivity.</small></div></footer><script src="../js/script.js?v=practical10"></script>
</body></html>
