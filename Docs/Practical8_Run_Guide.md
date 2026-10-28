# Practical 8: run and submit

## Run locally with XAMPP

1. Open the XAMPP Control Panel and start **MySQL** and **Apache**. The lab
   database name is `studenthub_practical8`; the existing `studenthub` database
   is left unchanged.
2. The SQL script creates the database, all four tables, foreign keys, and seed
   rows. If setting up on another computer, open `http://localhost/phpmyadmin`,
   choose **Import**, select `studenthub_schema.sql`, and click **Go**.
3. From PowerShell, run the project from its folder:

   ```powershell
   cd C:\Users\dell\Desktop\studenthub
   C:\xampp\php\php.exe -S 127.0.0.1:8000 router.php
   ```

4. Open `http://127.0.0.1:8000/php/db_test.php`. The successful page reads
   **Database connection successful!** Capture this page for the connection
   screenshot.
5. Open `http://127.0.0.1:8000/php/view_students_db.php` to see the prepared
   SELECT/JOIN output. Try insert, update, and delete from the links on that
   page. Use a new email for inserts; a seed student ID such as 1 can be used
   for the update demonstration.
6. In phpMyAdmin, select `studenthub_practical8` and choose **Export → Quick →
   SQL → Go** to save an export dump for submission. Include
   `studenthub_schema.sql`, `php/db.php`, the connection screenshot, and
   `Practical8_ER_Model.md` with your practical files.

Stop the PHP development server with **Ctrl+C** in its PowerShell window.

## Connection settings

`php/db.php` uses the standard local XAMPP defaults: host `127.0.0.1`, database
`studenthub_practical8`, user `root`, and an empty password. If your MySQL root
account has a password or uses another database name, set the corresponding
`STUDENTHUB_DB_HOST`, `STUDENTHUB_DB_NAME`, `STUDENTHUB_DB_USER`, and
`STUDENTHUB_DB_PASS` environment variables before starting PHP.

## Schema and security notes

- `courses` separates course labels from student rows; registrations implement
  the many-to-many relationship between students and events.
- Primary keys, unique student emails, unique student/event pairs, and foreign
  keys enforce identity and referential integrity.
- PHP uses PDO exception mode and native prepared statements. Database
  connection details are logged server-side; the connection page shows a
  generic actionable message.
- Demo student passwords are not stored. `password_hash` is nullable for the
  later authentication practical; never put plain-text passwords in the seed
  data.
