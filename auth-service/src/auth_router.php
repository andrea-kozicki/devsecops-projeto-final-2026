<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_controller.php';
require_once __DIR__ . '/auth_helpers.php';

function dispatchAuth(PDO $pdo, array $config): void
{
    try {
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
            return;
        }

        if ($path === '/ready') {
            if ($method !== 'GET') {
                authMethodNotAllowed(['GET']);
            }
            authReadyCheck($pdo);
            return;
        }

        if ($path === '/auth/register') {
            if ($method !== 'POST') {
                authMethodNotAllowed(['POST']);
            }
            registerUser($pdo);
            return;
        }

        if ($path === '/auth/login') {
            if ($method !== 'POST') {
                authMethodNotAllowed(['POST']);
            }
            loginUser($pdo, $config);
            return;
        }

        if ($path === '/auth/me') {
            if ($method === 'GET') {
                getProfile($pdo);
                return;
            }

            if ($method === 'PUT') {
                updateProfile($pdo);
                return;
            }

            authMethodNotAllowed(['GET', 'PUT']);
        }

        if ($path === '/auth/logout') {
            if ($method !== 'POST') {
                authMethodNotAllowed(['POST']);
            }
            logoutUser($pdo);
            return;
        }

        if ($path === '/auth/password/forgot') {
            if ($method !== 'POST') {
                authMethodNotAllowed(['POST']);
            }
            forgotPasswordAction($pdo);
            return;
        }

        if ($path === '/auth/password/reset') {
            if ($method !== 'POST') {
                authMethodNotAllowed(['POST']);
            }
            resetPasswordAction($pdo);
            return;
        }

        if ($path === '/auth/mfa/status') {
            if ($method !== 'GET') {
                authMethodNotAllowed(['GET']);
            }
            mfaStatusAction($pdo);
            return;
        }

        if ($path === '/auth/mfa/setup') {
            if ($method !== 'POST') {
                authMethodNotAllowed(['POST']);
            }
            mfaSetupAction($pdo);
            return;
        }

        if ($path === '/auth/mfa/enable') {
            if ($method !== 'POST') {
                authMethodNotAllowed(['POST']);
            }
            mfaEnableAction($pdo);
            return;
        }

        if ($path === '/auth/mfa/disable') {
            if ($method !== 'POST') {
                authMethodNotAllowed(['POST']);
            }
            mfaDisableAction($pdo);
            return;
        }

        if ($path === '/auth/mfa/verify-login') {
            if ($method !== 'POST') {
                authMethodNotAllowed(['POST']);
            }
            verifyLoginMfaAction($pdo, $config);
            return;
        }

        if ($path === '/admin/users') {
            if ($method !== 'GET') {
                authMethodNotAllowed(['GET']);
            }
            listUsersAction($pdo);
            return;
        }

        if (preg_match('#^/admin/users/(\d+)/block$#', $path, $matches)) {
            if ($method !== 'POST') {
                authMethodNotAllowed(['POST']);
            }
            blockUserAction($pdo, (int) $matches[1]);
            return;
        }

        if (preg_match('#^/admin/users/(\d+)/reactivate$#', $path, $matches)) {
            if ($method !== 'POST') {
                authMethodNotAllowed(['POST']);
            }
            reactivateUserAction($pdo, (int) $matches[1]);
            return;
        }

        if ($path === '/admin/audit') {
            if ($method !== 'GET') {
                authMethodNotAllowed(['GET']);
            }
            listAuditLogsAction($pdo);
            return;
        }

        authJsonResponse(404, [
            'success' => false,
            'message' => 'Rota não encontrada.',
            'errors' => [],
        ]);
    } catch (Throwable $e) {
        error_log('[AUTH ERROR] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

        authJsonResponse(500, [
            'success' => false,
            'message' => 'Erro interno no serviço de autenticação.',
            'errors' => [
                'debug' => $e->getMessage(),
            ],
        ]);
    }
}