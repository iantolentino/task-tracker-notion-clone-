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
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            username VARCHAR(100) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_users_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function ensure_default_admin(PDO $pdo): void
{
    $config = app_config();
    $username = $config['default_admin_user'] ?? 'admin';
    $password = $config['default_admin_pass'] ?? 'admin123';
    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

    if ($count === 0) {
        $stmt = $pdo->prepare('
            INSERT INTO users (username, password_hash, created_at, updated_at)
            VALUES (?, ?, NOW(), NOW())
        ');
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
    }
}

function create_uploads_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS uploads (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            task_id BIGINT UNSIGNED NULL,
            original_name VARCHAR(255) NOT NULL,
            stored_name VARCHAR(255) NOT NULL,
            mime_type VARCHAR(150) NOT NULL DEFAULT '',
            file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            uploaded_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_uploads_task_id (task_id),
            INDEX idx_uploads_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $columns = $pdo->query("SHOW COLUMNS FROM uploads LIKE 'task_id'")->fetchAll();
    if (!$columns) {
        $pdo->exec('ALTER TABLE uploads ADD COLUMN task_id BIGINT UNSIGNED NULL AFTER id');
        $pdo->exec('ALTER TABLE uploads ADD INDEX idx_uploads_task_id (task_id)');
    }
}

function upload_to_array(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'taskId' => isset($row['task_id']) ? (int) $row['task_id'] : null,
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

function parse_due_date(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $formats = ['Y-m-d', 'm/d/Y', 'n/j/Y'];
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date instanceof DateTime) {
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
