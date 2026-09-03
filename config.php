<?php
return [
    // SQLite is the production default for the standalone deployment.
    'db_driver' => 'sqlite',
    'sqlite_path' => __DIR__ . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'task-tracker.sqlite',
    // MySQL remains available when a hosted deployment needs it.
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => 'task_tracker',
    'db_user' => 'root',
    'db_pass' => '',
    'csv_seed_file' => __DIR__ . '/Tasks Tracker 2e7a0e68fd4180f49b9dd09276ea3ea2.csv',
    // Supply initial credentials through server environment variables or config.local.php.
    'default_admin_user' => getenv('APP_DEFAULT_ADMIN_USER') ?: '',
    'default_admin_pass' => getenv('APP_DEFAULT_ADMIN_PASSWORD') ?: '',
    'default_admin_role' => 'super_admin',
];
