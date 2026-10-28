# StudentHub Practical 11 Run Guide

## What is implemented

- Admin-only student management at `pages/student_management.php`, linked from the home page and admin dashboard.
- MySQLi prepared statements for student reads, inserts, updates, search, filters, archive/restore, and CSV export.
- Search by student name, email, or course name; filter by course, year, and active/archived status.
- Ten records per page, server-side validation, CSRF protection, and clear success/error messages.
- Delete asks for confirmation and archives the row. Restore is available from the archived-record view. This preserves event registrations that refer to the student.
- Twenty fictional seed records and the archive status column in the main schema and in the existing-database migration.

## Run with XAMPP

1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. In phpMyAdmin, select the SQL **Import** tab and import `studenthub_practical11_migration.sql`. It upgrades the existing `studenthub_practical8` database and inserts any missing demo rows. It is safe to run again.
3. Confirm that `studenthub_practical8.students` has at least 20 rows and that the `status` column exists.
4. Open `http://localhost/studenthub/pages/index.html` when this folder is under `C:\xampp\htdocs\studenthub`.
5. Select **Manage students**. Sign in with an existing account whose database role is `admin`; the page redirects non-admins to their own dashboard.

To run the project directly from this folder instead, use PowerShell:

```powershell
cd C:\Users\dell\Desktop\studenthub
C:\xampp\php\php.exe -d session.save_path=C:\Users\dell\Desktop\studenthub\.tmp -S 127.0.0.1:8000 router.php
```

Then open `http://127.0.0.1:8000/pages/index.html`. Stop the server with **Ctrl+C**.

Database defaults are host `127.0.0.1`, database `studenthub_practical8`, user `root`, and an empty password. Set `STUDENTHUB_DB_HOST`, `STUDENTHUB_DB_NAME`, `STUDENTHUB_DB_USER`, and `STUDENTHUB_DB_PASS` before starting PHP if your MySQL credentials differ.

## Practical test sequence

1. Add a student with an unused email. Confirm the success message and new table row.
2. Search for part of the name, the email, and the course name. Try a course and year filter together.
3. Edit a record and confirm its values changed.
4. Select **Delete**, cancel the browser confirmation, and verify the record remains. Select **Delete** again and confirm; the record should appear under **Archived records**.
5. Select **Restore** and verify that it returns to **Active records**. Its event registrations remain linked.
6. With at least 11 matching rows, use **Next** and check that the search/filter selections remain applied.
7. Search for a value that does not exist. The page should show an empty-state message and `Page 1 of 1`.
8. Select **Export CSV** and confirm the downloaded file contains the currently selected records.
9. Sign out, then open the management URL. It should redirect to sign in. A student-role account should be redirected to its student dashboard.

For the submission, capture the student list, add success, edit success, delete confirmation, archived/restored record, search/filter results, and page 2. Include the database dump (`studenthub_schema.sql` for a fresh setup, or `studenthub_practical11_migration.sql` for an existing setup) and your completed test results.

## Local verification performed

PHP 8.2 syntax checks passed. Using the local XAMPP MySQL database, the migration created 20 active sample rows and succeeded when rerun. HTTP checks passed for add, edit, search by name/email/course, course/year filtering, archive, restore, CSV export, pagination, empty results, invalid-input feedback, CSRF rejection, and the unauthenticated redirect. The temporary test record was removed; the database remains at 20 student rows.
