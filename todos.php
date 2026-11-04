<?php
require __DIR__ . '/auth.php';

try {
    require_api_login();
    require_api_csrf();
    $pdo = pdo_connect();
    create_tasks_table($pdo);
    create_tickets_table($pdo);
    create_task_todos_table($pdo);

    $method = $_SERVER['REQUEST_METHOD'];
    $id = max(0, (int) ($_GET['id'] ?? 0));
    $taskId = max(0, (int) ($_GET['task_id'] ?? 0));
    $ticketId = max(0, (int) ($_GET['ticket_id'] ?? 0));

    if ($method === 'GET') {
        if (($taskId > 0) === ($ticketId > 0)) {
            throw new InvalidArgumentException('Choose one task or ticket.');
        }
        $column = $taskId > 0 ? 'task_id' : 'ticket_id';
        $parentId = $taskId ?: $ticketId;
        $stmt = $pdo->prepare("SELECT id, task_id, ticket_id, text, completed, created_at, updated_at FROM task_todos WHERE {$column} = ? ORDER BY completed ASC, id ASC");
        $stmt->execute([$parentId]);
        send_json(array_map('todo_to_array', $stmt->fetchAll()));
    }

    $data = json_body();
    if ($method === 'POST') {
        $taskId = max(0, (int) ($data['taskId'] ?? 0));
        $ticketId = max(0, (int) ($data['ticketId'] ?? 0));
        if (($taskId > 0) === ($ticketId > 0)) {
            throw new InvalidArgumentException('Choose one task or ticket.');
        }
        $text = trim((string) ($data['text'] ?? ''));
        if ($text === '' || strlen($text) > 500) {
            throw new InvalidArgumentException('To-do text is required and must be 500 characters or fewer.');
        }
        $parentTable = $taskId > 0 ? 'tasks' : 'tickets';
        $parentId = $taskId ?: $ticketId;
        $stmt = $pdo->prepare("SELECT id FROM {$parentTable} WHERE id = ? LIMIT 1");
        $stmt->execute([$parentId]);
        if (!$stmt->fetch()) {
            send_error('Task not found.', 404);
        }
        $stmt = $pdo->prepare('INSERT INTO task_todos (task_id, ticket_id, text, completed, created_at, updated_at) VALUES (?, ?, ?, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
        $stmt->execute([$taskId ?: null, $ticketId ?: null, $text]);
        $id = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare('SELECT * FROM task_todos WHERE id = ?');
        $stmt->execute([$id]);
        send_json(todo_to_array($stmt->fetch()), 201);
    }

    if ($id <= 0) {
        throw new InvalidArgumentException('To-do item is required.');
    }
    if ($method === 'PUT') {
        $completed = !empty($data['completed']) ? 1 : 0;
        $stmt = $pdo->prepare('UPDATE task_todos SET completed = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$completed, $id]);
        if (!$stmt->rowCount()) {
            $exists = $pdo->prepare('SELECT id FROM task_todos WHERE id = ?');
            $exists->execute([$id]);
            if (!$exists->fetch()) send_error('To-do item not found.', 404);
        }
        $stmt = $pdo->prepare('SELECT * FROM task_todos WHERE id = ?');
        $stmt->execute([$id]);
        send_json(todo_to_array($stmt->fetch()));
    }

    if ($method === 'DELETE') {
        $stmt = $pdo->prepare('DELETE FROM task_todos WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->rowCount()) send_error('To-do item not found.', 404);
        http_response_code(204);
        exit;
    }

    send_error('Route not found.', 404);
} catch (InvalidArgumentException $e) {
    send_error($e->getMessage(), 400);
} catch (Throwable $e) {
    error_log($e->getMessage());
    send_error('The request could not be completed.', 500);
}

function todo_to_array(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'taskId' => $row['task_id'] === null ? null : (int) $row['task_id'],
        'ticketId' => $row['ticket_id'] === null ? null : (int) $row['ticket_id'],
        'text' => $row['text'],
        'completed' => (bool) $row['completed'],
        'createdAt' => $row['created_at'],
        'updatedAt' => $row['updated_at'],
    ];
}
