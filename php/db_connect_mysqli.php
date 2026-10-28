<?php
/** Practical 9 MySQLi connection to the shared StudentHub lab database. */
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$dbHost = getenv('STUDENTHUB_DB_HOST') ?: '127.0.0.1';
$dbName = getenv('STUDENTHUB_DB_NAME') ?: 'studenthub_practical8';
$dbUser = getenv('STUDENTHUB_DB_USER') ?: 'root';
$dbPass = getenv('STUDENTHUB_DB_PASS') ?: '';

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
$conn->set_charset('utf8mb4');
