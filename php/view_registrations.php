<?php
/**
 * view_registrations.php  —  Practical 7
 * ──────────────────────────────────────────────────────────────
 * Reads Data/registrations.csv and renders a styled HTML table.
 * The password column is masked for display (security best practice).
 *
 * Access via XAMPP: http://localhost/studenthub/php/view_registrations.php
 * ──────────────────────────────────────────────────────────────
 */

$root    = dirname(__DIR__);
$csvFile = $root . '/Data/registrations.csv';

// ── Read CSV into an array of rows ───────────────────────────
$rows    = [];
$headers = [];

if (file_exists($csvFile) && filesize($csvFile) > 0) {
    $fh = fopen($csvFile, 'r');
    if ($fh) {
        // First row is always the header
        $headers = fgetcsv($fh);
        while (($row = fgetcsv($fh)) !== false) {
            $rows[] = $row;
        }
        fclose($fh);
    }
}

$count = count($rows);

// ── Find column indexes ──────────────────────────────────────
$pwIdx   = array_search('password',   $headers);  // mask this column
$idIdx   = array_search('id',         $headers);  // shorten for display
$tsIdx   = array_search('registered', $headers);

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Registrations | StudentHub Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* ── table styles specific to this admin view ── */
        .admin-wrap   { padding: 32px clamp(16px,4vw,48px); }
        .admin-header { display:flex; align-items:center; justify-content:space-between;
                        flex-wrap:wrap; gap:12px; margin-bottom:24px; }
        .badge        { display:inline-block; padding:4px 14px; border-radius:999px;
                        background:linear-gradient(120deg,#6941c6,#3877d7);
                        color:#fff; font-size:.82rem; font-weight:700; }
        .tbl-wrap     { overflow-x:auto; }
        table         { width:100%; border-collapse:collapse; font-size:.9rem; }
        thead tr      { background:linear-gradient(115deg,#152452,#6941c6);color:#fff; }
        thead th      { padding:12px 14px; text-align:left; white-space:nowrap; }
        tbody tr      { border-bottom:1px solid #dce4f2; transition:background 120ms; }
        tbody tr:hover{ background:#f5f0ff; }
        tbody td      { padding:10px 14px; color:#333; vertical-align:middle; }
        .mono         { font-family:monospace; font-size:.78rem; color:#65708a; }
        .pw-mask      { font-family:monospace; letter-spacing:2px; color:#aaa; }
        .no-data      { padding:40px; text-align:center; color:#65708a; }
        .back-link    { display:inline-block; margin-bottom:18px; color:#6941c6;
                        font-weight:700; text-decoration:none; }
        .back-link:hover{ text-decoration:underline; }
        /* dark-theme overrides */
        body.dark-theme table          { color:#ebefff; }
        body.dark-theme thead tr       { background:linear-gradient(115deg,#0a0f2a,#4a2ea8); }
        body.dark-theme tbody tr       { border-color:#414d77; }
        body.dark-theme tbody tr:hover { background:#1e2a50; }
        body.dark-theme tbody td       { color:#d0d8f0; }
        body.dark-theme .mono          { color:#8899cc; }
    </style>
</head>
<body>
<header>
    <div class="header-row">
        <div class="brand">
            <img class="brand-logo" src="../images/logo.jpeg" alt="StudentHub logo">
            <p class="site-name">StudentHub</p>
        </div>
        <div class="header-actions">
            <button class="icon-button" type="button" data-theme-toggle aria-label="Switch color theme">☾</button>
        </div>
    </div>
    <nav class="site-nav" aria-label="Main navigation">
        <ul>
            <li><a href="../pages/index.html">Home</a></li>
            <li><a href="../pages/register_php.html">Register</a></li>
            <li><a href="view_registrations.php" aria-current="page">Registrations</a></li>
            <li><a href="view_contacts.php">Contact Messages</a></li>
        </ul>
    </nav>
</header>

<main>
    <div class="admin-wrap">
        <a class="back-link" href="../pages/register_php.html">&larr; Back to Registration</a>

        <div class="admin-header">
            <h1 style="margin:0">Registration Records</h1>
            <span class="badge"><?= $count ?> record<?= $count !== 1 ? 's' : '' ?></span>
        </div>
        <p>Records are read from <code>Data/registrations.csv</code>. Passwords are masked.</p>

        <?php if ($count === 0): ?>
            <div class="card no-data">
                <p>No registrations yet. Submit the form to see records here.</p>
            </div>
        <?php else: ?>
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <?php foreach ($headers as $col): ?>
                            <th><?= htmlspecialchars(ucfirst($col)) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $i => $row): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <?php foreach ($row as $ci => $cell): ?>
                        <td>
                            <?php if ($ci === $pwIdx): ?>
                                <span class="pw-mask" title="Bcrypt hash stored">••••••••</span>
                            <?php elseif ($ci === $idIdx): ?>
                                <span class="mono"><?= htmlspecialchars(substr($cell, 0, 16)) ?>…</span>
                            <?php elseif ($ci === $tsIdx): ?>
                                <span class="mono"><?= htmlspecialchars($cell) ?></span>
                            <?php else: ?>
                                <?= htmlspecialchars($cell) ?>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</main>

<footer>&copy; <?= date('Y') ?> StudentHub &middot; Admin Panel</footer>
<script src="../js/script.js"></script>
</body>
</html>

