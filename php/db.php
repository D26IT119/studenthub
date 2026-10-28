<?php
/** Shared PDO connection for StudentHub Practical 8. */
declare(strict_types=1);

$dbHost = getenv('STUDENTHUB_DB_HOST') ?: '127.0.0.1';
$dbName = getenv('STUDENTHUB_DB_NAME') ?: 'studenthub_practical8';
$dbUser = getenv('STUDENTHUB_DB_USER') ?: 'root';
$dbPass = getenv('STUDENTHUB_DB_PASS') ?: '';

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $exception) {
    error_log('StudentHub database connection failed: ' . $exception->getMessage());
    throw new RuntimeException('Database connection failed. Check that MySQL is running and the StudentHub database credentials are correct.');
}
