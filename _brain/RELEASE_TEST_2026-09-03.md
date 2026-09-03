# Local Release Test — 2026-09-03

Environment: XAMPP/Apache, PHP, SQLite, `http://localhost/tasktracker/`

## Passed workflows

- Public ticket creation rejects non-`@stratastaff.com` email addresses.
- Ticket lookup remains available to any valid email address.
- Detailed ticket submission saves assignee, deadline, multiple categories, estimated cost, description, business impact, and an attachment.
- Staff ticket lookup displays the saved reference, status, details, and attachment name.
- Admin triage saves subject, priority, effort, status, routing, deadline, cost, and categories.
- A triaged ticket appears in Tasks and opens its complete detail drawer.
- Ticket and native-task checklists support create, complete, and delete.
- Native task create, attachment upload, detail view, edit, and delete work.
- Ticket CSV export works for a selected start and end date.
- Settings supports admin-account creation and deletion; created accounts always have the `admin` role.
- Password change works and the changed password can be used to sign in.
- An admin cannot see or directly access Database Management.
- The super admin can access Database Management and delete records and their files.
- Invalid login is rejected; the configured super-admin login succeeds.
- Setup redirects unauthenticated visitors to login and requires the super-admin role.
- Direct access to the legacy database viewer, internal task template, configuration, SQLite, documentation, and upload storage is blocked by Apache.
- Authenticated write APIs require a session-bound CSRF token; public forms also enforce CSRF checks.
- Session cookies use strict cookie-only sessions, HttpOnly, SameSite=Lax, and Secure automatically under HTTPS.
- Deleted or role-changed accounts are revalidated against the database on every request.
- Responsive matrix passed at 375, 768, 1024, and 1440 px for Tasks, Tickets, Settings, Database, Staff Portal, and Ticket Status with no page-level horizontal overflow.
- Task rows open by keyboard Enter and no browser console errors were recorded.
- All deployed PHP files pass `php -l`; SQLite reports `integrity_check: ok`.

## Test cleanup

- All tickets were removed at the user's request and the ticket sequence was reset.
- Temporary native release-test task removed; the original 57 tasks remain.
- Temporary admin accounts removed.
- Temporary uploaded files removed with their parent records.
- Temporary checklist items removed.
- Existing checklist rows belonging to task ID 4 were preserved.

## Deployment note

Deploy to `public_html/tickets/creative` for `https://stratastaffglobal.com/tickets/creative/`. Initial credentials are not committed; create `config.local.php` from the provided example. Upload limits above PHP/Apache configuration cannot be guaranteed by application code alone; configure `upload_max_filesize` and `post_max_size` on the target server if uploads approaching 500 MB are required.
