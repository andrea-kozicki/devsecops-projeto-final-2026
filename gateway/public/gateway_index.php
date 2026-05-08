<?php
declare(strict_types=1);

$allowedOrigin = 'http://studyboard.local';

if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] === $allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Vary: Origin');
}

header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

require_once __DIR__ . '/../src/gateway_helpers.php';
require_once __DIR__ . '/../src/gateway_router.php';

$config = require __DIR__ . '/../src/gateway_config.php';

try {
    dispatchGateway($config);
} catch (Throwable $e) {
    error_log('[gateway] erro interno: ' . $e->getMessage());

    jsonResponse(500, [
        'success' => false,
        'message' => 'Erro interno do servidor.',
        'errors' => [],
    ]);
}