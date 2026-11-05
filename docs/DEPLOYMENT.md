# cPanel deployment

Target URL: `https://stratastaff.com/tickets/creative/`

## Updating the live site

Use this when the site is already running. GitHub holds the full system; the server only receives the website files that changed since the version that is live.

- The git tag `live` marks the commit running on the server.
- `scripts\build-release.bat` builds `release/mmts-update-<date>.zip` with only the website files changed since `live`, plus `release/mmts-update-<date>.txt` listing them and any old files that can be deleted from the server.
- The package never contains docs, scripts, the sample-task CSV, `config.local.php`, a database or uploaded attachments.

1. **Back up first.** In File Manager, open `public_html/tickets/creative/database/`, right-click `creatives-ticketing.sqlite` and download it. Keep it until the update is confirmed working.
2. **Upload the package.** Upload `release/mmts-update-<date>.zip` to `public_html/tickets/creative/` and extract it there, choosing to overwrite existing files. Live settings, data and uploaded files are left untouched.
   - Optionally delete the old files named at the end of the `.txt` list.
3. **Open the site once.** Visit the URL and sign in. The first page load upgrades the database automatically:
   - adds the `department`, `due_time` and `completed_at` columns and the `ticket_comments` table
   - renames the assignee "Aiko" to "Akio" on existing tickets
   - gives already-finished tickets and tasks a completion date (their last-updated time)
4. **Check.** Open Tasks, Tickets and the Request Form; switch dark mode on and off; add a test comment on a ticket and delete it from the Database page afterwards.
5. **Hard refresh** (Ctrl + F5) if a page still looks old; the stylesheet version changes with each update.

6. **Mark the new live version** so the next package only contains the next round of changes:

   ```
   git tag -f -a live -m "Live on <date>" HEAD
   git push -f origin live
   ```

**If something goes wrong:** upload the backed-up `creatives-ticketing.sqlite` back into `database/` and re-upload the previous version of the changed files (`git archive live` gives the old files).

Times are shown in the zone set by `timezone` in `config.php` (default `Asia/Manila`). To change it, add `'timezone' => 'Area/City'` to the server's `config.local.php`.

## First-time install

1. In File Manager, create `public_html/tickets/creative` and upload the project files there.
2. Do not upload a local SQLite database or `config.local.php`.
3. Copy `config.local.example.php` to `config.local.php` on the server and set a unique super-admin username and a password of at least 12 characters.
4. Confirm the cPanel PHP version is 8.1 or newer with `pdo_sqlite` and `fileinfo` enabled.
5. Ensure PHP can write to `database/` and `uploads/` (normally `0755`; use `0775` only if required by the host).
6. Open the HTTPS URL and sign in. The first login creates the SQLite database and the configured super-admin account.
7. Change the initial password in Settings immediately after the first login.

The included `.htaccess` files block direct access to SQLite, configuration, uploaded files, indexes, and internal documentation. Application links are relative, so the nested `/tickets/creative/` path needs no code changes.

For large attachments, cPanel PHP settings must be at least:

- `upload_max_filesize = 500M`
- `post_max_size = 510M`
- a suitable `max_execution_time` for the server connection

Keep HTTPS enabled so the session cookie is transmitted securely.
