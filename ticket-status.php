<?php
require __DIR__ . '/auth.php';

$email = '';
$rows = [];
$errors = [];
$searched = false;
$databaseError = '';
if (!isset($_SESSION['ticket_csrf'])) {
    $_SESSION['ticket_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['ticket_csrf'];

try {
    $pdo = pdo_connect();
    create_tickets_table($pdo);
    create_uploads_table($pdo);
} catch (Throwable $e) {
    $pdo = null;
    $databaseError = 'The ticket service is temporarily unavailable. Please try again later.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $searched = true;
    $email = strtolower(trim((string) ($_POST['requester_email'] ?? '')));
    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $errors[] = 'Your form expired. Refresh the page and try again.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address used on your ticket.';
    }
    if ($pdo === null) {
        $errors[] = $databaseError;
    }

}

if (!$searched && ($_GET['lookup'] ?? '') === '1') {
    $sessionEmail = strtolower(trim((string) ($_SESSION['ticket_lookup_email'] ?? '')));
    unset($_SESSION['ticket_lookup_email']);
    if (filter_var($sessionEmail, FILTER_VALIDATE_EMAIL)) {
        $searched = true;
        $email = $sessionEmail;
    }
}

if ($searched && !$errors) {
    try {
        $stmt = $pdo->prepare('SELECT id, requester_name, requester_email, assignee, due_date, ticket_type, estimated_cost, subject, category, priority, description, effort, business_impact, other_type, status, created_at, updated_at FROM tickets WHERE requester_email = ? ORDER BY id DESC');
        $stmt->execute([$email]);
        $rows = attach_uploads_to_tickets($pdo, $stmt->fetchAll());
    } catch (Throwable $e) {
        $errors[] = 'We could not load your tickets. Please try again.';
    }
}

$esc = fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My tickets - Creatives Ticketing System</title>
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
        .lookup { display: flex; gap: 12px; max-width: 720px; padding: 20px; background: #fff; border: 1px solid var(--border); border-radius: 16px; box-shadow: 0 12px 28px rgba(27, 63, 128, .07); }
        .lookup input { flex: 1; min-width: 0; border: 1px solid #bdcadd; border-radius: 10px; padding: 12px; outline: none; font: inherit; font-size: 14px; }
        .lookup input:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(20, 80, 190, .14); }
        button { min-width: 148px; border: 0; border-radius: 10px; background: var(--blue); color: #fff; cursor: pointer; font: inherit; font-weight: 800; }
        button:hover { background: #0f43a4; }
        .help { margin: 10px 0 0; color: var(--muted); font-size: 12px; }
        .alert { margin: 20px 0; padding: 12px 14px; border-radius: 10px; font-size: 13px; line-height: 1.45; }
        .alert.error { border: 1px solid #f0b7b7; background: #fff2f2; color: #9f1d1d; }
        .results { margin-top: 30px; }
        .results h2 { margin: 0 0 14px; font-size: 21px; }
        .ticket { margin: 0 0 14px; padding: 22px; background: #fff; border: 1px solid var(--border); border-radius: 16px; box-shadow: 0 10px 24px rgba(27, 63, 128, .06); }
        .ticket-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .reference { margin: 0 0 6px; color: var(--blue); font-size: 12px; font-weight: 800; letter-spacing: .1em; }
        .ticket h3 { margin: 0; font-size: 18px; }
        .status { display: inline-flex; flex: 0 0 auto; padding: 6px 10px; border-radius: 999px; background: #e9f7f1; color: var(--green); font-size: 12px; font-weight: 800; }
        .meta { display: flex; flex-wrap: wrap; gap: 7px 16px; margin: 15px 0; color: var(--muted); font-size: 12px; }
        .ticket-description { margin: 0; padding-top: 14px; border-top: 1px solid #e8edf5; color: #334155; font-size: 14px; line-height: 1.6; white-space: pre-wrap; }
        .attachments { margin-top: 12px; color: var(--muted); font-size: 12px; }
        .attachments span { display: inline-block; margin: 4px 8px 0 0; padding: 4px 8px; border-radius: 999px; background: #eef2f7; }
        .empty { padding: 28px; background: #fff; border: 1px dashed #b8c6d9; border-radius: 14px; color: var(--muted); }
        @media (max-width: 620px) { .topbar { align-items: flex-start; flex-direction: column; } main { padding-top: 36px; } .lookup { flex-direction: column; } button { min-height: 46px; } .ticket-head { flex-direction: column; } }
    </style>
    <link rel="stylesheet" href="assets/design-system.css?v=20260903d">
</head>
<body>
    <header class="topbar">
        <a class="brand" href="login.php"><img src="assets/stratastaff-logo.png" alt="Strata Staff"></a>
        <nav aria-label="Ticket navigation"><a href="ticket.php">Submit Ticket</a><a href="ticket-status.php" aria-current="page">See Tickets</a><a href="login.php">Admin sign in</a></nav>
    </header>
    <main id="main-content">
        <section class="hero">
            <h1>Check your requests.</h1>
        <p>Enter the same email you used when submitting a ticket. No account or password is needed.</p>
        </section>
        <form class="lookup" method="post">
            <input type="hidden" name="csrf" value="<?php echo $esc($csrf); ?>">
            <label for="requester_email" class="sr-only" style="position:absolute;left:-9999px;">Work email</label>
            <input id="requester_email" name="requester_email" type="email" maxlength="255" autocomplete="email" placeholder="you@stratastaff.com" value="<?php echo $esc($email); ?>" required>
            <button type="submit">View my tickets</button>
        </form>
        <?php if ($databaseError && !$errors): ?><div class="alert error" role="alert"><?php echo $esc($databaseError); ?></div><?php endif; ?>
        <?php if ($errors): ?><div class="alert error" role="alert"><?php echo $esc(implode(' ', $errors)); ?></div><?php endif; ?>
        <?php if ($searched && !$errors): ?>
            <section class="results" aria-live="polite">
                <h2><?php echo count($rows); ?> ticket<?php echo count($rows) === 1 ? '' : 's'; ?> for <?php echo $esc($email); ?></h2>
                <?php if (!$rows): ?>
                    <div class="empty">No tickets found for this email. <a href="ticket.php">Submit a new ticket</a>.</div>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <article class="ticket">
                            <div class="ticket-head">
                                <div><p class="reference"><?php echo $esc(ticket_reference((int) $row['id'])); ?></p><h3><?php echo $esc($row['subject']); ?></h3></div>
                                <span class="status"><?php echo $esc($row['status']); ?></span>
                            </div>
                            <div class="meta"><span>Requested by <?php echo $esc($row['requester_name']); ?></span><span><?php echo $esc($row['requester_email']); ?></span><span>Assignee: <?php echo $esc($row['assignee']); ?></span><span>Deadline: <?php echo $esc($row['due_date'] ?: 'Not set'); ?></span><span>Type / category: <?php echo $esc($row['category']); ?><?php if (($row['other_type'] ?? '') !== ''): ?> (<?php echo $esc($row['other_type']); ?>)<?php endif; ?></span><?php if ((string) ($row['estimated_cost'] ?? '') !== ''): ?><span>Estimated cost: <?php echo $esc($row['estimated_cost']); ?></span><?php endif; ?><span>Priority: <?php echo $esc($row['priority'] ?: 'Pending admin triage'); ?></span><span>Effort: <?php echo $esc($row['effort'] ?: 'Pending admin triage'); ?></span><span>Submitted <?php echo $esc($row['created_at']); ?></span><span>Updated <?php echo $esc($row['updated_at']); ?></span></div>
                            <p class="ticket-description"><?php echo nl2br($esc($row['description'])); ?></p>
                            <?php if (trim((string) ($row['business_impact'] ?? '')) !== ''): ?><p class="ticket-description"><strong>Business impact:</strong><br><?php echo nl2br($esc($row['business_impact'])); ?></p><?php endif; ?>
                            <?php if (!empty($row['uploads'])): ?><div class="attachments"><strong>Files:</strong> <?php foreach ($row['uploads'] as $file): ?><span><?php echo $esc($file['name']); ?></span><?php endforeach; ?></div><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
