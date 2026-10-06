<?php
/**
 * process_contact.php  —  Practical 7 (Contact form processor)
 * ──────────────────────────────────────────────────────────────
 * Handles POST from contact_php.html.
 * Validates → sanitizes → appends to contacts.csv + contacts.json.
 * ──────────────────────────────────────────────────────────────
 */

$root     = dirname(__DIR__);
$csvFile  = $root . '/Data/contacts.csv';
$jsonFile = $root . '/Data/contacts.json';

function redirect_contact(string $status, string $msg = ''): never {
    $qs = 'status=' . urlencode($status);
    if ($msg) $qs .= '&msg=' . urlencode($msg);
    header("Location: ../pages/contact_php.html?$qs");
    exit;
}

// ── Guard ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_contact('error', 'Invalid request method.');
}

// ── Sanitize ──────────────────────────────────────────────────
function sanitize(string $v): string {
    return htmlspecialchars(trim(strip_tags($v)), ENT_QUOTES, 'UTF-8');
}

$name    = sanitize($_POST['contact_name']    ?? '');
$email   = sanitize($_POST['contact_email']   ?? '');
$subject = sanitize($_POST['contact_subject'] ?? '');
$message = sanitize($_POST['contact_message'] ?? '');

// ── Validate ──────────────────────────────────────────────────
$errors = [];

if (!preg_match('/^[A-Za-z]+(?:[ .\'-][A-Za-z]+)+$/', $name)) {
    $errors[] = 'Please enter your full name (at least two words).';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
}
if (strlen($subject) < 3) {
    $errors[] = 'Subject must be at least 3 characters.';
}
if (strlen($message) < 10) {
    $errors[] = 'Message must be at least 10 characters.';
}

if (!empty($errors)) {
    redirect_contact('error', implode(' | ', $errors));
}

// ── Build record ──────────────────────────────────────────────
$timestamp = date('Y-m-d H:i:s');
$id        = uniqid('MSG', true);

$record = [
    'id'      => $id,
    'name'    => $name,
    'email'   => $email,
    'subject' => $subject,
    'message' => $message,
    'sent_at' => $timestamp,
];

// ── Append to CSV ─────────────────────────────────────────────
$csvNew = !file_exists($csvFile) || filesize($csvFile) === 0;
$fh = fopen($csvFile, 'a');
if ($fh) {
    flock($fh, LOCK_EX);
    if ($csvNew) fputcsv($fh, array_keys($record));
    fputcsv($fh, array_values($record));
    flock($fh, LOCK_UN);
    fclose($fh);
}

// ── Append to JSON ────────────────────────────────────────────
$jfh = fopen($jsonFile, 'c+');
if ($jfh) {
    flock($jfh, LOCK_EX);
    $existing = [];
    $size = filesize($jsonFile);
    if ($size > 0) {
        $decoded = json_decode(fread($jfh, $size), true);
        if (is_array($decoded)) $existing = $decoded;
    }
    $existing[] = $record;
    ftruncate($jfh, 0);
    rewind($jfh);
    fwrite($jfh, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($jfh, LOCK_UN);
    fclose($jfh);
}

redirect_contact('success');
