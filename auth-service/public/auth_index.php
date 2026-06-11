<?php
declare(strict_types=1);

header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

require_once __DIR__ . '/../src/auth_db.php';
require_once __DIR__ . '/../src/auth_helpers.php';
require_once __DIR__ . '/../src/auth_router.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = authNormalizePath($_SERVER['REQUEST_URI'] ?? '/');

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($method === 'GET' && $path === '/health') {
    authJsonResponse(200, [
        'success' => true,
        'message' => 'Auth service OK',
    ]);
}

try {
    $config = require __DIR__ . '/../src/auth_config.php';
    $pdo = getAuthPDO($config);

    dispatchAuth($pdo, $config);
} catch (Throwable $e) {
    error_log('[auth-service] erro interno: ' . $e->getMessage());

    authJsonResponse(500, [
        'success' => false,
        'message' => 'Erro interno do servidor.',
        'errors' => [],
    ]);
}