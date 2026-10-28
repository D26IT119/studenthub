<?php
declare(strict_types=1);

$connected = false;
$errorMessage = '';
try {
    require __DIR__ . '/db.php';
    $pdo->query('SELECT 1');
    $connected = true;
} catch (Throwable $exception) {
    $errorMessage = $exception->getMessage();
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>StudentHub DB connection test</title></head>
<body>
  <main>
    <h1>StudentHub database connection test</h1>
    <?php if ($connected): ?>
      <p role="status">Database connection successful!</p>
    <?php else: ?>
      <p role="alert"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <p><a href="view_students_db.php">View students</a></p>
  </main>
</body>
</html>
