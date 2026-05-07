<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_controller.php';
require_once __DIR__ . '/auth_helpers.php';

function dispatchAuth(PDO $pdo, array $config): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = normalizeAuthPath($_SERVER['REQUEST_URI'] ?? '/');

    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    if ($method === 'GET' && $path === '/health') {
        authHealthCheck();
    }

    if ($method === 'POST' && $path === '/auth/register') {
        registerUser($pdo);
    }

    if ($method === 'POST' && $path === '/auth/login') {
        loginUser($pdo, $config);
    }

    if ($method === 'GET' && $path === '/auth/me') {
        getProfile($pdo);
    }

    if ($method === 'POST' && $path === '/auth/logout') {
        logoutUser($pdo);
    }

    jsonResponse(404, [
        'success' => false,
        'message' => 'Rota não encontrada.',
        'errors' => [],
    ]);
}