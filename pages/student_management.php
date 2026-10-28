<?php
declare(strict_types=1);
require __DIR__ . '/../php/auth_session.php';
auth_require_role('admin');

require __DIR__ . '/../php/db_connect_mysqli.php';

function p11_e(string|int|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function p11_statement(mysqli $conn, string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $statement = $conn->prepare($sql);
    if ($types !== '') {
        $arguments = [$types];
        foreach ($params as $key => &$value) {
            $arguments[] = &$value;
        }
        call_user_func_array([$statement, 'bind_param'], $arguments);
        unset($value);
    }
    return $statement;
}

function p11_audit(mysqli $conn, string $action): void
{
    $statement = p11_statement($conn, 'INSERT INTO audit_logs (user_id, action) VALUES (?, ?)', 'is', [(int) $_SESSION['user_id'], $action]);
    $statement->execute();
    $statement->close();
}

function p11_redirect(array $query = []): never
{
    $url = 'student_management.php';
    if ($query !== []) {
        $url .= '?' . http_build_query($query);
    }
    header('Location: ' . $url, true, 303);
    exit;
}

if (empty($_SESSION['student_management_csrf'])) {
    $_SESSION['student_management_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['student_management_csrf'];
$flash = $_SESSION['student_management_flash'] ?? null;
unset($_SESSION['student_management_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($postedToken) || !hash_equals($csrf, $postedToken)) {
        http_response_code(403);
        exit('Invalid request token. Refresh the page and try again.');
    }

    $action = (string) ($_POST['action'] ?? '');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $error = '';

    try {
        if ($action === 'add' || $action === 'update') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $mobile = trim((string) ($_POST['mobile'] ?? ''));
            $courseId = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT);
            $year = filter_var($_POST['year'] ?? null, FILTER_VALIDATE_INT);
            $gender = (string) ($_POST['gender'] ?? '');
            $nameLength = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);

            if ($name === '' || $nameLength > 100) {
                $error = 'Enter a student name (maximum 100 characters).';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
                $error = 'Enter a valid email address (maximum 150 characters).';
            } elseif ($mobile === '' || strlen($mobile) > 15) {
                $error = 'Enter a mobile number of up to 15 characters.';
            } elseif (!$courseId || !$year || $year < 1 || $year > 4 || !in_array($gender, ['Male', 'Female', 'Other'], true)) {
                $error = 'Select a valid course, year, and gender.';
            } elseif ($action === 'update' && (!$id || $id < 1)) {
                $error = 'The student record could not be identified.';
            } else {
                $courseCheck = p11_statement($conn, 'SELECT course_id FROM courses WHERE course_id = ?', 'i', [$courseId]);
                $courseCheck->execute();
                $courseExists = $courseCheck->get_result()->num_rows === 1;
                $courseCheck->close();
                if (!$courseExists) {
                    $error = 'Select a course that exists in StudentHub.';
                } elseif ($action === 'add') {
                    $statement = p11_statement($conn, 'INSERT INTO students (name, email, mobile, course_id, year, gender) VALUES (?, ?, ?, ?, ?, ?)', 'sssiis', [$name, $email, $mobile, $courseId, $year, $gender]);
                    $statement->execute();
                    $statement->close();
                    p11_audit($conn, 'student_created');
                    $_SESSION['student_management_flash'] = ['type' => 'success', 'message' => 'Student added successfully.'];
                    p11_redirect();
                } else {
                    $statement = p11_statement($conn, "UPDATE students SET name = ?, email = ?, mobile = ?, course_id = ?, year = ?, gender = ? WHERE id = ? AND status = 'active'", 'sssiisi', [$name, $email, $mobile, $courseId, $year, $gender, $id]);
                    $statement->execute();
                    $changed = $statement->affected_rows;
                    $statement->close();
                    if ($changed > 0) {
                        p11_audit($conn, 'student_updated');
                        $_SESSION['student_management_flash'] = ['type' => 'success', 'message' => 'Student updated successfully.'];
                        p11_redirect();
                    }
                    $error = 'No student record was updated. It may no longer exist or the values were unchanged.';
                }
            }
        } elseif ($action === 'archive' || $action === 'restore') {
            if (!$id || $id < 1) {
                $error = 'The student record could not be identified.';
            } else {
                $fromStatus = $action === 'archive' ? 'active' : 'deleted';
                $toStatus = $action === 'archive' ? 'deleted' : 'active';
                $statement = p11_statement($conn, 'UPDATE students SET status = ? WHERE id = ? AND status = ?', 'sis', [$toStatus, $id, $fromStatus]);
                $statement->execute();
                $changed = $statement->affected_rows;
                $statement->close();
                if ($changed > 0) {
                    p11_audit($conn, $action === 'archive' ? 'student_archived' : 'student_restored');
                    $_SESSION['student_management_flash'] = ['type' => 'success', 'message' => $action === 'archive' ? 'Student archived successfully. Related registrations were preserved.' : 'Student restored successfully.'];
                    p11_redirect(['status' => $action === 'archive' ? 'active' : 'deleted']);
                }
                $error = 'No student record was changed. It may no longer exist.';
            }
        } else {
            $error = 'Unknown student management action.';
        }
    } catch (mysqli_sql_exception $exception) {
        error_log('StudentHub student management write failed: ' . $exception->getMessage());
        $error = $exception->getCode() === 1062
            ? 'That email address is already assigned to another student.'
            : 'The database could not save this change. Check the student details and try again.';
    } catch (Throwable $exception) {
        error_log('StudentHub student management request failed: ' . $exception->getMessage());
        $error = 'The request could not be completed. Please try again.';
    }

    $_SESSION['student_management_flash'] = ['type' => 'error', 'message' => $error];
    p11_redirect();
}

$courses = [];
$courseResult = $conn->query('SELECT course_id, course_name FROM courses ORDER BY course_name');
$courses = $courseResult->fetch_all(MYSQLI_ASSOC);
$courseResult->free();

$search = trim((string) ($_GET['q'] ?? ''));
$courseFilter = filter_var($_GET['course_id'] ?? '', FILTER_VALIDATE_INT) ?: 0;
$yearFilter = filter_var($_GET['year'] ?? '', FILTER_VALIDATE_INT) ?: 0;
$statusFilter = (string) ($_GET['status'] ?? 'active');
if (!in_array($statusFilter, ['active', 'deleted', 'all'], true)) {
    $statusFilter = 'active';
}
$where = [];
$types = '';
$params = [];
if ($search !== '') {
    $where[] = '(s.name LIKE ? OR s.email LIKE ? OR c.course_name LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
    $types .= 'sss';
}
if ($courseFilter > 0) {
    $where[] = 's.course_id = ?';
    $params[] = $courseFilter;
    $types .= 'i';
}
if ($yearFilter > 0 && $yearFilter <= 4) {
    $where[] = 's.year = ?';
    $params[] = $yearFilter;
    $types .= 'i';
}
if ($statusFilter !== 'all') {
    $where[] = 's.status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$fromSql = ' FROM students AS s INNER JOIN courses AS c ON c.course_id = s.course_id' . $whereSql;

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $statement = p11_statement($conn, 'SELECT s.id, s.name, s.email, s.mobile, c.course_name, s.year, s.gender, s.status FROM students AS s INNER JOIN courses AS c ON c.course_id = s.course_id' . $whereSql . ' ORDER BY s.id DESC', $types, $params);
    $statement->execute();
    $exportRows = $statement->get_result();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="studenthub-students-' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Name', 'Email', 'Mobile', 'Course', 'Year', 'Gender', 'Status']);
    while ($row = $exportRows->fetch_assoc()) {
        fputcsv($output, $row);
    }
    fclose($output);
    $statement->close();
    $conn->close();
    exit;
}

$editStudent = null;
$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
if ($editId && $editId > 0) {
    $statement = p11_statement($conn, "SELECT id, name, email, mobile, course_id, year, gender FROM students WHERE id = ? AND status = 'active'", 'i', [$editId]);
    $statement->execute();
    $editStudent = $statement->get_result()->fetch_assoc() ?: null;
    $statement->close();
}

$countStatement = p11_statement($conn, 'SELECT COUNT(*) AS total' . $fromSql, $types, $params);
$countStatement->execute();
$totalRecords = (int) $countStatement->get_result()->fetch_assoc()['total'];
$countStatement->close();
$perPage = 10;
$totalPages = max(1, (int) ceil($totalRecords / $perPage));
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$page = ($page && $page > 0) ? min($page, $totalPages) : 1;
$offset = ($page - 1) * $perPage;
$listStatement = p11_statement($conn, 'SELECT s.id, s.name, s.email, s.mobile, c.course_name, s.course_id, s.year, s.gender, s.status' . $fromSql . ' ORDER BY s.id DESC LIMIT ? OFFSET ?', $types . 'ii', array_merge($params, [$perPage, $offset]));
$listStatement->execute();
$students = $listStatement->get_result()->fetch_all(MYSQLI_ASSOC);
$listStatement->close();

$formStudent = $editStudent ?? ['id' => 0, 'name' => '', 'email' => '', 'mobile' => '', 'course_id' => '', 'year' => '', 'gender' => ''];
$statusMessages = ['added' => 'Student added successfully.', 'updated' => 'Student updated successfully.', 'archived' => 'Student archived successfully.', 'restored' => 'Student restored successfully.'];
$queryStatus = (string) ($_GET['notice'] ?? '');
if (!$flash && isset($statusMessages[$queryStatus])) {
    $flash = ['type' => 'success', 'message' => $statusMessages[$queryStatus]];
}
$filterQuery = ['q' => $search, 'course_id' => $courseFilter ?: '', 'year' => $yearFilter ?: '', 'status' => $statusFilter];
$filterQuery = array_filter($filterQuery, static fn ($value): bool => $value !== '' && $value !== 0);
$exportQuery = array_merge($filterQuery, ['export' => 'csv']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Manage Students | StudentHub</title><link rel="stylesheet" href="../css/style.css">
    <style>
        .p11-wrap{width:min(1240px,calc(100% - 32px));margin:32px auto 48px}.p11-head,.p11-list-head{display:flex;align-items:flex-end;justify-content:space-between;gap:18px;flex-wrap:wrap}.p11-head h1{margin:4px 0 8px}.p11-head p,.p11-muted{color:var(--muted)}.p11-panel{margin-top:20px;padding:clamp(18px,3vw,28px);border:1px solid var(--line);border-radius:18px;background:var(--surface);box-shadow:var(--shadow)}.p11-panel h2{margin:0 0 6px}.p11-form{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:0 16px}.p11-field label{margin-top:12px}.p11-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:18px}.p11-actions button,.p11-actions a,.p11-inline button{margin:0}.p11-secondary{display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border:1px solid var(--line);border-radius:10px;background:var(--surface);color:var(--ink);font:inherit;font-weight:700;text-decoration:none;cursor:pointer}.p11-message{margin:18px 0 0;padding:14px 17px;border-radius:12px;font-weight:700}.p11-success{border:1px solid #86efac;background:#ecfdf3;color:#166534}.p11-error{border:1px solid #fca5a5;background:#fff1f2;color:#9f1239}.p11-toolbar{display:grid;grid-template-columns:minmax(180px,2fr) repeat(3,minmax(130px,1fr)) auto;gap:10px;margin:20px 0}.p11-toolbar input,.p11-toolbar select{margin:0}.p11-table-wrap{overflow-x:auto;margin-top:12px;border:1px solid var(--line);border-radius:12px}.p11-table{width:100%;border-collapse:collapse;min-width:850px;background:var(--surface)}.p11-table th,.p11-table td{padding:12px 14px;border-bottom:1px solid var(--line);text-align:left;vertical-align:middle}.p11-table th{background:rgba(105,65,198,.08);color:var(--navy);font-size:.82rem;text-transform:uppercase;letter-spacing:.04em}.p11-table tr:last-child td{border-bottom:0}.p11-row-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.p11-link-button{padding:0;border:0;background:none;color:var(--violet);font:inherit;font-weight:750;text-decoration:underline;cursor:pointer}.p11-delete{color:#b42318}.p11-badge{display:inline-flex;padding:4px 9px;border-radius:999px;background:#e8fbf7;color:#087d70;font-size:.78rem;font-weight:750}.p11-badge--archived{background:#fff0ee;color:#9f3025}.p11-pagination{display:flex;align-items:center;justify-content:center;gap:14px;margin-top:18px}.p11-pagination .button{margin:0;padding:8px 13px}.p11-empty{padding:24px;text-align:center;color:var(--muted)}.p11-inline{display:inline}.p11-count{color:var(--muted);font-weight:700}.p11-home{color:#fff;text-decoration:none;font-weight:700}
        @media(max-width:850px){.p11-form{grid-template-columns:repeat(2,minmax(0,1fr))}.p11-toolbar{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:560px){.p11-form,.p11-toolbar{grid-template-columns:1fr}.p11-wrap{width:100%;margin:0 auto 28px;padding:20px 16px}.p11-panel{padding:18px}}
    </style>
</head>
<body>
<header class="site-header"><div class="header-row"><a class="brand" href="index.html"><img class="brand-logo" src="../images/logo.jpeg" alt=""><span class="site-name">StudentHub</span></a><nav class="p11-row-actions" aria-label="Administrator navigation"><a class="p11-home" href="admin_dashboard.php">Admin dashboard</a><form action="../php/logout.php" method="post"><button class="p11-home" type="submit">Sign out</button></form></nav></div></header>
<main class="p11-wrap">
    <div class="p11-head"><div><p class="eyebrow">StudentHub · Administration</p><h1>Student management</h1><p class="p11-muted">Add, update, search and manage student profiles from the StudentHub database.</p></div><a class="p11-secondary" href="index.html">Home</a></div>
    <?php if ($flash): ?><p class="p11-message <?= ($flash['type'] ?? '') === 'success' ? 'p11-success' : 'p11-error' ?>" role="<?= ($flash['type'] ?? '') === 'success' ? 'status' : 'alert' ?>"><?= p11_e((string) ($flash['message'] ?? '')) ?></p><?php endif; ?>
    <?php if (!$courses): ?><p class="p11-message p11-error" role="alert">No courses are available. Add a course before creating student records.</p><?php endif; ?>

    <section class="p11-panel" aria-labelledby="student-form-title">
        <h2 id="student-form-title"><?= $editStudent ? 'Edit student' : 'Add a student' ?></h2><p class="p11-muted">Student records are linked to courses already in the database.</p>
        <form method="post" action="student_management.php" class="p11-form">
            <input type="hidden" name="csrf_token" value="<?= p11_e($csrf) ?>"><input type="hidden" name="action" value="<?= $editStudent ? 'update' : 'add' ?>">
            <?php if ($editStudent): ?><input type="hidden" name="id" value="<?= (int) $formStudent['id'] ?>"><?php endif; ?>
            <div class="p11-field"><label for="name">Full name</label><input id="name" name="name" maxlength="100" required value="<?= p11_e($formStudent['name']) ?>"></div>
            <div class="p11-field"><label for="email">Email</label><input id="email" name="email" type="email" maxlength="150" required value="<?= p11_e($formStudent['email']) ?>"></div>
            <div class="p11-field"><label for="mobile">Mobile</label><input id="mobile" name="mobile" maxlength="15" required value="<?= p11_e($formStudent['mobile']) ?>"></div>
            <div class="p11-field"><label for="course_id">Course</label><select id="course_id" name="course_id" required><option value="">Select a course</option><?php foreach ($courses as $course): ?><option value="<?= (int) $course['course_id'] ?>" <?= (int) $formStudent['course_id'] === (int) $course['course_id'] ? 'selected' : '' ?>><?= p11_e((string) $course['course_name']) ?></option><?php endforeach; ?></select></div>
            <div class="p11-field"><label for="year">Year</label><select id="year" name="year" required><option value="">Select year</option><?php for ($yearOption = 1; $yearOption <= 4; $yearOption++): ?><option value="<?= $yearOption ?>" <?= (int) $formStudent['year'] === $yearOption ? 'selected' : '' ?>>Year <?= $yearOption ?></option><?php endfor; ?></select></div>
            <div class="p11-field"><label for="gender">Gender</label><select id="gender" name="gender" required><option value="">Select gender</option><?php foreach (['Male', 'Female', 'Other'] as $genderOption): ?><option value="<?= p11_e($genderOption) ?>" <?= $formStudent['gender'] === $genderOption ? 'selected' : '' ?>><?= p11_e($genderOption) ?></option><?php endforeach; ?></select></div>
            <div class="p11-actions" style="grid-column:1/-1"><button class="button" type="submit" <?= !$courses ? 'disabled' : '' ?>><?= $editStudent ? 'Save changes' : 'Add student' ?></button><?php if ($editStudent): ?><a class="p11-secondary" href="student_management.php">Cancel edit</a><?php endif; ?></div>
        </form>
    </section>

    <section class="p11-panel" aria-labelledby="student-list-title">
        <div class="p11-list-head"><div><h2 id="student-list-title">Student records</h2><p class="p11-count"><?= $totalRecords ?> matching record<?= $totalRecords === 1 ? '' : 's' ?> · Page <?= $page ?> of <?= $totalPages ?></p></div><a class="p11-secondary" href="<?= p11_e('student_management.php?' . http_build_query($exportQuery)) ?>">Export CSV</a></div>
        <form class="p11-toolbar" method="get" action="student_management.php">
            <input type="search" name="q" value="<?= p11_e($search) ?>" placeholder="Search name, email or course" aria-label="Search name, email or course">
            <select name="course_id" aria-label="Filter by course"><option value="">All courses</option><?php foreach ($courses as $course): ?><option value="<?= (int) $course['course_id'] ?>" <?= $courseFilter === (int) $course['course_id'] ? 'selected' : '' ?>><?= p11_e((string) $course['course_name']) ?></option><?php endforeach; ?></select>
            <select name="year" aria-label="Filter by year"><option value="">All years</option><?php for ($yearOption = 1; $yearOption <= 4; $yearOption++): ?><option value="<?= $yearOption ?>" <?= $yearFilter === $yearOption ? 'selected' : '' ?>>Year <?= $yearOption ?></option><?php endfor; ?></select>
            <select name="status" aria-label="Filter by record status"><option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active records</option><option value="deleted" <?= $statusFilter === 'deleted' ? 'selected' : '' ?>>Archived records</option><option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All records</option></select>
            <button class="button" type="submit">Search / filter</button>
        </form>
        <div class="p11-table-wrap"><table class="p11-table"><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Mobile</th><th>Course</th><th>Year</th><th>Status</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ($students as $student): ?><tr><td><?= (int) $student['id'] ?></td><td><?= p11_e((string) $student['name']) ?></td><td><?= p11_e((string) $student['email']) ?></td><td><?= p11_e((string) $student['mobile']) ?></td><td><?= p11_e((string) $student['course_name']) ?></td><td><?= (int) $student['year'] ?></td><td><span class="p11-badge <?= $student['status'] === 'deleted' ? 'p11-badge--archived' : '' ?>"><?= $student['status'] === 'deleted' ? 'Archived' : 'Active' ?></span></td><td><div class="p11-row-actions"><?php if ($student['status'] === 'active'): ?><a href="<?= p11_e('student_management.php?' . http_build_query(array_merge($filterQuery, ['edit' => (int) $student['id']]))) ?>">Edit</a><form class="p11-inline" method="post" action="student_management.php" onsubmit="return confirm('Archive <?= p11_e((string) $student['name']) ?>? Related registrations will be kept.');"><input type="hidden" name="csrf_token" value="<?= p11_e($csrf) ?>"><input type="hidden" name="action" value="archive"><input type="hidden" name="id" value="<?= (int) $student['id'] ?>"><button class="p11-link-button p11-delete" type="submit">Delete</button></form><?php else: ?><form class="p11-inline" method="post" action="student_management.php"><input type="hidden" name="csrf_token" value="<?= p11_e($csrf) ?>"><input type="hidden" name="action" value="restore"><input type="hidden" name="id" value="<?= (int) $student['id'] ?>"><button class="p11-link-button" type="submit">Restore</button></form><?php endif; ?></div></td></tr><?php endforeach; ?>
        <?php if (!$students): ?><tr><td class="p11-empty" colspan="8">No student records match these filters.</td></tr><?php endif; ?>
        </tbody></table></div>
        <?php if ($totalPages > 1): ?><nav class="p11-pagination" aria-label="Student record pages"><?php if ($page > 1): ?><a class="button" href="<?= p11_e('student_management.php?' . http_build_query(array_merge($filterQuery, ['page' => $page - 1]))) ?>">Previous</a><?php endif; ?><span>Page <?= $page ?> of <?= $totalPages ?></span><?php if ($page < $totalPages): ?><a class="button" href="<?= p11_e('student_management.php?' . http_build_query(array_merge($filterQuery, ['page' => $page + 1]))) ?>">Next</a><?php endif; ?></nav><?php endif; ?>
    </section>
</main>
<footer class="site-footer"><div class="home-footer-inner"><small>Student records are managed with prepared MySQLi statements and administrator access checks.</small></div></footer>
<script src="../js/script.js?v=practical11"></script>
</body></html>
<?php $conn->close(); ?>
