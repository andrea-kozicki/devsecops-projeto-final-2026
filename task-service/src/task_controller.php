<?php
declare(strict_types=1);

require_once __DIR__ . '/task_repository.php';
require_once __DIR__ . '/task_helpers.php';
require_once __DIR__ . '/task_logger.php';

function createTaskAction(PDO $pdo, array $config): void
{
    $user = requireAuthenticatedUser($config);
    $data = getJsonInput();

    $errors = requireFields($data, ['title']);

    $title = trim((string) ($data['title'] ?? ''));
    $description = trim((string) ($data['description'] ?? ''));
    $priority = trim((string) ($data['priority'] ?? 'media'));
    $status = trim((string) ($data['status'] ?? 'pendente'));
    $dueDate = trim((string) ($data['due_date'] ?? ''));

    if ($title === '') {
        $errors['title'] = 'O título é obrigatório.';
    }

    if (!isValidPriority($priority)) {
        $errors['priority'] = 'Prioridade inválida.';
    }

    if (!isValidStatus($status)) {
        $errors['status'] = 'Status inválido.';
    }

    if (!isValidDate($dueDate)) {
        $errors['due_date'] = 'Data inválida. Use o formato YYYY-MM-DD.';
    }

    if (!empty($errors)) {
        taskLogWarning('Falha de validação ao criar tarefa', [
            'user_id' => (int) $user['id'],
            'errors' => $errors,
        ]);

        jsonResponse(422, [
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

    taskLogInfo('Tarefa criada', [
        'user_id' => (int) $user['id'],
        'task_id' => $taskId,
        'title' => $title,
    ]);

    jsonResponse(201, [
        'success' => true,
        'message' => 'Tarefa criada com sucesso.',
        'data' => $task,
    ]);
}

function listTasksAction(PDO $pdo, array $config): void
{
    $user = requireAuthenticatedUser($config);

    $filters = [
        'status' => $_GET['status'] ?? null,
        'priority' => $_GET['priority'] ?? null,
        'due_date' => $_GET['due_date'] ?? null,
    ];

    if (!empty($filters['status']) && !isValidStatus((string) $filters['status'])) {
        taskLogWarning('Filtro de status inválido', [
            'user_id' => (int) $user['id'],
            'status' => $filters['status'],
        ]);

        jsonResponse(422, [
            'success' => false,
            'message' => 'Filtro de status inválido.',
            'errors' => ['status' => 'Status inválido.'],
        ]);
    }

    if (!empty($filters['priority']) && !isValidPriority((string) $filters['priority'])) {
        taskLogWarning('Filtro de prioridade inválido', [
            'user_id' => (int) $user['id'],
            'priority' => $filters['priority'],
        ]);

        jsonResponse(422, [
            'success' => false,
            'message' => 'Filtro de prioridade inválido.',
            'errors' => ['priority' => 'Prioridade inválida.'],
        ]);
    }

    if (!empty($filters['due_date']) && !isValidDate((string) $filters['due_date'])) {
        taskLogWarning('Filtro de prazo inválido', [
            'user_id' => (int) $user['id'],
            'due_date' => $filters['due_date'],
        ]);

        jsonResponse(422, [
            'success' => false,
            'message' => 'Filtro de prazo inválido.',
            'errors' => ['due_date' => 'Data inválida.'],
        ]);
    }

    $tasks = listTasks($pdo, (int) $user['id'], $filters);

    taskLogInfo('Tarefas listadas', [
        'user_id' => (int) $user['id'],
        'filters' => $filters,
        'count' => count($tasks),
    ]);

    jsonResponse(200, [
        'success' => true,
        'message' => 'Tarefas carregadas com sucesso.',
        'data' => $tasks,
    ]);
}

function getTaskAction(PDO $pdo, array $config, int $taskId): void
{
    $user = requireAuthenticatedUser($config);

    $task = findTaskByIdForUser($pdo, $taskId, (int) $user['id']);

    if ($task === null) {
        taskLogWarning('Tarefa não encontrada', [
            'user_id' => (int) $user['id'],
            'task_id' => $taskId,
        ]);

        jsonResponse(404, [
            'success' => false,
            'message' => 'Tarefa não encontrada.',
            'errors' => [],
        ]);
    }

    taskLogInfo('Tarefa consultada', [
        'user_id' => (int) $user['id'],
        'task_id' => $taskId,
    ]);

    jsonResponse(200, [
        'success' => true,
        'message' => 'Tarefa carregada com sucesso.',
        'data' => $task,
    ]);
}

function updateTaskAction(PDO $pdo, array $config, int $taskId): void
{
    $user = requireAuthenticatedUser($config);
    $data = getJsonInput();

    $errors = [];

    if (array_key_exists('priority', $data) && !isValidPriority((string) $data['priority'])) {
        $errors['priority'] = 'Prioridade inválida.';
    }

    if (array_key_exists('status', $data) && !isValidStatus((string) $data['status'])) {
        $errors['status'] = 'Status inválido.';
    }

    if (array_key_exists('due_date', $data) && !isValidDate((string) $data['due_date'])) {
        $errors['due_date'] = 'Data inválida. Use o formato YYYY-MM-DD.';
    }

    if (!empty($errors)) {
        taskLogWarning('Falha de validação ao atualizar tarefa', [
            'user_id' => (int) $user['id'],
            'task_id' => $taskId,
            'errors' => $errors,
        ]);

        jsonResponse(422, [
            'success' => false,
            'message' => 'Dados inválidos.',
            'errors' => $errors,
        ]);
    }

    $updated = updateTaskForUser($pdo, $taskId, (int) $user['id'], $data);

    if (!$updated) {
        $task = findTaskByIdForUser($pdo, $taskId, (int) $user['id']);

        if ($task === null) {
            taskLogWarning('Tentativa de atualizar tarefa inexistente', [
                'user_id' => (int) $user['id'],
                'task_id' => $taskId,
            ]);

            jsonResponse(404, [
                'success' => false,
                'message' => 'Tarefa não encontrada.',
                'errors' => [],
            ]);
        }
    }

    $task = findTaskByIdForUser($pdo, $taskId, (int) $user['id']);

    taskLogInfo('Tarefa atualizada', [
        'user_id' => (int) $user['id'],
        'task_id' => $taskId,
        'fields' => array_keys($data),
    ]);

    jsonResponse(200, [
        'success' => true,
        'message' => 'Tarefa atualizada com sucesso.',
        'data' => $task,
    ]);
}

function deleteTaskAction(PDO $pdo, array $config, int $taskId): void
{
    $user = requireAuthenticatedUser($config);

    $deleted = deleteTaskForUser($pdo, $taskId, (int) $user['id']);

    if (!$deleted) {
        taskLogWarning('Tentativa de excluir tarefa inexistente', [
            'user_id' => (int) $user['id'],
            'task_id' => $taskId,
        ]);

        jsonResponse(404, [
            'success' => false,
            'message' => 'Tarefa não encontrada.',
            'errors' => [],
        ]);
    }

    taskLogInfo('Tarefa excluída', [
        'user_id' => (int) $user['id'],
        'task_id' => $taskId,
    ]);

    jsonResponse(200, [
        'success' => true,
        'message' => 'Tarefa excluída com sucesso.',
    ]);
}