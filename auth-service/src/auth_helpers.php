<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_repository.php';
require_once __DIR__ . '/auth_token_service.php';

function authJsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function authGetJsonInput(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        authJsonResponse(400, [
            'success' => false,
            'message' => 'JSON inválido.',
            'errors' => ['json' => json_last_error_msg()],
        ]);
    }

    return is_array($data) ? $data : [];
}

function authGetBearerToken(): ?string
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

function authRequireAuthenticatedUser(PDO $pdo): array
{
    $token = authGetBearerToken();

    if ($token === null) {
        authJsonResponse(401, [
            'success' => false,
            'message' => 'Token não informado.',
            'errors' => [],
        ]);
    }

    $tokenHash = hashAccessToken($token);
    $record = findValidTokenRecord($pdo, $tokenHash);

    if ($record === null) {
        authJsonResponse(401, [
            'success' => false,
            'message' => 'Token inválido ou expirado.',
            'errors' => [],
        ]);
    }

    touchTokenUsage($pdo, (int) $record['token_id']);

    return $record;
}

function authNormalizePath(?string $requestUri): string
{
    $path = parse_url($requestUri ?? '/', PHP_URL_PATH) ?: '/';

    $prefixes = [
        '/internal-auth',
        '/auth-service/public',
        '/auth-service',
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

function authRequireFields(array $data, array $requiredFields): array
{
    $errors = [];

    foreach ($requiredFields as $field) {
        if (!array_key_exists($field, $data) || trim((string) $data[$field]) === '') {
            $errors[$field] = "O campo '{$field}' é obrigatório.";
        }
    }

    return $errors;
}

function authValidateEmailAddress(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function authSanitizeUserOutput(array $user): array
{
    return [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'is_active' => (int) $user['is_active'],
        'created_at' => $user['created_at'] ?? null,
        'updated_at' => $user['updated_at'] ?? null,
    ];
}

function authMethodNotAllowed(array $allowedMethods): void
{
    header('Allow: ' . implode(', ', $allowedMethods));

    authJsonResponse(405, [
        'success' => false,
        'message' => 'Método não permitido.',
        'errors' => [],
    ]);
}