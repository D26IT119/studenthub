<?php
declare(strict_types=1);
require __DIR__ . '/db.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $mobile = trim((string) ($_POST['mobile'] ?? ''));
    $courseId = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
    $year = filter_input(INPUT_POST, 'year', FILTER_VALIDATE_INT);
    $gender = (string) ($_POST['gender'] ?? '');

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^\+?[0-9]{7,15}$/', $mobile)
        || !$courseId || !$year || $year < 1 || $year > 4 || !in_array($gender, ['Male', 'Female', 'Other'], true)) {
        $message = 'Enter valid values in every field.';
    } else {
        try {
            $statement = $pdo->prepare(
                'INSERT INTO students (name, email, mobile, course_id, year, gender)
                 VALUES (:name, :email, :mobile, :course_id, :year, :gender)'
            );
            $statement->execute([
                ':name' => $name, ':email' => $email, ':mobile' => $mobile,
                ':course_id' => $courseId, ':year' => $year, ':gender' => $gender,
            ]);
            $message = 'Student inserted successfully (ID ' . $pdo->lastInsertId() . ').';
        } catch (PDOException $exception) {
            error_log('StudentHub insert failed: ' . $exception->getMessage());
            $message = $exception->getCode() === '23000'
                ? 'That email is already registered or the selected course does not exist.'
                : 'The student could not be saved. Check the server log.';
        }
    }
}
$courses = $pdo->query('SELECT course_id, course_name FROM courses ORDER BY course_name')->fetchAll();
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Insert student</title></head>
<body><main><h1>Insert a student</h1><?php if ($message !== ''): ?><p role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<form method="post">
  <label>Name <input name="name" required maxlength="100"></label><br>
  <label>Email <input name="email" type="email" required maxlength="150"></label><br>
  <label>Mobile <input name="mobile" required maxlength="15" pattern="\+?[0-9]{7,15}"></label><br>
  <label>Course <select name="course_id" required><option value="">Select course</option><?php foreach ($courses as $course): ?><option value="<?= (int) $course['course_id'] ?>"><?= htmlspecialchars($course['course_name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label><br>
  <label>Year <select name="year" required><?php for ($i = 1; $i <= 4; $i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?></select></label><br>
  <label>Gender <select name="gender" required><option>Female</option><option>Male</option><option>Other</option></select></label><br>
  <button type="submit">Save student</button>
</form><p><a href="view_students_db.php">View students</a></p></main></body></html>
