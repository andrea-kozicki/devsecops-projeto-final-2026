<?php
declare(strict_types=1);

require_once __DIR__ . '/task_controller.php';
require_once __DIR__ . '/task_helpers.php';

function dispatchTask(PDO $pdo, array $config): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = normalizeTaskPath($_SERVER['REQUEST_URI'] ?? '/');

    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    if ($path === '/tasks') {
        if ($method === 'POST') {
            createTaskAction($pdo, $config);
        }

        if ($method === 'GET') {
            listTasksAction($pdo, $config);
        }

        methodNotAllowed(['GET', 'POST']);
    }

    if ($path === '/ready') {
        if ($method !== 'GET') {
            methodNotAllowed(['GET']);
        }
        taskReadyCheck($pdo);
    }

    if (preg_match('#^/tasks/(\d+)$#', $path, $matches)) {
        $taskId = (int) $matches[1];

        if ($method === 'GET') {
            getTaskAction($pdo, $config, $taskId);
        }

        if ($method === 'PUT') {
            updateTaskAction($pdo, $config, $taskId);
        }

        if ($method === 'DELETE') {
            deleteTaskAction($pdo, $config, $taskId);
        }

        methodNotAllowed(['GET', 'PUT', 'DELETE']);
    }

    jsonResponse(404, [
        'success' => false,
        'message' => 'Rota não encontrada.',
        'errors' => [],
    ]);
}