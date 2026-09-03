<?php
require_once __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    $secureCookie = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

function current_user(): ?array
{
    $user = $_SESSION['user'] ?? null;
    if (!$user) {
        return null;
    }

    try {
        $pdo = pdo_connect();
        create_users_table($pdo);
        $stmt = $pdo->prepare('SELECT id, username, role FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) ($user['id'] ?? 0)]);
        $freshUser = $stmt->fetch();
        if (!$freshUser) {
            unset($_SESSION['user']);
            return null;
        }

        $_SESSION['user'] = [
            'id' => (int) $freshUser['id'],
            'username' => $freshUser['username'],
            'role' => $freshUser['role'] ?? 'admin',
        ];
        return $_SESSION['user'];
    } catch (Throwable $e) {
        unset($_SESSION['user']);
        return null;
    }
}

function auth_csrf_token(): string
{
    if (!isset($_SESSION['auth_csrf'])) {
        $_SESSION['auth_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['auth_csrf'];
}

function require_api_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        return;
    }
    $provided = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($provided === '' || !hash_equals(auth_csrf_token(), $provided)) {
        send_error('Your session expired. Refresh and try again.', 403);
    }
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_super_admin(): bool
{
    return is_logged_in() && (current_user()['role'] ?? '') === 'super_admin';
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function require_api_login(): void
{
    if (!is_logged_in()) {
        send_error('Login required.', 401);
    }
}

function require_super_admin(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
    if (!is_super_admin()) {
        http_response_code(403);
        echo 'Super admin access required.';
        exit;
    }
}
