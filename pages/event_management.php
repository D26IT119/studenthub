<?php
declare(strict_types=1);
require __DIR__ . '/../php/auth_session.php';
auth_require_role('admin');
require __DIR__ . '/../php/db_connect_mysqli.php';

function p12_e(string|int|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function p12_redirect(): never
{
    header('Location: event_management.php', true, 303);
    exit;
}

function p12_remove_poster(?string $path): void
{
    if ($path !== null && preg_match('~^images/uploads/events/[a-f0-9]{40}\.(?:jpg|png)$~', $path)) {
        $absolute = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }
}

function p12_upload_poster(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !isset($file['tmp_name'])) {
        throw new RuntimeException('The poster upload failed. Choose an image smaller than 2 MB and try again.');
    }
    if ((int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Poster images must be no larger than 2 MB.');
    }

    $image = @getimagesize((string) $file['tmp_name']);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    if (!$image || !isset($types[$mime]) || ($image['mime'] ?? '') !== $mime) {
        throw new RuntimeException('Choose a valid JPG, JPEG, or PNG image for the poster.');
    }

    $directory = dirname(__DIR__) . '/images/uploads/events';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('The poster upload folder is unavailable.');
    }
    $filename = bin2hex(random_bytes(20)) . '.' . $types[$mime];
    if (!move_uploaded_file((string) $file['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('The poster could not be saved. Check the upload folder permissions.');
    }
    return 'images/uploads/events/' . $filename;
}

if (empty($_SESSION['event_management_csrf'])) {
    $_SESSION['event_management_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['event_management_csrf'];
$flash = $_SESSION['event_management_flash'] ?? null;
unset($_SESSION['event_management_flash']);
$editEvent = null;
$uploadToRemove = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($postedToken) || !hash_equals($csrf, $postedToken)) {
        http_response_code(403);
        exit('Invalid request token. Refresh the page and try again.');
    }

    $action = (string) ($_POST['action'] ?? '');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $newPoster = null;
    try {
        if ($action === 'add' || $action === 'update') {
            $title = trim((string) ($_POST['title'] ?? ''));
            $date = trim((string) ($_POST['event_date'] ?? ''));
            $time = trim((string) ($_POST['event_time'] ?? ''));
            $location = trim((string) ($_POST['location'] ?? ''));
            $category = trim((string) ($_POST['category'] ?? ''));
            $capacity = filter_var($_POST['capacity'] ?? null, FILTER_VALIDATE_INT);
            $description = trim((string) ($_POST['description'] ?? ''));
            $status = (string) ($_POST['status'] ?? '');
            $dateObject = DateTime::createFromFormat('!Y-m-d', $date);
            $timeObject = DateTime::createFromFormat('!H:i', $time);

            if ($title === '' || strlen($title) > 150) {
                throw new RuntimeException('Enter an event title of up to 150 characters.');
            }
            if (!$dateObject || $dateObject->format('Y-m-d') !== $date || !$timeObject || $timeObject->format('H:i') !== $time) {
                throw new RuntimeException('Enter a valid event date and time.');
            }
            if ($location === '' || strlen($location) > 100 || $category === '' || strlen($category) > 50) {
                throw new RuntimeException('Enter a location (up to 100 characters) and category (up to 50 characters).');
            }
            if ($capacity === false || $capacity < 1 || $capacity > 100000) {
                throw new RuntimeException('Set an event capacity between 1 and 100,000.');
            }
            if (strlen($description) > 5000 || !in_array($status, ['open', 'closed'], true)) {
                throw new RuntimeException('Enter a description under 5,000 characters and choose an event status.');
            }
            if ($action === 'update' && (!$id || $id < 1)) {
                throw new RuntimeException('The event record could not be identified.');
            }

            $newPoster = p12_upload_poster($_FILES['poster'] ?? []);
            $conn->begin_transaction();
            if ($action === 'add') {
                $statement = $conn->prepare('INSERT INTO events (title, event_date, event_time, location, category, capacity, description, status, poster_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $statement->bind_param('sssssisss', $title, $date, $time, $location, $category, $capacity, $description, $status, $newPoster);
                $statement->execute();
                $eventId = (int) $conn->insert_id;
                $statement->close();
                $actionText = 'event.added#' . $eventId;
            } else {
                $find = $conn->prepare('SELECT poster_path, (SELECT COUNT(*) FROM registrations r WHERE r.event_id = events.event_id) AS registration_count FROM events WHERE event_id = ?');
                $find->bind_param('i', $id);
                $find->execute();
                $existing = $find->get_result()->fetch_assoc();
                $find->close();
                if (!$existing) {
                    throw new RuntimeException('That event no longer exists. Refresh and try again.');
                }
                if ($capacity < (int) $existing['registration_count']) {
                    throw new RuntimeException('Capacity cannot be lower than the ' . (int) $existing['registration_count'] . ' student registrations already recorded.');
                }
                $uploadToRemove = $newPoster === null ? null : ($existing['poster_path'] ?: null);
                if ($newPoster === null) {
                    $statement = $conn->prepare('UPDATE events SET title = ?, event_date = ?, event_time = ?, location = ?, category = ?, capacity = ?, description = ?, status = ? WHERE event_id = ?');
                    $statement->bind_param('sssssissi', $title, $date, $time, $location, $category, $capacity, $description, $status, $id);
                } else {
                    $statement = $conn->prepare('UPDATE events SET title = ?, event_date = ?, event_time = ?, location = ?, category = ?, capacity = ?, description = ?, status = ?, poster_path = ? WHERE event_id = ?');
                    $statement->bind_param('sssssisssi', $title, $date, $time, $location, $category, $capacity, $description, $status, $newPoster, $id);
                }
                $statement->execute();
                $statement->close();
                $eventId = $id;
                $actionText = 'event.updated#' . $eventId;
            }
            $actorId = (int) $_SESSION['user_id'];
            $audit = $conn->prepare('INSERT INTO audit_logs (user_id, action) VALUES (?, ?)');
            $audit->bind_param('is', $actorId, $actionText);
            $audit->execute();
            $audit->close();
            $conn->commit();
            p12_remove_poster($uploadToRemove);
            $_SESSION['event_management_flash'] = ['type' => 'success', 'message' => $action === 'add' ? 'Event added successfully.' : 'Event updated successfully.'];
            p12_redirect();
        } elseif ($action === 'delete') {
            if (!$id || $id < 1) {
                throw new RuntimeException('The event record could not be identified.');
            }
            $conn->begin_transaction();
            $find = $conn->prepare('SELECT poster_path FROM events WHERE event_id = ? FOR UPDATE');
            $find->bind_param('i', $id);
            $find->execute();
            $event = $find->get_result()->fetch_assoc();
            $find->close();
            if (!$event) {
                throw new RuntimeException('That event no longer exists. Refresh and try again.');
            }
            $delete = $conn->prepare('DELETE FROM events WHERE event_id = ?');
            $delete->bind_param('i', $id);
            $delete->execute();
            $delete->close();
            $actorId = (int) $_SESSION['user_id'];
            $actionText = 'event.deleted#' . $id;
            $audit = $conn->prepare('INSERT INTO audit_logs (user_id, action) VALUES (?, ?)');
            $audit->bind_param('is', $actorId, $actionText);
            $audit->execute();
            $audit->close();
            $conn->commit();
            p12_remove_poster($event['poster_path'] ?: null);
            $_SESSION['event_management_flash'] = ['type' => 'success', 'message' => 'Event deleted successfully.'];
            p12_redirect();
        } else {
            throw new RuntimeException('Unknown event management action.');
        }
    } catch (Throwable $exception) {
        if ($conn->errno === 0 || $conn->ping()) {
            try { $conn->rollback(); } catch (Throwable $ignored) { }
        }
        p12_remove_poster($newPoster);
        error_log('StudentHub event management failed: ' . $exception->getMessage());
        $message = $exception instanceof mysqli_sql_exception
            ? ((int) $exception->getCode() === 1062
                ? 'An event with that title and date already exists.'
                : 'The database could not complete this change. Check the event details and try again.')
            : ($exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'The database could not complete this change. Check the event details and try again.');
        $_SESSION['event_management_flash'] = ['type' => 'error', 'message' => $message];
        p12_redirect();
    }
}

if (isset($_GET['edit'])) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
    if ($editId && $editId > 0) {
        $statement = $conn->prepare('SELECT event_id, title, event_date, event_time, location, category, capacity, description, status, poster_path FROM events WHERE event_id = ?');
        $statement->bind_param('i', $editId);
        $statement->execute();
        $editEvent = $statement->get_result()->fetch_assoc() ?: null;
        $statement->close();
    }
}
$result = $conn->query('SELECT e.event_id, e.title, e.event_date, e.event_time, e.location, e.category, e.capacity, e.description, e.status, e.poster_path, (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.event_id) AS registration_count FROM events e ORDER BY e.event_date, e.event_time, e.event_id');
$events = $result->fetch_all(MYSQLI_ASSOC);
$result->free();
$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Manage Events | StudentHub</title><link rel="stylesheet" href="../css/style.css">
    <style>
        .p12-wrap{max-width:1240px;margin:32px auto;padding:24px}.p12-head{display:flex;justify-content:space-between;align-items:end;gap:16px;flex-wrap:wrap}.p12-head h1{margin:.2em 0}.p12-muted{color:var(--muted)}.p12-grid{display:grid;grid-template-columns:minmax(280px,.75fr) minmax(0,1.5fr);gap:22px;align-items:start;margin-top:22px}.p12-panel{padding:22px;border:1px solid var(--line);border-radius:18px;background:var(--surface);box-shadow:var(--shadow)}.p12-panel h2{margin-top:0}.p12-form{display:grid;gap:13px}.p12-form label{display:grid;gap:6px;font-weight:650}.p12-form input,.p12-form select,.p12-form textarea{width:100%;padding:11px 12px;border:1px solid var(--line);border-radius:10px;background:var(--surface);color:var(--ink);font:inherit}.p12-form textarea{min-height:100px;resize:vertical}.p12-two{display:grid;grid-template-columns:1fr 1fr;gap:12px}.p12-help{font-size:.86rem;color:var(--muted);font-weight:400}.p12-message{padding:13px 16px;border-radius:12px}.p12-success{background:#eaf8ef;color:#176339}.p12-error{background:#fff0ee;color:#8f1d18}.p12-list{display:grid;gap:13px}.p12-event{display:grid;grid-template-columns:110px minmax(0,1fr) auto;gap:16px;align-items:center;padding:14px;border:1px solid var(--line);border-radius:14px}.p12-poster{width:110px;height:78px;object-fit:cover;border-radius:9px;background:var(--line)}.p12-placeholder{display:grid;place-items:center;color:var(--muted);font-size:.8rem}.p12-event h3{margin:0 0 4px}.p12-event p{margin:4px 0}.p12-badge{display:inline-block;padding:3px 9px;border-radius:999px;background:#e8f5ed;color:#176339;font-size:.8rem;font-weight:700}.p12-badge--closed{background:#fff1e8;color:#8b4513}.p12-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.p12-actions form{margin:0}.p12-link{border:0;padding:0;background:none;color:#a12424;font:inherit;text-decoration:underline;cursor:pointer}.p12-preview{max-width:100%;max-height:180px;border-radius:10px}.p12-empty{text-align:center;padding:24px;color:var(--muted)}@media(max-width:850px){.p12-grid{grid-template-columns:1fr}}@media(max-width:560px){.p12-wrap{padding:16px}.p12-event{grid-template-columns:76px 1fr}.p12-poster{width:76px;height:70px}.p12-actions{grid-column:1/-1}.p12-two{grid-template-columns:1fr}}
    </style>
</head>
<body>
<header class="site-header"><div class="header-row"><a class="brand" href="index.html"><img class="brand-logo" src="../images/logo.jpeg" alt=""><span class="site-name">StudentHub</span></a><nav class="p12-actions" aria-label="Administrator navigation"><a href="admin_dashboard.php">Dashboard</a><a href="events.html">Public events</a><form action="../php/logout.php" method="post"><button class="p12-link" type="submit">Sign out</button></form></nav></div></header>
<main class="p12-wrap"><div class="p12-head"><div><p class="eyebrow">StudentHub · Administration</p><h1>Event management</h1><p class="p12-muted">Create campus events, manage open or closed status, and upload validated posters.</p></div><span class="p12-badge"><?= count($events) ?> events</span></div>
<?php if (is_array($flash)): ?><p class="p12-message <?= ($flash['type'] ?? '') === 'success' ? 'p12-success' : 'p12-error' ?>" role="<?= ($flash['type'] ?? '') === 'success' ? 'status' : 'alert' ?>"><?= p12_e($flash['message'] ?? '') ?></p><?php endif; ?>
<div class="p12-grid"><section class="p12-panel" id="event-form"><h2><?= $editEvent ? 'Edit event' : 'Add event' ?></h2><form class="p12-form" method="post" enctype="multipart/form-data" action="event_management.php"><input type="hidden" name="csrf_token" value="<?= p12_e($csrf) ?>"><input type="hidden" name="action" value="<?= $editEvent ? 'update' : 'add' ?>"><?php if ($editEvent): ?><input type="hidden" name="id" value="<?= (int) $editEvent['event_id'] ?>"><?php endif; ?>
<label>Event title<input name="title" maxlength="150" required value="<?= p12_e($editEvent['title'] ?? '') ?>"></label><div class="p12-two"><label>Date<input type="date" name="event_date" required value="<?= p12_e($editEvent['event_date'] ?? '') ?>"></label><label>Time<input type="time" name="event_time" required value="<?= p12_e(substr((string) ($editEvent['event_time'] ?? '09:00'), 0, 5)) ?>"></label></div><div class="p12-two"><label>Location<input name="location" maxlength="100" required value="<?= p12_e($editEvent['location'] ?? '') ?>"></label><label>Category<input name="category" maxlength="50" required value="<?= p12_e($editEvent['category'] ?? '') ?>"></label></div><label>Capacity <span class="p12-help">Maximum number of student registrations</span><input type="number" name="capacity" min="1" max="100000" required value="<?= p12_e($editEvent['capacity'] ?? 100) ?>"></label><label>Description <span class="p12-help">Optional, up to 5,000 characters</span><textarea name="description" maxlength="5000"><?= p12_e($editEvent['description'] ?? '') ?></textarea></label><label>Status<select name="status"><option value="open" <?= ($editEvent['status'] ?? 'open') === 'open' ? 'selected' : '' ?>>Open</option><option value="closed" <?= ($editEvent['status'] ?? '') === 'closed' ? 'selected' : '' ?>>Closed</option></select></label><label>Poster <span class="p12-help">JPG, JPEG, or PNG · maximum 2 MB<?= $editEvent ? ' · leave empty to keep the current poster' : '' ?></span><input type="file" name="poster" accept="image/jpeg,image/png"></label>
<?php if (!empty($editEvent['poster_path'])): ?><img class="p12-preview" src="../<?= p12_e($editEvent['poster_path']) ?>" alt="Current poster for <?= p12_e($editEvent['title']) ?>"><?php endif; ?><button class="button" type="submit"><?= $editEvent ? 'Save changes' : 'Add event' ?></button><?php if ($editEvent): ?><a href="event_management.php">Cancel edit</a><?php endif; ?></form></section>
<section class="p12-panel"><h2>Events</h2><?php if (!$events): ?><p class="p12-empty">No events yet. Use the form to add the first event.</p><?php else: ?><div class="p12-list"><?php foreach ($events as $event): ?><article class="p12-event"><?php if ($event['poster_path']): ?><img class="p12-poster" src="../<?= p12_e($event['poster_path']) ?>" alt="Poster for <?= p12_e($event['title']) ?>"><?php else: ?><div class="p12-poster p12-placeholder" aria-label="No poster">No poster</div><?php endif; ?><div><h3><?= p12_e($event['title']) ?></h3><p class="p12-muted"><?= p12_e(date('M j, Y', strtotime((string) $event['event_date']))) ?> · <?= p12_e(substr((string) $event['event_time'], 0, 5)) ?> · <?= p12_e($event['location']) ?></p><p><?= p12_e($event['category']) ?> <span class="p12-badge <?= $event['status'] === 'closed' ? 'p12-badge--closed' : '' ?>"><?= p12_e(ucfirst((string) $event['status'])) ?></span> · Registrations <?= (int) $event['registration_count'] ?> / <?= (int) $event['capacity'] ?></p><?php if (trim((string) $event['description']) !== ''): ?><p class="p12-muted"><?= p12_e($event['description']) ?></p><?php endif; ?></div><div class="p12-actions"><a href="event_management.php?edit=<?= (int) $event['event_id'] ?>#event-form">Edit</a><form method="post" action="event_management.php" onsubmit="return confirm('Delete <?= p12_e($event['title']) ?>? Student registrations for this event will also be removed.');"><input type="hidden" name="csrf_token" value="<?= p12_e($csrf) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $event['event_id'] ?>"><button class="p12-link" type="submit">Delete</button></form></div></article><?php endforeach; ?></div><?php endif; ?></section></div>
</main><footer class="site-footer"><div class="home-footer-inner"><small>Event changes are restricted to signed-in administrators and recorded in the StudentHub audit log.</small></div></footer><script src="../js/script.js"></script></body></html>
