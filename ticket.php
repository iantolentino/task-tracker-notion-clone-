<?php
require __DIR__ . '/auth.php';

$categories = ['Graphics', 'Marketing', 'Sales Request', 'Project', 'Other'];
$assignees = ['Aiko', 'Ivan', 'Aiko/Ivan'];
$values = [
    'requester_name' => '',
    'requester_email' => '',
    'assignee' => 'Aiko',
    'due_date' => '',
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
        foreach (['requester_name', 'requester_email', 'assignee', 'due_date', 'estimated_cost', 'subject', 'description', 'business_impact', 'other_type'] as $key) {
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
                $stmt = $pdo->prepare('INSERT INTO tickets (requester_name, requester_email, assignee, due_date, ticket_type, estimated_cost, subject, category, priority, description, effort, business_impact, other_type, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$values['requester_name'], $values['requester_email'], $values['assignee'], $storedDueDate, $values['ticket_type'], $storedCost, $values['subject'], implode(', ', $selectedCategories), '', $values['description'], '', $values['business_impact'], $values['other_type'], 'Open']);
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
    <title><?php echo $createMode ? 'Create a ticket' : 'Staff portal'; ?> - Creatives Ticketing System</title>
    <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
    <style>
        :root { --navy: #0f172a; --blue: #1450be; --muted: #5b6b85; --border: #d8e1ee; --bg: #f4f7fb; --green: #087f5b; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: var(--bg); color: var(--navy); font-family: Arial, Helvetica, sans-serif; }
        a { color: var(--blue); }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 20px; padding: 18px max(24px, calc((100vw - 1060px) / 2)); background: #fff; border-bottom: 1px solid var(--border); }
        .brand { display: inline-flex; align-items: center; text-decoration: none; }
        .brand img { display: block; width: 154px; height: 38px; object-fit: contain; object-position: left center; }
        .topbar nav { display: flex; gap: 16px; font-size: 13px; }
        .topbar nav a { text-decoration: none; font-weight: 700; }
        main { max-width: 1060px; margin: 0 auto; padding: 54px 24px 72px; }
        .hero { max-width: 700px; margin-bottom: 28px; }
        .eyebrow { margin: 0 0 10px; color: var(--blue); font-size: 12px; font-weight: 800; letter-spacing: .13em; text-transform: uppercase; }
        h1 { margin: 0; font-size: clamp(32px, 5vw, 48px); letter-spacing: -.05em; line-height: 1.02; }
        .hero p { margin: 15px 0 0; color: var(--muted); line-height: 1.6; }
        .layout { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(240px, .75fr); gap: 24px; align-items: start; }
        .card { padding: 28px; background: #fff; border: 1px solid var(--border); border-radius: 18px; box-shadow: 0 14px 34px rgba(27, 63, 128, .08); }
        .card h2 { margin: 0 0 6px; font-size: 20px; }
        .card-intro { margin: 0 0 22px; color: var(--muted); font-size: 13px; line-height: 1.5; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px; }
        label { display: block; margin: 15px 0 7px; font-size: 13px; font-weight: 800; }
        input, select, textarea { width: 100%; border: 1px solid #bdcadd; border-radius: 10px; padding: 11px 12px; color: var(--navy); background: #fff; font: inherit; font-size: 14px; outline: none; }
        textarea { min-height: 170px; resize: vertical; line-height: 1.5; }
        input:focus, select:focus, textarea:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(20, 80, 190, .14); }
        input[readonly] { background: #f4f7fb; }
        .help { margin: 6px 0 0; color: var(--muted); font-size: 12px; line-height: 1.45; }
        .submit, .portal-button { width: 100%; min-height: 46px; margin-top: 22px; border: 0; border-radius: 10px; background: var(--blue); color: #fff; cursor: pointer; font: inherit; font-weight: 800; transition: background .2s, transform .2s; }
        .submit:hover, .portal-button:hover { background: #0f43a4; transform: translateY(-1px); }
        .portal-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .portal-actions .portal-button { margin-top: 18px; }
        .portal-button.secondary { background: #0f172a; }
        .portal-button.secondary:hover { background: #1e293b; }
        .alert { margin: 0 0 20px; padding: 12px 14px; border-radius: 10px; font-size: 13px; line-height: 1.45; }
        .alert.error { border: 1px solid #f0b7b7; background: #fff2f2; color: #9f1d1d; }
        .alert.success { border: 1px solid #9cdec6; background: #edfff7; color: #096b4d; }
        .alert ul { margin: 0; padding-left: 18px; }
        .side-card { background: #0f172a; color: #fff; }
        .side-card h2 { color: #fff; }
        .side-card p, .side-card li { color: #c5d0e2; font-size: 13px; line-height: 1.6; }
        .side-card a { color: #9cc4ff; font-weight: 700; }
        .side-card ul { margin: 18px 0 0; padding-left: 18px; }
        .honeypot { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
        .success-card { max-width: 700px; }
        @media (max-width: 760px) { .topbar { align-items: flex-start; flex-direction: column; } main { padding-top: 36px; } .layout, .grid, .portal-actions { grid-template-columns: 1fr; } .card { padding: 22px; } }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { scroll-behavior: auto !important; transition: none !important; } }
    </style>
    <link rel="stylesheet" href="assets/design-system.css?v=20260903d">
</head>
<body>
    <header class="topbar">
        <a class="brand" href="ticket.php"><img src="assets/stratastaff-logo.png" alt="Strata Staff"></a>
        <nav aria-label="Ticket navigation"><a href="ticket.php" aria-current="page">Staff Portal</a><a href="ticket-status.php">View Tickets</a><a href="login.php">Admin sign in</a></nav>
    </header>
    <main id="main-content">
        <section class="hero">
            <h1><?php echo $createMode ? 'Create a creative request.' : 'How can we help?'; ?></h1>
            <p><?php echo $createMode ? 'Add the details our team needs to investigate and route your request.' : 'Use your Strata Staff work email to submit a detailed request or view tickets you have already sent.'; ?></p>
        </section>

        <?php if ($submitted): ?>
            <section class="card success-card" aria-labelledby="submitted-title">
                <h2 id="submitted-title">Ticket submitted</h2>
                <p class="card-intro">Your request is now <strong>Open</strong> and visible to the support team.</p>
                <div class="alert success" role="status">Reference: <strong><?php echo $esc($submitted); ?></strong></div>
                <p><a href="ticket-status.php">View your tickets</a> or <a href="ticket.php">return to the staff portal</a>.</p>
            </section>
        <?php elseif (!$createMode): ?>
            <div class="layout">
                <section class="card" aria-label="Ticket access">
                    <?php if ($errors): ?><div class="alert error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?php echo $esc($error); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?php echo $esc($csrf); ?>">
                        <label for="portal_email">Strata Staff work email</label>
                        <input id="portal_email" name="requester_email" type="email" maxlength="255" autocomplete="email" placeholder="you@stratastaff.com" required>
                        <div class="portal-actions">
                            <button class="portal-button" type="submit" name="portal_action" value="start_create">Create ticket</button>
                            <button class="portal-button secondary" type="submit" name="portal_action" value="view_tickets">View tickets</button>
                        </div>
                    </form>
                </section>
                <aside class="card side-card">
                    <h2>How requests are handled</h2>
                    <p>Your request starts as <strong>Open</strong>. Aiko and Ivan review requests, manage assignments and due dates, and keep the progress status updated.</p>
                    <ul><li>Use Create ticket for a new request.</li><li>Use View tickets to check status by email.</li><li>Never include passwords or access tokens.</li></ul>
                </aside>
            </div>
        <?php else: ?>
            <div class="layout">
                <section class="card" aria-labelledby="ticket-form-title">
                    <h2 id="ticket-form-title">Ticket details</h2>
                    <p class="card-intro">These fields become the ticket record the support team will work from. Your ticket will start as <strong>Open</strong>.</p>
                    <?php if ($databaseError && !$errors): ?><div class="alert error" role="alert"><?php echo $esc($databaseError); ?></div><?php endif; ?>
                    <?php if ($errors): ?><div class="alert error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?php echo $esc($error); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf" value="<?php echo $esc($csrf); ?>">
                        <input type="hidden" name="portal_action" value="submit_ticket">
                        <div class="honeypot" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
                        <div class="grid">
                            <div><label for="requester_name">Your name <span class="help" style="display:inline;">(optional)</span></label><input id="requester_name" name="requester_name" maxlength="120" autocomplete="name" value="<?php echo $esc($values['requester_name']); ?>"></div>
                            <div><label for="requester_email">Work email</label><input id="requester_email" name="requester_email" type="email" maxlength="255" autocomplete="email" value="<?php echo $esc($values['requester_email']); ?>" readonly required></div>
                        </div>
                        <div class="grid">
                            <div><label for="assignee">Assign to <span aria-hidden="true">*</span></label><select id="assignee" name="assignee" required><?php foreach ($assignees as $assignee): ?><option value="<?php echo $esc($assignee); ?>"<?php echo $values['assignee'] === $assignee ? ' selected' : ''; ?>><?php echo $esc($assignee); ?></option><?php endforeach; ?></select></div>
                            <div><label for="due_date">Deadline <span class="help" style="display:inline;">(optional)</span></label><input id="due_date" name="due_date" type="date" value="<?php echo $esc($values['due_date']); ?>"><p class="help">Choose a target date only if one is known.</p></div>
                        </div>
                        <div class="grid">
                            <div><label for="category">Type / category <span aria-hidden="true">*</span></label><select id="category" name="category[]" multiple size="5" required><?php foreach ($categories as $category): ?><option value="<?php echo $esc($category); ?>"<?php echo in_array($category, $values['category'], true) ? ' selected' : ''; ?>><?php echo $esc($category); ?></option><?php endforeach; ?></select><p class="help">Select one or more. Project is available when a cost estimate is useful.</p></div>
                            <div><label for="other_type">Other type</label><input id="other_type" name="other_type" value="<?php echo $esc($values['other_type']); ?>" placeholder="Describe another request type"></div>
                        </div>
                        <div class="grid">
                            <div><label for="estimated_cost">Estimated cost <span class="help" style="display:inline;">(optional)</span></label><input id="estimated_cost" name="estimated_cost" type="number" min="0" step="0.01" inputmode="decimal" value="<?php echo $esc($values['estimated_cost']); ?>" placeholder="0.00"><p class="help">Add a budget estimate when useful, including for projects.</p></div>
                            <div><label>Priority</label><p class="help" style="margin-top:12px;">Set by an admin during triage. Your ticket starts in the Open inbox.</p></div>
                        </div>
                        <label for="subject">Task name <span aria-hidden="true">*</span></label>
                        <input id="subject" name="subject" maxlength="180" value="<?php echo $esc($values['subject']); ?>" required>
                        <label for="description">Description <span aria-hidden="true">*</span></label>
                        <textarea id="description" name="description" placeholder="Describe the request in as much detail as needed." required><?php echo $esc($values['description']); ?></textarea>
                        <label for="business_impact">Business impact <span class="help" style="display:inline;">(optional)</span></label>
                        <textarea id="business_impact" name="business_impact" placeholder="Explain urgency, affected work, or consequences if this is delayed."><?php echo $esc($values['business_impact']); ?></textarea>
                        <p class="help">Do not include passwords, access tokens, or other sensitive credentials.</p>
                        <label for="attachments">Attachments</label>
                        <input id="attachments" name="attachments[]" type="file" multiple accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,.doc,.docx,.xls,.xlsx,.txt,.zip">
                        <p class="help">Optional: multiple files are allowed, up to 500 MB per file. PDF, images, Office, text, and ZIP files are supported. MP4 is blocked.</p>
                        <button class="submit" type="submit">Submit ticket</button>
                    </form>
                </section>
                <aside class="card side-card">
                    <h2>Ticket lifecycle</h2>
                    <p>After submission, admins work the ticket from the task list. Active work can be marked In progress, On hold, Waiting for material, or Waiting for budget.</p>
                    <p><a href="ticket-status.php">View your tickets</a></p>
                </aside>
            </div>
        <?php endif; ?>
    </main>
    <?php if ($createMode && !$submitted): ?><script>
        const categoryField = document.getElementById('category');
        const otherField = document.getElementById('other_type');
        function syncOtherType() {
            const hasOtherCategory = Array.from(categoryField.selectedOptions).some(option => option.value === 'Other');
            const required = hasOtherCategory;
            otherField.required = required;
        }
        categoryField.addEventListener('change', syncOtherType);
        syncOtherType();
    </script><?php endif; ?>
</body>
</html>
