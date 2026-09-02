<?php
require __DIR__ . '/auth.php';

try {
    require_api_login();
    $pdo = pdo_connect();
    create_tasks_table($pdo);
    create_uploads_table($pdo);

    $method = $_SERVER['REQUEST_METHOD'];
    $id = $_GET['id'] ?? null;
    if ($id === null && preg_match('#/api/tasks/(\d+)#', $_SERVER['REQUEST_URI'], $matches)) {
        $id = $matches[1];
    }

    if ($method === 'GET' && $id === null) {
        $rows = $pdo->query('SELECT * FROM tasks ORDER BY id ASC')->fetchAll();
        send_json(attach_uploads_to_tasks($pdo, array_map('row_to_task', $rows)));
    }

    if ($method === 'POST' && $id === null) {
        $data = json_body();
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            send_error('Task name is required.', 400);
        }

        $stmt = $pdo->prepare('
            INSERT INTO tasks (name, assignee, due_date, effort, priority, status, type, `desc`, updated_at)
            VALUES (:name, :assignee, :due_date, :effort, :priority, :status, :type, :desc, NOW())
        ');
        $stmt->execute([
            ':name' => $name,
            ':assignee' => trim($data['assignee'] ?? ''),
            ':due_date' => parse_due_date($data['dueDate'] ?? ''),
            ':effort' => $data['effort'] ?? '',
            ':priority' => $data['priority'] ?? '',
            ':status' => $data['status'] ?? 'Not started',
            ':type' => trim($data['type'] ?? ''),
            ':desc' => trim($data['desc'] ?? ''),
        ]);

        $stmt = $pdo->prepare('SELECT * FROM tasks WHERE id = ?');
        $stmt->execute([$pdo->lastInsertId()]);
        send_json(attach_uploads_to_tasks($pdo, [row_to_task($stmt->fetch())])[0], 201);
    }

    if ($method === 'PUT' && $id !== null) {
        $data = json_body();
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            send_error('Task name is required.', 400);
        }

        $stmt = $pdo->prepare('
            UPDATE tasks
               SET name = :name,
                   assignee = :assignee,
                   due_date = :due_date,
                   effort = :effort,
                   priority = :priority,
                   status = :status,
                   type = :type,
                   `desc` = :desc,
                   updated_at = NOW()
             WHERE id = :id
        ');
        $stmt->execute([
            ':name' => $name,
            ':assignee' => trim($data['assignee'] ?? ''),
            ':due_date' => parse_due_date($data['dueDate'] ?? ''),
            ':effort' => $data['effort'] ?? '',
            ':priority' => $data['priority'] ?? '',
            ':status' => $data['status'] ?? 'Not started',
            ':type' => trim($data['type'] ?? ''),
            ':desc' => trim($data['desc'] ?? ''),
            ':id' => (int) $id,
        ]);

        $stmt = $pdo->prepare('SELECT * FROM tasks WHERE id = ?');
        $stmt->execute([(int) $id]);
        $row = $stmt->fetch();
        if (!$row) {
            send_error('Task not found.', 404);
        }
        send_json(attach_uploads_to_tasks($pdo, [row_to_task($row)])[0]);
    }

    if ($method === 'DELETE' && $id !== null) {
        $stmt = $pdo->prepare('SELECT stored_name FROM uploads WHERE task_id = ?');
        $stmt->execute([(int) $id]);
        foreach ($stmt->fetchAll() as $upload) {
            $path = __DIR__ . '/uploads/' . $upload['stored_name'];
            if (is_file($path)) {
                unlink($path);
            }
        }
        $stmt = $pdo->prepare('DELETE FROM uploads WHERE task_id = ?');
        $stmt->execute([(int) $id]);
        $stmt = $pdo->prepare('DELETE FROM tasks WHERE id = ?');
        $stmt->execute([(int) $id]);
        http_response_code(204);
        exit;
    }

    send_error('Route not found.', 404);
} catch (Throwable $e) {
    send_error($e->getMessage(), 500);
}
