<?php
require __DIR__ . '/auth.php';

if (is_logged_in()) {
    header('Location: ./');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = pdo_connect();
        create_users_table($pdo);
        ensure_default_admin($pdo);

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => (int) $user['id'],
                'username' => $user['username'],
            ];
            header('Location: ./');
            exit;
        }

        $error = 'Wrong username or password.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Tasks Tracker</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: Arial, sans-serif; background: #f8fafc; color: #0f172a; }
        main { width: min(380px, calc(100vw - 32px)); background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        h1 { font-size: 22px; margin: 0 0 6px; }
        p { color: #64748b; margin: 0 0 18px; font-size: 13px; }
        label { display: block; font-size: 12px; font-weight: 700; margin: 14px 0 6px; }
        input { width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
        button { width: 100%; margin-top: 18px; padding: 10px 14px; border: 0; border-radius: 6px; background: #2563eb; color: #fff; font-weight: 700; cursor: pointer; }
        .error { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; border-radius: 6px; padding: 10px; margin-bottom: 12px; font-size: 13px; }
        .hint { margin-top: 14px; font-size: 12px; color: #64748b; }
    </style>
</head>
<body>
<main>
    <h1>Tasks Tracker</h1>
    <p>Sign in to manage your tasks.</p>
    <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <form method="post">
        <label for="username">Username</label>
        <input id="username" name="username" autocomplete="username" required>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button type="submit">Login</button>
    </form>
    <div class="hint">First login: admin / admin123</div>
</main>
</body>
</html>
