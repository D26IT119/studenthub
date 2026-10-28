<?php
/** Practical 13 JSON API for StudentHub events. */
declare(strict_types=1);

require __DIR__ . '/auth_session.php';
auth_restore_remember();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

function events_api_reply(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function events_api_admin(): bool
{
    if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        return false;
    }
    if (!isset($_SESSION['last_activity']) || time() - (int) $_SESSION['last_activity'] > 900) {
        auth_clear_session();
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

function events_api_date(string $value): bool
{
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

try {
    require __DIR__ . '/db_connect_mysqli.php';
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'GET') {
        $result = $conn->query(
            'SELECT event_id AS id, title, event_date AS date, event_time AS time,
                    location AS venue, category, capacity, description, status
             FROM events ORDER BY event_date, event_time, event_id'
        );
        $events = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
        foreach ($events as &$event) {
            $event['id'] = (int) $event['id'];
            $event['capacity'] = (int) $event['capacity'];
            $event['time'] = substr((string) $event['time'], 0, 5);
            $event['description'] = (string) ($event['description'] ?? '');
        }
        unset($event);

        $canManage = events_api_admin();
        $response = ['success' => true, 'data' => $events, 'permissions' => ['canManage' => $canManage]];
        if ($canManage) {
            $auditResult = $conn->query(
                'SELECT l.id, l.event_id, l.event_title, l.action, l.occurred_at, u.name AS admin_name
                 FROM event_audit_log AS l
                 INNER JOIN users AS u ON u.id = l.admin_user_id
                 ORDER BY l.id DESC LIMIT 20'
            );
            $response['auditLog'] = $auditResult->fetch_all(MYSQLI_ASSOC);
            $auditResult->free();
            if (empty($_SESSION['events_api_csrf'])) {
                $_SESSION['events_api_csrf'] = bin2hex(random_bytes(32));
            }
            $response['csrfToken'] = (string) $_SESSION['events_api_csrf'];
        }
        events_api_reply(200, $response);
    }

    if ($method !== 'POST') {
        header('Allow: GET, POST');
        events_api_reply(405, ['success' => false, 'message' => 'Use GET to list events or POST to change an event.']);
    }

    if (!events_api_admin()) {
        events_api_reply(401, ['success' => false, 'message' => 'Sign in with an administrator account to manage events.']);
    }

    $contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
    if ($contentType !== 'application/json') {
        events_api_reply(415, ['success' => false, 'message' => 'Send changes with the application/json content type.']);
    }

    $csrf = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $expectedCsrf = (string) ($_SESSION['events_api_csrf'] ?? '');
    if ($csrf === '' || $expectedCsrf === '' || !hash_equals($expectedCsrf, $csrf)) {
        events_api_reply(403, ['success' => false, 'message' => 'Your session token is missing or expired. Refresh the page and try again.']);
    }

    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody === false ? '' : $rawBody, true);
    if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
        events_api_reply(400, ['success' => false, 'message' => 'Send a valid JSON object.']);
    }
    $action = $payload['action'] ?? '';
    if (!is_string($action) || !in_array($action, ['add', 'update', 'delete'], true)) {
        events_api_reply(400, ['success' => false, 'message' => 'Choose add, update, or delete.']);
    }

    $id = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
    if ($action !== 'add' && (!$id || $id < 1)) {
        events_api_reply(422, ['success' => false, 'message' => 'Provide a valid event ID.']);
    }

    if ($action === 'delete') {
        $conn->begin_transaction();
        $check = $conn->prepare('SELECT event_id, title FROM events WHERE event_id = ? FOR UPDATE');
        $check->bind_param('i', $id);
        $check->execute();
        $event = $check->get_result()->fetch_assoc();
        $check->close();
        if (!$event) {
            $conn->rollback();
            events_api_reply(404, ['success' => false, 'message' => 'That event no longer exists. Refresh the list and try again.']);
        }
        $delete = $conn->prepare('DELETE FROM events WHERE event_id = ?');
        $delete->bind_param('i', $id);
        $delete->execute();
        $delete->close();
        $actorId = (int) $_SESSION['user_id'];
        $eventTitle = (string) $event['title'];
        $eventAudit = $conn->prepare("INSERT INTO event_audit_log (admin_user_id, event_id, event_title, action) VALUES (?, NULL, ?, 'deleted')");
        $eventAudit->bind_param('is', $actorId, $eventTitle);
        $eventAudit->execute();
        $eventAudit->close();
        $actionText = 'event.deleted#' . $id;
        $audit = $conn->prepare('INSERT INTO audit_logs (user_id, action) VALUES (?, ?)');
        $audit->bind_param('is', $actorId, $actionText);
        $audit->execute();
        $audit->close();
        $conn->commit();
        events_api_reply(200, ['success' => true, 'message' => 'Event deleted successfully.']);
    }

    $title = trim((string) ($payload['title'] ?? ''));
    $date = trim((string) ($payload['date'] ?? ''));
    $time = trim((string) ($payload['time'] ?? ''));
    $venue = trim((string) ($payload['venue'] ?? ''));
    $category = trim((string) ($payload['category'] ?? ''));
    $capacity = filter_var($payload['capacity'] ?? null, FILTER_VALIDATE_INT);
    $description = trim((string) ($payload['description'] ?? ''));
    $status = (string) ($payload['status'] ?? 'open');

    if ($title === '' || mb_strlen($title, 'UTF-8') > 150) {
        events_api_reply(422, ['success' => false, 'message' => 'Enter an event title of up to 150 characters.']);
    }
    if (!events_api_date($date) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
        events_api_reply(422, ['success' => false, 'message' => 'Enter a valid event date and time.']);
    }
    if ($venue === '' || mb_strlen($venue, 'UTF-8') > 100 || $category === '' || mb_strlen($category, 'UTF-8') > 50) {
        events_api_reply(422, ['success' => false, 'message' => 'Enter a venue (up to 100 characters) and category (up to 50 characters).']);
    }
    if ($capacity === false || $capacity < 1 || $capacity > 100000) {
        events_api_reply(422, ['success' => false, 'message' => 'Set a capacity between 1 and 100,000.']);
    }
    if (mb_strlen($description, 'UTF-8') > 5000 || !in_array($status, ['open', 'closed'], true)) {
        events_api_reply(422, ['success' => false, 'message' => 'Keep the description under 5,000 characters and choose Open or Closed.']);
    }

    if ($action === 'add') {
        $statement = $conn->prepare('INSERT INTO events (title, event_date, event_time, location, category, capacity, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $statement->bind_param('sssssiss', $title, $date, $time, $venue, $category, $capacity, $description, $status);
    } else {
        $statement = $conn->prepare('UPDATE events SET title = ?, event_date = ?, event_time = ?, location = ?, category = ?, capacity = ?, description = ?, status = ? WHERE event_id = ?');
        $statement->bind_param('sssssissi', $title, $date, $time, $venue, $category, $capacity, $description, $status, $id);
    }

    $conn->begin_transaction();
    try {
        if ($action === 'update') {
            $exists = $conn->prepare('SELECT event_id FROM events WHERE event_id = ? FOR UPDATE');
            $exists->bind_param('i', $id);
            $exists->execute();
            $found = $exists->get_result()->num_rows === 1;
            $exists->close();
            if (!$found) {
                $conn->rollback();
                events_api_reply(404, ['success' => false, 'message' => 'That event no longer exists. Refresh the list and try again.']);
            }
        }
        $statement->execute();
        $eventId = $action === 'add' ? (int) $conn->insert_id : (int) $id;
        $statement->close();
        $actorId = (int) $_SESSION['user_id'];
        $actionText = 'event.' . ($action === 'add' ? 'added#' : 'updated#') . $eventId;
        $eventAction = $action === 'add' ? 'created' : 'updated';
        $eventAudit = $conn->prepare('INSERT INTO event_audit_log (admin_user_id, event_id, event_title, action) VALUES (?, ?, ?, ?)');
        $eventAudit->bind_param('iiss', $actorId, $eventId, $title, $eventAction);
        $eventAudit->execute();
        $eventAudit->close();
        $audit = $conn->prepare('INSERT INTO audit_logs (user_id, action) VALUES (?, ?)');
        $audit->bind_param('is', $actorId, $actionText);
        $audit->execute();
        $audit->close();
        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    }

    $statusCode = $action === 'add' ? 201 : 200;
    events_api_reply($statusCode, [
        'success' => true,
        'message' => $action === 'add' ? 'Event added successfully.' : 'Event updated successfully.',
        'data' => ['id' => $eventId],
    ]);
} catch (mysqli_sql_exception $exception) {
    error_log('StudentHub event API database error: ' . $exception->getMessage());
    if ((int) $exception->getCode() === 1062) {
        events_api_reply(409, ['success' => false, 'message' => 'An event with that title and date already exists.']);
    }
    events_api_reply(500, ['success' => false, 'message' => 'The database could not complete this request. Check the event details and try again.']);
} catch (Throwable $exception) {
    error_log('StudentHub event API request failed: ' . $exception->getMessage());
    events_api_reply(500, ['success' => false, 'message' => 'The request could not be completed. Please try again.']);
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
