<?php
declare(strict_types=1);

function jsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function normalizeGatewayPath(?string $requestUri): string
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

function getBearerToken(): ?string
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

function getCurrentQueryString(): string
{
    $query = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
    return is_string($query) && $query !== '' ? $query : '';
}

function methodNotAllowed(array $allowedMethods): void
{
    header('Allow: ' . implode(', ', $allowedMethods));

    jsonResponse(405, [
        'success' => false,
        'message' => 'Método não permitido.',
        'errors' => [],
    ]);
}

function getHttpStatusFromHeaders(array $headers): int
{
    if (!isset($headers[0])) {
        return 0;
    }

    if (preg_match('#\s(\d{3})\s#', $headers[0], $matches)) {
        return (int) $matches[1];
    }

    return 0;
}

function remoteHealthOk(string $url): bool
{
    $headers = @get_headers(rtrim($url, '/') . '/health');
    if ($headers === false || !is_array($headers)) {
        return false;
    }

    return getHttpStatusFromHeaders($headers) === 200;
}

function gatewayReadyCheck(array $config): void
{
    $authOk = remoteHealthOk($config['auth_service_url']);
    $taskOk = remoteHealthOk($config['task_service_url']);

    if (!$authOk || !$taskOk) {
        jsonResponse(503, [
            'success' => false,
            'message' => 'Gateway não está pronto.',
            'errors' => [
                'auth_service' => $authOk ? 'ok' : 'indisponível',
                'task_service' => $taskOk ? 'ok' : 'indisponível',
            ],
        ]);
    }

    jsonResponse(200, [
        'success' => true,
        'message' => 'Gateway ready.',
    ]);
}



