<?php
declare(strict_types=1);

require_once __DIR__ . '/task_repository.php';
require_once __DIR__ . '/task_audit_repository.php';
require_once __DIR__ . '/task_helpers.php';
require_once __DIR__ . '/task_logger.php';

function createTaskAction(PDO $pdo, array $config): void
{
    $user = taskRequireAuthenticatedUser($config);
    $data = taskGetJsonInput();

    $errors = taskRequireFields($data, ['title']);

    $title = trim((string) ($data['title'] ?? ''));
    $description = trim((string) ($data['description'] ?? ''));
    $priority = trim((string) ($data['priority'] ?? 'media'));
    $status = trim((string) ($data['status'] ?? 'pendente'));
    $dueDate = trim((string) ($data['due_date'] ?? ''));

    if ($title === '') {
        $errors['title'] = 'O título é obrigatório.';
    } elseif (mb_strlen($title) > 150) {
        $errors['title'] = 'O título deve ter no máximo 150 caracteres.';
    } elseif (taskContainsHtmlMarkup($title)) {
        $errors['title'] = 'O título não pode conter marcação HTML.';
    }

    if (mb_strlen($description) > 2000) {
        $errors['description'] = 'A descrição deve ter no máximo 2000 caracteres.';
    } elseif ($description !== '' && taskContainsHtmlMarkup($description)) {
        $errors['description'] = 'A descrição não pode conter marcação HTML.';
    }

    if (!taskIsValidPriority($priority)) {
        $errors['priority'] = 'Prioridade inválida.';
    }

    if (!taskIsValidStatus($status)) {
        $errors['status'] = 'Status inválido.';
    }

    if (!taskIsValidDate($dueDate)) {
        $errors['due_date'] = 'Data inválida. Use o formato YYYY-MM-DD.';
    }

    if (!empty($errors)) {
        taskLogWarning('Falha de validação ao criar tarefa', [
            'user_id' => (int) $user['id'],
            'errors' => $errors,
        ]);

        taskJsonResponse(422, [
            'success' => false,
            'message' => 'Dados inválidos.',
            'errors' => $errors,
        ]);
    }

    $taskId = createTask($pdo, (int) $user['id'], [
        'title' => $title,
        'description' => $description,
        'priority' => $priority,
        'status' => $status,
        'due_date' => $dueDate,
    ]);

    $task = findTaskByIdForUser($pdo, $taskId, (int) $user['id']);

    createTaskAuditLog(
        $pdo,
        (int) $user['id'],
        $taskId,
        'task_created',
        taskClientIp(),
        [
            'title' => $title,
            'priority' => $priority,
            'status' => $status,
            'due_date' => $dueDate !== '' ? $dueDate : null,
        ]
    );

    taskLogInfo('Tarefa criada', [
        'user_id' => (int) $user['id'],
        'task_id' => $taskId,
        'title' => $title,
    ]);

    taskJsonResponse(201, [
        'success' => true,
        'message' => 'Tarefa criada com sucesso.',
        'data' => $task,
    ]);
}

function listTasksAction(PDO $pdo, array $config): void
{
    $user = taskRequireAuthenticatedUser($config);

    $filters = [
        'status' => $_GET['status'] ?? null,
        'priority' => $_GET['priority'] ?? null,
        'due_date' => $_GET['due_date'] ?? null,
    ];

    if (!empty($filters['status']) && !taskIsValidStatus((string) $filters['status'])) {
        taskLogWarning('Filtro de status inválido', [
            'user_id' => (int) $user['id'],
            'status' => $filters['status'],
        ]);

        taskJsonResponse(422, [
            'success' => false,
            'message' => 'Filtro de status inválido.',
            'errors' => ['status' => 'Status inválido.'],
        ]);
    }

    if (!empty($filters['priority']) && !taskIsValidPriority((string) $filters['priority'])) {
        taskLogWarning('Filtro de prioridade inválido', [
            'user_id' => (int) $user['id'],
            'priority' => $filters['priority'],
        ]);

        taskJsonResponse(422, [
            'success' => false,
            'message' => 'Filtro de prioridade inválido.',
            'errors' => ['priority' => 'Prioridade inválida.'],
        ]);
    }

    if (!empty($filters['due_date']) && !taskIsValidDate((string) $filters['due_date'])) {
        taskLogWarning('Filtro de prazo inválido', [
            'user_id' => (int) $user['id'],
            'due_date' => $filters['due_date'],
        ]);

        taskJsonResponse(422, [
            'success' => false,
            'message' => 'Filtro de prazo inválido.',
            'errors' => ['due_date' => 'Data inválida.'],
        ]);
    }

    $tasks = listTasks($pdo, (int) $user['id'], $filters);

    $hasFilters =
        !empty($filters['status']) ||
        !empty($filters['priority']) ||
        !empty($filters['due_date']);

    createTaskAuditLog(
        $pdo,
        (int) $user['id'],
        null,
        $hasFilters ? 'tasks_filtered' : 'tasks_listed',
        taskClientIp(),
        [
            'filters' => $filters,
            'count' => count($tasks),
        ]
    );

    taskLogInfo('Tarefas listadas', [
        'user_id' => (int) $user['id'],
        'filters' => $filters,
        'count' => count($tasks),
    ]);

    taskJsonResponse(200, [
        'success' => true,
        'message' => 'Tarefas carregadas com sucesso.',
        'data' => $tasks,
    ]);
}

function getTaskAction(PDO $pdo, array $config, int $taskId): void
{
    $user = taskRequireAuthenticatedUser($config);

    $task = findTaskByIdForUser($pdo, $taskId, (int) $user['id']);

    if ($task === null) {
        taskLogWarning('Tarefa não encontrada', [
            'user_id' => (int) $user['id'],
            'task_id' => $taskId,
        ]);

        taskJsonResponse(404, [
            'success' => false,
            'message' => 'Tarefa não encontrada.',
            'errors' => [],
        ]);
    }

    createTaskAuditLog(
        $pdo,
        (int) $user['id'],
        $taskId,
        'task_viewed',
        taskClientIp(),
        [
            'title' => $task['title'] ?? null,
        ]
    );

    taskLogInfo('Tarefa consultada', [
        'user_id' => (int) $user['id'],
        'task_id' => $taskId,
    ]);

    taskJsonResponse(200, [
        'success' => true,
        'message' => 'Tarefa carregada com sucesso.',
        'data' => $task,
    ]);
}

function updateTaskAction(PDO $pdo, array $config, int $taskId): void
{
    $user = taskRequireAuthenticatedUser($config);
    $data = taskGetJsonInput();

    $errors = [];

    if (array_key_exists('title', $data)) {
        $title = trim((string) $data['title']);

        if ($title === '') {
            $errors['title'] = 'O título é obrigatório.';
        } elseif (mb_strlen($title) > 150) {
            $errors['title'] = 'O título deve ter no máximo 150 caracteres.';
        } elseif (taskContainsHtmlMarkup($title)) {
            $errors['title'] = 'O título não pode conter marcação HTML.';
        }

        $data['title'] = $title;
    }

    if (array_key_exists('description', $data)) {
        $description = trim((string) $data['description']);

        if (mb_strlen($description) > 2000) {
            $errors['description'] = 'A descrição deve ter no máximo 2000 caracteres.';
        } elseif ($description !== '' && taskContainsHtmlMarkup($description)) {
            $errors['description'] = 'A descrição não pode conter marcação HTML.';
        }

        $data['description'] = $description;
    }

    if (array_key_exists('priority', $data) && !taskIsValidPriority((string) $data['priority'])) {
        $errors['priority'] = 'Prioridade inválida.';
    }

    if (array_key_exists('status', $data) && !taskIsValidStatus((string) $data['status'])) {
        $errors['status'] = 'Status inválido.';
    }

    if (array_key_exists('due_date', $data) && !taskIsValidDate((string) $data['due_date'])) {
        $errors['due_date'] = 'Data inválida. Use o formato YYYY-MM-DD.';
    }

    if (!empty($errors)) {
        taskLogWarning('Falha de validação ao atualizar tarefa', [
            'user_id' => (int) $user['id'],
            'task_id' => $taskId,
            'errors' => $errors,
        ]);

        taskJsonResponse(422, [
            'success' => false,
            'message' => 'Dados inválidos.',
            'errors' => $errors,
        ]);
    }

    $existingTask = findTaskByIdForUser($pdo, $taskId, (int) $user['id']);

    if ($existingTask === null) {
        taskLogWarning('Tentativa de atualizar tarefa inexistente', [
            'user_id' => (int) $user['id'],
            'task_id' => $taskId,
        ]);

        taskJsonResponse(404, [
            'success' => false,
            'message' => 'Tarefa não encontrada.',
            'errors' => [],
        ]);
    }

    $updated = updateTaskForUser($pdo, $taskId, (int) $user['id'], $data);
    $task = findTaskByIdForUser($pdo, $taskId, (int) $user['id']);

    createTaskAuditLog(
        $pdo,
        (int) $user['id'],
        $taskId,
        'task_updated',
        taskClientIp(),
        [
            'fields' => array_keys($data),
            'updated' => $updated,
            'title' => $task['title'] ?? ($existingTask['title'] ?? null),
        ]
    );

    taskLogInfo('Tarefa atualizada', [
        'user_id' => (int) $user['id'],
        'task_id' => $taskId,
        'fields' => array_keys($data),
    ]);

    taskJsonResponse(200, [
        'success' => true,
        'message' => 'Tarefa atualizada com sucesso.',
        'data' => $task,
    ]);
}

function deleteTaskAction(PDO $pdo, array $config, int $taskId): void
{
    $user = taskRequireAuthenticatedUser($config);

    $existingTask = findTaskByIdForUser($pdo, $taskId, (int) $user['id']);

    if ($existingTask === null) {
        taskLogWarning('Tentativa de excluir tarefa inexistente', [
            'user_id' => (int) $user['id'],
            'task_id' => $taskId,
        ]);

        taskJsonResponse(404, [
            'success' => false,
            'message' => 'Tarefa não encontrada.',
            'errors' => [],
        ]);
    }

    $deleted = deleteTaskForUser($pdo, $taskId, (int) $user['id']);

    if (!$deleted) {
        taskLogWarning('Falha ao excluir tarefa', [
            'user_id' => (int) $user['id'],
            'task_id' => $taskId,
        ]);

        taskJsonResponse(500, [
            'success' => false,
            'message' => 'Falha ao excluir tarefa.',
            'errors' => [],
        ]);
    }

    createTaskAuditLog(
        $pdo,
        (int) $user['id'],
        $taskId,
        'task_deleted',
        taskClientIp(),
        [
            'title' => $existingTask['title'] ?? null,
            'priority' => $existingTask['priority'] ?? null,
            'status' => $existingTask['status'] ?? null,
        ]
    );

    taskLogInfo('Tarefa excluída', [
        'user_id' => (int) $user['id'],
        'task_id' => $taskId,
    ]);

    taskJsonResponse(200, [
        'success' => true,
        'message' => 'Tarefa excluída com sucesso.',
    ]);
}

function taskReadyCheck(PDO $pdo): void
{
    $pdo->query('SELECT 1');

    taskJsonResponse(200, [
        'success' => true,
        'message' => 'Task service ready.',
    ]);
}