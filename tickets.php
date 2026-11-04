<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/nav.php';

$assignees = ['Aiko', 'Ivan', 'Aiko/Ivan'];
$categories = ['Graphics', 'Marketing', 'Sales Request', 'Project', 'Other'];
$priorities = ['', 'Low', 'Normal', 'High', 'Urgent'];
$efforts = ['', 'Small', 'Medium', 'Large'];
$statuses = ['Open', 'In progress', 'On hold', 'Waiting for material', 'Waiting for budget', 'Resolved', 'Closed', 'New', 'On Hold/Waiting for Material'];
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

    if (($_GET['export'] ?? '') === 'csv') {
        $start = parse_due_date($exportStart);
        $end = parse_due_date($exportEnd);
        if (!$start || !$end) throw new RuntimeException('Choose both a valid start date and end date for the export.');
        if ($start > $end) throw new RuntimeException('The start date must be on or before the end date.');

        $stmt = $pdo->prepare('SELECT id, requester_name, requester_email, assignee, due_date, other_type, category, priority, effort, status, estimated_cost, subject, description, business_impact, created_at, updated_at FROM tickets WHERE created_at >= ? AND created_at <= ? ORDER BY created_at DESC, id DESC');
        $stmt->execute([$start . ' 00:00:00', $end . ' 23:59:59']);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="tickets-' . $start . '-to-' . $end . '.csv"');
        $csvValue = static function ($value): string {
            $value = (string) $value;
            $trimmed = ltrim($value);
            return $trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@'], true) ? "'" . $value : $value;
        };
        $output = fopen('php://output', 'wb');
        fputcsv($output, ['Reference', 'Task name', 'Requester name', 'Requester email', 'Assignee', 'Deadline', 'Type / category', 'Other type', 'Priority', 'Effort', 'Status', 'Estimated cost', 'Description', 'Business impact', 'Created at', 'Updated at']);
        while ($row = $stmt->fetch()) {
            fputcsv($output, array_map($csvValue, [ticket_reference((int) $row['id']), $row['subject'], $row['requester_name'], $row['requester_email'], $row['assignee'], $row['due_date'], $row['category'], $row['other_type'], $row['priority'], $row['effort'], $row['status'], $row['estimated_cost'], $row['description'], $row['business_impact'], $row['created_at'], $row['updated_at']]));
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
            $stmt = $pdo->prepare('UPDATE tickets SET subject = ?, assignee = ?, due_date = ?, ticket_type = ?, estimated_cost = ?, category = ?, priority = ?, effort = ?, description = ?, business_impact = ?, other_type = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->execute([$subject, $assignee, $storedDueDate, $ticketType, $storedCost, $category, $priority, $effort, $description, $businessImpact, $otherType, $status, $ticketId]);
            $reference = ticket_reference($ticketId);
            header('Location: tickets.php?updated=' . rawurlencode($reference) . '#' . $reference);
            exit;
        }
    }

    $rows = attach_uploads_to_tickets($pdo, $pdo->query('SELECT * FROM tickets ORDER BY id DESC')->fetchAll());
    if (isset($_GET['updated']) && preg_match('/^TCK-\d{6}$/', $_GET['updated'])) {
        $message = 'Ticket ' . $_GET['updated'] . ' updated.';
    }
} catch (Throwable $e) {
    $error = $e->getMessage() ?: 'Tickets could not be loaded right now.';
    if (!$rows && isset($pdo) && $pdo instanceof PDO) {
        try {
            $rows = attach_uploads_to_tickets($pdo, $pdo->query('SELECT * FROM tickets ORDER BY id DESC')->fetchAll());
        } catch (Throwable $ignored) {
            // Keep the original error visible if the fallback read also fails.
        }
    }
}

$esc = fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tickets - Creatives Ticketing System</title>
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
    <link rel="stylesheet" href="assets/design-system.css?v=20260903d">
</head>
<body class="tickets-page">
<?php render_nav('tickets'); ?>
<main id="main-content">
    <div class="heading">
        <div><h1>Ticket inbox</h1><p class="subtitle"><?php echo count($rows); ?> submitted request<?php echo count($rows) === 1 ? '' : 's'; ?>, newest first. Set both priority and effort to release a ticket to Tasks.</p></div>
        <div class="heading-actions">
            <form class="export-form" method="get">
                <label for="export-start">Submitted from<input id="export-start" name="start_date" type="date" value="<?php echo $esc($exportStart); ?>" required></label>
                <label for="export-end">Submitted through<input id="export-end" name="end_date" type="date" value="<?php echo $esc($exportEnd); ?>" required></label>
                <button class="export-button" type="submit" name="export" value="csv">Export tickets</button>
            </form>
            <a class="public-link" href="ticket.php" target="_blank" rel="noopener">Open staff portal</a>
        </div>
    </div>
    <?php if ($error): ?><div class="alert error" role="alert"><?php echo $esc($error); ?></div><?php endif; ?>
    <?php if ($message): ?><div class="alert ok" role="status"><?php echo $esc($message); ?></div><?php endif; ?>
    <section class="ticket-list" aria-live="polite">
        <?php if (!$rows && !$error): ?><div class="empty">No tickets have been submitted yet.</div><?php endif; ?>
        <?php foreach ($rows as $row):
            $id = (int) $row['id'];
            $reference = ticket_reference($id);
            $readyForTasks = trim((string) ($row['priority'] ?? '')) !== '' && trim((string) ($row['effort'] ?? '')) !== '';
        ?>
            <article class="ticket" id="<?php echo $esc($reference); ?>">
                <div class="ticket-head">
                    <div><p class="reference"><?php echo $esc($reference); ?></p><h2><?php echo $esc($row['subject']); ?></h2></div>
                    <span class="status"><?php echo $esc($row['status']); ?></span>
                </div>
                <div class="meta"><span><strong>From:</strong> <?php echo $esc($row['requester_name']); ?> (<?php echo $esc($row['requester_email']); ?>)</span><span><strong>Assignee:</strong> <?php echo $esc($row['assignee']); ?></span><span><strong>Deadline:</strong> <?php echo $esc($row['due_date'] ?: 'Not set'); ?></span><span><strong>Type / category:</strong> <?php echo $esc($row['category']); ?></span><?php if ((string) ($row['other_type'] ?? '') !== ''): ?><span><strong>Other type:</strong> <?php echo $esc($row['other_type']); ?></span><?php endif; ?><?php if ((string) ($row['estimated_cost'] ?? '') !== ''): ?><span><strong>Estimated cost:</strong> <?php echo $esc($row['estimated_cost']); ?></span><?php endif; ?><span><strong>Priority:</strong> <?php echo $esc($row['priority'] ?: 'Not set'); ?></span><span><strong>Effort:</strong> <?php echo $esc($row['effort'] ?: 'Not set'); ?></span><span><strong>Task list:</strong> <?php echo $readyForTasks ? 'Ready' : 'Waiting for priority + effort'; ?></span><span><strong>Submitted:</strong> <?php echo $esc($row['created_at']); ?></span></div>
                <?php if ($editId === $id): ?>
                    <form method="post" class="edit-form">
                        <input type="hidden" name="csrf" value="<?php echo $esc($csrf); ?>"><input type="hidden" name="action" value="update_ticket"><input type="hidden" name="ticket_id" value="<?php echo $id; ?>">
                        <div class="edit-grid">
                            <div class="edit-field full"><label for="subject-<?php echo $id; ?>">Subject</label><input id="subject-<?php echo $id; ?>" name="subject" maxlength="180" value="<?php echo $esc($row['subject']); ?>" required></div>
                            <div class="edit-field"><label for="assignee-<?php echo $id; ?>">Assignee</label><select id="assignee-<?php echo $id; ?>" name="assignee" required><?php foreach ($assignees as $assignee): ?><option value="<?php echo $esc($assignee); ?>"<?php echo $row['assignee'] === $assignee ? ' selected' : ''; ?>><?php echo $esc($assignee); ?></option><?php endforeach; ?></select></div>
                            <div class="edit-field"><label for="due-<?php echo $id; ?>">Deadline</label><input id="due-<?php echo $id; ?>" name="due_date" type="date" value="<?php echo $esc($row['due_date']); ?>"></div>
                            <div class="edit-field"><label for="cost-<?php echo $id; ?>">Estimated cost (optional)</label><input id="cost-<?php echo $id; ?>" name="estimated_cost" type="number" min="0" step="0.01" value="<?php echo $esc($row['estimated_cost'] ?? ''); ?>" placeholder="Optional"></div>
                            <div class="edit-field"><label for="category-<?php echo $id; ?>">Type / category</label><select id="category-<?php echo $id; ?>" name="category[]" multiple size="5" required><?php $rowCategories = array_map('trim', explode(',', (string) ($row['category'] ?? ''))); foreach ($categories as $category): ?><option value="<?php echo $esc($category); ?>"<?php echo in_array($category, $rowCategories, true) ? ' selected' : ''; ?>><?php echo $esc($category); ?></option><?php endforeach; ?></select></div>
                            <div class="edit-field"><label for="other-<?php echo $id; ?>">Other type</label><input id="other-<?php echo $id; ?>" name="other_type" value="<?php echo $esc($row['other_type'] ?? ''); ?>"></div>
                            <div class="edit-field"><label for="priority-<?php echo $id; ?>">Priority</label><select id="priority-<?php echo $id; ?>" name="priority"><?php foreach ($priorities as $priority): ?><option value="<?php echo $esc($priority); ?>"<?php echo ($row['priority'] ?? '') === $priority ? ' selected' : ''; ?>><?php echo $esc($priority ?: 'Not set'); ?></option><?php endforeach; ?></select></div>
                            <div class="edit-field"><label for="effort-<?php echo $id; ?>">Effort</label><select id="effort-<?php echo $id; ?>" name="effort"><?php foreach ($efforts as $effort): ?><option value="<?php echo $esc($effort); ?>"<?php echo ($row['effort'] ?? '') === $effort ? ' selected' : ''; ?>><?php echo $esc($effort ?: 'Not set'); ?></option><?php endforeach; ?></select></div>
                            <div class="edit-field"><label for="status-<?php echo $id; ?>">Status</label><select id="status-<?php echo $id; ?>" name="status" required><?php foreach ($statuses as $status): ?><option value="<?php echo $esc($status); ?>"<?php echo $row['status'] === $status ? ' selected' : ''; ?>><?php echo $esc($status); ?></option><?php endforeach; ?></select></div>
                            <div class="edit-field full"><label for="description-<?php echo $id; ?>">Description</label><textarea id="description-<?php echo $id; ?>" name="description" required><?php echo $esc($row['description']); ?></textarea></div>
                            <div class="edit-field full"><label for="impact-<?php echo $id; ?>">Business impact</label><textarea id="impact-<?php echo $id; ?>" name="business_impact"><?php echo $esc($row['business_impact'] ?? ''); ?></textarea></div>
                        </div>
                        <div class="edit-footer"><button class="save-button" type="submit">Save changes</button><a class="cancel-link" href="tickets.php#<?php echo $esc($reference); ?>">Cancel</a></div>
                    </form>
                <?php else: ?>
                    <p class="description"><?php echo nl2br($esc($row['description'])); ?></p>
                    <?php if (!empty($row['uploads'])): ?><div class="attachments"><strong>Files:</strong> <?php foreach ($row['uploads'] as $file): ?><a href="<?php echo $esc($file['url']); ?>"><?php echo $esc($file['name']); ?></a><?php endforeach; ?></div><?php endif; ?>
                    <?php if (trim((string) ($row['business_impact'] ?? '')) !== ''): ?><p class="description"><strong>Business impact:</strong><br><?php echo nl2br($esc($row['business_impact'])); ?></p><?php endif; ?>
                    <div class="ticket-actions"><a class="edit-link" href="tickets.php?edit=<?php echo $id; ?>#<?php echo $esc($reference); ?>">Edit ticket</a></div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
</main>
<script>
setInterval(() => {
    const exportInUse = Array.from(document.querySelectorAll('.export-form input')).some(input => input.value !== '');
    if (!document.hidden && !document.querySelector('.edit-form') && !exportInUse) window.location.reload();
}, 10000);
</script>
</body>
</html>
