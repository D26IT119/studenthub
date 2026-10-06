<?php
/**
 * process_registration.php  —  Practical 7
 * ──────────────────────────────────────────────────────────────
 * Handles POST data from register.html / register_php.html.
 *
 * Steps performed:
 *   1. Accept only POST requests.
 *   2. Sanitize every input with filter_var / htmlspecialchars.
 *   3. Validate each field server-side (mirrors client-side rules).
 *   4. Hash the password with password_hash (bcrypt).
 *   5. Append one row to  Data/registrations.csv
 *   6. Append one object to  Data/registrations.json
 *   7. Redirect back with ?status=success or ?status=error&msg=…
 * ──────────────────────────────────────────────────────────────
 */

// ── 0. Setup ──────────────────────────────────────────────────
// Allow script to be called from php/ subfolder; paths are
// relative to the project root (one level up).
$root    = dirname(__DIR__);                          // studenthub/
$csvFile = $root . '/Data/registrations.csv';
$jsonFile= $root . '/Data/registrations.json';

// Helper: redirect back to the form with a result flag
function redirect(string $status, string $msg = ''): never {
    $back = '../pages/register_php.html';
    $qs   = 'status=' . urlencode($status);
    if ($msg) $qs .= '&msg=' . urlencode($msg);
    header("Location: $back?$qs");
    exit;
}

// ── 1. Guard: POST only ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('error', 'Invalid request method.');
}

// ── 2. Collect & sanitize raw POST values ────────────────────
/**
 * sanitize(string $value): string
 * Trims whitespace and strips HTML tags/entities.
 * This is the first line of defence before field-level validation.
 */
function sanitize(string $value): string {
    return htmlspecialchars(trim(strip_tags($value)), ENT_QUOTES, 'UTF-8');
}

$name     = sanitize($_POST['name']            ?? '');
$email    = sanitize($_POST['email']           ?? '');
$mobile   = sanitize($_POST['mobile']          ?? '');
$course   = sanitize($_POST['course']          ?? '');
$year     = sanitize($_POST['year']            ?? '');
$gender   = sanitize($_POST['gender']          ?? '');
$password = $_POST['password']                 ?? '';   // plain – will be hashed
$confirm  = $_POST['confirmPassword']          ?? '';
$terms    = isset($_POST['terms']) ? 'yes' : 'no';

// ── 3. Server-side validation ────────────────────────────────
/**
 * Each rule checks the sanitized value and pushes a human-readable
 * message into $errors if it fails.
 * Using filter_var for email/URL validation is the PHP idiomatic way.
 */
$errors = [];

// Full name: at least two words, letters only
if (!preg_match('/^[A-Za-z]+(?:[ .\'-][A-Za-z]+)+$/', $name)) {
    $errors[] = 'Full name must contain at least a first and last name using letters only.';
}

// Email: must pass PHP email filter
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
}

// Mobile: 10 digits, must start with 6–9 (Indian mobile)
if (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
    $errors[] = 'Mobile must be a valid 10-digit Indian number.';
}

// Course: must be a known value (whitelist)
$allowedCourses = ['IT', 'CS', 'DS', 'Design', 'BA', 'Other'];
if (!in_array($course, $allowedCourses, true)) {
    $errors[] = 'Please select a valid course.';
}

// Year: 1–4 integer
if (!in_array($year, ['1','2','3','4'], true)) {
    $errors[] = 'Please select a valid year.';
}

// Gender: whitelist
if (!in_array($gender, ['Male','Female','Other'], true)) {
    $errors[] = 'Please select your gender.';
}

// Password strength (mirrors client rule)
if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $password)) {
    $errors[] = 'Password must be 8+ characters with uppercase, lowercase, digit and symbol.';
}

// Password confirmation
if ($password !== $confirm) {
    $errors[] = 'Passwords do not match.';
}

// Terms acceptance
if ($terms !== 'yes') {
    $errors[] = 'You must accept the terms and privacy policy.';
}

// Return on validation failure
if (!empty($errors)) {
    redirect('error', implode(' | ', $errors));
}

// ── 4. Hash the password (bcrypt, cost 12) ───────────────────
/**
 * password_hash() uses bcrypt by default.
 * NEVER store plain-text passwords — this fulfils the "sanitization"
 * and safe-storage requirement from the practical spec.
 */
$passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// ── 5. Build the record ───────────────────────────────────────
$timestamp = date('Y-m-d H:i:s');
$id        = uniqid('REG', true);   // unique record ID

$record = [
    'id'        => $id,
    'name'      => $name,
    'email'     => $email,
    'mobile'    => $mobile,
    'course'    => $course,
    'year'      => $year,
    'gender'    => $gender,
    'password'  => $passwordHash,   // hashed
    'terms'     => $terms,
    'registered'=> $timestamp,
];

// ── 6a. Append to CSV ─────────────────────────────────────────
/**
 * fopen with mode 'a' opens for appending; flock acquires an
 * exclusive lock so concurrent writes don't corrupt the file.
 * The header row is written only when the file is new/empty.
 */
$csvHeaders = ['id','name','email','mobile','course','year','gender',
               'password','terms','registered'];

$csvNew = !file_exists($csvFile) || filesize($csvFile) === 0;

$fh = fopen($csvFile, 'a');
if (!$fh) {
    redirect('error', 'Could not open CSV file for writing.');
}
flock($fh, LOCK_EX);

if ($csvNew) {
    fputcsv($fh, $csvHeaders);   // write column headers once
}
fputcsv($fh, array_values($record));

flock($fh, LOCK_UN);
fclose($fh);

// ── 6b. Append to JSON ───────────────────────────────────────
/**
 * Read the existing JSON array (or start fresh), push the new
 * record, then write the entire array back with pretty-printing.
 * flock is used here too for safe concurrent access.
 */
$jfh = fopen($jsonFile, 'c+');   // open for r/w, create if absent
if (!$jfh) {
    redirect('error', 'Could not open JSON file for writing.');
}
flock($jfh, LOCK_EX);

$existing = [];
$size = filesize($jsonFile);
if ($size > 0) {
    $raw = fread($jfh, $size);
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) $existing = $decoded;
}

$existing[] = $record;

ftruncate($jfh, 0);
rewind($jfh);
fwrite($jfh, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

flock($jfh, LOCK_UN);
fclose($jfh);

// ── 7. Done — redirect with success ──────────────────────────
redirect('success');

