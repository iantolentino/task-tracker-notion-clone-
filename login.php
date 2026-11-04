<?php
require __DIR__ . '/auth.php';

if (is_logged_in()) {
    header('Location: ./');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals(auth_csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Your session expired. Refresh and try again.');
        }
        $pdo = pdo_connect();
        create_users_table($pdo);
        ensure_default_admin($pdo);

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || strlen($username) > 80 || $password === '' || strlen($password) > 255) {
            throw new RuntimeException('Wrong username or password.');
        }

        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => (int) $user['id'],
                'username' => $user['username'],
                'role' => $user['role'] ?? 'admin',
            ];
            header('Location: ./');
            exit;
        }

        $error = 'Wrong username or password.';
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log($e->getMessage());
        $error = 'Sign in is temporarily unavailable.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in - Creatives Ticketing System</title>
    <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
    <style>
        :root { --blue: #1047a9; --ink: #071b42; --muted: #50688e; --panel: #eff5ff; }
        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; background: #d9e2f1; color: var(--ink); font-family: Arial, Helvetica, sans-serif; }
        .login-layout { min-height: 100vh; display: grid; grid-template-columns: 56% 44%; overflow: hidden; }
        .intro { position: relative; display: flex; flex-direction: column; min-height: 100vh; padding: 60px 6%; color: #fff; background: linear-gradient(145deg, #0c3d94 0%, #124dac 53%, #0f46a7 100%); }
        .eyebrow, .access-label { margin: 0 0 14px; color: inherit; font-size: 12px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
        .intro h1 { max-width: 620px; margin: 0; font-size: clamp(40px, 4vw, 58px); line-height: .98; letter-spacing: -.055em; }
        .intro-copy { max-width: 535px; margin: 20px 0 0; color: #fff; font-size: 16px; line-height: 1.55; }
        .feature-tags { display: flex; flex-wrap: wrap; gap: 10px; margin-top: auto; padding-top: 36px; }
        .feature-tags span { border: 1px solid rgba(255,255,255,.35); border-radius: 999px; padding: 9px 13px; font-size: 12px; font-weight: 700; }
        .sign-in-panel { display: grid; place-items: center; padding: 32px; background: radial-gradient(circle at 45% 45%, #f8fbff 0%, var(--panel) 62%, #e7effc 100%); }
        .login-card { width: min(410px, 100%); padding: 35px; border-radius: 18px; background: #fff; box-shadow: 0 20px 45px rgba(27, 63, 128, .13); }
        .brand-logo { width: 205px; height: 42px; margin: 0 0 25px; overflow: hidden; }
        .brand-logo img { display: block; width: 100%; height: 100%; object-fit: contain; object-position: left center; }
        .access-label { margin-bottom: 8px; color: var(--blue); }
        .login-card h2 { margin: 0; font-size: 32px; line-height: 1.05; letter-spacing: -.04em; }
        .subcopy { margin: 10px 0 24px; color: var(--muted); font-size: 14px; line-height: 1.5; }
        label { display: block; margin: 17px 0 8px; color: #0d2045; font-size: 13px; font-weight: 800; }
        input { width: 100%; height: 46px; border: 1px solid #c4d0e3; border-radius: 12px; padding: 10px 13px; outline: none; color: var(--ink); font: inherit; transition: border-color .15s, box-shadow .15s; }
        input:focus { border-color: #1760ce; box-shadow: 0 0 0 3px rgba(23,96,206,.15); }
        button { width: 100%; margin-top: 23px; min-height: 47px; border: 0; border-radius: 12px; background: #1450be; box-shadow: 0 9px 18px rgba(17,72,177,.24); color: #fff; cursor: pointer; font: inherit; font-size: 14px; font-weight: 800; transition: background .15s, transform .15s; }
        button:hover { background: #0f43a4; transform: translateY(-1px); }
        .error { margin: 0 0 16px; padding: 10px 12px; border: 1px solid #f1b7b7; border-radius: 10px; background: #fff2f2; color: #a71f1f; font-size: 13px; }
        .hint { margin: 18px 0 0; color: #71819a; font-size: 12px; text-align: center; }
        @media (max-width: 760px) { .login-layout { display: block; } .intro { min-height: auto; padding: 42px 28px; } .intro h1 { font-size: 38px; } .feature-tags { display: none; } .sign-in-panel { min-height: 68vh; padding: 28px 20px; } .login-card { padding: 30px 25px; } }
    </style>
    <link rel="stylesheet" href="assets/design-system.css?v=20260903d">
</head>
<body class="login-page">
    <main class="login-layout">
        <section class="intro" aria-label="Creatives Ticketing System overview">
            <p class="eyebrow">Team productivity</p>
            <h1>Creatives Ticketing System</h1>
            <p class="intro-copy">Manage creative requests, assign clear ownership, and follow progress from one focused workspace.</p>
            <div class="feature-tags" aria-label="System features"><span>Clear ownership</span><span>Shared progress</span><span>One focused dashboard</span></div>
        </section>
        <section class="sign-in-panel">
            <div class="login-card">
                <div class="brand-logo"><img src="assets/stratastaff-logo.png" alt="Strata Staff"></div>
                <p class="access-label">Admin access</p>
                <h2>Sign in</h2>
                <p class="subcopy">Manage tasks, ownership, and project progress.</p>
                <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(auth_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <label for="username">Username</label>
                    <input id="username" name="username" autocomplete="username" maxlength="80" required>
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" maxlength="255" required>
                    <button type="submit">Sign in</button>
                </form>
                <p class="hint"><a href="ticket.php">Need help? Submit a ticket</a></p>
            </div>
        </section>
    </main>
</body>
</html>
