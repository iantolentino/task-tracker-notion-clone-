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
    <title>Sign in - Multimedia and Marketing Ticketing System</title>
    <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
    <style>
        :root { --blue: #1047a9; --ink: #071b42; --muted: #50688e; --panel: #eff5ff; }
        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; background: #d9e2f1; color: var(--ink); font-family: Arial, Helvetica, sans-serif; }
        .login-layout { min-height: 100vh; display: grid; grid-template-columns: 56% 44%; overflow: hidden; }
        .intro { position: relative; display: flex; flex-direction: column; justify-content: center; min-height: 100vh; padding: 60px 6%; color: #fff; background: linear-gradient(145deg, #0c3d94 0%, #124dac 53%, #0f46a7 100%); }
        .eyebrow, .access-label { margin: 0 0 14px; color: inherit; font-size: 12px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
        .intro h1 { max-width: 620px; margin: 0; font-size: clamp(40px, 4vw, 58px); line-height: .98; letter-spacing: -.055em; }
        .intro-copy { max-width: 535px; margin: 20px 0 0; color: #fff; font-size: 16px; line-height: 1.55; }
        .feature-tags { display: flex; flex-wrap: wrap; gap: 10px; margin-top: auto; padding-top: 36px; }
        .feature-tags span { border: 1px solid rgba(255,255,255,.35); border-radius: 999px; padding: 9px 13px; font-size: 12px; font-weight: 700; }
        .sign-in-panel { display: grid; place-items: center; padding: 32px; background: radial-gradient(circle at 45% 45%, #f8fbff 0%, var(--panel) 62%, #e7effc 100%); }
        .login-card { width: min(410px, 100%); padding: 35px; border-radius: 18px; background: #fff; box-shadow: 0 20px 45px rgba(27, 63, 128, .13); }
        .brand-logo { display: flex; justify-content: center; margin: 0 0 24px; padding: 4px 0 24px; border-bottom: 1px solid var(--color-border, #e4e4e7); }
        .brand-logo img { display: block; width: 210px; max-width: 100%; height: auto; }
        .login-card h2, .subcopy { text-align: center; }
        .access-label { margin-bottom: 8px; color: var(--blue); }
        .login-card h2 { margin: 0; font-size: 32px; line-height: 1.05; letter-spacing: -.04em; }
        .subcopy { margin: 10px 0 24px; color: var(--muted); font-size: 14px; line-height: 1.5; }
        label { display: block; margin: 17px 0 8px; color: #0d2045; font-size: 13px; font-weight: 800; }
        input { width: 100%; height: 46px; border: 1px solid #c4d0e3; border-radius: 12px; padding: 10px 13px; outline: none; color: var(--ink); font: inherit; transition: border-color .15s, box-shadow .15s; }
        input:focus { border-color: #1760ce; box-shadow: 0 0 0 3px rgba(23,96,206,.15); }
        .login-card button { width: 100%; margin-top: 23px; min-height: 47px; border: 0; border-radius: 12px; background: #1450be; box-shadow: 0 9px 18px rgba(17,72,177,.24); color: #fff; cursor: pointer; font: inherit; font-size: 14px; font-weight: 800; transition: background .15s, transform .15s; }
        .login-card button:hover { background: #0f43a4; transform: translateY(-1px); }
        .error { margin: 0 0 16px; padding: 10px 12px; border: 1px solid #f1b7b7; border-radius: 10px; background: #fff2f2; color: #a71f1f; font-size: 13px; }
        .hint { margin: 18px 0 0; color: #71819a; font-size: 12px; text-align: center; }
        @media (max-width: 760px) { .login-layout { display: block; } .intro { min-height: auto; padding: 42px 28px; } .intro h1 { font-size: 38px; } .feature-tags { display: none; } .sign-in-panel { min-height: 68vh; padding: 28px 20px; } .login-card { padding: 30px 25px; } }
    </style>
    <link rel="stylesheet" href="assets/design-system.css?v=20261106c">
    <script src="assets/theme.js?v=20261105e"></script>
</head>
<body class="login-page">
    <div class="login-theme"><button class="theme-toggle icon-only" type="button" data-theme-toggle aria-label="Toggle dark mode" title="Toggle dark mode"><svg class="when-light" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg><svg class="when-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg></button></div>
    <main class="login-layout">
        <section class="intro" aria-label="Multimedia and Marketing Ticketing System overview">
            <h1>Multimedia and Marketing Ticketing System</h1>
            <p class="intro-copy">Creative requests, in one place.</p>
        </section>
        <section class="sign-in-panel">
            <div class="login-card">
                <div class="brand-logo"><img src="assets/stratastaff-logo.png" alt="Strata Staff"></div>
                <h2>Admin sign in</h2>
                <p class="subcopy">Welcome back.</p>
                <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(auth_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <label for="username">Username</label>
                    <input id="username" name="username" autocomplete="username" maxlength="80" required>
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" maxlength="255" required>
                    <button type="submit">Sign in</button>
                </form>
                <p class="hint">Not an admin? <a href="ticket.php">Go to the staff portal</a></p>
            </div>
        </section>
    </main>
</body>
</html>
