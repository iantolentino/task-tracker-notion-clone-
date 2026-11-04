<?php
require __DIR__ . '/auth.php';

try {
    require_api_login();
    require_api_csrf();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_error('Route not found.', 404);
    }

    $taskId = (int) ($_POST['task_id'] ?? 0);
    if ($taskId <= 0) {
        send_error('Task is required.', 400);
    }

    if (!isset($_FILES['files'])) {
        send_error('Choose at least one file.', 400);
    }

    $pdo = pdo_connect();
    create_tasks_table($pdo);
    create_uploads_table($pdo);

    $stmt = $pdo->prepare('SELECT id FROM tasks WHERE id = ? LIMIT 1');
    $stmt->execute([$taskId]);
    if (!$stmt->fetch()) {
        send_error('Task not found.', 404);
    }

    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $files = $_FILES['files'];
    $count = is_array($files['name']) ? count($files['name']) : 1;
    $saved = [];

    for ($i = 0; $i < $count; $i++) {
        $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
        if ($error !== UPLOAD_ERR_OK) {
            continue;
        }

        $originalName = basename(is_array($files['name']) ? $files['name'][$i] : $files['name']);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $fileSize = (int) (is_array($files['size']) ? $files['size'][$i] : $files['size']);
        if ($fileSize <= 0 || $fileSize > 500 * 1024 * 1024) {
            send_error('Each file must be between 1 byte and 500 MB.', 400);
        }
        if (!in_array($extension, allowed_upload_extensions(), true)) {
            send_error('Unsupported file type. Use PDF, image, Office, text, or ZIP files.', 400);
        }

        $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $storedName = bin2hex(random_bytes(16)) . ($extension ? ".{$extension}" : '');
        $target = $uploadDir . '/' . $storedName;

        if (!move_uploaded_file($tmpName, $target)) {
            send_error('Upload failed.', 500);
        }

        $mimeType = mime_content_type($target) ?: 'application/octet-stream';
        if ($mimeType === 'video/mp4' || $extension === 'mp4') {
            unlink($target);
            send_error('MP4 files are not allowed.', 400);
        }

        $stmt = $pdo->prepare('
            INSERT INTO uploads (task_id, original_name, stored_name, mime_type, file_size, uploaded_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([$taskId, $originalName, $storedName, $mimeType, $fileSize, current_user()['id'] ?? null]);

        $stmt = $pdo->prepare('SELECT * FROM uploads WHERE id = ? LIMIT 1');
        $stmt->execute([$pdo->lastInsertId()]);
        $saved[] = upload_to_array($stmt->fetch());
    }

    send_json($saved, 201);
} catch (Throwable $e) {
    error_log($e->getMessage());
    send_error('The upload could not be completed.', 500);
}
