# Multimedia and Marketing Ticketing System (MMTS)

Internal request tracker for the Strata Staff multimedia and marketing team. Staff submit creative requests through a public portal; admins triage them, track work on a task dashboard and manage everything from a small admin area.

Plain PHP + SQLite (MySQL optional). No framework or build step.

## Pages

| Page | Who | Purpose |
|---|---|---|
| `ticket.php` | Staff (no login) | Request Form — submit a new request with attachments |
| `ticket-status.php` | Staff (no login) | My tickets — look up your requests by work email |
| `login.php` | Admins | Sign in |
| `index.php` | Admins | Tasks dashboard (tasks plus triaged tickets) |
| `tickets.php` | Admins | Ticket inbox — triage, change status, export CSV |
| `settings.php` | Admins | Change password, add admins |
| `db-view.php` | Super admin | View, edit or delete any ticket, task or account |

## Run locally

See [docs/LOCAL_SETUP.md](docs/LOCAL_SETUP.md). In short: copy `config.local.example.php` to `config.local.php`, set an admin username and password, and open the folder through XAMPP or `start-php-local.bat`.

## Deploy

See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md). Never upload `config.local.php` or a local database.

## Project layout

```
assets/            Shared stylesheet (design-system.css), theme switch (theme.js), logo
database/          SQLite file lives here on the server (blocked from the web)
uploads/           Uploaded attachments (blocked from direct access)
docs/              Setup, deployment and the admin request to-do list
*.php              Pages and the JSON endpoints (api.php, todos.php, task-upload.php, upload.php)
db.php, auth.php   Database helpers and sign-in/session handling
schema.sql         MySQL schema (SQLite tables are created automatically)
```

## Roadmap

Open admin requests are tracked in [docs/ADMIN_REQUESTS_TODO.md](docs/ADMIN_REQUESTS_TODO.md).
