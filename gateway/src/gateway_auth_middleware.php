<?php
declare(strict_types=1);

require_once __DIR__ . '/gateway_helpers.php';

function isProtectedGatewayPath(string $path): bool
{
    if ($path === '/auth/me' || $path === '/auth/logout') {
        return true;
    }

    if ($path === '/tasks') {
        return true;
    }

    if (preg_match('#^/tasks/\d+$#', $path)) {
        return true;
    }

    return false;
}

function enforceGatewayAuth(string $path): void
{
    if (!isProtectedGatewayPath($path)) {
        return;
    }

    $token = gatewayGetBearerToken();

    if ($token === null) {
        gatewayJsonResponse(401, [
            'success' => false,
            'message' => 'Token não informado.',
            'errors' => [],
        ]);
    }
}