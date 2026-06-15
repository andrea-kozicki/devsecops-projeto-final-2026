<?php
declare(strict_types=1);

function taskJsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function taskGetJsonInput(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        taskJsonResponse(400, [
            'success' => false,
            'message' => 'JSON inválido.',
            'errors' => ['json' => json_last_error_msg()],
        ]);
    }

    return is_array($data) ? $data : [];
}

function taskGetBearerToken(): ?string
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

function taskNormalizePath(?string $requestUri): string
{
    $path = parse_url($requestUri ?? '/', PHP_URL_PATH) ?: '/';

    $prefixes = [
        '/internal-tasks',
        '/task-service/public',
        '/task-service',
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

function taskRequireFields(array $data, array $requiredFields): array
{
    $errors = [];

    foreach ($requiredFields as $field) {
        if (!array_key_exists($field, $data) || trim((string) $data[$field]) === '') {
            $errors[$field] = "O campo '{$field}' é obrigatório.";
        }
    }

    return $errors;
}

function taskContainsHtmlMarkup(string $value): bool
{
    return str_contains($value, '<') || str_contains($value, '>');
}

function taskIsValidPriority(string $priority): bool
{
    return in_array($priority, ['baixa', 'media', 'alta'], true);
}

function taskIsValidStatus(string $status): bool
{
    return in_array($status, ['pendente', 'em_andamento', 'concluida'], true);
}

function taskIsValidDate(?string $date): bool
{
    if ($date === null || $date === '') {
        return true;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt !== false && $dt->format('Y-m-d') === $date;
}

function taskFetchAuthenticatedUser(array $config, string $token): ?array
{
    $url = rtrim($config['auth_service_url'], '/') . '/auth/me';

    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Bearer {$token}\r\nAccept: application/json\r\n",
            'ignore_errors' => true,
            'timeout' => 5,
        ],
    ];

    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);

    $responseHeaders = function_exists('http_get_last_response_headers')
    ? http_get_last_response_headers()
    : [];

    $statusCode = 0;
    if (is_array($responseHeaders) && isset($responseHeaders[0]) && preg_match('#\s(\d{3})\s#', $responseHeaders[0], $matches)) {
        $statusCode = (int) $matches[1];
    }

    if ($response === false || $statusCode !== 200) {
        return null;
    }

    $decoded = json_decode($response, true);

    if (!is_array($decoded) || !($decoded['success'] ?? false)) {
        return null;
    }

    return is_array($decoded['data'] ?? null) ? $decoded['data'] : null;
}

function taskRequireAuthenticatedUser(array $config): array
{
    $token = taskGetBearerToken();

    if ($token === null) {
        taskJsonResponse(401, [
            'success' => false,
            'message' => 'Token não informado.',
            'errors' => [],
        ]);
    }

    $user = taskFetchAuthenticatedUser($config, $token);

    if ($user === null) {
        taskJsonResponse(401, [
            'success' => false,
            'message' => 'Token inválido ou expirado.',
            'errors' => [],
        ]);
    }

    return $user;
}

function taskClientIp(): string
{
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';

    if ($forwarded !== '') {
        $parts = explode(',', $forwarded);
        $candidate = trim($parts[0]);
        if ($candidate !== '') {
            return $candidate;
        }
    }

    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    return $remoteAddr !== '' ? $remoteAddr : 'unknown';
}
function taskMethodNotAllowed(array $allowedMethods): void
{
    header('Allow: ' . implode(', ', $allowedMethods));

    taskJsonResponse(405, [
        'success' => false,
        'message' => 'Método não permitido.',
        'errors' => [],
    ]);
}