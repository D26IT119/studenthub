# StudentHub Practical 13 Run Guide

## What changed

- The existing `pages/events.html` page now loads events from the MySQL `events` table through `php/events_api.php` using the Fetch API.
- Administrators can add, edit, and delete an event without reloading the page. Search, category filtering, sorting, and pagination continue to work.
- The API returns JSON for successful requests and errors. It validates fields before writes, uses prepared statements, protects writes with the administrator session and a CSRF token, and records changes in `event_audit_log` and the shared `audit_logs` table.
- The administrator view shows the most recent event changes, including the event title after deletion.
- Deleting an event follows the existing schema's foreign-key cascade and removes registrations for that event; the browser asks for confirmation first.

## Start StudentHub

1. Start **MySQL** in the XAMPP Control Panel.
2. If this is an existing Practical 8 database, import `studenthub_practical12_migration.sql` to ensure event time, capacity, description, status, and poster columns exist, then import `studenthub_practical13_migration.sql` to add the event audit table. Both migrations are safe to import again. For a new database, import `studenthub_schema.sql` instead; it includes the audit table.
3. From PowerShell, start the PHP development server:

   ```powershell
   cd C:\Users\dell\Desktop\studenthub
   C:\xampp\php\php.exe -d session.save_path=C:\Users\dell\Desktop\studenthub\.tmp\sessions -S 127.0.0.1:8000 router.php
   ```

4. Open `http://127.0.0.1:8000/pages/index.html`. Use **Events** to view the database list and **Manage events** or **Events** to perform Practical 13 CRUD.
5. Sign in with an account whose `users.role` is `admin` to add, edit, or delete records. Visitors can read events. Default database settings are host `127.0.0.1`, database `studenthub_practical8`, user `root`, and an empty password; set the `STUDENTHUB_DB_*` environment variables before starting PHP if yours differ.

Opening `index.html` directly as a local file will not run PHP. Start the PHP server and open the HTTP address above so the browser can reach the API.

## API response and request format

- `GET /php/events_api.php` returns `{ "success": true, "data": [...], "permissions": { "canManage": true|false } }`. For a signed-in administrator it also returns a session-bound `csrfToken` and the recent `auditLog` entries.
- `POST /php/events_api.php` expects a JSON object and an `X-CSRF-Token` header. Set `action` to `add`, `update`, or `delete`. Add/update fields are `title`, `date` (`YYYY-MM-DD`), `time` (`HH:MM`), `venue`, `category`, `capacity`, `description`, and `status` (`open` or `closed`); update/delete also require `id`.
- Responses use `{ "success": true, "message": "...", "data": ... }` or `{ "success": false, "message": "..." }`. Invalid fields return HTTP 422, duplicate title/date returns 409, a missing event returns 404, an expired/missing sign-in returns 401, and a missing/invalid CSRF token returns 403.

## Practical demonstration and screenshots

1. Open browser Developer Tools with **F12** and select **Network**. Enable **Preserve log** if available.
2. Load the Events page and show the `events_api.php` GET request with a JSON response.
3. Sign in as an administrator and add a temporary event. Show the POST JSON request, the 201 response, the success message, and the new event card.
4. Edit that event and show the 200 response and updated card without a document navigation.
5. Delete the temporary event and show the confirmation, 200 response, and removed card. Use a temporary event with no registrations, because event deletion cascades to registrations.
6. Submit an empty required field or a duplicate title/date and show the validation/conflict response and frontend error message.
7. Capture screenshots of the event list, add success, edit success, delete success, and Network responses for submission.

## Browser checks

The page status line reports loading, event count, or a load error. The feedback area reports save/delete progress, success, and validation/server errors. Browser form validation and matching PHP validation protect database writes; the API enforces unique title/date and database foreign-key constraints.
