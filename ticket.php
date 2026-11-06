<?php
require __DIR__ . '/auth.php';

$categories = ['Graphics', 'Marketing', 'Sales Request', 'Project', 'Other'];
$assignees = ['Akio', 'Ivan', 'Akio/Ivan'];
$values = [
    'requester_name' => '',
    'requester_email' => '',
    'department' => '',
    'assignee' => 'Akio',
    'due_date' => '',
    'due_time' => '',
    'ticket_type' => '',
    'estimated_cost' => '',
    'category' => [],
    'priority' => '',
    'subject' => '',
    'description' => '',
    'business_impact' => '',
    'other_type' => '',
];
$errors = [];
$databaseError = '';
$submitted = preg_match('/^TCK-\d{6}$/', $_GET['submitted'] ?? '') ? $_GET['submitted'] : '';

if (!isset($_SESSION['ticket_csrf'])) {
    $_SESSION['ticket_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['ticket_csrf'];

try {
    $pdo = pdo_connect();
    create_uploads_table($pdo);
    create_tickets_table($pdo);
} catch (Throwable $e) {
    $pdo = null;
    $databaseError = 'The ticket service is temporarily unavailable. Please try again later.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $portalAction = $_POST['portal_action'] ?? 'submit_ticket';
    $email = strtolower(trim((string) ($_POST['requester_email'] ?? $_POST['portal_email'] ?? '')));

    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $errors[] = 'Your form expired. Refresh the page and try again.';
    }
    if ($portalAction === 'view_tickets') {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address to find your tickets.';
    } elseif (!is_stratastaff_email($email)) {
        $errors[] = 'Ticket creation requires a valid @stratastaff.com work email.';
    }

    if (!$errors && $portalAction === 'start_create') {
        $_SESSION['ticket_email'] = $email;
        header('Location: ticket.php?create=1');
        exit;
    }

    if (!$errors && $portalAction === 'view_tickets') {
        $_SESSION['ticket_lookup_email'] = $email;
        header('Location: ticket-status.php?lookup=1');
        exit;
    }

    if ($portalAction === 'submit_ticket') {
        foreach (['requester_name', 'requester_email', 'department', 'assignee', 'due_date', 'due_time', 'estimated_cost', 'subject', 'description', 'business_impact', 'other_type'] as $key) {
            $values[$key] = trim((string) ($_POST[$key] ?? $values[$key]));
        }
        $values['requester_email'] = $email;
        $_SESSION['ticket_email'] = $email;

        $rawCategories = $_POST['category'] ?? [];
        $rawCategories = is_array($rawCategories) ? $rawCategories : [$rawCategories];
        $selectedCategories = array_values(array_unique(array_intersect($categories, array_map('strval', $rawCategories))));
        $values['category'] = $selectedCategories;
        $values['ticket_type'] = in_array('Project', $selectedCategories, true) ? 'Project' : (in_array('Other', $selectedCategories, true) ? 'Other' : ($selectedCategories[0] ?? ''));
        if (!$selectedCategories) $errors[] = 'Select at least one category.';
        if (trim((string) ($_POST['website'] ?? '')) !== '') $errors[] = 'Unable to submit this request.';
        if ($values['requester_name'] !== '' && strlen($values['requester_name']) > 120) $errors[] = 'Keep your name to 120 characters or fewer.';
        if ($values['requester_name'] === '') {
            $localPart = (string) strstr($email, '@', true);
            $values['requester_name'] = ucwords(str_replace(['.', '_', '-'], ' ', $localPart));
        }
        if (!in_array($values['assignee'], $assignees, true)) $errors[] = 'Choose a valid assignee.';
        $dueDate = parse_due_date($values['due_date']);
        if ($values['due_date'] !== '' && !$dueDate) $errors[] = 'Choose a valid deadline or leave it blank.';
        $dueTime = normalize_due_time($values['due_time']);
        if ($dueTime === null) $errors[] = 'Choose a valid deadline time or leave it blank.';
        if ($dueTime && !$dueDate) $errors[] = 'Add a deadline date to go with the time.';
        if (strlen($values['department']) > 120) $errors[] = 'Keep the department to 120 characters or fewer.';
        if ($values['subject'] === '' || strlen($values['subject']) > 180) $errors[] = 'Enter a task name (up to 180 characters).';
        $cost = $values['estimated_cost'];
        if ($cost !== '' && (!preg_match('/^\d+(?:\.\d{1,2})?$/', $cost) || (float) $cost < 0)) $errors[] = 'Estimated cost must be a valid non-negative amount.';
        if ($values['description'] === '') $errors[] = 'Add a description of the request.';
        if (($values['ticket_type'] === 'Other' || in_array('Other', $selectedCategories, true)) && $values['other_type'] === '') $errors[] = 'Describe the other type of request.';
        if ($pdo === null) $errors[] = $databaseError;

        if (!$errors) {
            try {
                $pdo->beginTransaction();
                $storedDueDate = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? ($dueDate ?? '') : $dueDate;
                $storedCost = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? $cost : ($cost === '' ? null : $cost);
                $stmt = $pdo->prepare('INSERT INTO tickets (requester_name, requester_email, department, assignee, due_date, due_time, ticket_type, estimated_cost, subject, category, priority, description, effort, business_impact, other_type, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$values['requester_name'], $values['requester_email'], $values['department'], $values['assignee'], $storedDueDate, (string) $dueTime, $values['ticket_type'], $storedCost, $values['subject'], implode(', ', $selectedCategories), '', $values['description'], '', $values['business_impact'], $values['other_type'], 'Open']);
                $ticketId = (int) $pdo->lastInsertId();
                save_ticket_uploads($pdo, $ticketId, $_FILES['attachments'] ?? null);
                $pdo->commit();
                unset($_SESSION['ticket_email']);
                header('Location: ticket.php?submitted=' . rawurlencode(ticket_reference($ticketId)));
                exit;
            } catch (Throwable $e) {
                if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'We could not save your ticket. Please try again.';
            }
        }
    }
}

$portalEmail = strtolower(trim((string) ($_SESSION['ticket_email'] ?? '')));
$createMode = $submitted === '' && is_stratastaff_email($portalEmail);
if (!$createMode && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['portal_action'] ?? '') === 'submit_ticket') {
    $createMode = true;
}
if ($createMode && $values['requester_email'] === '') {
    $values['requester_email'] = $portalEmail;
}
$esc = fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $createMode ? 'Request Form' : 'Staff portal'; ?> - Multimedia and Marketing Ticketing System</title>
    <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/design-system.css?v=20261106c">
    <script src="assets/theme.js?v=20261105e"></script>
</head>
<body class="public-page">
    <header class="topbar">
        <a class="brand" href="ticket.php"><img src="assets/stratastaff-logo.png" alt="Strata Staff"></a>
        <nav aria-label="Ticket navigation"><a href="ticket.php" aria-current="page">Request Form</a><a href="ticket-status.php">My tickets</a><a class="nav-quiet" href="login.php">Admin</a><button class="theme-toggle icon-only" type="button" data-theme-toggle aria-label="Toggle dark mode" title="Toggle dark mode"><svg class="when-light" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg><svg class="when-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg></button></nav>
    </header>
    <main id="main-content" class="<?php echo $createMode && !$submitted ? 'form-width' : 'narrow'; ?>">
        <?php if ($submitted): ?>
            <section class="card success-card" aria-labelledby="submitted-title">
                <div class="success-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></div>
                <h2 id="submitted-title">Request sent</h2>
                <p>We'll review it and keep you posted.</p>
                <p class="success-ref-label">Ticket no.</p>
                <div class="success-ref" role="status"><?php echo $esc($submitted); ?></div>
                <div class="portal-actions">
                    <a class="btn-lg" href="ticket-status.php">Track my tickets</a>
                    <a class="btn-lg ghost" href="ticket.php">Back to portal</a>
                </div>
            </section>
        <?php elseif (!$createMode): ?>
            <section class="hero">
                <h1>How can we help?</h1>
                <p>Enter your work email to start.</p>
            </section>
            <section class="card" aria-label="Ticket access">
                <?php if ($errors): ?><div class="alert error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?php echo $esc($error); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?php echo $esc($csrf); ?>">
                    <label class="field-label" for="portal_email">Work email</label>
                    <input id="portal_email" name="requester_email" type="email" maxlength="255" autocomplete="email" placeholder="you@stratastaff.com" required>
                    <div class="portal-actions">
                        <button class="btn-lg" type="submit" name="portal_action" value="start_create">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Request Form
                        </button>
                        <button class="btn-lg ghost" type="submit" name="portal_action" value="view_tickets">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>My tickets
                        </button>
                    </div>
                </form>
            </section>
        <?php else: ?>
            <section class="hero">
                <h1>Request Form</h1>
                <p>Complete the form and provide the details and materials needed to get started.</p>
            </section>
            <section class="card" aria-label="Request details">
                <?php if ($databaseError && !$errors): ?><div class="alert error" role="alert"><?php echo $esc($databaseError); ?></div><?php endif; ?>
                <?php if ($errors): ?><div class="alert error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?php echo $esc($error); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
                <form method="post" enctype="multipart/form-data" id="ticketForm">
                    <input type="hidden" name="csrf" value="<?php echo $esc($csrf); ?>">
                    <input type="hidden" name="portal_action" value="submit_ticket">
                    <input type="hidden" name="requester_email" value="<?php echo $esc($values['requester_email']); ?>">
                    <div class="honeypot" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>

                    <div class="requester-chip">Sending as <strong><?php echo $esc($values['requester_email']); ?></strong></div>

                    <div class="form-section">
                        <h2>What do you need?</h2>
                        <div class="field">
                            <label class="field-label" for="subject">Title</label>
                            <input id="subject" name="subject" maxlength="180" value="<?php echo $esc($values['subject']); ?>" placeholder="e.g. Poster for the March town hall" required>
                        </div>
                        <fieldset class="field" style="border:0;margin:0 0 18px;padding:0;">
                            <legend class="field-label">Type</legend>
                            <div class="chip-group" id="categoryGroup">
                                <?php foreach ($categories as $category): ?>
                                    <label><input type="checkbox" name="category[]" value="<?php echo $esc($category); ?>"<?php echo in_array($category, $values['category'], true) ? ' checked' : ''; ?>><span><?php echo $esc($category); ?></span></label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                        <div class="field" id="otherTypeField">
                            <label class="field-label" for="other_type">What type is it?</label>
                            <input id="other_type" name="other_type" value="<?php echo $esc($values['other_type']); ?>" placeholder="e.g. Video edit">
                        </div>
                        <div class="field">
                            <label class="field-label" for="description">Details</label>
                            <textarea id="description" name="description" placeholder="Size, wording, links, references…" required><?php echo $esc($values['description']); ?></textarea>
                        </div>
                    </div>

                    <div class="form-section">
                        <h2>Timing and owner</h2>
                        <div class="form-row">
                            <div class="field">
                                <label class="field-label" for="assignee">Assign to</label>
                                <select id="assignee" name="assignee" required><?php foreach ($assignees as $assignee): ?><option value="<?php echo $esc($assignee); ?>"<?php echo $values['assignee'] === $assignee ? ' selected' : ''; ?>><?php echo $esc($assignee); ?></option><?php endforeach; ?></select>
                            </div>
                            <div class="field">
                                <label class="field-label" for="due_date">Deadline <span class="optional">(optional)</span></label>
                                <div class="deadline-pair">
                                    <input id="due_date" name="due_date" type="date" value="<?php echo $esc($values['due_date']); ?>">
                                    <input id="due_time" name="due_time" type="time" aria-label="Deadline time (optional)" value="<?php echo $esc($values['due_time']); ?>">
                                </div>
                                <p class="field-hint">Time is optional.</p>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="field">
                                <label class="field-label" for="requester_name">Your name <span class="optional">(optional)</span></label>
                                <input id="requester_name" name="requester_name" maxlength="120" autocomplete="name" value="<?php echo $esc($values['requester_name']); ?>">
                            </div>
                            <div class="field">
                                <label class="field-label" for="department">Department <span class="optional">(optional)</span></label>
                                <input id="department" name="department" maxlength="120" autocomplete="organization-title" placeholder="e.g. HR, Sales, Operations" value="<?php echo $esc($values['department']); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h2>Attachments <span class="optional" style="color:var(--color-muted);font-weight:400;font-size:13px;">(optional)</span></h2>
                        <p class="field-hint" style="margin:-8px 0 12px;">If you have any design references or suggestions, feel free to add them here.</p>
                        <label class="file-drop" for="attachments" style="position:relative;">
                            <input id="attachments" name="attachments[]" type="file" multiple accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,.doc,.docx,.xls,.xlsx,.txt,.zip">
                            <span><strong>Choose files</strong> or drop them here</span>
                            <span style="font-size:12px;">PDF, images, Office, text or ZIP · up to 500 MB each</span>
                        </label>
                        <div class="file-list" id="fileList" aria-live="polite"></div>
                    </div>

                    <div class="form-disclaimer" role="note">
                        <strong>Please note:</strong> Turnaround times may vary depending on the request's complexity, scope, priority, and current workload. Submission of a request does not guarantee immediate completion.
                    </div>
                    <div class="form-footer">
                        <p>Please don't include passwords.</p>
                        <button class="btn-lg" type="submit">Send request</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>
    </main>
    <?php if ($createMode && !$submitted): ?><script>
        const categoryBoxes = Array.from(document.querySelectorAll('#categoryGroup input'));
        const otherFieldWrap = document.getElementById('otherTypeField');
        const otherField = document.getElementById('other_type');
        function syncCategories() {
            const hasOther = categoryBoxes.some(box => box.checked && box.value === 'Other');
            otherFieldWrap.hidden = !hasOther;
            otherField.required = hasOther;
            categoryBoxes[0].setCustomValidity(categoryBoxes.some(box => box.checked) ? '' : 'Pick at least one type.');
        }
        categoryBoxes.forEach(box => box.addEventListener('change', syncCategories));
        syncCategories();

        const fileInput = document.getElementById('attachments');
        const fileList = document.getElementById('fileList');
        fileInput.addEventListener('change', () => {
            fileList.replaceChildren(...Array.from(fileInput.files).map(file => {
                const chip = document.createElement('span');
                chip.textContent = file.name;
                return chip;
            }));
        });
    </script><?php endif; ?>
</body>
</html>
