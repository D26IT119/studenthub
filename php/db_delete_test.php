<?php
declare(strict_types=1);
require __DIR__ . '/db.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        $message = 'Enter a valid student ID.';
    } else {
        try {
            $statement = $pdo->prepare('DELETE FROM students WHERE id = :id');
            $statement->execute([':id' => $id]);
            $message = $statement->rowCount() > 0
                ? 'Student deleted. Any event registrations were removed by the foreign key cascade.'
                : 'No student found with that ID.';
        } catch (PDOException $exception) {
            error_log('StudentHub delete failed: ' . $exception->getMessage());
            $message = 'The delete failed. Check the student ID and server log.';
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Delete student</title></head>
<body><main><h1>Delete a student</h1><p>This permanently deletes the selected student and their registrations.</p><?php if ($message !== ''): ?><p role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<form method="post" onsubmit="return confirm('Delete this student and their registrations?')"><label>Student ID <input name="id" type="number" min="1" required></label><br><button type="submit">Delete student</button></form><p><a href="view_students_db.php">View students</a></p></main></body></html>
