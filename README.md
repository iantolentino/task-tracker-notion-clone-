# Multimedia and Marketing Ticketing System (MMTS)

Internal request tracker for the Strata Staff multimedia and marketing team. Staff submit creative requests through a public portal; admins triage them, track work on a task dashboard and manage everything from a small admin area.

Plain PHP + SQLite (MySQL optional). No framework or build step.

## Features

- Request Form with optional department, deadline date and time, and attachments
- Ticket inbox with filter tabs, one-click status changes and CSV export
- Statuses: Open, In progress, On hold, Waiting for Approval, For Revision, Resolved/Closed — each with its own color
- Comments on tickets between the team and the requester
- Completion date recorded when work is marked done
- Tasks dashboard combining internal tasks and triaged tickets
- Light and dark mode; times shown in the configured time zone (default Asia/Manila)

## Pages

| Page | Who | Purpose |
|---|---|---|
| `ticket.php` | Staff (no login) | Request Form — submit a new request with attachments |
| `ticket-status.php` | Staff (no login) | My tickets — look up your requests by work email and reply to comments |
| `login.php` | Admins | Sign in |
| `index.php` | Admins | Tasks dashboard (tasks plus triaged tickets) |
| `tickets.php` | Admins | Ticket inbox — triage, change status, comment, export CSV |
| `settings.php` | Admins | Change password, add admins |
| `db-view.php` | Super admin | View, edit or delete any ticket, task or account |

## Run locally

See [docs/LOCAL_SETUP.md](docs/LOCAL_SETUP.md). In short: copy `config.local.example.php` to `config.local.php`, set an admin username and password, and open the folder through XAMPP or `start-php-local.bat`.

## Deploy

See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md). GitHub holds the full system; the server only gets what changed. `scripts\build-release.bat` packages the website files changed since the `live` tag — never docs, sample data, `config.local.php`, a database or uploads. Back up the live database before each update.

## Project layout

```
assets/            Shared stylesheet (design-system.css), theme switch (theme.js), logo
database/          SQLite file lives here on the server (blocked from the web)
uploads/           Uploaded attachments (blocked from direct access)
docs/              Setup, deployment and the admin request to-do list
scripts/           build-release.bat/.ps1 (cPanel upload zip of changed files, in release/)
*.php              Pages and the JSON endpoints (api.php, todos.php, task-upload.php, upload.php)
db.php, auth.php   Database helpers, automatic schema upgrades, sign-in/session handling
comments-view.php  Shared comment thread markup
schema.sql         MySQL schema (SQLite tables are created automatically)
```

## Admin requests

The admins' improvement list and its status is in [docs/ADMIN_REQUESTS_TODO.md](docs/ADMIN_REQUESTS_TODO.md).
