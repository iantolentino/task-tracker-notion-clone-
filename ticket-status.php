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
    <title>My tickets - Multimedia and Marketing Ticketing System</title>
    <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/design-system.css?v=20261105e">
    <script src="assets/theme.js?v=20261105e"></script>
</head>
<body class="public-page">
    <header class="topbar">
        <a class="brand" href="ticket.php"><img src="assets/stratastaff-logo.png" alt="Strata Staff"></a>
        <nav aria-label="Ticket navigation"><a href="ticket.php">Request Form</a><a href="ticket-status.php" aria-current="page">My tickets</a><a class="nav-quiet" href="login.php">Admin</a><button class="theme-toggle icon-only" type="button" data-theme-toggle aria-label="Toggle dark mode" title="Toggle dark mode"><svg class="when-light" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg><svg class="when-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg></button></nav>
    </header>
    <main id="main-content" class="form-width">
        <section class="hero">
            <h1>My tickets</h1>
            <p>Enter the email you used on your request.</p>
        </section>
        <form class="lookup" method="post">
            <input type="hidden" name="csrf" value="<?php echo $esc($csrf); ?>">
            <label for="requester_email" style="position:absolute;left:-9999px;">Work email</label>
            <input id="requester_email" name="requester_email" type="email" maxlength="255" autocomplete="email" placeholder="you@stratastaff.com" value="<?php echo $esc($email); ?>" required>
            <button class="btn-lg" type="submit">Find tickets</button>
        </form>
        <?php if ($databaseError && !$errors): ?><div class="alert error" role="alert" style="margin-top:20px;"><?php echo $esc($databaseError); ?></div><?php endif; ?>
        <?php if ($errors): ?><div class="alert error" role="alert" style="margin-top:20px;"><?php echo $esc(implode(' ', $errors)); ?></div><?php endif; ?>
        <?php if ($searched && !$errors): ?>
            <section class="results" aria-live="polite">
                <?php if (!$rows): ?>
                    <div class="empty-state">
                        <p>No tickets yet for <strong><?php echo $esc($email); ?></strong>.</p>
                        <a class="btn-lg" href="ticket.php">Request Form</a>
                    </div>
                <?php else: ?>
                    <div class="results-head">
                        <h2><?php echo count($rows); ?> ticket<?php echo count($rows) === 1 ? '' : 's'; ?></h2>
                        <span><?php echo $esc($email); ?></span>
                    </div>
                    <?php foreach ($rows as $row): ?>
                        <article class="ticket">
                            <div class="ticket-head">
                                <div>
                                    <p class="reference">Ticket no. <?php echo $esc(ticket_reference((int) $row['id'])); ?> · <?php echo $esc($niceDate($row['created_at'])); ?></p>
                                    <h3><?php echo $esc($row['subject']); ?></h3>
                                </div>
                                <span class="status" data-status="<?php echo $esc(strtolower((string) $row['status'])); ?>"><?php echo $esc($row['status']); ?></span>
                            </div>
                            <ul class="ticket-facts">
                                <li><strong>With</strong> <?php echo $esc($row['assignee']); ?></li>
                                <li><strong>Due</strong> <?php echo $esc($row['due_date'] ? $niceDate($row['due_date']) : 'No deadline'); ?></li>
                                <li><strong>Type</strong> <?php echo $esc($row['category']); ?><?php if (($row['other_type'] ?? '') !== ''): ?> (<?php echo $esc($row['other_type']); ?>)<?php endif; ?></li>
                                <?php if (($row['priority'] ?? '') !== ''): ?><li><strong>Priority</strong> <?php echo $esc($row['priority']); ?></li><?php endif; ?>
                            </ul>
                            <details class="ticket-more">
                                <summary>Details</summary>
                                <div class="ticket-more-body">
                                    <h4>Description</h4>
                                    <p><?php echo $esc($row['description']); ?></p>
                                    <?php if (!empty($row['uploads'])): ?><h4>Files</h4><div class="file-list" style="margin-top:4px;"><?php foreach ($row['uploads'] as $file): ?><span><?php echo $esc($file['name']); ?></span><?php endforeach; ?></div><?php endif; ?>
                                    <h4>Last updated</h4>
                                    <p><?php echo $esc($niceDate($row['updated_at'])); ?></p>
                                </div>
                            </details>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
