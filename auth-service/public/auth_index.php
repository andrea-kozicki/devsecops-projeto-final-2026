<?php
declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

require_once __DIR__ . '/../src/auth_db.php';
require_once __DIR__ . '/../src/auth_helpers.php';
require_once __DIR__ . '/../src/auth_router.php';

$config = require __DIR__ . '/../src/auth_config.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = normalizeAuthPath($_SERVER['REQUEST_URI'] ?? '/');

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($method === 'GET' && $path === '/health') {
    jsonResponse(200, [
        'success' => true,
        'message' => 'Auth service OK',
    ]);
}

$pdo = getAuthPDO($config);
dispatchAuth($pdo, $config);