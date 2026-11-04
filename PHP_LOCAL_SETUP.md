# Run Locally With PHP and SQLite

This version uses PHP, HTML, CSS, JavaScript, and SQLite for a fast standalone deployment. The SQLite file lives in the protected `database/` folder (and is never served directly). MySQL remains supported as an optional hosted deployment.

## Local XAMPP test setup

1. Copy `config.local.example.php` to `config.local.php` and set a super-admin username and password of at least 12 characters.
2. Start Apache in the XAMPP Control Panel.
3. Keep the project at `C:\xampp\htdocs\creatives-ticketing-system`.
4. Open `http://localhost/creatives-ticketing-system/` and sign in. The SQLite database and initial account are created automatically.
5. On this prepared test workstation, use:

   - Username: `admin`
   - Password: `AdminTest123!`
   - Role: `super_admin`

`config.local.php` is ignored by Git and may override the SQLite path for local testing. Do not upload the local override or database to production.

## Option 2: PHP built-in server

If PHP is already installed:

1. Double-click `start-php-local.bat`.
2. The setup page opens first. SQLite is created automatically.
3. After setup, open `http://127.0.0.1:8000`.

## Hosted deployment

For cPanel/SQLite deployment, follow `_brain/CPANEL_DEPLOYMENT.md`. MySQL remains optional: set `db_driver` and private database credentials in the ignored `config.local.php`.

Settings includes:

- Password updates for the logged-in user.
- Adding administrator users only. The single super-admin account cannot be created from Settings.
- Deleting users, except the currently logged-in user.

Task files are uploaded from the Add/Edit Task window. Ticket attachments allow multiple supported files up to 500 MB each; MP4 files are blocked. Uploaded files appear in the Files column on the task dashboard.

The navbar is shared across the protected pages and links to Tasks, Tickets, Settings, Database (super admins only), and Logout.
