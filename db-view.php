<?php
require __DIR__ . '/auth.php';
require_super_admin();
require __DIR__ . '/nav.php';

$assignees = ['Aiko', 'Ivan', 'Aiko/Ivan'];
$categories = ['Graphics', 'Marketing', 'Sales Request', 'Project', 'Other'];
$priorities = ['', 'Low', 'Normal', 'High', 'Urgent'];
$efforts = ['', 'Small', 'Medium', 'Large'];
$statuses = ['Open', 'In progress', 'On hold', 'Waiting for material', 'Waiting for budget', 'Resolved', 'Closed', 'New', 'On Hold/Waiting for Material'];
$message = '';
$error = '';

if (!isset($_SESSION['db_csrf'])) {
    $_SESSION['db_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['db_csrf'];

try {
    $pdo = pdo_connect();
    create_tasks_table($pdo);
    create_users_table($pdo);
    create_uploads_table($pdo);
    create_tickets_table($pdo);
    create_task_todos_table($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Your session expired. Refresh and try again.');
        }

        $action = $_POST['action'] ?? '';
        if ($action === 'update_task') {
            $taskId = (int) ($_POST['task_id'] ?? 0);
            if ($taskId <= 0) {
                throw new RuntimeException('Invalid task selected.');
            }
            $taskInput = normalize_task_input([
                'name' => $_POST['name'] ?? '',
                'assignee' => $_POST['assignee'] ?? '',
                'dueDate' => $_POST['due_date'] ?? '',
                'effort' => $_POST['effort'] ?? '',
                'priority' => $_POST['priority'] ?? '',
                'status' => $_POST['status'] ?? '',
                'type' => $_POST['type'] ?? '',
                'desc' => $_POST['desc'] ?? '',
            ]);
            $stmt = $pdo->prepare('UPDATE tasks SET name = ?, assignee = ?, due_date = ?, effort = ?, priority = ?, status = ?, type = ?, `desc` = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->execute([$taskInput['name'], $taskInput['assignee'], $taskInput['due_date'], $taskInput['effort'], $taskInput['priority'], $taskInput['status'], $taskInput['type'], $taskInput['desc'], $taskId]);
            $message = $stmt->rowCount() ? 'Task updated.' : 'Task saved.';
        }

        if ($action === 'update_user') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $username = trim((string) ($_POST['username'] ?? ''));
            $newPassword = (string) ($_POST['password'] ?? '');
            if ($userId <= 0 || $username === '' || strlen($username) > 100 || !preg_match('/^[A-Za-z0-9._@-]+$/', $username)) {
                throw new RuntimeException('Use a valid username (letters, numbers, dots, dashes, underscores, or @).');
            }
            if ($newPassword !== '' && strlen($newPassword) < 12) {
                throw new RuntimeException('New passwords must be at least 12 characters.');
            }
            $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $existingRole = $stmt->fetchColumn();
            if (!$existingRole) {
                throw new RuntimeException('Account not found.');
            }
            if ($newPassword === '') {
                $stmt = $pdo->prepare('UPDATE users SET username = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                $stmt->execute([$username, $userId]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET username = ?, password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                $stmt->execute([$username, password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
            }
            $message = 'Account updated.';
        }
        if ($action === 'update_ticket') {
            $ticketId = (int) ($_POST['ticket_id'] ?? 0);
            $assignee = $_POST['assignee'] ?? '';
            $dueRaw = trim((string) ($_POST['due_date'] ?? ''));
            $dueDate = parse_due_date($dueRaw);
            $estimatedCost = trim((string) ($_POST['estimated_cost'] ?? ''));
            $priority = (string) ($_POST['priority'] ?? '');
            $effort = (string) ($_POST['effort'] ?? '');
            $rawCategories = $_POST['category'] ?? [];
            $rawCategories = is_array($rawCategories) ? $rawCategories : [$rawCategories];
            $categoryValues = array_values(array_unique(array_intersect($categories, array_map('strval', $rawCategories))));
            $category = implode(', ', $categoryValues);
            $ticketType = in_array('Project', $categoryValues, true) ? 'Project' : (in_array('Other', $categoryValues, true) ? 'Other' : ($categoryValues[0] ?? 'Other'));
            $status = $_POST['status'] ?? 'Open';
            if ($ticketId <= 0 || !in_array($assignee, $assignees, true) || ($dueRaw !== '' && !$dueDate) || $category === '' || !in_array($priority, $priorities, true) || !in_array($effort, $efforts, true) || !in_array($status, $statuses, true) || ($estimatedCost !== '' && (!preg_match('/^\d+(?:\.\d{1,2})?$/', $estimatedCost) || (float) $estimatedCost < 0))) {
                throw new RuntimeException('Use valid routing, deadline, type/category, cost, priority, effort, and status values.');
            }
            $storedDueDate = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? ($dueDate ?? '') : $dueDate;
            $storedCost = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? $estimatedCost : ($estimatedCost === '' ? null : $estimatedCost);
            $stmt = $pdo->prepare('UPDATE tickets SET assignee = ?, due_date = ?, ticket_type = ?, estimated_cost = ?, category = ?, priority = ?, effort = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->execute([$assignee, $storedDueDate, $ticketType, $storedCost, $category, $priority, $effort, $status, $ticketId]);
            $message = 'Ticket updated.';
        }

        if ($action === 'delete_ticket') {
            $ticketId = (int) ($_POST['ticket_id'] ?? 0);
            $stmt = $pdo->prepare('SELECT stored_name FROM uploads WHERE ticket_id = ?');
            $stmt->execute([$ticketId]);
            foreach ($stmt->fetchAll() as $upload) {
                $path = __DIR__ . '/uploads/' . $upload['stored_name'];
                if (is_file($path)) {
                    unlink($path);
                }
            }
            $pdo->prepare('DELETE FROM uploads WHERE ticket_id = ?')->execute([$ticketId]);
            $pdo->prepare('DELETE FROM task_todos WHERE ticket_id = ?')->execute([$ticketId]);
            $stmt = $pdo->prepare('DELETE FROM tickets WHERE id = ?');
            $stmt->execute([$ticketId]);
            $message = $stmt->rowCount() ? 'Ticket deleted.' : 'Ticket not found.';
        }

        if ($action === 'delete_task') {
            $taskId = (int) ($_POST['task_id'] ?? 0);
            $stmt = $pdo->prepare('SELECT stored_name FROM uploads WHERE task_id = ?');
            $stmt->execute([$taskId]);
            foreach ($stmt->fetchAll() as $upload) {
                $path = __DIR__ . '/uploads/' . $upload['stored_name'];
                if (is_file($path)) {
                    unlink($path);
                }
            }
            $pdo->prepare('DELETE FROM uploads WHERE task_id = ?')->execute([$taskId]);
            $pdo->prepare('DELETE FROM task_todos WHERE task_id = ?')->execute([$taskId]);
            $stmt = $pdo->prepare('DELETE FROM tasks WHERE id = ?');
            $stmt->execute([$taskId]);
            $message = $stmt->rowCount() ? 'Task deleted.' : 'Task not found.';
        }

        if ($action === 'delete_user') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            if ($userId === (int) current_user()['id']) {
                throw new RuntimeException('You cannot delete the account you are using.');
            }
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $message = $stmt->rowCount() ? 'User deleted.' : 'User not found.';
        }
    }

    $tasks = $pdo->query('SELECT * FROM tasks ORDER BY id DESC')->fetchAll();
        $tickets = attach_uploads_to_tickets($pdo, $pdo->query('SELECT * FROM tickets ORDER BY id DESC')->fetchAll());
    $users = $pdo->query('SELECT id, username, role, created_at FROM users ORDER BY username ASC')->fetchAll();
} catch (Throwable $e) {
    $error = $e->getMessage();
    $tasks = $tasks ?? [];
    $tickets = $tickets ?? [];
    $users = $users ?? [];
}

$esc = fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Database - Creatives Ticketing System</title>
    <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
    <style>
        body { margin: 0; background: #f8fafc; color: #0f172a; font-family: Arial, Helvetica, sans-serif; }
        <?php echo nav_css(); ?>
        main { max-width: 1400px; margin: 0 auto; padding: 32px 18px 60px; }
        .heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 18px; margin-bottom: 22px; }
        h1 { margin: 0 0 6px; font-size: 26px; letter-spacing: -.03em; }
        h2 { margin: 0 0 14px; font-size: 18px; }
        .subtitle { margin: 0; color: #64748b; font-size: 13px; }
        .link-button { display: inline-flex; align-items: center; min-height: 40px; padding: 0 14px; border-radius: 8px; background: #2563eb; color: #fff; text-decoration: none; font-size: 13px; font-weight: 700; }
        .panel { margin-bottom: 22px; padding: 20px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 12px rgba(15, 23, 42, .04); }
        .alert { margin-bottom: 18px; padding: 12px 14px; border-radius: 8px; font-size: 13px; }
        .ok { border: 1px solid #a7f3d0; background: #ecfdf5; color: #047857; }
        .bad { border: 1px solid #fca5a5; background: #fef2f2; color: #b91c1c; }
        .scroll { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 9px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; font-size: 13px; }
        th { color: #64748b; background: #f8fafc; font-size: 11px; text-transform: uppercase; }
        td form { margin: 0; }
        input, select, textarea { width: 100%; min-width: 110px; box-sizing: border-box; padding: 7px 8px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; color: #0f172a; font: inherit; font-size: 12px; }
        textarea { resize: vertical; }
        .task-edit-form, .task-routing-form, .account-edit-form { display: grid; gap: 7px; min-width: 260px; }
        .inline-form { display: grid; grid-template-columns: repeat(8, minmax(100px, 1fr)) auto; gap: 7px; align-items: center; min-width: 1250px; }
        button { padding: 7px 10px; border: 0; border-radius: 6px; background: #2563eb; color: #fff; cursor: pointer; font: inherit; font-size: 12px; font-weight: 700; }
        button.danger { background: #dc2626; }
        .muted { color: #64748b; font-size: 12px; }
        .description { max-width: 440px; white-space: pre-wrap; color: #334155; line-height: 1.5; }
        .ticket-ref { color: #2563eb; font-size: 11px; font-weight: 800; letter-spacing: .1em; }
        .section-note { margin: -7px 0 15px; color: #64748b; font-size: 12px; }
        @media (max-width: 760px) { .heading { align-items: flex-start; flex-direction: column; } .link-button { width: 100%; justify-content: center; } .panel { padding: 15px; } }
    </style>
    <link rel="stylesheet" href="assets/design-system.css?v=20260903d">
</head>
<body class="db-page">
<?php render_nav('db'); ?>
<main id="main-content">
    <div class="heading"><div><h1>Database management</h1><p class="subtitle">Super-admin controls for users, tasks, and submitted tickets.</p></div><a class="link-button" href="ticket.php" target="_blank" rel="noopener">Open public ticket form</a></div>
    <?php if ($message): ?><div class="alert ok" role="status"><?php echo $esc($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert bad" role="alert"><?php echo $esc($error); ?></div><?php endif; ?>

    <section class="panel">
        <h2>Submitted tickets (<?php echo count($tickets); ?>)</h2>
        <p class="section-note">Super admins can edit routing, cost, and status here. Public requesters can only view their own tickets by email.</p>
        <div class="scroll"><table>
            <thead><tr><th>Reference / subject</th><th>Requester</th><th>Routing and status</th><th>Details</th><th>Delete</th></tr></thead>
            <tbody>
            <?php foreach ($tickets as $ticket): ?>
                <tr>
                    <td><div class="ticket-ref"><?php echo $esc(ticket_reference((int) $ticket['id'])); ?></div><strong><?php echo $esc($ticket['subject']); ?></strong></td>
                    <td><?php echo $esc($ticket['requester_name']); ?><br><span class="muted"><?php echo $esc($ticket['requester_email']); ?></span></td>
                    <td>
                        <form method="post" class="inline-form">
                            <input type="hidden" name="csrf" value="<?php echo $esc($csrf); ?>"><input type="hidden" name="action" value="update_ticket"><input type="hidden" name="ticket_id" value="<?php echo (int) $ticket['id']; ?>">
                            <select name="assignee" aria-label="Assignee"><?php foreach ($assignees as $assignee): ?><option value="<?php echo $esc($assignee); ?>"<?php echo ($ticket['assignee'] ?? '') === $assignee ? ' selected' : ''; ?>><?php echo $esc($assignee); ?></option><?php endforeach; ?></select>
                            <input type="date" name="due_date" aria-label="Deadline" value="<?php echo $esc($ticket['due_date'] ?? ''); ?>">
                            <select name="category[]" aria-label="Type and category" multiple size="5"><?php $rowCategories = array_map('trim', explode(',', (string) ($ticket['category'] ?? ''))); foreach ($categories as $category): ?><option value="<?php echo $esc($category); ?>"<?php echo in_array($category, $rowCategories, true) ? ' selected' : ''; ?>><?php echo $esc($category); ?></option><?php endforeach; ?></select>
                            <input type="number" min="0" step="0.01" name="estimated_cost" aria-label="Estimated cost" value="<?php echo $esc($ticket['estimated_cost'] ?? ''); ?>" placeholder="Cost">
                            <select name="priority" aria-label="Priority"><?php foreach ($priorities as $priority): ?><option value="<?php echo $esc($priority); ?>"<?php echo ($ticket['priority'] ?? '') === $priority ? ' selected' : ''; ?>><?php echo $esc($priority ?: 'Not set'); ?></option><?php endforeach; ?></select>
                            <select name="effort" aria-label="Effort"><?php foreach ($efforts as $effort): ?><option value="<?php echo $esc($effort); ?>"<?php echo ($ticket['effort'] ?? '') === $effort ? ' selected' : ''; ?>><?php echo $esc($effort ?: 'Not set'); ?></option><?php endforeach; ?></select>
                            <select name="status" aria-label="Status"><?php foreach ($statuses as $status): ?><option value="<?php echo $esc($status); ?>"<?php echo ($ticket['status'] ?? '') === $status ? ' selected' : ''; ?>><?php echo $esc($status); ?></option><?php endforeach; ?></select>
                            <button type="submit">Save</button>
                        </form>
                    </td>
                    <td class="description"><?php echo nl2br($esc($ticket['description'])); ?><?php if ((string) ($ticket['estimated_cost'] ?? '') !== ''): ?><div class="muted" style="margin-top:8px;"><strong>Estimated cost:</strong> <?php echo $esc($ticket['estimated_cost']); ?></div><?php endif; ?><?php if (!empty($ticket['uploads'])): ?><div class="muted" style="margin-top:8px;"><strong>Files:</strong> <?php foreach ($ticket['uploads'] as $file): ?><a href="<?php echo $esc($file['url']); ?>"><?php echo $esc($file['name']); ?></a> <?php endforeach; ?></div><?php endif; ?></td>
                    <td><form method="post" onsubmit="return confirm('Delete this ticket and its files?');"><input type="hidden" name="csrf" value="<?php echo $esc($csrf); ?>"><input type="hidden" name="action" value="delete_ticket"><input type="hidden" name="ticket_id" value="<?php echo (int) $ticket['id']; ?>"><button class="danger" type="submit">Delete</button></form></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$tickets): ?><tr><td colspan="5" class="muted">No tickets submitted.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>

    <section class="panel">
        <h2>Tasks (<?php echo count($tasks); ?>)</h2>
        <p class="section-note">Super admins can edit every task field or remove a task and its files.</p>
        <div class="scroll"><table><thead><tr><th>ID</th><th>Task fields</th><th>Routing</th><th>Delete</th></tr></thead><tbody>
            <?php foreach ($tasks as $task): ?><tr>
                <td><?php echo (int) $task['id']; ?></td>
                <td><form method="post" class="task-edit-form"><input type="hidden" name="csrf" value="<?php echo $esc($csrf); ?>"><input type="hidden" name="action" value="update_task"><input type="hidden" name="task_id" value="<?php echo (int) $task['id']; ?>"><input type="hidden" name="assignee" value="<?php echo $esc($task['assignee']); ?>"><input type="hidden" name="due_date" value="<?php echo $esc($task['due_date'] ?? ''); ?>"><input type="hidden" name="status" value="<?php echo $esc($task['status']); ?>"><input type="hidden" name="priority" value="<?php echo $esc($task['priority']); ?>"><input type="hidden" name="effort" value="<?php echo $esc($task['effort']); ?>"><input name="name" aria-label="Task name" value="<?php echo $esc($task['name']); ?>" required><textarea name="desc" aria-label="Description" rows="2" placeholder="Description"><?php echo $esc($task['desc'] ?? ''); ?></textarea><input name="type" aria-label="Type" value="<?php echo $esc($task['type']); ?>" placeholder="Type"><button type="submit">Save</button></form></td>
                <td><form method="post" class="task-routing-form"><input type="hidden" name="csrf" value="<?php echo $esc($csrf); ?>"><input type="hidden" name="action" value="update_task"><input type="hidden" name="task_id" value="<?php echo (int) $task['id']; ?>"><input type="hidden" name="name" value="<?php echo $esc($task['name']); ?>"><input type="hidden" name="desc" value="<?php echo $esc($task['desc'] ?? ''); ?>"><input type="hidden" name="type" value="<?php echo $esc($task['type']); ?>"><input name="assignee" aria-label="Assignee" value="<?php echo $esc($task['assignee']); ?>" placeholder="Assignee"><input type="date" name="due_date" aria-label="Due date" value="<?php echo $esc($task['due_date'] ?? ''); ?>"><select name="status" aria-label="Status"><?php foreach (['Done','In progress','Not started','On Hold/Waiting for Material'] as $status): ?><option<?php echo ($task['status'] ?? '') === $status ? ' selected' : ''; ?>><?php echo $esc($status); ?></option><?php endforeach; ?></select><select name="priority" aria-label="Priority"><?php foreach (['','Urgent','High','Medium','Low'] as $priority): ?><option value="<?php echo $esc($priority); ?>"<?php echo ($task['priority'] ?? '') === $priority ? ' selected' : ''; ?>><?php echo $esc($priority ?: 'Not set'); ?></option><?php endforeach; ?></select><select name="effort" aria-label="Effort"><?php foreach (['','Small','Medium','Large'] as $effort): ?><option value="<?php echo $esc($effort); ?>"<?php echo ($task['effort'] ?? '') === $effort ? ' selected' : ''; ?>><?php echo $esc($effort ?: 'Not set'); ?></option><?php endforeach; ?></select><button type="submit">Save</button></form></td>
                <td><form method="post" onsubmit="return confirm('Delete this task and its files?');"><input type="hidden" name="csrf" value="<?php echo $esc($csrf); ?>"><input type="hidden" name="action" value="delete_task"><input type="hidden" name="task_id" value="<?php echo (int) $task['id']; ?>"><button class="danger" type="submit">Delete</button></form></td></tr><?php endforeach; ?>
            <?php if (!$tasks): ?><tr><td colspan="4" class="muted">No tasks found.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>

    <section class="panel">
        <h2>Accounts (<?php echo count($users); ?>)</h2>
        <p class="section-note">Edit usernames or reset passwords here. Roles are fixed: only the existing super-admin remains super-admin.</p>
        <div class="scroll"><table><thead><tr><th>ID</th><th>Account</th><th>Role</th><th>Created</th><th>Delete</th></tr></thead><tbody>
            <?php foreach ($users as $account): ?><tr><td><?php echo (int) $account['id']; ?></td><td><form method="post" class="account-edit-form"><input type="hidden" name="csrf" value="<?php echo $esc($csrf); ?>"><input type="hidden" name="action" value="update_user"><input type="hidden" name="user_id" value="<?php echo (int) $account['id']; ?>"><input name="username" aria-label="Username" value="<?php echo $esc($account['username']); ?>" required><input type="password" name="password" aria-label="New password" placeholder="New password (optional)"><button type="submit">Save</button></form></td><td><?php echo $esc(ucwords(str_replace('_', ' ', $account['role'] ?? 'admin'))); ?></td><td><?php echo $esc($account['created_at']); ?></td><td><?php if ((int) $account['id'] === (int) current_user()['id']): ?><span class="muted">Current account</span><?php else: ?><form method="post" onsubmit="return confirm('Delete this account?');"><input type="hidden" name="csrf" value="<?php echo $esc($csrf); ?>"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?php echo (int) $account['id']; ?>"><button class="danger" type="submit">Delete</button></form><?php endif; ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </section>
</main>
</body>
</html>
