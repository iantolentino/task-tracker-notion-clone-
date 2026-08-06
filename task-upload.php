<?php
require __DIR__ . '/auth.php';

try {
    require_api_login();

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
        if ($extension === 'mp4') {
            send_error('MP4 files are not allowed.', 400);
        }

        $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $storedName = bin2hex(random_bytes(16)) . ($extension ? ".{$extension}" : '');
        $target = $uploadDir . '/' . $storedName;

        if (!move_uploaded_file($tmpName, $target)) {
            send_error('Upload failed.', 500);
        }

        $mimeType = mime_content_type($target) ?: '';
        if ($mimeType === 'video/mp4') {
            unlink($target);
            send_error('MP4 files are not allowed.', 400);
        }

        $stmt = $pdo->prepare('
            INSERT INTO uploads (task_id, original_name, stored_name, mime_type, file_size, uploaded_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ');
        $stmt->execute([$taskId, $originalName, $storedName, $mimeType, filesize($target), current_user()['id'] ?? null]);

        $stmt = $pdo->prepare('SELECT * FROM uploads WHERE id = ? LIMIT 1');
        $stmt->execute([$pdo->lastInsertId()]);
        $saved[] = upload_to_array($stmt->fetch());
    }

    send_json($saved, 201);
} catch (Throwable $e) {
    send_error($e->getMessage(), 500);
}
