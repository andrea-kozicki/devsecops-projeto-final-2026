<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_repository.php';
require_once __DIR__ . '/auth_token_service.php';
require_once __DIR__ . '/auth_helpers.php';

function authHealthCheck(): void
{
    jsonResponse(200, [
        'success' => true,
        'message' => 'Auth service OK',
    ]);
}

function registerUser(PDO $pdo): void
{
    $data = getJsonInput();
    $errors = requireFields($data, ['name', 'email', 'password']);

    if (!empty($errors)) {
        jsonResponse(422, [
            'success' => false,
            'message' => 'Dados inválidos.',
            'errors' => $errors,
        ]);
    }

    $name = trim((string) $data['name']);
    $email = mb_strtolower(trim((string) $data['email']));
    $password = (string) $data['password'];

    if (mb_strlen($name) > 120) {
        $errors['name'] = 'O nome deve ter no máximo 120 caracteres.';
    }

    if (mb_strlen($email) > 150) {
        $errors['email'] = 'O e-mail deve ter no máximo 150 caracteres.';
    }

    if (mb_strlen($password) > 255) {
        $errors['password'] = 'A senha deve ter no máximo 255 caracteres.';
    }

    if (!empty($errors)) {
        jsonResponse(422, [
            'success' => false,
            'message' => 'Dados inválidos.',
            'errors' => $errors,
        ]);
    }
     
    
    if (!validateEmailAddress($email)) {
        jsonResponse(422, [
            'success' => false,
            'message' => 'E-mail inválido.',
            'errors' => ['email' => 'Informe um e-mail válido.'],
        ]);
    }

    $passwordError = validatePasswordStrength($password);
    if ($passwordError !== null) {
        jsonResponse(422, [
            'success' => false,
            'message' => 'Senha inválida.',
            'errors' => ['password' => $passwordError],
        ]);
    }

    if (findUserByEmail($pdo, $email) !== null) {
        jsonResponse(409, [
            'success' => false,
            'message' => 'Já existe um usuário com esse e-mail.',
            'errors' => ['email' => 'E-mail já cadastrado.'],
        ]);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $userId = createUser($pdo, $name, $email, $passwordHash);

    $user = findUserById($pdo, $userId);

    error_log("[auth-service] usuário cadastrado: {$email}");

    jsonResponse(201, [
        'success' => true,
        'message' => 'Usuário cadastrado com sucesso.',
        'data' => sanitizeUserOutput($user),
    ]);
}

function loginUser(PDO $pdo, array $config): void
{
    $data = getJsonInput();
    $errors = requireFields($data, ['email', 'password']);

    if (!empty($errors)) {
        jsonResponse(422, [
            'success' => false,
            'message' => 'Dados inválidos.',
            'errors' => $errors,
        ]);
    }

    $email = mb_strtolower(trim((string) $data['email']));
    $password = (string) $data['password'];

    $user = findUserByEmail($pdo, $email);

    if ($user === null || (int) $user['is_active'] !== 1 || !password_verify($password, $user['password_hash'])) {
        error_log("[auth-service] falha de login para: {$email}");

        jsonResponse(401, [
            'success' => false,
            'message' => 'Credenciais inválidas.',
            'errors' => [],
        ]);
    }

    $plainToken = generatePlainToken();
    $tokenHash = hashAccessToken($plainToken);
    $expiresAt = makeTokenExpiration((int) $config['token_ttl']);

    storeAuthToken($pdo, (int) $user['id'], $tokenHash, $expiresAt);

    error_log("[auth-service] login realizado: {$email}");

    jsonResponse(200, [
        'success' => true,
        'message' => 'Login realizado com sucesso.',
        'data' => [
            'access_token' => $plainToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt,
            'user' => sanitizeUserOutput($user),
        ],
    ]);
}

function getProfile(PDO $pdo): void
{
    $token = getBearerToken();

    if ($token === null) {
        jsonResponse(401, [
            'success' => false,
            'message' => 'Token não informado.',
            'errors' => [],
        ]);
    }

    $tokenHash = hashAccessToken($token);
    $record = findValidTokenRecord($pdo, $tokenHash);

    if ($record === null) {
        jsonResponse(401, [
            'success' => false,
            'message' => 'Token inválido ou expirado.',
            'errors' => [],
        ]);
    }

    touchTokenUsage($pdo, (int) $record['token_id']);

    jsonResponse(200, [
        'success' => true,
        'message' => 'Perfil carregado com sucesso.',
        'data' => sanitizeUserOutput($record),
    ]);
}

function logoutUser(PDO $pdo): void
{
    $token = getBearerToken();

    if ($token === null) {
        jsonResponse(401, [
            'success' => false,
            'message' => 'Token não informado.',
            'errors' => [],
        ]);
    }

    $tokenHash = hashAccessToken($token);
    $revoked = revokeTokenByHash($pdo, $tokenHash);

    if (!$revoked) {
        jsonResponse(401, [
            'success' => false,
            'message' => 'Token inválido ou já revogado.',
            'errors' => [],
        ]);
    }

    jsonResponse(200, [
        'success' => true,
        'message' => 'Logout realizado com sucesso.',
    ]);
}

function authReadyCheck(PDO $pdo): void
{
    $pdo->query('SELECT 1');

    jsonResponse(200, [
        'success' => true,
        'message' => 'Auth service ready.',
    ]);
}