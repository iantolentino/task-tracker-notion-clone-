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
$niceDate = function ($value): string {
    $time = strtotime((string) $value);
    return $time ? date('M j, Y', $time) : '';
};
$priorityClass = ['Urgent' => 'bhi', 'High' => 'bhi', 'Medium' => 'bme', 'Normal' => 'be', 'Low' => 'blo'];
$currentUserId = (int) (current_user()['id'] ?? 0);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Database - Multimedia and Marketing Ticketing System</title>
    <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
    <style>
        body { margin: 0; }
        <?php echo nav_css(); ?>
    </style>
    <link rel="stylesheet" href="assets/design-system.css?v=20261105e">
    <script src="assets/theme.js?v=20261105e"></script>
</head>
<body class="db-page">
<?php render_nav('db'); ?>
<main id="main-content">
    <div class="heading">
        <div><h1>Database</h1><p class="subtitle">View, edit or remove any ticket, task or account.</p></div>
    </div>
    <?php if ($message): ?><div class="alert ok" role="status"><?php echo $esc($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error" role="alert"><?php echo $esc($error); ?></div><?php endif; ?>

    <div class="db-toolbar">
        <div class="tk-tabs" role="tablist" aria-label="Database tables">
            <button type="button" role="tab" data-tab="tickets" aria-selected="true">Tickets <span><?php echo count($tickets); ?></span></button>
            <button type="button" role="tab" data-tab="tasks" aria-selected="false">Tasks <span><?php echo count($tasks); ?></span></button>
            <button type="button" role="tab" data-tab="accounts" aria-selected="false">Accounts <span><?php echo count($users); ?></span></button>
        </div>
        <input type="search" id="dbSearch" class="db-search" placeholder="Search this table…" aria-label="Search this table">
    </div>

    <!-- Tickets -->
    <section class="tk-table db-panel" data-panel="tickets" style="--db-cols: minmax(240px, 2.4fr) 110px 130px 110px 110px 70px;">
        <div class="tk-head db-head" aria-hidden="true"><span>Ticket</span><span>Assignee</span><span>Status</span><span>Priority</span><span>Updated</span><span></span></div>
        <?php foreach ($tickets as $ticket):
            $tid = (int) $ticket['id'];
            $ref = ticket_reference($tid);
        ?>
            <details class="db-row" id="db-<?php echo $esc($ref); ?>">
                <summary class="tk-main db-main">
                    <div class="tk-cell tk-request">
                        <span class="tk-subject"><?php echo $esc($ticket['subject']); ?></span>
                        <span class="tk-sub">Ticket no. <span class="tk-ref"><?php echo $esc($ref); ?></span> · <?php echo $esc($ticket['requester_name']); ?> · <?php echo $esc($ticket['requester_email']); ?></span>
                    </div>
                    <div class="tk-cell" data-label="Assignee"><?php echo $esc($ticket['assignee']); ?></div>
                    <div class="tk-cell" data-label="Status"><span class="status" data-status="<?php echo $esc(strtolower((string) $ticket['status'])); ?>"><?php echo $esc($ticket['status']); ?></span></div>
                    <div class="tk-cell" data-label="Priority"><?php if (($ticket['priority'] ?? '') !== ''): ?><span class="badge <?php echo $priorityClass[$ticket['priority']] ?? 'be'; ?>"><?php echo $esc($ticket['priority']); ?></span><?php else: ?><span class="tk-dash">—</span><?php endif; ?></div>
                    <div class="tk-cell db-muted" data-label="Updated"><?php echo $esc($niceDate($ticket['updated_at'] ?? $ticket['created_at'])); ?></div>
                    <div class="tk-cell tk-action"><span class="edit-link db-edit-toggle">Edit</span></div>
                </summary>
                <div class="db-edit">
                    <div class="db-edit-grid">
                        <form method="post" action="db-view.php#tickets" class="db-form">
                            <input type="hidden" name="csrf" value="<?php echo $esc($csrf); ?>"><input type="hidden" name="action" value="update_ticket"><input type="hidden" name="ticket_id" value="<?php echo $tid; ?>">
                            <div class="edit-grid edit-grid-4">
                                <div class="edit-field"><label for="t-status-<?php echo $tid; ?>">Status</label><select id="t-status-<?php echo $tid; ?>" name="status"><?php foreach ($statuses as $status): ?><option value="<?php echo $esc($status); ?>"<?php echo ($ticket['status'] ?? '') === $status ? ' selected' : ''; ?>><?php echo $esc($status); ?></option><?php endforeach; ?></select></div>
                                <div class="edit-field"><label for="t-priority-<?php echo $tid; ?>">Priority</label><select id="t-priority-<?php echo $tid; ?>" name="priority"><?php foreach ($priorities as $priority): ?><option value="<?php echo $esc($priority); ?>"<?php echo ($ticket['priority'] ?? '') === $priority ? ' selected' : ''; ?>><?php echo $esc($priority ?: 'Not set'); ?></option><?php endforeach; ?></select></div>
                                <div class="edit-field"><label for="t-effort-<?php echo $tid; ?>">Effort</label><select id="t-effort-<?php echo $tid; ?>" name="effort"><?php foreach ($efforts as $effort): ?><option value="<?php echo $esc($effort); ?>"<?php echo ($ticket['effort'] ?? '') === $effort ? ' selected' : ''; ?>><?php echo $esc($effort ?: 'Not set'); ?></option><?php endforeach; ?></select></div>
                                <div class="edit-field"><label for="t-assignee-<?php echo $tid; ?>">Assignee</label><select id="t-assignee-<?php echo $tid; ?>" name="assignee"><?php foreach ($assignees as $assignee): ?><option value="<?php echo $esc($assignee); ?>"<?php echo ($ticket['assignee'] ?? '') === $assignee ? ' selected' : ''; ?>><?php echo $esc($assignee); ?></option><?php endforeach; ?></select></div>
                                <div class="edit-field"><label for="t-due-<?php echo $tid; ?>">Deadline</label><input id="t-due-<?php echo $tid; ?>" type="date" name="due_date" value="<?php echo $esc($ticket['due_date'] ?? ''); ?>"></div>
                                <input type="hidden" name="estimated_cost" value="<?php echo $esc($ticket['estimated_cost'] ?? ''); ?>">
                            </div>
                            <div class="edit-field" style="margin-top:12px;"><span class="edit-label">Type</span><div class="chip-group"><?php $rowCategories = array_map('trim', explode(',', (string) ($ticket['category'] ?? ''))); foreach ($categories as $category): ?><label><input type="checkbox" name="category[]" value="<?php echo $esc($category); ?>"<?php echo in_array($category, $rowCategories, true) ? ' checked' : ''; ?>><span><?php echo $esc($category); ?></span></label><?php endforeach; ?></div></div>
                            <div class="db-form-actions"><button class="save-button" type="submit">Save ticket</button></div>
                        </form>
                        <aside class="tk-body-side db-side">
                            <div><dt>Description</dt><dd class="db-desc"><?php echo $esc($ticket['description']); ?></dd></div>
                            <div><dt>Submitted</dt><dd><?php echo $esc($niceDate($ticket['created_at'])); ?></dd></div>
                            <?php if (!empty($ticket['uploads'])): ?><div><dt>Files</dt><dd class="tk-files"><?php foreach ($ticket['uploads'] as $file): ?><a href="<?php echo $esc($file['url']); ?>"><?php echo $esc($file['name']); ?></a><?php endforeach; ?></dd></div><?php endif; ?>
                            <form method="post" action="db-view.php#tickets" class="db-delete" onsubmit="return confirm('Delete ticket <?php echo $esc($ref); ?> and its files? This cannot be undone.');"><input type="hidden" name="csrf" value="<?php echo $esc($csrf); ?>"><input type="hidden" name="action" value="delete_ticket"><input type="hidden" name="ticket_id" value="<?php echo $tid; ?>"><button class="btn-danger" type="submit">Delete ticket</button></form>
                        </aside>
                    </div>
                </div>
            </details>
        <?php endforeach; ?>
        <?php if (!$tickets): ?><div class="tk-empty">No tickets yet.</div><?php endif; ?>
        <div class="tk-empty db-no-match" hidden>Nothing matches your search.</div>
    </section>

    <!-- Tasks -->
    <section class="tk-table db-panel" data-panel="tasks" hidden style="--db-cols: minmax(240px, 2.4fr) 140px 110px 130px 100px 70px;">
        <div class="tk-head db-head" aria-hidden="true"><span>Task</span><span>Assignee</span><span>Due</span><span>Status</span><span>Priority</span><span></span></div>
        <?php foreach ($tasks as $task):
            $kid = (int) $task['id'];
        ?>
            <details class="db-row" id="db-task-<?php echo $kid; ?>">
                <summary class="tk-main db-main">
                    <div class="tk-cell tk-request">
                        <span class="tk-subject"><?php echo $esc($task['name']); ?></span>
                        <span class="tk-sub"><span class="tk-ref">#<?php echo $kid; ?></span><?php if (($task['type'] ?? '') !== ''): ?> · <?php echo $esc($task['type']); ?><?php endif; ?></span>
                    </div>
                    <div class="tk-cell tk-type" data-label="Assignee"><?php echo ($task['assignee'] ?? '') !== '' ? $esc($task['assignee']) : '<span class="tk-dash">—</span>'; ?></div>
                    <div class="tk-cell db-muted" data-label="Due"><?php echo ($task['due_date'] ?? '') ? $esc($niceDate($task['due_date'])) : '<span class="tk-dash">—</span>'; ?></div>
                    <div class="tk-cell" data-label="Status"><span class="status" data-status="<?php echo $esc(strtolower((string) $task['status'])); ?>"><?php echo $esc($task['status'] === 'On Hold/Waiting for Material' ? 'On hold' : $task['status']); ?></span></div>
                    <div class="tk-cell" data-label="Priority"><?php if (($task['priority'] ?? '') !== ''): ?><span class="badge <?php echo $priorityClass[$task['priority']] ?? 'be'; ?>"><?php echo $esc($task['priority']); ?></span><?php else: ?><span class="tk-dash">—</span><?php endif; ?></div>
                    <div class="tk-cell tk-action"><span class="edit-link db-edit-toggle">Edit</span></div>
                </summary>
                <div class="db-edit">
                    <div class="db-edit-grid">
                        <form method="post" action="db-view.php#tasks" class="db-form">
                            <input type="hidden" name="csrf" value="<?php echo $esc($csrf); ?>"><input type="hidden" name="action" value="update_task"><input type="hidden" name="task_id" value="<?php echo $kid; ?>">
                            <div class="edit-grid">
                                <div class="edit-field full"><label for="k-name-<?php echo $kid; ?>">Task name</label><input id="k-name-<?php echo $kid; ?>" name="name" value="<?php echo $esc($task['name']); ?>" required></div>
                            </div>
                            <div class="edit-grid edit-grid-4" style="margin-top:12px;">
                                <div class="edit-field"><label for="k-status-<?php echo $kid; ?>">Status</label><select id="k-status-<?php echo $kid; ?>" name="status"><?php foreach (['Not started', 'In progress', 'On Hold/Waiting for Material', 'Done'] as $status): ?><option value="<?php echo $esc($status); ?>"<?php echo ($task['status'] ?? '') === $status ? ' selected' : ''; ?>><?php echo $esc($status === 'On Hold/Waiting for Material' ? 'On hold' : $status); ?></option><?php endforeach; ?></select></div>
                                <div class="edit-field"><label for="k-priority-<?php echo $kid; ?>">Priority</label><select id="k-priority-<?php echo $kid; ?>" name="priority"><?php foreach (['', 'Urgent', 'High', 'Medium', 'Low'] as $priority): ?><option value="<?php echo $esc($priority); ?>"<?php echo ($task['priority'] ?? '') === $priority ? ' selected' : ''; ?>><?php echo $esc($priority ?: 'Not set'); ?></option><?php endforeach; ?></select></div>
                                <div class="edit-field"><label for="k-effort-<?php echo $kid; ?>">Effort</label><select id="k-effort-<?php echo $kid; ?>" name="effort"><?php foreach (['', 'Small', 'Medium', 'Large'] as $effort): ?><option value="<?php echo $esc($effort); ?>"<?php echo ($task['effort'] ?? '') === $effort ? ' selected' : ''; ?>><?php echo $esc($effort ?: 'Not set'); ?></option><?php endforeach; ?></select></div>
                                <div class="edit-field"><label for="k-due-<?php echo $kid; ?>">Due date</label><input id="k-due-<?php echo $kid; ?>" type="date" name="due_date" value="<?php echo $esc($task['due_date'] ?? ''); ?>"></div>
                                <div class="edit-field"><label for="k-assignee-<?php echo $kid; ?>">Assignee</label><input id="k-assignee-<?php echo $kid; ?>" name="assignee" value="<?php echo $esc($task['assignee']); ?>" placeholder="e.g. Ivan"></div>
                                <div class="edit-field"><label for="k-type-<?php echo $kid; ?>">Type</label><input id="k-type-<?php echo $kid; ?>" name="type" value="<?php echo $esc($task['type']); ?>" placeholder="e.g. Graphics"></div>
                            </div>
                            <div class="edit-field" style="margin-top:12px;"><label for="k-desc-<?php echo $kid; ?>">Description</label><textarea id="k-desc-<?php echo $kid; ?>" name="desc" rows="3" placeholder="Optional"><?php echo $esc($task['desc'] ?? ''); ?></textarea></div>
                            <div class="db-form-actions"><button class="save-button" type="submit">Save task</button></div>
                        </form>
                        <aside class="tk-body-side db-side">
                            <div><dt>Last updated</dt><dd><?php echo $esc($niceDate($task['updated_at'] ?? '')); ?></dd></div>
                            <form method="post" action="db-view.php#tasks" class="db-delete" onsubmit="return confirm('Delete this task and its files? This cannot be undone.');"><input type="hidden" name="csrf" value="<?php echo $esc($csrf); ?>"><input type="hidden" name="action" value="delete_task"><input type="hidden" name="task_id" value="<?php echo $kid; ?>"><button class="btn-danger" type="submit">Delete task</button></form>
                        </aside>
                    </div>
                </div>
            </details>
        <?php endforeach; ?>
        <?php if (!$tasks): ?><div class="tk-empty">No tasks yet.</div><?php endif; ?>
        <div class="tk-empty db-no-match" hidden>Nothing matches your search.</div>
    </section>

    <!-- Accounts -->
    <section class="tk-table db-panel" data-panel="accounts" hidden style="--db-cols: minmax(200px, 2fr) 140px 130px 70px;">
        <div class="tk-head db-head" aria-hidden="true"><span>Account</span><span>Role</span><span>Created</span><span></span></div>
        <?php foreach ($users as $account):
            $uid = (int) $account['id'];
            $isMe = $uid === $currentUserId;
        ?>
            <details class="db-row" id="db-user-<?php echo $uid; ?>">
                <summary class="tk-main db-main">
                    <div class="tk-cell tk-request"><span class="tk-subject"><?php echo $esc($account['username']); ?><?php if ($isMe): ?> <span class="ticket-pill">You</span><?php endif; ?></span></div>
                    <div class="tk-cell" data-label="Role"><?php echo $esc(ucwords(str_replace('_', ' ', $account['role'] ?? 'admin'))); ?></div>
                    <div class="tk-cell db-muted" data-label="Created"><?php echo $esc($niceDate($account['created_at'])); ?></div>
                    <div class="tk-cell tk-action"><span class="edit-link db-edit-toggle">Edit</span></div>
                </summary>
                <div class="db-edit">
                    <div class="db-edit-grid">
                        <form method="post" action="db-view.php#accounts" class="db-form">
                            <input type="hidden" name="csrf" value="<?php echo $esc($csrf); ?>"><input type="hidden" name="action" value="update_user"><input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                            <div class="edit-grid">
                                <div class="edit-field"><label for="u-name-<?php echo $uid; ?>">Username</label><input id="u-name-<?php echo $uid; ?>" name="username" value="<?php echo $esc($account['username']); ?>" required></div>
                                <div class="edit-field"><label for="u-pass-<?php echo $uid; ?>">New password</label><input id="u-pass-<?php echo $uid; ?>" type="password" name="password" autocomplete="new-password" placeholder="Leave blank to keep current"></div>
                            </div>
                            <div class="db-form-actions"><button class="save-button" type="submit">Save account</button></div>
                        </form>
                        <aside class="tk-body-side db-side">
                            <?php if ($isMe): ?>
                                <div><dt>Note</dt><dd>You can't delete the account you're signed in with.</dd></div>
                            <?php else: ?>
                                <form method="post" action="db-view.php#accounts" class="db-delete" onsubmit="return confirm('Delete the account <?php echo $esc($account['username']); ?>?');"><input type="hidden" name="csrf" value="<?php echo $esc($csrf); ?>"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?php echo $uid; ?>"><button class="btn-danger" type="submit">Delete account</button></form>
                            <?php endif; ?>
                        </aside>
                    </div>
                </div>
            </details>
        <?php endforeach; ?>
        <div class="tk-empty db-no-match" hidden>Nothing matches your search.</div>
    </section>
</main>
<script>
(() => {
    const tabs = Array.from(document.querySelectorAll('[data-tab]'));
    const panels = Array.from(document.querySelectorAll('[data-panel]'));
    const search = document.getElementById('dbSearch');
    let active = 'tickets';

    function applySearch() {
        const query = search.value.trim().toLowerCase();
        const panel = panels.find(p => p.dataset.panel === active);
        const rows = Array.from(panel.querySelectorAll('.db-row'));
        let shown = 0;
        rows.forEach(row => {
            const match = !query || row.querySelector('summary').textContent.toLowerCase().includes(query);
            row.hidden = !match;
            if (match) shown++;
        });
        panel.querySelector('.db-no-match').hidden = !query || shown > 0;
    }

    function showTab(name) {
        active = tabs.some(t => t.dataset.tab === name) ? name : 'tickets';
        tabs.forEach(t => t.setAttribute('aria-selected', t.dataset.tab === active ? 'true' : 'false'));
        panels.forEach(p => { p.hidden = p.dataset.panel !== active; });
        search.placeholder = `Search ${active}…`;
        history.replaceState(null, '', '#' + active);
        applySearch();
    }

    tabs.forEach(t => t.addEventListener('click', () => showTab(t.dataset.tab)));
    search.addEventListener('input', applySearch);
    window.addEventListener('hashchange', () => showTab(location.hash.slice(1)));
    showTab((location.hash || '#tickets').slice(1));
})();
</script>
</body>
</html>
