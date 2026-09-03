# cPanel deployment

Target URL: `https://stratastaffglobal.com/tickets/creative/`

1. In File Manager, create `public_html/tickets/creative` and upload the project files there.
2. Do not upload a local SQLite database or `config.local.php`.
3. Copy `config.local.example.php` to `config.local.php` on the server and set a unique super-admin username and a password of at least 12 characters.
4. Confirm the cPanel PHP version is 8.1 or newer with `pdo_sqlite` and `fileinfo` enabled.
5. Ensure PHP can write to `database/` and `uploads/` (normally `0755`; use `0775` only if required by the host).
6. Open the HTTPS URL and sign in. The first login creates the SQLite database and the configured super-admin account.
7. Change the initial password in Settings, then remove `default_admin_pass` from `config.local.php`; the existing account remains in SQLite.

The included `.htaccess` files block direct access to SQLite, configuration, uploaded files, indexes, and internal documentation. Application links are relative, so the nested `/tickets/creative/` path needs no code changes.

For large attachments, cPanel PHP settings must be at least:

- `upload_max_filesize = 500M`
- `post_max_size = 510M`
- a suitable `max_execution_time` for the server connection

Keep HTTPS enabled so the session cookie is transmitted securely.
