# Run Locally With PHP and phpMyAdmin

This version uses your existing HTML design with a PHP backend and MySQL database.

## Option 1: XAMPP on your PC

1. Install XAMPP.
2. Start Apache and MySQL in the XAMPP Control Panel.
3. Put this project folder inside `C:\xampp\htdocs\task-tracker`.
4. Open `http://localhost/phpmyadmin`.
5. Create a database named `task_tracker`.
6. Open `http://localhost/task-tracker/setup.php`.
7. Open `http://localhost/task-tracker/`.
8. Login with `admin` / `admin123`, then open Settings and change the password.

Default local database settings are in `config.php`:

```php
'db_host' => '127.0.0.1',
'db_name' => 'task_tracker',
'db_user' => 'root',
'db_pass' => '',
```

## Option 2: PHP Built-In Server

If PHP is already installed and MySQL is running:

1. Double-click `start-php-local.bat`.
2. The setup page opens first.
3. After setup, open `http://127.0.0.1:8000`.

## cPanel

1. In cPanel, create a MySQL database and database user.
2. Add the user to the database with all privileges.
3. Upload the project files to `public_html` or a subfolder.
4. Edit `config.php` with your cPanel database name, username, and password.
5. Visit `https://your-domain.com/setup.php` once.
6. Visit your site homepage.

After setup works, you can delete or rename `setup.php` on the live server so visitors cannot rerun it.

Settings includes:

- Password updates for the logged-in user.
- Adding new users.
- Deleting users, except the currently logged-in user.

Task files are uploaded from the Add/Edit Task window. Uploaded files appear in the Files column on the task dashboard. MP4 files are blocked.

The navbar is shared across the protected pages and links to Tasks, Settings, and Logout. DB View and Setup still exist for manual admin use, but they are hidden from the normal navbar.
