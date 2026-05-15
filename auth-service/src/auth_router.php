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

    if ($path === '/health') {
        if ($method !== 'GET') {
            methodNotAllowed(['GET']);
        }
        authHealthCheck();
    }

    if ($path === '/ready') {
        if ($method !== 'GET') {
            methodNotAllowed(['GET']);
        }
        authReadyCheck($pdo);
    }

    if ($path === '/auth/register') {
        if ($method !== 'POST') {
            methodNotAllowed(['POST']);
        }
        registerUser($pdo);
    }

    if ($path === '/auth/login') {
        if ($method !== 'POST') {
            methodNotAllowed(['POST']);
        }
        loginUser($pdo, $config);
    }

    if ($path === '/auth/me') {
        if ($method === 'GET') {
            getProfile($pdo);
        }

        if ($method === 'PUT') {
            updateProfile($pdo);
        }

        methodNotAllowed(['GET', 'PUT']);
    }

    if ($path === '/auth/logout') {
        if ($method !== 'POST') {
            methodNotAllowed(['POST']);
        }
        logoutUser($pdo);
    }

    if ($path === '/admin/users') {
        if ($method !== 'GET') {
            methodNotAllowed(['GET']);
        }

        listUsersAction($pdo);
    }

    if (preg_match('#^/admin/users/(\d+)/block$#', $path, $matches)) {
    if ($method !== 'POST') {
        methodNotAllowed(['POST']);
    }

    blockUserAction($pdo, (int) $matches[1]);


    if (preg_match('#^/admin/users/(\d+)/reactivate$#', $path, $matches)) {
        if ($method !== 'POST') {
            methodNotAllowed(['POST']);
        }

        reactivateUserAction($pdo, (int) $matches[1]);
    }

    if ($path === '/admin/audit') {
        if ($method !== 'GET') {
            methodNotAllowed(['GET']);
        }

        listAuditLogsAction($pdo);
    }
}
    jsonResponse(404, [
        'success' => false,
        'message' => 'Rota não encontrada.',
        'errors' => [],
    ]);
}