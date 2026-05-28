<?php
declare(strict_types=1);

function gatewayJsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function gatewayNormalizePath(?string $requestUri): string
{
    $path = parse_url($requestUri ?? '/', PHP_URL_PATH) ?: '/';

    $prefixes = [
        '/api',
        '/gateway/public',
        '/gateway',
    ];

    foreach ($prefixes as $prefix) {
        if (str_starts_with($path, $prefix)) {
            $path = substr($path, strlen($prefix));
            break;
        }
    }

    $path = '/' . ltrim($path, '/');

    return $path === '//' ? '/' : (rtrim($path, '/') ?: '/');
}

function gatewayGetBearerToken(): ?string
{
    $authorization = null;

    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authorization = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['Authorization'])) {
        $authorization = $_SERVER['Authorization'];
    } elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        $authorization = $headers['Authorization'] ?? $headers['authorization'] ?? null;
    }

    if (!$authorization) {
        return null;
    }

    if (!preg_match('/Bearer\s+(.+)/i', $authorization, $matches)) {
        return null;
    }

    $token = trim($matches[1]);
    return $token !== '' ? $token : null;
}

function gatewayGetCurrentQueryString(): string
{
    $query = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
    return is_string($query) && $query !== '' ? $query : '';
}

function gatewayMethodNotAllowed(array $allowedMethods): void
{
    header('Allow: ' . implode(', ', $allowedMethods));

    gatewayJsonResponse(405, [
        'success' => false,
        'message' => 'Método não permitido.',
        'errors' => [],
    ]);
}

function gatewayGetHttpStatusFromHeaders(array $headers): int
{
    if (!isset($headers[0])) {
        return 0;
    }

    if (preg_match('#\s(\d{3})\s#', $headers[0], $matches)) {
        return (int) $matches[1];
    }

    return 0;
}

function gatewayRemoteHealthOk(string $url): bool
{
    $headers = @get_headers(rtrim($url, '/') . '/health');
    if ($headers === false || !is_array($headers)) {
        return false;
    }

    return gatewayGetHttpStatusFromHeaders($headers) === 200;
}

function gatewayReadyCheck(array $config): void
{
    $authOk = gatewayRemoteHealthOk($config['auth_service_url']);
    $taskOk = gatewayRemoteHealthOk($config['task_service_url']);

    if (!$authOk || !$taskOk) {
        gatewayJsonResponse(503, [
            'success' => false,
            'message' => 'Gateway não está pronto.',
            'errors' => [
                'auth_service' => $authOk ? 'ok' : 'indisponível',
                'task_service' => $taskOk ? 'ok' : 'indisponível',
            ],
        ]);
    }

    gatewayJsonResponse(200, [
        'success' => true,
        'message' => 'Gateway ready.',
    ]);
}



