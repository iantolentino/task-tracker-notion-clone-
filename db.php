<?php

function app_config(): array
{
    $config = require __DIR__ . '/config.php';
    $local = __DIR__ . '/config.local.php';
    if (file_exists($local)) {
        $config = array_merge($config, require $local);
    }
    return $config;
}

function pdo_connect(?string $database = null): PDO
{
    $config = app_config();

    if (($config['db_driver'] ?? 'mysql') === 'sqlite') {
        $path = $database ?: ($config['sqlite_path'] ?? (__DIR__ . '/tasks.sqlite'));
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        return $pdo;
    }

    $db = $database ?? $config['db_name'];
    $dsn = 'mysql:host=' . $config['db_host'] . ';port=' . $config['db_port'];
    if ($db !== '') {
        $dsn .= ';dbname=' . $db;
    }
    $dsn .= ';charset=utf8mb4';

    return new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function create_tasks_table(PDO $pdo): void
{
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL DEFAULT '',
                assignee TEXT NOT NULL DEFAULT '',
                due_date TEXT NULL,
                effort TEXT NOT NULL DEFAULT '',
                priority TEXT NOT NULL DEFAULT '',
                status TEXT NOT NULL DEFAULT 'Not started',
                type TEXT NOT NULL DEFAULT '',
                \"desc\" TEXT NOT NULL DEFAULT '',
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_tasks_status ON tasks(status)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_tasks_due_date ON tasks(due_date)');
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tasks (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL DEFAULT '',
            assignee VARCHAR(255) NOT NULL DEFAULT '',
            due_date DATE NULL,
            effort VARCHAR(50) NOT NULL DEFAULT '',
            priority VARCHAR(50) NOT NULL DEFAULT '',
            status VARCHAR(100) NOT NULL DEFAULT 'Not started',
            type VARCHAR(255) NOT NULL DEFAULT '',
            `desc` TEXT NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_tasks_status (status),
            INDEX idx_tasks_due_date (due_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function create_users_table(PDO $pdo): void
{
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'admin',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $columns = $pdo->query('PRAGMA table_info(users)')->fetchAll();
        $columnNames = array_map(fn($column) => $column['name'], $columns);
        if (!in_array('role', $columnNames, true)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'admin'");
        }
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            username VARCHAR(100) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(30) NOT NULL DEFAULT 'admin',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_users_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetchAll();
    if (!$columns) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(30) NOT NULL DEFAULT 'admin' AFTER password_hash");
    }
}

function ensure_default_admin(PDO $pdo): void
{
    $config = app_config();
    $username = trim((string) ($config['default_admin_user'] ?? ''));
    $password = (string) ($config['default_admin_pass'] ?? '');
    $role = in_array(($config['default_admin_role'] ?? 'super_admin'), ['admin', 'super_admin'], true) ? $config['default_admin_role'] : 'super_admin';
    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

    if ($count === 0) {
        if ($username === '' || strlen($password) < 12) {
            throw new RuntimeException('Initial super-admin credentials are not configured. Create config.local.php from config.local.example.php first.');
        }
        $stmt = $pdo->prepare('
            INSERT INTO users (username, password_hash, role, created_at, updated_at)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role]);
    }
}

function create_uploads_table(PDO $pdo): void
{
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS uploads (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                task_id INTEGER NULL,
                ticket_id INTEGER NULL,
                original_name TEXT NOT NULL,
                stored_name TEXT NOT NULL,
                mime_type TEXT NOT NULL DEFAULT '',
                file_size INTEGER NOT NULL DEFAULT 0,
                uploaded_by INTEGER NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $columns = $pdo->query('PRAGMA table_info(uploads)')->fetchAll();
        $columnNames = array_map(fn($column) => $column['name'], $columns);
        if (!in_array('ticket_id', $columnNames, true)) {
            $pdo->exec('ALTER TABLE uploads ADD COLUMN ticket_id INTEGER NULL');
        }
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_uploads_task_id ON uploads(task_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_uploads_ticket_id ON uploads(ticket_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_uploads_created_at ON uploads(created_at)');
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS uploads (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            task_id BIGINT UNSIGNED NULL,
            ticket_id BIGINT UNSIGNED NULL,
            original_name VARCHAR(255) NOT NULL,
            stored_name VARCHAR(255) NOT NULL,
            mime_type VARCHAR(150) NOT NULL DEFAULT '',
            file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            uploaded_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_uploads_task_id (task_id),
            INDEX idx_uploads_ticket_id (ticket_id),
            INDEX idx_uploads_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $columns = $pdo->query("SHOW COLUMNS FROM uploads LIKE 'task_id'")->fetchAll();
    if (!$columns) {
        $pdo->exec('ALTER TABLE uploads ADD COLUMN task_id BIGINT UNSIGNED NULL AFTER id');
        $pdo->exec('ALTER TABLE uploads ADD INDEX idx_uploads_task_id (task_id)');
    }
    $columns = $pdo->query("SHOW COLUMNS FROM uploads LIKE 'ticket_id'")->fetchAll();
    if (!$columns) {
        $pdo->exec('ALTER TABLE uploads ADD COLUMN ticket_id BIGINT UNSIGNED NULL AFTER task_id');
        $pdo->exec('ALTER TABLE uploads ADD INDEX idx_uploads_ticket_id (ticket_id)');
    }
}

function create_tickets_table(PDO $pdo): void
{
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tickets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                requester_name TEXT NOT NULL,
                requester_email TEXT NOT NULL,
                assignee TEXT NOT NULL DEFAULT 'Aiko',
                due_date TEXT NOT NULL,
                ticket_type TEXT NOT NULL,
                estimated_cost TEXT NOT NULL DEFAULT '',
                effort TEXT NOT NULL DEFAULT '',
                subject TEXT NOT NULL,
                category TEXT NOT NULL DEFAULT 'Other',
                priority TEXT NOT NULL DEFAULT '',
                description TEXT NOT NULL,
                business_impact TEXT NOT NULL DEFAULT '',
                other_type TEXT NOT NULL DEFAULT '',
                status TEXT NOT NULL DEFAULT 'Open',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $columns = $pdo->query('PRAGMA table_info(tickets)')->fetchAll();
        $columnNames = array_map(fn($column) => $column['name'], $columns);
        if (!in_array('assignee', $columnNames, true)) {
            $pdo->exec("ALTER TABLE tickets ADD COLUMN assignee TEXT NOT NULL DEFAULT 'Aiko'");
        }
        if (!in_array('due_date', $columnNames, true)) {
            $pdo->exec("ALTER TABLE tickets ADD COLUMN due_date TEXT NOT NULL DEFAULT ''");
        }
        if (!in_array('ticket_type', $columnNames, true)) {
            $pdo->exec("ALTER TABLE tickets ADD COLUMN ticket_type TEXT NOT NULL DEFAULT 'Other'");
        }
        if (!in_array('estimated_cost', $columnNames, true)) {
            $pdo->exec("ALTER TABLE tickets ADD COLUMN estimated_cost TEXT NOT NULL DEFAULT ''");
        }
        if (!in_array('effort', $columnNames, true)) {
            $pdo->exec("ALTER TABLE tickets ADD COLUMN effort TEXT NOT NULL DEFAULT ''");
        }
        if (!in_array('business_impact', $columnNames, true)) {
            $pdo->exec("ALTER TABLE tickets ADD COLUMN business_impact TEXT NOT NULL DEFAULT ''");
        }
        if (!in_array('other_type', $columnNames, true)) {
            $pdo->exec("ALTER TABLE tickets ADD COLUMN other_type TEXT NOT NULL DEFAULT ''");
        }
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_tickets_status ON tickets(status)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_tickets_created_at ON tickets(created_at)');
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tickets (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            requester_name VARCHAR(120) NOT NULL,
            requester_email VARCHAR(255) NOT NULL,
            assignee VARCHAR(120) NOT NULL DEFAULT 'Aiko',
            due_date DATE NULL,
            ticket_type VARCHAR(120) NOT NULL,
            estimated_cost DECIMAL(12,2) NULL,
            subject VARCHAR(180) NOT NULL,
            category VARCHAR(255) NOT NULL DEFAULT 'Other',
            priority VARCHAR(20) NOT NULL DEFAULT '',
            description TEXT NOT NULL,
            effort VARCHAR(50) NOT NULL DEFAULT '',
            business_impact TEXT NULL,
            other_type VARCHAR(255) NOT NULL DEFAULT '',
            status VARCHAR(30) NOT NULL DEFAULT 'Open',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_tickets_status (status),
            INDEX idx_tickets_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    foreach (['assignee' => "VARCHAR(120) NOT NULL DEFAULT 'Aiko'", 'due_date' => 'DATE NULL', 'ticket_type' => "VARCHAR(120) NOT NULL DEFAULT 'Other'", 'estimated_cost' => 'DECIMAL(12,2) NULL', 'effort' => "VARCHAR(50) NOT NULL DEFAULT ''", 'business_impact' => 'TEXT NULL', 'other_type' => "VARCHAR(255) NOT NULL DEFAULT ''"] as $column => $definition) {
        $columns = $pdo->query("SHOW COLUMNS FROM tickets LIKE '{$column}'")->fetchAll();
        if (!$columns) {
            $pdo->exec("ALTER TABLE tickets ADD COLUMN {$column} {$definition}");
        }
    }
}

function create_task_todos_table(PDO $pdo): void
{
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $pdo->exec("\n            CREATE TABLE IF NOT EXISTS task_todos (\n                id INTEGER PRIMARY KEY AUTOINCREMENT,\n                task_id INTEGER NULL,\n                ticket_id INTEGER NULL,\n                text TEXT NOT NULL,\n                completed INTEGER NOT NULL DEFAULT 0,\n                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,\n                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,\n                CHECK (task_id IS NOT NULL OR ticket_id IS NOT NULL)\n            )\n        ");
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_task_todos_task_id ON task_todos(task_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_task_todos_ticket_id ON task_todos(ticket_id)');
        return;
    }

    $pdo->exec("\n        CREATE TABLE IF NOT EXISTS task_todos (\n            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n            task_id BIGINT UNSIGNED NULL,\n            ticket_id BIGINT UNSIGNED NULL,\n            text VARCHAR(500) NOT NULL,\n            completed TINYINT(1) NOT NULL DEFAULT 0,\n            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n            PRIMARY KEY (id),\n            INDEX idx_task_todos_task_id (task_id),\n            INDEX idx_task_todos_ticket_id (ticket_id)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n    ");
}

function is_stratastaff_email(string $email): bool
{
    $email = trim($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $at = strrpos($email, '@');
    return $at !== false && strtolower(substr($email, $at + 1)) === 'stratastaff.com';
}

function ticket_reference(int $id): string
{
    return sprintf('TCK-%06d', $id);
}

function allowed_upload_extensions(): array
{
    return ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'];
}

function save_ticket_uploads(PDO $pdo, int $ticketId, ?array $files): void
{
    if (!$files || !isset($files['name'])) {
        return;
    }

    $allowedExtensions = allowed_upload_extensions();
    $maxBytes = 500 * 1024 * 1024;
    $count = is_array($files['name']) ? count($files['name']) : 1;

    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Upload storage is unavailable.');
    }

    $savedPaths = [];
    try {
        for ($i = 0; $i < $count; $i++) {
            $error = is_array($files['error']) ? (int) $files['error'][$i] : (int) $files['error'];
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error !== UPLOAD_ERR_OK) {
                throw new RuntimeException('One of the selected files could not be uploaded.');
            }

            $originalName = basename((string) (is_array($files['name']) ? $files['name'][$i] : $files['name']));
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $size = (int) (is_array($files['size']) ? $files['size'][$i] : $files['size']);
            if ($size <= 0 || $size > $maxBytes) {
                throw new RuntimeException('Each attachment must be between 1 byte and 500 MB.');
            }
            if (!in_array($extension, $allowedExtensions, true)) {
                throw new RuntimeException('Unsupported attachment type. Use PDF, image, Office, text, or ZIP files.');
            }

            $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
            $mimeType = mime_content_type($tmpName) ?: 'application/octet-stream';
            if ($mimeType === 'video/mp4' || $extension === 'mp4') {
                throw new RuntimeException('MP4 files are not allowed.');
            }

            $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
            $target = $uploadDir . '/' . $storedName;
            if (!move_uploaded_file($tmpName, $target)) {
                throw new RuntimeException('One of the attachments could not be saved.');
            }
            $savedPaths[] = $target;

            $stmt = $pdo->prepare('INSERT INTO uploads (task_id, ticket_id, original_name, stored_name, mime_type, file_size, uploaded_by, created_at) VALUES (NULL, ?, ?, ?, ?, ?, NULL, CURRENT_TIMESTAMP)');
            $stmt->execute([$ticketId, $originalName, $storedName, $mimeType, $size]);
        }
    } catch (Throwable $e) {
        foreach ($savedPaths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        throw $e;
    }
}

function attach_uploads_to_tickets(PDO $pdo, array $tickets): array
{
    if (!$tickets) {
        return [];
    }

    $ids = array_map(fn($ticket) => (int) $ticket['id'], $tickets);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM uploads WHERE ticket_id IN ({$placeholders}) ORDER BY created_at DESC, id DESC");
    $stmt->execute($ids);

    $byTicket = [];
    foreach ($stmt->fetchAll() as $upload) {
        $ticketId = (int) $upload['ticket_id'];
        $byTicket[$ticketId][] = upload_to_array($upload);
    }
    foreach ($tickets as &$ticket) {
        $ticket['uploads'] = $byTicket[(int) $ticket['id']] ?? [];
    }
    unset($ticket);
    return $tickets;
}

function upload_to_array(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'taskId' => isset($row['task_id']) && $row['task_id'] !== null ? (int) $row['task_id'] : null,
        'name' => $row['original_name'] ?? '',
        'url' => 'upload/' . (int) $row['id'],
        'size' => isset($row['file_size']) ? (int) $row['file_size'] : 0,
        'created_at' => $row['created_at'] ?? '',
    ];
}

function attach_uploads_to_tasks(PDO $pdo, array $tasks): array
{
    if (!$tasks) {
        return [];
    }

    $ids = array_map(fn($task) => (int) $task['id'], $tasks);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM uploads WHERE task_id IN ({$placeholders}) ORDER BY created_at DESC, id DESC");
    $stmt->execute($ids);

    $byTask = [];
    foreach ($stmt->fetchAll() as $upload) {
        $taskId = (int) $upload['task_id'];
        $byTask[$taskId][] = upload_to_array($upload);
    }

    foreach ($tasks as &$task) {
        $task['uploads'] = $byTask[(int) $task['id']] ?? [];
    }
    unset($task);

    return $tasks;
}

function row_to_task(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'name' => $row['name'] ?? '',
        'assignee' => $row['assignee'] ?? '',
        'dueDate' => $row['due_date'] ?? '',
        'effort' => $row['effort'] ?? '',
        'priority' => $row['priority'] ?? '',
        'status' => $row['status'] ?? 'Not started',
        'type' => $row['type'] ?? '',
        'desc' => $row['desc'] ?? '',
        'updated_at' => $row['updated_at'] ?? '',
    ];
}

function normalize_task_input(array $data): array
{
    $read = static function (string $key, string $default = '') use ($data): string {
        $value = $data[$key] ?? $default;
        return is_scalar($value) ? trim((string) $value) : $default;
    };

    $name = $read('name');
    if ($name === '') {
        throw new InvalidArgumentException('Task name is required.');
    }
    if (strlen($name) > 255) {
        throw new InvalidArgumentException('Task name must be 255 characters or fewer.');
    }

    $dueRaw = $read('dueDate');
    $dueDate = parse_due_date($dueRaw);
    if ($dueRaw !== '' && $dueDate === null) {
        throw new InvalidArgumentException('Use a valid due date.');
    }

    $assignee = $read('assignee');
    $effort = $read('effort');
    $priority = $read('priority');
    $status = $read('status', 'Not started') ?: 'Not started';
    $type = $read('type');
    $description = $read('desc');

    if (strlen($assignee) > 255 || strlen($type) > 255) {
        throw new InvalidArgumentException('Assignee and task type must be 255 characters or fewer.');
    }
    if (strlen($description) > 10000) {
        throw new InvalidArgumentException('Description must be 10,000 characters or fewer.');
    }
    if (!in_array($effort, ['', 'Small', 'Medium', 'Large'], true)) {
        throw new InvalidArgumentException('Use a valid effort level.');
    }
    if (!in_array($priority, ['', 'Urgent', 'High', 'Medium', 'Low'], true)) {
        throw new InvalidArgumentException('Use a valid priority.');
    }
    if (!in_array($status, ['Done', 'In progress', 'Not started', 'On Hold/Waiting for Material'], true)) {
        throw new InvalidArgumentException('Use a valid task status.');
    }

    return [
        'name' => $name,
        'assignee' => $assignee,
        'due_date' => $dueDate,
        'effort' => $effort,
        'priority' => $priority,
        'status' => $status,
        'type' => $type,
        'desc' => $description,
    ];
}

function parse_due_date(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $formats = ['Y-m-d', 'm/d/Y', 'n/j/Y'];
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat('!' . $format, $value);
        $details = DateTime::getLastErrors();
        $hasDateErrors = is_array($details) && ($details['warning_count'] > 0 || $details['error_count'] > 0);
        if ($date instanceof DateTime && !$hasDateErrors && $date->format($format) === $value) {
            return $date->format('Y-m-d');
        }
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('Y-m-d', $timestamp) : null;
}

function json_body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : [];
}

function send_json($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function send_error(string $message, int $status = 500): void
{
    send_json(['error' => $message], $status);
}
