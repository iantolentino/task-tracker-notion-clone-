<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/nav.php';
require __DIR__ . '/comments-view.php';

$assignees = ['Akio', 'Ivan', 'Akio/Ivan'];
$categories = ['Graphics', 'Marketing', 'Sales Request', 'Project', 'Other'];
$priorities = ['', 'Low', 'Normal', 'High', 'Urgent'];
$efforts = ['', 'Small', 'Medium', 'Large'];
$statuses = ['Open', 'In progress', 'On hold', 'Waiting for material', 'Waiting for budget', 'Waiting for Approval', 'For Revision', 'Resolved', 'Closed', 'New', 'On Hold/Waiting for Material'];
$rows = [];
$error = '';
$message = '';
$editId = (int) ($_GET['edit'] ?? 0);
$exportStart = is_scalar($_GET['start_date'] ?? null) ? trim((string) $_GET['start_date']) : '';
$exportEnd = is_scalar($_GET['end_date'] ?? null) ? trim((string) $_GET['end_date']) : '';

if (!isset($_SESSION['tickets_csrf'])) {
    $_SESSION['tickets_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['tickets_csrf'];

try {
    $pdo = pdo_connect();
    create_tickets_table($pdo);
    create_uploads_table($pdo);
    create_ticket_comments_table($pdo);

    if (($_GET['export'] ?? '') === 'csv') {
        $start = parse_due_date($exportStart);
        $end = parse_due_date($exportEnd);
        if (!$start || !$end) throw new RuntimeException('Choose both a valid start date and end date for the export.');
        if ($start > $end) throw new RuntimeException('The start date must be on or before the end date.');

        $stmt = $pdo->prepare('SELECT id, requester_name, requester_email, department, assignee, due_date, due_time, other_type, category, priority, effort, status, estimated_cost, subject, description, business_impact, created_at, updated_at, completed_at FROM tickets WHERE created_at >= ? AND created_at <= ? ORDER BY created_at DESC, id DESC');
        $stmt->execute([$start . ' 00:00:00', $end . ' 23:59:59']);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="tickets-' . $start . '-to-' . $end . '.csv"');
        $csvValue = static function ($value): string {
            $value = (string) $value;
            $trimmed = ltrim($value);
            return $trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@'], true) ? "'" . $value : $value;
        };
        $output = fopen('php://output', 'wb');
        fputcsv($output, ['Ticket no.', 'Task name', 'Requester name', 'Requester email', 'Department', 'Assignee', 'Deadline', 'Deadline time', 'Type / category', 'Other type', 'Priority', 'Effort', 'Status', 'Estimated cost', 'Description', 'Business impact', 'Created at', 'Updated at', 'Completed at']);
        while ($row = $stmt->fetch()) {
            fputcsv($output, array_map($csvValue, [ticket_reference((int) $row['id']), $row['subject'], $row['requester_name'], $row['requester_email'], $row['department'], $row['assignee'], $row['due_date'], $row['due_time'], $row['category'], $row['other_type'], $row['priority'], $row['effort'], $row['status'], $row['estimated_cost'], $row['description'], $row['business_impact'], $row['created_at'], $row['updated_at'], $row['completed_at']]));
        }
        fclose($output);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Your session expired. Refresh and try again.');
        }
        if (($_POST['action'] ?? '') === 'update_ticket') {
            $ticketId = (int) ($_POST['ticket_id'] ?? 0);
            $editId = $ticketId;
            $stmt = $pdo->prepare('SELECT * FROM tickets WHERE id = ? LIMIT 1');
            $stmt->execute([$ticketId]);
            $existing = $stmt->fetch();
            if (!$existing) {
                throw new RuntimeException('Ticket not found.');
            }
            $subject = trim((string) ($_POST['subject'] ?? ''));
            $assignee = (string) ($_POST['assignee'] ?? '');
            $dueRaw = trim((string) ($_POST['due_date'] ?? ''));
            $dueDate = parse_due_date($dueRaw);
            $dueTime = normalize_due_time((string) ($_POST['due_time'] ?? ($existing['due_time'] ?? '')));
            $estimatedCost = trim((string) ($_POST['estimated_cost'] ?? ''));
            $rawCategories = $_POST['category'] ?? [];
            $rawCategories = is_array($rawCategories) ? $rawCategories : [$rawCategories];
            $categoryValues = array_values(array_unique(array_intersect($categories, array_map('strval', $rawCategories))));
            $category = implode(', ', $categoryValues);
            // Preserve a legacy category when an older ticket is edited without choosing a new one.
            if ($category === '') $category = trim((string) ($existing['category'] ?? '')) ?: 'Other';
            $ticketType = $categoryValues ? (in_array('Project', $categoryValues, true) ? 'Project' : (in_array('Other', $categoryValues, true) ? 'Other' : $categoryValues[0])) : (string) ($existing['ticket_type'] ?? 'Other');
            $priority = (string) ($_POST['priority'] ?? '');
            $effort = (string) ($_POST['effort'] ?? '');
            $status = (string) ($_POST['status'] ?? 'Open');
            $description = trim((string) ($_POST['description'] ?? ''));
            $businessImpact = trim((string) ($_POST['business_impact'] ?? ''));
            $otherType = trim((string) ($_POST['other_type'] ?? ''));

            if ($subject === '' || strlen($subject) > 180) throw new RuntimeException('Enter a subject up to 180 characters.');
            if (!in_array($assignee, $assignees, true)) throw new RuntimeException('Choose a valid assignee.');
            if ($dueRaw !== '' && !$dueDate) throw new RuntimeException('Choose a valid due date or leave it blank.');
            if ($dueTime === null) throw new RuntimeException('Choose a valid deadline time or leave it blank.');
            if ($dueTime !== '' && !$dueDate) throw new RuntimeException('Add a deadline date to go with the time.');
            if (!$categoryValues && trim((string) ($existing['category'] ?? '')) === '') throw new RuntimeException('Choose at least one category.');
            if (!in_array($priority, $priorities, true)) throw new RuntimeException('Choose a valid priority.');
            if (!in_array($effort, $efforts, true)) throw new RuntimeException('Choose a valid effort.');
            if (!in_array($status, $statuses, true)) throw new RuntimeException('Choose a valid status.');
            if ($estimatedCost !== '' && !preg_match('/^\d+(?:\.\d{1,2})?$/', $estimatedCost)) throw new RuntimeException('Estimated cost must be a valid amount.');
            if ($estimatedCost !== '' && (float) $estimatedCost < 0) throw new RuntimeException('Estimated cost cannot be negative.');
            if ($description === '') throw new RuntimeException('Description is required.');
            if (($ticketType === 'Other' || in_array('Other', $categoryValues, true)) && $otherType === '') throw new RuntimeException('Describe the other type of request.');

            $storedDueDate = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? ($dueDate ?? '') : $dueDate;
            $storedCost = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? $estimatedCost : ($estimatedCost === '' ? null : $estimatedCost);
            $stmt = $pdo->prepare('UPDATE tickets SET subject = ?, assignee = ?, due_date = ?, due_time = ?, ticket_type = ?, estimated_cost = ?, category = ?, priority = ?, effort = ?, description = ?, business_impact = ?, other_type = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->execute([$subject, $assignee, $storedDueDate, $dueTime, $ticketType, $storedCost, $category, $priority, $effort, $description, $businessImpact, $otherType, $status, $ticketId]);
            sync_completed_at($pdo, 'tickets', $ticketId);
            $reference = ticket_reference($ticketId);
            header('Location: tickets.php?updated=' . rawurlencode($reference) . '#' . $reference);
            exit;
        }
        if (($_POST['action'] ?? '') === 'add_comment') {
            $ticketId = (int) ($_POST['ticket_id'] ?? 0);
            $stmt = $pdo->prepare('SELECT id FROM tickets WHERE id = ? LIMIT 1');
            $stmt->execute([$ticketId]);
            if (!$stmt->fetch()) {
                throw new RuntimeException('Ticket not found.');
            }
            add_ticket_comment($pdo, $ticketId, 'admin', (string) (current_user()['username'] ?? 'Team'), (string) ($_POST['body'] ?? ''));
            $reference = ticket_reference($ticketId);
            header('Location: tickets.php?commented=' . rawurlencode($reference) . '#' . $reference);
            exit;
        }
    }

    $rows = attach_comments_to_tickets($pdo, attach_uploads_to_tickets($pdo, $pdo->query('SELECT * FROM tickets ORDER BY id DESC')->fetchAll()));
    if (isset($_GET['updated']) && preg_match('/^TCK-\d{6}$/', $_GET['updated'])) {
        $message = 'Ticket ' . $_GET['updated'] . ' updated.';
    }
    if (isset($_GET['commented']) && preg_match('/^TCK-\d{6}$/', $_GET['commented'])) {
        $message = 'Comment added to ' . $_GET['commented'] . '.';
    }
} catch (Throwable $e) {
    $error = $e->getMessage() ?: 'Tickets could not be loaded right now.';
    if (!$rows && isset($pdo) && $pdo instanceof PDO) {
        try {
            $rows = attach_comments_to_tickets($pdo, attach_uploads_to_tickets($pdo, $pdo->query('SELECT * FROM tickets ORDER BY id DESC')->fetchAll()));
        } catch (Throwable $ignored) {
            // Keep the original error visible if the fallback read also fails.
        }
    }
}

$esc = fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$niceDate = function ($value): string {
    $time = strtotime((string) $value);
    return $time ? date('M j, Y', $time) : '';
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tickets - Multimedia and Marketing Ticketing System</title>
    <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
    <style>
        body { margin: 0; background: #f8fafc; color: #0f172a; font-family: Arial, Helvetica, sans-serif; }
        <?php echo nav_css(); ?>
        main { max-width: 1180px; margin: 0 auto; padding: 32px 18px 60px; }
        .heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 18px; margin-bottom: 22px; }
        h1 { margin: 0 0 6px; font-size: 26px; letter-spacing: -.03em; }
        .subtitle { margin: 0; color: #64748b; font-size: 13px; }
        .public-link, .edit-link, .save-button, .cancel-link { display: inline-flex; align-items: center; min-height: 38px; padding: 0 13px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 700; }
        .public-link, .save-button { background: #2563eb; color: #fff; border: 0; cursor: pointer; }
        .public-link:hover, .save-button:hover { background: #1d4ed8; }
        .edit-link, .cancel-link { border: 1px solid #cbd5e1; color: #475569; background: #fff; }
        .heading-actions { display: flex; align-items: flex-end; flex-wrap: wrap; justify-content: flex-end; gap: 10px; }
        .export-form { display: flex; align-items: flex-end; flex-wrap: wrap; gap: 8px; padding: 8px; border: 1px solid #dbe4ef; border-radius: 10px; background: #fff; }
        .export-form label { display: grid; gap: 4px; color: #475569; font-size: 11px; font-weight: 800; }
        .export-form input { min-height: 38px; padding: 0 9px; border: 1px solid #cbd5e1; border-radius: 7px; color: #0f172a; font: inherit; font-size: 12px; }
        .export-button { min-height: 38px; padding: 0 12px; border: 0; border-radius: 7px; background: #0f766e; color: #fff; cursor: pointer; font: inherit; font-size: 12px; font-weight: 800; }
        .export-button:hover { background: #0d625b; }
        .alert { margin-bottom: 18px; padding: 12px 14px; border-radius: 8px; font-size: 13px; }
        .alert.error { border: 1px solid #f0b7b7; background: #fff2f2; color: #9f1d1f; }
        .alert.ok { border: 1px solid #a7f3d0; background: #ecfdf5; color: #047857; }
        .ticket-list { display: grid; gap: 14px; }
        .ticket { padding: 20px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 12px rgba(15, 23, 42, .04); }
        .ticket-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .reference { margin: 0 0 5px; color: #2563eb; font-size: 11px; font-weight: 800; letter-spacing: .12em; }
        h2 { margin: 0; font-size: 18px; }
        .status { flex: 0 0 auto; padding: 6px 10px; border-radius: 999px; background: #eff6ff; color: #1d4ed8; font-size: 12px; font-weight: 800; }
        .meta { display: flex; flex-wrap: wrap; gap: 8px 18px; margin: 13px 0; color: #64748b; font-size: 12px; }
        .meta strong { color: #334155; }
        .description { margin: 0; padding-top: 14px; border-top: 1px solid #e2e8f0; color: #334155; font-size: 14px; line-height: 1.55; white-space: pre-wrap; }
        .attachments { margin-top: 12px; color: #64748b; font-size: 12px; }
        .attachments a { color: #2563eb; margin-right: 12px; }
        .ticket-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 16px; }
        .lock-note { align-self: center; color: #94a3b8; font-size: 12px; }
        .edit-form { margin-top: 18px; padding-top: 18px; border-top: 1px solid #e2e8f0; }
        .edit-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .edit-field.full { grid-column: 1 / -1; }
        .edit-field label { display: block; margin-bottom: 5px; color: #334155; font-size: 12px; font-weight: 800; }
        .edit-field input, .edit-field select, .edit-field textarea { width: 100%; box-sizing: border-box; padding: 9px 10px; border: 1px solid #cbd5e1; border-radius: 7px; font: inherit; font-size: 13px; }
        .edit-field textarea { min-height: 130px; resize: vertical; }
        .edit-footer { display: flex; gap: 8px; margin-top: 14px; }
        .empty { padding: 42px 20px; border: 1px dashed #cbd5e1; border-radius: 12px; background: #fff; color: #64748b; text-align: center; }
        @media (max-width: 650px) { .heading { align-items: flex-start; flex-direction: column; } .heading-actions, .export-form { width: 100%; justify-content: stretch; } .export-form label, .export-form input, .export-button, .public-link { flex: 1 1 100%; width: 100%; } .public-link { justify-content: center; } .ticket-head { flex-direction: column; } .edit-grid { grid-template-columns: 1fr; } .edit-field.full { grid-column: auto; } }
    </style>
    <link rel="stylesheet" href="assets/design-system.css?v=20261105g">
    <script src="assets/theme.js?v=20261105e"></script>
</head>
<body class="tickets-page">
<?php render_nav('tickets'); ?>
<main id="main-content">
    <?php
    $needsTriage = count(array_filter($rows, fn($r) => trim((string) ($r['priority'] ?? '')) === ''));
    $statusGroup = function (string $status): string {
        $status = strtolower($status);
        if (in_array($status, ['resolved', 'closed'], true)) return 'done';
        if (in_array($status, ['open', 'new'], true)) return 'open';
        return 'active';
    };
    $priorityClass = ['Urgent' => 'bhi', 'High' => 'bhi', 'Normal' => 'be', 'Low' => 'blo'];
    $groupCounts = ['open' => 0, 'active' => 0, 'done' => 0];
    foreach ($rows as $r) $groupCounts[$statusGroup((string) $r['status'])]++;
    ?>
    <div class="heading">
        <div><h1>Tickets</h1><p class="subtitle">Requests submitted through the staff portal.</p></div>
        <div class="inbox-actions">
            <details class="export-menu"<?php echo ($exportStart !== '' || $exportEnd !== '') ? ' open' : ''; ?>>
                <summary class="edit-link">Export CSV</summary>
                <form class="export-form" method="get">
                    <label for="export-start">From<input id="export-start" name="start_date" type="date" value="<?php echo $esc($exportStart); ?>" required></label>
                    <label for="export-end">To<input id="export-end" name="end_date" type="date" value="<?php echo $esc($exportEnd); ?>" required></label>
                    <button class="export-button" type="submit" name="export" value="csv">Download</button>
                </form>
            </details>
            <a class="public-link" href="ticket.php" target="_blank" rel="noopener">Staff portal ↗</a>
        </div>
    </div>
    <?php if ($error): ?><div class="alert error" role="alert"><?php echo $esc($error); ?></div><?php endif; ?>
    <?php if ($message): ?><div class="alert ok" role="status"><?php echo $esc($message); ?></div><?php endif; ?>

    <div class="tk-tabs" role="tablist" aria-label="Filter tickets">
        <button type="button" role="tab" data-filter="all" aria-selected="true">All <span><?php echo count($rows); ?></span></button>
        <button type="button" role="tab" data-filter="triage" aria-selected="false">Needs triage <span><?php echo $needsTriage; ?></span></button>
        <button type="button" role="tab" data-filter="open" aria-selected="false">Open <span><?php echo $groupCounts['open']; ?></span></button>
        <button type="button" role="tab" data-filter="active" aria-selected="false">In progress <span><?php echo $groupCounts['active']; ?></span></button>
        <button type="button" role="tab" data-filter="done" aria-selected="false">Done <span><?php echo $groupCounts['done']; ?></span></button>
    </div>

    <section class="tk-table" aria-live="polite">
        <div class="tk-head" aria-hidden="true"><span>Request</span><span>Type</span><span>Due</span><span>Priority</span><span>Status</span><span></span></div>
        <?php if (!$rows && !$error): ?><div class="tk-empty">No tickets yet. New requests from the staff portal will appear here.</div><?php endif; ?>
        <div class="tk-empty" id="tkFilterEmpty" hidden>No tickets in this view.</div>
        <?php foreach ($rows as $row):
            $id = (int) $row['id'];
            $reference = ticket_reference($id);
            $readyForTasks = trim((string) ($row['priority'] ?? '')) !== '';
            $isEditing = $editId === $id;
            ob_start();
        ?>
                <div class="tk-cell tk-request">
                    <span class="tk-subject"><?php echo $esc($row['subject']); ?></span>
                    <span class="tk-sub">Ticket no. <span class="tk-ref"><?php echo $esc($reference); ?></span> · <span title="<?php echo $esc($row['requester_email']); ?>"><?php echo $esc($row['requester_name']); ?></span><?php if (($row['department'] ?? '') !== ''): ?> · <?php echo $esc($row['department']); ?><?php endif; ?> · <?php echo $esc($niceDate($row['created_at'])); ?><?php if (!empty($row['comments'])): ?> · <span class="tk-comments" title="Comments"><?php echo count($row['comments']); ?> comment<?php echo count($row['comments']) === 1 ? '' : 's'; ?></span><?php endif; ?></span>
                </div>
                <div class="tk-cell tk-type" data-label="Type"><?php echo $esc($row['category']); ?><?php if ((string) ($row['other_type'] ?? '') !== ''): ?> · <?php echo $esc($row['other_type']); ?><?php endif; ?></div>
                <div class="tk-cell" data-label="Due"><?php if (in_array($row['status'], done_statuses(), true) && !empty($row['completed_at'])): ?><span class="tk-done-date">Done <?php echo $esc($niceDate($row['completed_at'])); ?></span><?php elseif ($row['due_date']): ?><?php echo $esc(format_deadline($row['due_date'], $row['due_time'] ?? '')); ?><?php else: ?><span class="tk-dash">—</span><?php endif; ?></div>
                <div class="tk-cell" data-label="Priority"><?php if ($readyForTasks): ?><span class="badge <?php echo $priorityClass[$row['priority']] ?? 'be'; ?>"><?php echo $esc($row['priority']); ?></span><?php else: ?><span class="triage-badge">Needs triage</span><?php endif; ?></div>
                <div class="tk-cell" data-label="Status"><?php if ($isEditing): ?><span class="status" data-status="<?php echo $esc(strtolower((string) $row['status'])); ?>"><?php echo $esc($row['status']); ?></span><?php else: ?>
                    <form method="post" class="status-quick" onclick="event.stopPropagation()">
                        <input type="hidden" name="csrf" value="<?php echo $esc($csrf); ?>"><input type="hidden" name="action" value="update_ticket"><input type="hidden" name="ticket_id" value="<?php echo $id; ?>">
                        <input type="hidden" name="subject" value="<?php echo $esc($row['subject']); ?>">
                        <input type="hidden" name="assignee" value="<?php echo $esc($row['assignee']); ?>">
                        <input type="hidden" name="due_date" value="<?php echo $esc($row['due_date']); ?>">
                        <input type="hidden" name="due_time" value="<?php echo $esc($row['due_time'] ?? ''); ?>">
                        <input type="hidden" name="estimated_cost" value="<?php echo $esc($row['estimated_cost'] ?? ''); ?>">
                        <?php foreach (array_intersect($categories, array_map('trim', explode(',', (string) ($row['category'] ?? '')))) as $category): ?><input type="hidden" name="category[]" value="<?php echo $esc($category); ?>"><?php endforeach; ?>
                        <input type="hidden" name="other_type" value="<?php echo $esc($row['other_type'] ?? ''); ?>">
                        <input type="hidden" name="priority" value="<?php echo $esc($row['priority'] ?? ''); ?>">
                        <input type="hidden" name="effort" value="<?php echo $esc($row['effort'] ?? ''); ?>">
                        <input type="hidden" name="description" value="<?php echo $esc($row['description']); ?>">
                        <input type="hidden" name="business_impact" value="<?php echo $esc($row['business_impact'] ?? ''); ?>">
                        <select name="status" class="status-select" data-status="<?php echo $esc(strtolower((string) $row['status'])); ?>" aria-label="Status for <?php echo $esc($reference); ?>" onchange="this.form.submit()"><?php foreach ($statuses as $status): ?><option value="<?php echo $esc($status); ?>"<?php echo $row['status'] === $status ? ' selected' : ''; ?>><?php echo $esc($status); ?></option><?php endforeach; ?></select>
                    </form>
                <?php endif; ?></div>
                <div class="tk-cell tk-action"><?php if (!$isEditing): ?><a class="edit-link" href="tickets.php?edit=<?php echo $id; ?>#<?php echo $esc($reference); ?>" onclick="event.stopPropagation()"><?php echo $readyForTasks ? 'Edit' : 'Triage'; ?></a><?php endif; ?></div>
        <?php $rowCells = ob_get_clean(); ?>
            <article class="tk-row<?php echo $isEditing ? ' is-editing' : ''; ?>" id="<?php echo $esc($reference); ?>" data-group="<?php echo $statusGroup((string) $row['status']); ?>" data-triage="<?php echo $readyForTasks ? '0' : '1'; ?>">
                <?php if ($isEditing): ?>
                    <div class="tk-main"><?php echo $rowCells; ?></div>
                    <form method="post" class="edit-form">
                        <input type="hidden" name="csrf" value="<?php echo $esc($csrf); ?>"><input type="hidden" name="action" value="update_ticket"><input type="hidden" name="ticket_id" value="<?php echo $id; ?>">
                        <p class="edit-group-title">Triage</p>
                        <div class="edit-grid edit-grid-4">
                            <div class="edit-field"><label for="priority-<?php echo $id; ?>">Priority</label><select id="priority-<?php echo $id; ?>" name="priority"><?php foreach ($priorities as $priority): ?><option value="<?php echo $esc($priority); ?>"<?php echo ($row['priority'] ?? '') === $priority ? ' selected' : ''; ?>><?php echo $esc($priority ?: 'Not set'); ?></option><?php endforeach; ?></select></div>
                            <input type="hidden" name="effort" value="<?php echo $esc($row['effort'] ?? ''); ?>">
                            <div class="edit-field"><label for="status-<?php echo $id; ?>">Status</label><select id="status-<?php echo $id; ?>" name="status" required><?php foreach ($statuses as $status): ?><option value="<?php echo $esc($status); ?>"<?php echo $row['status'] === $status ? ' selected' : ''; ?>><?php echo $esc($status); ?></option><?php endforeach; ?></select></div>
                            <div class="edit-field"><label for="assignee-<?php echo $id; ?>">Assignee</label><select id="assignee-<?php echo $id; ?>" name="assignee" required><?php foreach ($assignees as $assignee): ?><option value="<?php echo $esc($assignee); ?>"<?php echo $row['assignee'] === $assignee ? ' selected' : ''; ?>><?php echo $esc($assignee); ?></option><?php endforeach; ?></select></div>
                        </div>
                        <p class="edit-group-title">Request</p>
                        <div class="edit-grid">
                            <div class="edit-field full"><label for="subject-<?php echo $id; ?>">Title</label><input id="subject-<?php echo $id; ?>" name="subject" maxlength="180" value="<?php echo $esc($row['subject']); ?>" required></div>
                            <div class="edit-field full"><span class="edit-label">Type</span><div class="chip-group"><?php $rowCategories = array_map('trim', explode(',', (string) ($row['category'] ?? ''))); foreach ($categories as $category): ?><label><input type="checkbox" name="category[]" value="<?php echo $esc($category); ?>"<?php echo in_array($category, $rowCategories, true) ? ' checked' : ''; ?>><span><?php echo $esc($category); ?></span></label><?php endforeach; ?></div></div>
                            <div class="edit-field"><label for="other-<?php echo $id; ?>">Other type</label><input id="other-<?php echo $id; ?>" name="other_type" value="<?php echo $esc($row['other_type'] ?? ''); ?>" placeholder="Only if Other"></div>
                            <div class="edit-field"><label for="due-<?php echo $id; ?>">Deadline</label><div class="deadline-pair"><input id="due-<?php echo $id; ?>" name="due_date" type="date" value="<?php echo $esc($row['due_date']); ?>"><input name="due_time" type="time" aria-label="Deadline time (optional)" value="<?php echo $esc($row['due_time'] ?? ''); ?>"></div></div>
                            <input type="hidden" name="estimated_cost" value="<?php echo $esc($row['estimated_cost'] ?? ''); ?>">
                            <div class="edit-field full"><label for="description-<?php echo $id; ?>">Description</label><textarea id="description-<?php echo $id; ?>" name="description" required><?php echo $esc($row['description']); ?></textarea></div>
                            <input type="hidden" name="business_impact" value="<?php echo $esc($row['business_impact'] ?? ''); ?>">
                        </div>
                        <div class="edit-footer"><button class="save-button" type="submit">Save changes</button><a class="cancel-link" href="tickets.php#<?php echo $esc($reference); ?>">Cancel</a></div>
                    </form>
                <?php else: ?>
                    <details class="tk-details">
                        <summary class="tk-main"><?php echo $rowCells; ?></summary>
                        <div class="tk-body">
                            <div class="tk-body-main">
                                <h4>Description</h4>
                                <p><?php echo $esc($row['description']); ?></p>
                                <h4 class="comments-title">Comments</h4>
                                <?php render_ticket_comments($row['comments'] ?? []); ?>
                                <form method="post" class="comment-form">
                                    <input type="hidden" name="csrf" value="<?php echo $esc($csrf); ?>"><input type="hidden" name="action" value="add_comment"><input type="hidden" name="ticket_id" value="<?php echo $id; ?>">
                                    <label class="sr-only" for="comment-<?php echo $id; ?>">Add a comment</label>
                                    <textarea id="comment-<?php echo $id; ?>" name="body" rows="2" maxlength="2000" placeholder="Write a comment the requester can see…" required></textarea>
                                    <button class="save-button" type="submit">Comment</button>
                                </form>
                            </div>
                            <dl class="tk-body-side">
                                <div><dt>Requester</dt><dd><?php echo $esc($row['requester_email']); ?></dd></div>
                                <?php if (($row['department'] ?? '') !== ''): ?><div><dt>Department</dt><dd><?php echo $esc($row['department']); ?></dd></div><?php endif; ?>
                                <?php if ($row['due_date']): ?><div><dt>Deadline</dt><dd><?php echo $esc(format_deadline($row['due_date'], $row['due_time'] ?? '')); ?></dd></div><?php endif; ?>
                                <?php if (!empty($row['completed_at'])): ?><div><dt>Completed</dt><dd><?php echo $esc($niceDate($row['completed_at'])); ?></dd></div><?php endif; ?>
                                <div><dt>Assignee</dt><dd><?php echo $esc($row['assignee']); ?></dd></div>
                                <?php if (!empty($row['uploads'])): ?><div><dt>Files</dt><dd class="tk-files"><?php foreach ($row['uploads'] as $file): ?><a href="<?php echo $esc($file['url']); ?>"><?php echo $esc($file['name']); ?></a><?php endforeach; ?></dd></div><?php endif; ?>
                            </dl>
                        </div>
                    </details>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
</main>
<script>
(() => {
    const tabs = Array.from(document.querySelectorAll('.tk-tabs [data-filter]'));
    const rows = Array.from(document.querySelectorAll('.tk-row'));
    const empty = document.getElementById('tkFilterEmpty');
    function applyFilter(filter) {
        let shown = 0;
        rows.forEach(row => {
            const match = filter === 'all' || (filter === 'triage' ? row.dataset.triage === '1' : row.dataset.group === filter);
            row.hidden = !match && !row.classList.contains('is-editing');
            if (!row.hidden) shown++;
        });
        tabs.forEach(tab => tab.setAttribute('aria-selected', tab.dataset.filter === filter ? 'true' : 'false'));
        empty.hidden = shown > 0 || rows.length === 0;
        try { sessionStorage.setItem('ticketFilter', filter); } catch (err) {}
    }
    tabs.forEach(tab => tab.addEventListener('click', () => applyFilter(tab.dataset.filter)));
    let saved = 'all';
    try { saved = sessionStorage.getItem('ticketFilter') || 'all'; } catch (err) {}
    applyFilter(tabs.some(tab => tab.dataset.filter === saved) ? saved : 'all');
    if (new URLSearchParams(location.search).has('commented') && location.hash) {
        document.getElementById(location.hash.slice(1))?.querySelector('details')?.setAttribute('open', '');
    }
})();
setInterval(() => {
    const exportInUse = Array.from(document.querySelectorAll('.export-form input')).some(input => input.value !== '');
    const detailsOpen = document.querySelector('.tk-details[open]');
    const pickingStatus = document.activeElement && document.activeElement.matches('.status-select');
    if (!document.hidden && !document.querySelector('.edit-form') && !exportInUse && !detailsOpen && !pickingStatus) window.location.reload();
}, 10000);
</script>
</body>
</html>
