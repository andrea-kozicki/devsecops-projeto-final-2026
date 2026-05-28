<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_controller.php';
require_once __DIR__ . '/auth_helpers.php';

function dispatchAuth(PDO $pdo, array $config): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = authNormalizePath($_SERVER['REQUEST_URI'] ?? '/');

    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    if ($path === '/health') {
        if ($method !== 'GET') {
            authMethodNotAllowed(['GET']);
        }
        authHealthCheck();
    }

    if ($path === '/ready') {
        if ($method !== 'GET') {
            authMethodNotAllowed(['GET']);
        }
        authReadyCheck($pdo);
    }

    if ($path === '/auth/register') {
        if ($method !== 'POST') {
            authMethodNotAllowed(['POST']);
        }
        registerUser($pdo);
    }

    if ($path === '/auth/login') {
        if ($method !== 'POST') {
            authMethodNotAllowed(['POST']);
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

        authMethodNotAllowed(['GET', 'PUT']);
    }

    if ($path === '/auth/logout') {
        if ($method !== 'POST') {
            authMethodNotAllowed(['POST']);
        }
        logoutUser($pdo);
    }

    if ($path === '/auth/password/forgot') {
        if ($method !== 'POST') {
            authMethodNotAllowed(['POST']);
        }
        forgotPasswordAction($pdo);
    }

    if ($path === '/auth/password/reset') {
        if ($method !== 'POST') {
            authMethodNotAllowed(['POST']);
        }
        resetPasswordAction($pdo);
    }

    if ($path === '/auth/mfa/status') {
        if ($method !== 'GET') {
            authMethodNotAllowed(['GET']);
        }
        mfaStatusAction($pdo);
    }

    if ($path === '/auth/mfa/setup') {
        if ($method !== 'POST') {
            authMethodNotAllowed(['POST']);
        }
        mfaSetupAction($pdo);
    }

    if ($path === '/auth/mfa/enable') {
        if ($method !== 'POST') {
            authMethodNotAllowed(['POST']);
        }
        mfaEnableAction($pdo);
    }

    if ($path === '/auth/mfa/disable') {
        if ($method !== 'POST') {
            authMethodNotAllowed(['POST']);
        }
        mfaDisableAction($pdo);
    }

    if ($path === '/auth/mfa/verify-login') {
        if ($method !== 'POST') {
            authMethodNotAllowed(['POST']);
        }
        verifyLoginMfaAction($pdo, $config);
    }

    if ($path === '/admin/users') {
        if ($method !== 'GET') {
            authMethodNotAllowed(['GET']);
        }
        listUsersAction($pdo);
    }

    if (preg_match('#^/admin/users/(\d+)/block$#', $path, $matches)) {
        if ($method !== 'POST') {
            authMethodNotAllowed(['POST']);
        }
        blockUserAction($pdo, (int) $matches[1]);
    }

    if (preg_match('#^/admin/users/(\d+)/reactivate$#', $path, $matches)) {
        if ($method !== 'POST') {
            authMethodNotAllowed(['POST']);
        }
        reactivateUserAction($pdo, (int) $matches[1]);
    }

    if ($path === '/admin/audit') {
        if ($method !== 'GET') {
            authMethodNotAllowed(['GET']);
        }
        listAuditLogsAction($pdo);
    }

    authJsonResponse(404, [
        'success' => false,
        'message' => 'Rota não encontrada.',
        'errors' => [],
    ]);
}