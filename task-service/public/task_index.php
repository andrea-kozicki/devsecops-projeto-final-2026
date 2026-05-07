<?php
declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

require_once __DIR__ . '/../src/task_db.php';
require_once __DIR__ . '/../src/task_helpers.php';
require_once __DIR__ . '/../src/task_router.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = normalizeTaskPath($_SERVER['REQUEST_URI'] ?? '/');

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($method === 'GET' && $path === '/health') {
    jsonResponse(200, [
        'success' => true,
        'message' => 'Task service OK',
    ]);
}

$config = require __DIR__ . '/../src/task_config.php';
$pdo = getTaskPDO($config);

dispatchTask($pdo, $config);