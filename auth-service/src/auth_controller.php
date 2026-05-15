<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_repository.php';
require_once __DIR__ . '/auth_token_service.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/auth_audit_repository.php';

function authClientIp(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

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

    if ($name === '') {
        $errors['name'] = 'O nome é obrigatório.';
    } elseif (mb_strlen($name) > 120) {
        $errors['name'] = 'O nome deve ter no máximo 120 caracteres.';
    }

    if ($email === '') {
        $errors['email'] = 'O e-mail é obrigatório.';
    } elseif (mb_strlen($email) > 150) {
        $errors['email'] = 'O e-mail deve ter no máximo 150 caracteres.';
    } elseif (!validateEmailAddress($email)) {
        $errors['email'] = 'Informe um e-mail válido.';
    }

    if ($password === '') {
        $errors['password'] = 'A senha é obrigatória.';
    } elseif (mb_strlen($password) < 10) {
        $errors['password'] = 'A senha deve ter pelo menos 10 caracteres.';
    } elseif (mb_strlen($password) > 255) {
        $errors['password'] = 'A senha deve ter no máximo 255 caracteres.';
    } elseif (
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/[0-9]/', $password) ||
        !preg_match('/[^A-Za-z0-9]/', $password)
    ) {
        $errors['password'] = 'A senha deve conter letra maiúscula, minúscula, número e símbolo.';
    }

    if (!empty($errors)) {
        jsonResponse(422, [
            'success' => false,
            'message' => 'Dados inválidos.',
            'errors' => $errors,
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

    createAuditLog(
        $pdo,
        $userId,
        $userId,
        'user_registered',
        'user',
        $userId,
        authClientIp(),
        ['email' => $email]
    );

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
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $recentAttempts = countRecentLoginAttempts($pdo, $email, $ipAddress, 15);

    if ($recentAttempts >= 5) {
        jsonResponse(429, [
            'success' => false,
            'message' => 'Muitas tentativas de login. Tente novamente mais tarde.',
            'errors' => [],
        ]);
    }

    $password = (string) $data['password'];

    $user = findUserByEmail($pdo, $email);

    if ($user === null || (int) $user['is_active'] !== 1 || !password_verify($password, $user['password_hash'])) {
        error_log("[auth-service] falha de login para: {$email}");

        registerLoginAttempt($pdo, $email, $ipAddress);

        createAuditLog(
            $pdo,
            null,
            null,
            'login_failed',
            'auth',
            null,
            authClientIp(),
            ['email' => $email]
        );


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

    clearLoginAttempts($pdo, $email, $ipAddress);

    createAuditLog(
        $pdo,
        (int) $user['id'],
        (int) $user['id'],
        'login_success',
        'auth',
        (int) $user['id'],
        authClientIp(),
        ['email' => $email]
    );

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

function updateProfile(PDO $pdo): void
{
    $authenticatedUser = requireAuthenticatedUser($pdo);
    $data = getJsonInput();

    $name = array_key_exists('name', $data) ? trim((string) $data['name']) : null;
    $email = array_key_exists('email', $data) ? mb_strtolower(trim((string) $data['email'])) : null;
    $password = array_key_exists('password', $data) ? (string) $data['password'] : null;

    $errors = [];
    $fields = [];

    if ($name !== null) {
        if ($name === '') {
            $errors['name'] = 'O nome é obrigatório.';
        } elseif (mb_strlen($name) > 120) {
            $errors['name'] = 'O nome deve ter no máximo 120 caracteres.';
        } else {
            $fields['name'] = $name;
        }
    }

    if ($email !== null) {
        if ($email === '') {
            $errors['email'] = 'O e-mail é obrigatório.';
        } elseif (mb_strlen($email) > 150) {
            $errors['email'] = 'O e-mail deve ter no máximo 150 caracteres.';
        } elseif (!validateEmailAddress($email)) {
            $errors['email'] = 'Informe um e-mail válido.';
        } else {
            $existingUser = findUserByEmail($pdo, $email);

            if ($existingUser && (int) $existingUser['id'] !== (int) $authenticatedUser['id']) {
                $errors['email'] = 'E-mail já cadastrado.';
            } else {
                $fields['email'] = $email;
            }
        }
    }

    if ($password !== null && $password !== '') {
        if (mb_strlen($password) < 10) {
            $errors['password'] = 'A senha deve ter pelo menos 10 caracteres.';
        } elseif (mb_strlen($password) > 255) {
            $errors['password'] = 'A senha deve ter no máximo 255 caracteres.';
        } elseif (
            !preg_match('/[A-Z]/', $password) ||
            !preg_match('/[a-z]/', $password) ||
            !preg_match('/[0-9]/', $password) ||
            !preg_match('/[^A-Za-z0-9]/', $password)
        ) {
            $errors['password'] = 'A senha deve conter letra maiúscula, minúscula, número e símbolo.';
        } else {
            $fields['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
    }

    if (!empty($errors)) {
        jsonResponse(422, [
            'success' => false,
            'message' => 'Dados inválidos.',
            'errors' => $errors,
        ]);
    }

    if (empty($fields)) {
        jsonResponse(422, [
            'success' => false,
            'message' => 'Nenhum dado informado para atualização.',
            'errors' => [],
        ]);
    }

    $updatedUser = updateUserProfile($pdo, (int) $authenticatedUser['id'], $fields);

    createAuditLog(
        $pdo,
        (int) $authenticatedUser['id'],
        (int) $authenticatedUser['id'],
        'profile_updated',
        'user',
        (int) $authenticatedUser['id'],
        authClientIp(),
        ['fields' => array_keys($fields)]
    );

    jsonResponse(200, [
        'success' => true,
        'message' => 'Perfil atualizado com sucesso.',
        'data' => $updatedUser,
    ]);
}

function listUsersAction(PDO $pdo): void
{
    $authenticatedUser = requireAuthenticatedUser($pdo);

    if (($authenticatedUser['role'] ?? '') !== 'admin') {
        jsonResponse(403, [
            'success' => false,
            'message' => 'Acesso negado.',
            'errors' => [],
        ]);
    }

    $users = listUsers($pdo);

    createAuditLog(
        $pdo,
        (int) $authenticatedUser['id'],
        null,
        'admin_users_listed',
        'user',
        null,
        authClientIp(),
        ['count' => count($users)]
    );

    jsonResponse(200, [
        'success' => true,
        'message' => 'Usuários carregados com sucesso.',
        'data' => $users,
    ]);
}

function blockUserAction(PDO $pdo, int $targetUserId): void
{
    $authenticatedUser = requireAuthenticatedUser($pdo);

    if (($authenticatedUser['role'] ?? '') !== 'admin') {
        jsonResponse(403, [
            'success' => false,
            'message' => 'Acesso negado.',
            'errors' => [],
        ]);
    }

    if ((int) $authenticatedUser['id'] === $targetUserId) {
        jsonResponse(422, [
            'success' => false,
            'message' => 'Você não pode bloquear a própria conta.',
            'errors' => [],
        ]);
    }

    $targetUser = findUserById($pdo, $targetUserId);

    if (!$targetUser) {
        jsonResponse(404, [
            'success' => false,
            'message' => 'Usuário não encontrado.',
            'errors' => [],
        ]);
    }

    $updatedUser = updateUserActiveStatus($pdo, $targetUserId, false);

    createAuditLog(
        $pdo,
        (int) $authenticatedUser['id'],
        $targetUserId,
        'user_blocked',
        'user',
        $targetUserId,
        authClientIp(),
        ['email' => $updatedUser['email']]
    );

    jsonResponse(200, [
        'success' => true,
        'message' => 'Usuário bloqueado com sucesso.',
        'data' => $updatedUser,
    ]);
}

function reactivateUserAction(PDO $pdo, int $targetUserId): void
{
    $authenticatedUser = requireAuthenticatedUser($pdo);

    if (($authenticatedUser['role'] ?? '') !== 'admin') {
        jsonResponse(403, [
            'success' => false,
            'message' => 'Acesso negado.',
            'errors' => [],
        ]);
    }

    $targetUser = findUserById($pdo, $targetUserId);

    if (!$targetUser) {
        jsonResponse(404, [
            'success' => false,
            'message' => 'Usuário não encontrado.',
            'errors' => [],
        ]);
    }

    $updatedUser = updateUserActiveStatus($pdo, $targetUserId, true);

    createAuditLog(
        $pdo,
        (int) $authenticatedUser['id'],
        $targetUserId,
        'user_reactivated',
        'user',
        $targetUserId,
        authClientIp(),
        ['email' => $updatedUser['email']]
    );

    jsonResponse(200, [
        'success' => true,
        'message' => 'Usuário reativado com sucesso.',
        'data' => $updatedUser,
    ]);
}

function listAuditLogsAction(PDO $pdo): void
{
    $authenticatedUser = requireAuthenticatedUser($pdo);

    if (($authenticatedUser['role'] ?? '') !== 'admin') {
        jsonResponse(403, [
            'success' => false,
            'message' => 'Acesso negado.',
            'errors' => [],
        ]);
    }

    $logs = listAuditLogs($pdo, 100);

    createAuditLog(
        $pdo,
        (int) $authenticatedUser['id'],
        null,
        'audit_viewed',
        'audit',
        null,
        authClientIp(),
        ['count' => count($logs)]
    );

    jsonResponse(200, [
        'success' => true,
        'message' => 'Auditoria carregada com sucesso.',
        'data' => $logs,
    ]);
}
