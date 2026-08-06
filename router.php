<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/' || $path === '/index.php') {
    require __DIR__ . '/index.php';
    return true;
}

if ($path === '/setup' || $path === '/setup.php') {
    require __DIR__ . '/setup.php';
    return true;
}

if ($path === '/db' || $path === '/db.php') {
    require __DIR__ . '/db-view.php';
    return true;
}

if (preg_match('#^/upload/(\d+)$#', $path, $matches)) {
    $_GET['id'] = $matches[1];
    require __DIR__ . '/upload.php';
    return true;
}

if ($path === '/api/tasks' || preg_match('#^/api/tasks/\d+$#', $path)) {
    require __DIR__ . '/api.php';
    return true;
}

if ($path === '/api/uploads') {
    require __DIR__ . '/task-upload.php';
    return true;
}

$file = __DIR__ . $path;
if (is_file($file)) {
    return false;
}

http_response_code(404);
echo 'Not found';
return true;
