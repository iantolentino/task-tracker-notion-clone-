<?php
require __DIR__ . '/auth.php';

try {
    require_api_login();
    require_api_csrf();
    $pdo = pdo_connect();
    create_tasks_table($pdo);
    create_uploads_table($pdo);
    create_tickets_table($pdo);
    create_task_todos_table($pdo);

    $method = $_SERVER['REQUEST_METHOD'];
    $id = $_GET['id'] ?? null;
    if ($id === null && preg_match('#/api/tasks/(\d+)#', $_SERVER['REQUEST_URI'], $matches)) {
        $id = $matches[1];
    }

    if ($method === 'GET' && $id === null) {
        $rows = $pdo->query('SELECT * FROM tasks ORDER BY id ASC')->fetchAll();
        $items = attach_uploads_to_tasks($pdo, array_map('row_to_task', $rows));
        $tickets = attach_uploads_to_tickets($pdo, $pdo->query('SELECT id, requester_name, requester_email, assignee, due_date, ticket_type, estimated_cost, subject, category, priority, description, status, effort, business_impact, other_type, created_at, updated_at FROM tickets ORDER BY id ASC')->fetchAll());
        foreach ($tickets as $ticket) {
            // A ticket becomes a task only after admin triage configures both fields.
            if (trim((string) ($ticket['priority'] ?? '')) === '' || trim((string) ($ticket['effort'] ?? '')) === '') {
                continue;
            }
            $items[] = [
                'id' => 'ticket-' . (int) $ticket['id'],
                'name' => $ticket['subject'],
                'assignee' => $ticket['assignee'] . ' / ' . $ticket['requester_name'] . ' (' . $ticket['requester_email'] . ')',
                'ticketAssignee' => $ticket['assignee'],
                'requesterName' => $ticket['requester_name'],
                'requesterEmail' => $ticket['requester_email'],
                'dueDate' => $ticket['due_date'],
                'effort' => $ticket['effort'] ?? '',
                'priority' => $ticket['priority'],
                'status' => $ticket['status'],
                'type' => 'Ticket - ' . $ticket['category'],
                'estimatedCost' => $ticket['estimated_cost'] ?? '',
                'ticketId' => (int) $ticket['id'],
                'desc' => $ticket['description'],
                'created_at' => $ticket['created_at'],
                'updated_at' => $ticket['updated_at'],
                'uploads' => $ticket['uploads'],
                'isTicket' => true,
                'ticketReference' => ticket_reference((int) $ticket['id']),
                'category' => $ticket['category'],
                'businessImpact' => $ticket['business_impact'] ?? '',
                'otherType' => $ticket['other_type'] ?? '',
            ];
        }
        send_json($items);
    }

    if ($method === 'POST' && $id === null) {
        $data = json_body();
        $task = normalize_task_input($data);

        $stmt = $pdo->prepare('
            INSERT INTO tasks (name, assignee, due_date, effort, priority, status, type, `desc`, updated_at)
            VALUES (:name, :assignee, :due_date, :effort, :priority, :status, :type, :desc, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([
            ':name' => $task['name'],
            ':assignee' => $task['assignee'],
            ':due_date' => $task['due_date'],
            ':effort' => $task['effort'],
            ':priority' => $task['priority'],
            ':status' => $task['status'],
            ':type' => $task['type'],
            ':desc' => $task['desc'],
        ]);

        $stmt = $pdo->prepare('SELECT * FROM tasks WHERE id = ?');
        $stmt->execute([$pdo->lastInsertId()]);
        send_json(attach_uploads_to_tasks($pdo, [row_to_task($stmt->fetch())])[0], 201);
    }

    if ($method === 'PUT' && $id !== null) {
        $data = json_body();
        $task = normalize_task_input($data);

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
                   updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
        ');
        $stmt->execute([
            ':name' => $task['name'],
            ':assignee' => $task['assignee'],
            ':due_date' => $task['due_date'],
            ':effort' => $task['effort'],
            ':priority' => $task['priority'],
            ':status' => $task['status'],
            ':type' => $task['type'],
            ':desc' => $task['desc'],
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
        $pdo->prepare('DELETE FROM task_todos WHERE task_id = ?')->execute([(int) $id]);
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
} catch (InvalidArgumentException $e) {
    send_error($e->getMessage(), 400);
} catch (Throwable $e) {
    error_log($e->getMessage());
    send_error('The request could not be completed.', 500);
}
