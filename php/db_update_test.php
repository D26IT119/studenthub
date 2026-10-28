<?php
declare(strict_types=1);
require __DIR__ . '/db.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $email = trim((string) ($_POST['email'] ?? ''));
    if (!$id || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Enter a valid student ID and email.';
    } else {
        try {
            $statement = $pdo->prepare('UPDATE students SET email = :email WHERE id = :id');
            $statement->execute([':email' => $email, ':id' => $id]);
            $message = $statement->rowCount() > 0 ? 'Student email updated.' : 'No row changed. Check the ID or enter a different email.';
        } catch (PDOException $exception) {
            error_log('StudentHub update failed: ' . $exception->getMessage());
            $message = $exception->getCode() === '23000' ? 'That email is already in use.' : 'The update failed. Check the server log.';
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Update student</title></head>
<body><main><h1>Update a student email</h1><p>Use a seed student ID such as 1 to try this operation.</p><?php if ($message !== ''): ?><p role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<form method="post"><label>Student ID <input name="id" type="number" min="1" required></label><br><label>New email <input name="email" type="email" required maxlength="150"></label><br><button type="submit">Update email</button></form><p><a href="view_students_db.php">View students</a></p></main></body></html>
