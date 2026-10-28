# Practical 9: Secure account registration

## What was added

- `pages/register.php` is a StudentHub-styled account registration form for full name, username, email, password, and confirmation.
- `php/process_user_registration.php` repeats the browser validation on the server, validates a CSRF token, checks existing username/email with a MySQLi prepared SELECT, hashes passwords with `password_hash()`, inserts with a prepared statement, and records successful registrations in `audit_logs`.
- `php/db_connect_mysqli.php` connects to the existing `studenthub_practical8` MySQL database using MySQLi. The Practical 8 PDO connection remains unchanged.
- The `users` table stores account credentials separately from student academic profiles. Email and username each have a UNIQUE key as a database-level safeguard.
- `php/view_users.php` displays account details but never reads or displays password hashes.

## Set up and run in XAMPP

1. In XAMPP Control Panel, start **Apache** and **MySQL**.
2. Open `http://localhost/phpmyadmin`. Select the `studenthub_practical8` database. If the database is not present, use **Import** and import `studenthub_schema.sql` from the project folder. Re-importing creates the Practical 9 tables without replacing existing rows.
3. If the database already exists, open its **SQL** tab and run the Practical 9 table definitions from `studenthub_schema.sql` (`users` and `audit_logs`).
4. Put this project folder under `C:\xampp\htdocs\studenthub` if it is not already there. Or start the existing PHP development server from PowerShell:

   ```powershell
   cd C:\Users\dell\Desktop\studenthub
   C:\xampp\php\php.exe -S 127.0.0.1:8000 router.php
   ```

5. Visit `http://127.0.0.1:8000/pages/register.php` (development server) or `http://localhost/studenthub/pages/register.php` (Apache).
6. Submit a unique account, then visit `http://127.0.0.1:8000/php/view_users.php` or `http://localhost/studenthub/php/view_users.php` to see the new user. The password column is intentionally omitted.
7. In phpMyAdmin, open `studenthub_practical8.users` and confirm `password` begins with a password-hash prefix such as `$2y$` or `$argon2...`; it must not equal the typed password. `audit_logs` should contain a `user_registered` row.

Default connection settings are `127.0.0.1`, database `studenthub_practical8`, user `root`, and a blank password. If your local MySQL credentials differ, set `STUDENTHUB_DB_HOST`, `STUDENTHUB_DB_NAME`, `STUDENTHUB_DB_USER`, and `STUDENTHUB_DB_PASS` in the environment before starting the PHP server.

## Test report

| # | Test | Expected result | Database result |
|---|---|---|---|
| 1 | Valid name, unique username/email, matching strong password, terms accepted | Success message | One `users` row with a hash; one audit row |
| 2 | Submit the same email again | Friendly duplicate email/username message | No additional row |
| 3 | Submit the same username with another email | Friendly duplicate email/username message | No additional row |
| 4 | Leave required fields blank with JavaScript disabled | Backend validation errors | No row |
| 5 | Invalid email | Browser inline feedback; backend rejects if submitted directly | No row |
| 6 | Password shorter than 8 or missing a required character class | Browser inline feedback; backend rejects if submitted directly | No row |
| 7 | Password confirmation differs | Browser inline feedback; backend rejects if submitted directly | No row |
| 8 | SQL injection text in a field | Validation rejects it or stores only as a bound value; no SQL executes from input | No unauthorized changes |
| 9 | Remove/change the CSRF token | Form verification error | No row |
| 10 | Register two accounts with the same password and different identities | Both success messages | Two distinct password hashes |

### Validation consistency

| Field | Browser | PHP server |
|---|---|---|
| Name | 2–80 letters with spaces, apostrophes, periods, or hyphens | Same Unicode-aware rule |
| Username | 3–30 ASCII letters, digits, or underscores | Same rule |
| Email | Email input and format check, max 150 characters | `FILTER_VALIDATE_EMAIL`, max 150 characters |
| Password | 8+ with uppercase, lowercase, digit, and symbol | Same rule |
| Confirmation | Must match password | Must match password |
| Terms | Required checkbox | Required POST field |

Capture the success page, duplicate response, invalid-input response, and the phpMyAdmin `users` table after successful and duplicate attempts for the practical submission. Use fictional test details. Stop the PHP development server with **Ctrl+C**.

## Viva answers

1. **Is `password_hash()` used before storing passwords?** Yes. The INSERT binds only the hash; the plain password is not stored or logged.
2. **Are duplicate email/username values checked?** Yes. A prepared SELECT gives a friendly message, and UNIQUE constraints handle race conditions.
3. **Are frontend and backend validations consistent?** Yes. Both layers enforce the same field rules. Server-side validation remains authoritative because browser checks can be bypassed.
