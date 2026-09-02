<?php
require __DIR__ . '/auth.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    echo 'File not found';
    exit;
}

$pdo = pdo_connect();
create_uploads_table($pdo);

$stmt = $pdo->prepare('SELECT * FROM uploads WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$file = $stmt->fetch();

if (!$file) {
    http_response_code(404);
    echo 'File not found';
    exit;
}

$path = __DIR__ . '/uploads/' . $file['stored_name'];
if (!is_file($path)) {
    http_response_code(404);
    echo 'File missing';
    exit;
}

header('Content-Type: ' . ($file['mime_type'] ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . addslashes($file['original_name']) . '"');
readfile($path);
exit;
