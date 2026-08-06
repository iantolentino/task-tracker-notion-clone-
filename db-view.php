<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/nav.php';

$pdo = pdo_connect();
create_tasks_table($pdo);
create_uploads_table($pdo);
$rows = attach_uploads_to_tasks($pdo, array_map('row_to_task', $pdo->query('SELECT * FROM tasks ORDER BY id DESC')->fetchAll()));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DB View - Tasks Tracker</title>
    <style>
        <?php echo nav_css(); ?>
        body { font-family: Arial, sans-serif; margin: 0; background: #f8fafc; color: #0f172a; }
        main { max-width: 1400px; margin: 0 auto; padding: 24px; }
        .panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .meta { color: #64748b; font-size: 13px; padding: 12px 16px; border-bottom: 1px solid #e2e8f0; }
        .scroll { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 9px 10px; text-align: left; font-size: 13px; vertical-align: top; }
        th { color: #64748b; font-size: 11px; text-transform: uppercase; background: #f8fafc; }
        a { color: #2563eb; text-decoration: none; }
    </style>
</head>
<body>
<?php render_nav('db'); ?>
<main>
    <div class="panel">
        <div class="meta"><?php echo count($rows); ?> rows - latest first</div>
        <div class="scroll">
            <table>
                <thead>
                    <tr>
                        <th>ID</th><th>Name</th><th>Assignee</th><th>Due Date</th><th>Status</th><th>Priority</th><th>Effort</th><th>Type</th><th>Files</th><th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?php echo (int) $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row['assignee'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row['dueDate'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row['priority'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row['effort'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row['type'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php foreach ($row['uploads'] as $file): ?>
                                    <a href="<?php echo htmlspecialchars($file['url'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?></a><br>
                                <?php endforeach; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['updated_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>
