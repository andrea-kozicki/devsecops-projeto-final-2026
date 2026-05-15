<?php
declare(strict_types=1);

require_once __DIR__ . '/gateway_helpers.php';
require_once __DIR__ . '/gateway_proxy.php';
require_once __DIR__ . '/gateway_auth_middleware.php';
require_once __DIR__ . '/gateway_logger.php';

function dispatchGateway(array $config): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = normalizeGatewayPath($_SERVER['REQUEST_URI'] ?? '/');

    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    if ($path === '/health') {
        if ($method !== 'GET') {
            methodNotAllowed(['GET']);
        }

        jsonResponse(200, [
            'success' => true,
            'message' => 'Gateway healthy.',
        ]);
    }

    if ($path === '/ready') {
        if ($method !== 'GET') {
            methodNotAllowed(['GET']);
        }

        gatewayReadyCheck($config);
    }

    enforceGatewayAuth($path);

    $authBase = rtrim($config['auth_service_url'], '/');
    $taskBase = rtrim($config['task_service_url'], '/');

    if ($path === '/auth/register') {
        if ($method !== 'POST') {
            methodNotAllowed(['POST']);
        }
        forwardRequest($authBase . '/auth/register');
    }

    if ($path === '/auth/login') {
        if ($method !== 'POST') {
            methodNotAllowed(['POST']);
        }
        forwardRequest($authBase . '/auth/login');
    }

    if ($path === '/auth/me') {
        if ($method === 'GET' || $method === 'PUT') {
            forwardRequest($authBase . '/auth/me');
        }

        methodNotAllowed(['GET', 'PUT']);
    }

    if ($path === '/auth/logout') {
        if ($method !== 'POST') {
            methodNotAllowed(['POST']);
        }
        forwardRequest($authBase . '/auth/logout');
    }

    if ($path === '/tasks') {
        if ($method === 'GET' || $method === 'POST') {
            forwardRequest($taskBase . '/tasks');
        }

        methodNotAllowed(['GET', 'POST']);
    }

    if (preg_match('#^/tasks/(\d+)$#', $path, $matches)) {
        $taskId = (int) $matches[1];
        $target = $taskBase . '/tasks/' . $taskId;

        if ($method === 'GET' || $method === 'PUT' || $method === 'DELETE') {
            forwardRequest($target);
        }

        methodNotAllowed(['GET', 'PUT', 'DELETE']);
    }

    if ($path === '/admin/users') {
        if ($method !== 'GET') {
            methodNotAllowed(['GET']);
        }

        forwardRequest($authBase . '/admin/users');
    }

    if (preg_match('#^/admin/users/(\d+)/block$#', $path, $matches)) {
        if ($method !== 'POST') {
            methodNotAllowed(['POST']);
        }

        forwardRequest($authBase . '/admin/users/' . (int) $matches[1] . '/block');
    }

    if (preg_match('#^/admin/users/(\d+)/reactivate$#', $path, $matches)) {
        if ($method !== 'POST') {
            methodNotAllowed(['POST']);
        }

        forwardRequest($authBase . '/admin/users/' . (int) $matches[1] . '/reactivate');
    }

    if ($path === '/admin/audit') {
        if ($method !== 'GET') {
            methodNotAllowed(['GET']);
        }

        forwardRequest($authBase . '/admin/audit');
    }

    gatewayLogWarning('Rota não encontrada no gateway', [
        'method' => $method,
        'path' => $path,
    ]);

    jsonResponse(404, [
        'success' => false,
        'message' => 'Rota não encontrada.',
        'errors' => [],
    ]);
}