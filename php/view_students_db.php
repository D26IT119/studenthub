<?php
declare(strict_types=1);
require __DIR__ . '/db.php';

$statement = $pdo->prepare(
    'SELECT s.id, s.name, s.email, s.mobile, c.course_name, s.year, s.gender
     FROM students AS s
     INNER JOIN courses AS c ON c.course_id = s.course_id
     ORDER BY s.id'
);
$statement->execute();
$students = $statement->fetchAll();
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Students | StudentHub database</title></head>
<body>
  <main>
    <h1>Students from MySQL</h1>
    <p>This page uses a prepared SELECT with a JOIN to show each course name.</p>
    <p><a href="db_insert_test.php">Insert</a> · <a href="db_update_test.php">Update</a> · <a href="db_delete_test.php">Delete</a></p>
    <?php if ($students === []): ?><p>No students found.</p><?php else: ?>
      <table border="1" cellpadding="6">
        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Mobile</th><th>Course</th><th>Year</th><th>Gender</th></tr></thead>
        <tbody>
          <?php foreach ($students as $student): ?>
            <tr><td><?= $escape($student['id']) ?></td><td><?= $escape($student['name']) ?></td><td><?= $escape($student['email']) ?></td><td><?= $escape($student['mobile']) ?></td><td><?= $escape($student['course_name']) ?></td><td><?= $escape($student['year']) ?></td><td><?= $escape($student['gender']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </main>
</body>
</html>
