<?php
declare(strict_types=1);

header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

require_once __DIR__ . '/../src/task_db.php';
require_once __DIR__ . '/../src/task_helpers.php';
require_once __DIR__ . '/../src/task_router.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = taskNormalizePath($_SERVER['REQUEST_URI'] ?? '/');

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($method === 'GET' && $path === '/health') {
    taskJsonResponse(200, [
        'success' => true,
        'message' => 'Task service OK',
    ]);
}

try {
    $config = require __DIR__ . '/../src/task_config.php';
    $pdo = getTaskPDO($config);

    dispatchTask($pdo, $config);
} catch (Throwable $e) {
    error_log('[task-service] erro interno: ' . $e->getMessage());

    taskJsonResponse(500, [
        'success' => false,
        'message' => 'Erro interno do servidor.',
        'errors' => [],
    ]);
}