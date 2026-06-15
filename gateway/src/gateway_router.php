<?php
declare(strict_types=1);

require_once __DIR__ . '/gateway_helpers.php';
require_once __DIR__ . '/gateway_proxy.php';
require_once __DIR__ . '/gateway_auth_middleware.php';
require_once __DIR__ . '/gateway_logger.php';

function dispatchGateway(array $config): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = gatewayNormalizePath($_SERVER['REQUEST_URI'] ?? '/');

    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    if ($path === '/health') {
        if ($method !== 'GET') {
            gatewayMethodNotAllowed(['GET']);
        }

        gatewayJsonResponse(200, [
            'success' => true,
            'message' => 'Gateway healthy.',
        ]);
    }

    if ($path === '/ready') {
        if ($method !== 'GET') {
            gatewayMethodNotAllowed(['GET']);
        }

        gatewayReadyCheck($config);
    }

    enforceGatewayAuth($path);

    $authBase = rtrim($config['auth_service_url'], '/');
    $taskBase = rtrim($config['task_service_url'], '/');

    if ($path === '/auth/register') {
        if ($method !== 'POST') {
            gatewayMethodNotAllowed(['POST']);
        }
        forwardRequest($authBase . '/auth/register');
    }

    if ($path === '/auth/login') {
        if ($method !== 'POST') {
            gatewayMethodNotAllowed(['POST']);
        }
        forwardRequest($authBase . '/auth/login');
    }

    if ($path === '/auth/me') {
        if ($method === 'GET' || $method === 'PUT') {
            forwardRequest($authBase . '/auth/me');
        }
        gatewayMethodNotAllowed(['GET', 'PUT']);
    }

    if ($path === '/auth/logout') {
        if ($method !== 'POST') {
            gatewayMethodNotAllowed(['POST']);
        }
        forwardRequest($authBase . '/auth/logout');
    }

    if ($path === '/auth/password/forgot') {
        if ($method !== 'POST') {
            gatewayMethodNotAllowed(['POST']);
        }
        forwardRequest($authBase . '/auth/password/forgot');
    }

    if ($path === '/auth/password/reset') {
        if ($method !== 'POST') {
            gatewayMethodNotAllowed(['POST']);
        }
        forwardRequest($authBase . '/auth/password/reset');
    }

    if ($path === '/auth/mfa/status') {
        if ($method !== 'GET') {
            gatewayMethodNotAllowed(['GET']);
        }
        forwardRequest($authBase . '/auth/mfa/status');
    }

    if ($path === '/auth/mfa/setup') {
        if ($method !== 'POST') {
            gatewayMethodNotAllowed(['POST']);
        }
        forwardRequest($authBase . '/auth/mfa/setup');
    }

    if ($path === '/auth/mfa/enable') {
        if ($method !== 'POST') {
            gatewayMethodNotAllowed(['POST']);
        }
        forwardRequest($authBase . '/auth/mfa/enable');
    }

    if ($path === '/auth/mfa/disable') {
        if ($method !== 'POST') {
            gatewayMethodNotAllowed(['POST']);
        }
        forwardRequest($authBase . '/auth/mfa/disable');
    }

    if ($path === '/auth/mfa/verify-login') {
        if ($method !== 'POST') {
            gatewayMethodNotAllowed(['POST']);
        }
        forwardRequest($authBase . '/auth/mfa/verify-login');
    }

    if ($path === '/tasks') {
        if ($method === 'GET' || $method === 'POST') {
            forwardRequest($taskBase . '/tasks');
        }
        gatewayMethodNotAllowed(['GET', 'POST']);
    }

    if (preg_match('#^/tasks/(\d+)$#', $path, $matches)) {
        $taskId = (int) $matches[1];
        $target = $taskBase . '/tasks/' . $taskId;

        if ($method === 'GET' || $method === 'PUT' || $method === 'DELETE') {
            forwardRequest($target);
        }
        gatewayMethodNotAllowed(['GET', 'PUT', 'DELETE']);
    }

    if ($path === '/admin/users') {
        if ($method !== 'GET') {
            gatewayMethodNotAllowed(['GET']);
        }
        forwardRequest($authBase . '/admin/users');
    }

    if (preg_match('#^/admin/users/(\d+)/block$#', $path, $matches)) {
        if ($method !== 'POST') {
            gatewayMethodNotAllowed(['POST']);
        }
        forwardRequest($authBase . '/admin/users/' . (int) $matches[1] . '/block');
    }

    if (preg_match('#^/admin/users/(\d+)/reactivate$#', $path, $matches)) {
        if ($method !== 'POST') {
            gatewayMethodNotAllowed(['POST']);
        }
        forwardRequest($authBase . '/admin/users/' . (int) $matches[1] . '/reactivate');
    }

    if ($path === '/admin/audit') {
        if ($method !== 'GET') {
            gatewayMethodNotAllowed(['GET']);
        }
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        $auditUrl = $authBase . '/admin/audit' . ($queryString !== '' ? '?' . $queryString : '');
        forwardRequest($auditUrl);
    }

    gatewayLogWarning('Rota não encontrada no gateway', [
        'method' => $method,
        'path' => $path,
    ]);

    gatewayJsonResponse(404, [
        'success' => false,
        'message' => 'Rota não encontrada.',
        'errors' => [],
    ]);
}