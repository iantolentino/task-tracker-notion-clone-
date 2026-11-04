<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/nav.php';

$message = '';
$error = '';
$user = current_user();
$pdo = pdo_connect();
create_users_table($pdo);
create_uploads_table($pdo);
if (!isset($_SESSION['settings_csrf'])) {
    $_SESSION['settings_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['settings_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Your session expired. Refresh and try again.');
        }
        if ($action === 'password') {
            $current = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if (strlen($new) < 12 || strlen($new) > 255) {
                throw new RuntimeException('New password must be between 12 and 255 characters.');
            }
            if ($new !== $confirm) {
                throw new RuntimeException('New passwords do not match.');
            }

            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$user['id']]);
            $row = $stmt->fetch();

            if (!$row || !password_verify($current, $row['password_hash'])) {
                throw new RuntimeException('Current password is incorrect.');
            }

            $stmt = $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            $message = 'Password updated.';
        }

        if ($action === 'user') {
            $username = trim($_POST['username'] ?? '');
            $newPassword = $_POST['user_password'] ?? '';
            // This account is the only super admin. Settings can create admin accounts only.
            $newRole = 'admin';

            if ($username === '') {
                throw new RuntimeException('Username is required.');
            }
            if (strlen($username) > 80) {
                throw new RuntimeException('Username must be 80 characters or fewer.');
            }
            if (strlen($newPassword) < 12 || strlen($newPassword) > 255) {
                throw new RuntimeException('User password must be between 12 and 255 characters.');
            }
            $stmt = $pdo->prepare('
                INSERT INTO users (username, password_hash, role, created_at, updated_at)
                VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ');
            $stmt->execute([$username, password_hash($newPassword, PASSWORD_DEFAULT), $newRole]);
            $message = "User {$username} added.";
        }

        if ($action === 'delete_user') {
            $deleteId = (int) ($_POST['user_id'] ?? 0);
            if ($deleteId <= 0) {
                throw new RuntimeException('Choose a user to delete.');
            }
            if ($deleteId === (int) $user['id']) {
                throw new RuntimeException('You cannot delete the user you are logged in as.');
            }

            $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$deleteId]);
            $deleteRole = $stmt->fetchColumn();
            if ($deleteRole === false) {
                throw new RuntimeException('User not found.');
            }
            if ($deleteRole === 'super_admin' && !is_super_admin()) {
                throw new RuntimeException('Administrators cannot delete the super-admin account.');
            }

            $totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            if ($totalUsers <= 1) {
                throw new RuntimeException('At least one user must remain.');
            }

            $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$deleteId]);
            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('User not found.');
            }
            $message = 'User deleted.';
        }

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$users = $pdo->query('SELECT id, username, role, created_at FROM users ORDER BY username ASC')->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Settings - Multimedia and Marketing Ticketing System</title>
    <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #f8fafc; color: #0f172a; }
        <?php echo nav_css(); ?>
        main { max-width: 980px; margin: 32px auto; padding: 0 18px; }
        section { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 24px; margin-bottom: 18px; }
        h1 { font-size: 22px; margin: 0 0 6px; }
        h2 { font-size: 17px; margin: 0 0 12px; }
        p { color: #64748b; margin: 0 0 18px; font-size: 13px; }
        label { display: block; font-size: 12px; font-weight: 700; margin: 14px 0 6px; }
        input { width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
        button { display: inline-block; margin-top: 18px; padding: 10px 14px; border: 0; border-radius: 6px; background: #2563eb; color: #fff; font-weight: 700; cursor: pointer; }
        button.danger { margin-top: 0; background: #dc2626; padding: 7px 10px; font-size: 12px; }
        a.link { color: #2563eb; text-decoration: none; margin-left: 12px; }
        .muted { color: #64748b; font-size: 12px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .msg { background: #dcfce7; color: #15803d; border: 1px solid #86efac; border-radius: 6px; padding: 10px; margin-bottom: 12px; font-size: 13px; }
        .error { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; border-radius: 6px; padding: 10px; margin-bottom: 12px; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 9px 8px; text-align: left; font-size: 13px; }
        td form { margin: 0; }
        th { color: #64748b; font-size: 11px; text-transform: uppercase; }
        @media (max-width: 760px) { .grid { grid-template-columns: 1fr; } }
    </style>
    <link rel="stylesheet" href="assets/design-system.css?v=20261105e">
    <script src="assets/theme.js?v=20261105e"></script>
</head>
<body class="settings-page">
<?php render_nav('settings'); ?>
<main id="main-content">
    <div class="heading"><div><h1>Settings</h1><p class="subtitle">Your password and admin accounts.</p></div></div>
    <?php if ($message): ?><div class="msg"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

    <div class="grid">
        <section>
            <h2>Change password</h2>
            <form method="post">
                <input type="hidden" name="action" value="password">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <label for="current_password">Current password</label>
                <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                <label for="new_password">New password</label>
                <input id="new_password" name="new_password" type="password" autocomplete="new-password" minlength="12" maxlength="255" placeholder="At least 12 characters" required>
                <label for="confirm_password">Confirm new password</label>
                <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" minlength="12" maxlength="255" required>
                <button type="submit">Save password</button>
            </form>
        </section>

        <section>
            <h2>Add admin</h2>
            <form method="post">
                <input type="hidden" name="action" value="user">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <label for="username">Username</label>
                <input id="username" name="username" maxlength="80" required>
                <label for="user_password">Password</label>
                <input id="user_password" name="user_password" type="password" minlength="12" maxlength="255" placeholder="At least 12 characters" required>
                <button type="submit">Add admin</button>
            </form>
        </section>
    </div>

    <section>
        <h2>Admins</h2>
        <table>
            <thead><tr><th>Username</th><th>Role</th><th>Added</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $row): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                    <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $row['role'] ?? 'admin')), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php $added = strtotime((string) $row['created_at']); echo htmlspecialchars($added ? date('M j, Y', $added) : (string) $row['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <?php $canDelete = (int) $row['id'] !== (int) $user['id'] && (is_super_admin() || ($row['role'] ?? 'admin') !== 'super_admin'); ?>
                        <?php if ($canDelete): ?>
                            <form method="post" onsubmit="return confirm('Delete this user?');">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="user_id" value="<?php echo (int) $row['id']; ?>">
                                <button class="danger" type="submit">Delete</button>
                            </form>
                        <?php elseif ((int) $row['id'] === (int) $user['id']): ?>
                            <span class="muted">You</span>
                        <?php else: ?>
                            <span class="muted">Protected</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

</main>
</body>
</html>
