<?php
require __DIR__ . '/auth.php';
$loggedIn = is_logged_in();
if ($loggedIn) {
    require __DIR__ . '/nav.php';
}

function import_seed_csv(PDO $pdo): int
{
    $config = app_config();
    $path = $config['csv_seed_file'];
    if (!file_exists($path)) {
        return 0;
    }

    $count = (int) $pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn();
    if ($count > 0) {
        return 0;
    }

    $handle = fopen($path, 'r');
    if (!$handle) {
        return 0;
    }

    $header = array_map(
        fn($value) => preg_replace('/^\xEF\xBB\xBF/', '', $value),
        fgetcsv($handle)
    );
    $inserted = 0;
    $stmt = $pdo->prepare('
        INSERT INTO tasks (name, assignee, due_date, effort, priority, status, type, `desc`, updated_at)
        VALUES (:name, :assignee, :due_date, :effort, :priority, :status, :type, :desc, NOW())
    ');

    while (($row = fgetcsv($handle)) !== false) {
        $item = array_combine($header, $row);
        if (!$item || trim($item['Task name'] ?? '') === '') {
            continue;
        }
        $stmt->execute([
            ':name' => trim($item['Task name'] ?? ''),
            ':assignee' => trim($item['Assignee'] ?? ''),
            ':due_date' => parse_due_date($item['Due date'] ?? ''),
            ':effort' => trim($item['Effort level'] ?? ''),
            ':priority' => trim($item['Priority'] ?? ''),
            ':status' => trim($item['Status'] ?? 'Not started') ?: 'Not started',
            ':type' => trim($item['Task type'] ?? ''),
            ':desc' => trim($item['Description'] ?? ''),
        ]);
        $inserted++;
    }

    fclose($handle);
    return $inserted;
}

$message = '';
$ok = false;

try {
    $config = app_config();
    try {
        $pdo = pdo_connect();
    } catch (Throwable $connectError) {
        $pdoServer = pdo_connect('');
        $pdoServer->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $config['db_name']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo = pdo_connect();
    }
    create_tasks_table($pdo);
    create_users_table($pdo);
    create_uploads_table($pdo);
    ensure_default_admin($pdo);
    $inserted = import_seed_csv($pdo);
    $total = (int) $pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn();

    $ok = true;
    $message = "Database is ready. Imported {$inserted} starter tasks. Total tasks: {$total}. Login with admin / admin123, then change the password in Settings.";
} catch (Throwable $e) {
    $message = $e->getMessage();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Task Tracker Setup</title>
    <style>
        <?php if ($loggedIn) echo nav_css(); ?>
        body { font-family: Arial, sans-serif; background: #f8fafc; color: #0f172a; margin: 0; }
        main { max-width: 720px; margin: 40px auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 24px; }
        .ok { color: #15803d; }
        .bad { color: #b91c1c; }
        code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; }
        a { color: #2563eb; }
    </style>
</head>
<body>
<?php if ($loggedIn) render_nav('setup'); ?>
<main>
    <h1 class="<?php echo $ok ? 'ok' : 'bad'; ?>"><?php echo $ok ? 'Setup Complete' : 'Setup Error'; ?></h1>
    <p><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
    <p><a href="./">Open Tasks Tracker</a></p>
    <p>Database settings are in <code>config.php</code>. For private credentials, create <code>config.local.php</code>.</p>
</main>
</body>
</html>
