<?php
declare(strict_types=1);

function createTask(PDO $pdo, int $userId, array $data): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO tasks (user_id, title, description, priority, status, due_date)
         VALUES (:user_id, :title, :description, :priority, :status, :due_date)'
    );

    $stmt->execute([
        'user_id' => $userId,
        'title' => $data['title'],
        'description' => $data['description'],
        'priority' => $data['priority'],
        'status' => $data['status'],
        'due_date' => $data['due_date'] !== '' ? $data['due_date'] : null,
    ]);

    return (int) $pdo->lastInsertId();
}

function listTasks(PDO $pdo, int $userId, array $filters = []): array
{
    $sql = 'SELECT id, user_id, title, description, priority, status, due_date, created_at, updated_at
            FROM tasks
            WHERE user_id = :user_id';

    $params = ['user_id' => $userId];

    if (!empty($filters['status'])) {
        $sql .= ' AND status = :status';
        $params['status'] = $filters['status'];
    }

    if (!empty($filters['priority'])) {
        $sql .= ' AND priority = :priority';
        $params['priority'] = $filters['priority'];
    }

    if (!empty($filters['due_date'])) {
        $sql .= ' AND due_date = :due_date';
        $params['due_date'] = $filters['due_date'];
    }

    $sql .= ' ORDER BY created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll() ?: [];
}

function findTaskByIdForUser(PDO $pdo, int $taskId, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, user_id, title, description, priority, status, due_date, created_at, updated_at
         FROM tasks
         WHERE id = :id AND user_id = :user_id
         LIMIT 1'
    );

    $stmt->execute([
        'id' => $taskId,
        'user_id' => $userId,
    ]);

    $task = $stmt->fetch();

    return $task ?: null;
}

function updateTaskForUser(PDO $pdo, int $taskId, int $userId, array $data): bool
{
    $allowedFields = ['title', 'description', 'priority', 'status', 'due_date'];
    $set = [];
    $params = [
        'id' => $taskId,
        'user_id' => $userId,
    ];

    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $data)) {
            $set[] = "{$field} = :{$field}";
            $params[$field] = ($field === 'due_date' && $data[$field] === '') ? null : $data[$field];
        }
    }

    if (empty($set)) {
        return false;
    }

    $sql = 'UPDATE tasks SET ' . implode(', ', $set) . ' WHERE id = :id AND user_id = :user_id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->rowCount() > 0;
}

function deleteTaskForUser(PDO $pdo, int $taskId, int $userId): bool
{
    $stmt = $pdo->prepare(
        'DELETE FROM tasks
         WHERE id = :id AND user_id = :user_id'
    );

    $stmt->execute([
        'id' => $taskId,
        'user_id' => $userId,
    ]);

    return $stmt->rowCount() > 0;
}