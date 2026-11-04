<?php
return [
    // SQLite is the production default for the standalone deployment.
    'db_driver' => 'sqlite',
    'sqlite_path' => __DIR__ . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'creatives-ticketing.sqlite',
    // MySQL remains available when a hosted deployment needs it.
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => 'creatives_ticketing_system',
    'db_user' => 'root',
    'db_pass' => '',
    'csv_seed_file' => __DIR__ . '/creative-requests-seed.csv',
    // Supply initial credentials through server environment variables or config.local.php.
    'default_admin_user' => getenv('APP_DEFAULT_ADMIN_USER') ?: '',
    'default_admin_pass' => getenv('APP_DEFAULT_ADMIN_PASSWORD') ?: '',
    'default_admin_role' => 'super_admin',
];
