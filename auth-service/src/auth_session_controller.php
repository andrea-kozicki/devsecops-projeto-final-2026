<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_repository.php';
require_once __DIR__ . '/auth_token_service.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/auth_validation.php';
require_once __DIR__ . '/auth_action_helpers.php';
require_once __DIR__ . '/auth_audit_repository.php';
require_once __DIR__ . '/auth_mfa.php';

function authHealthCheck(): void
{
    jsonResponse(200, [
        'success' => true,
        'message' => 'Auth service OK',
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

    $passwordError = validateStrongPassword($password, true);
    if ($passwordError !== null) {
        $errors['password'] = $passwordError;
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
    $ipAddress = authClientIp();
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
        registerLoginAttempt($pdo, $email, $ipAddress);

        createAuditLog(
            $pdo,
            null,
            null,
            'login_failed',
            'auth',
            null,
            authClientIp(),
            [
                'email' => $email,
                'reason' => 'invalid_credentials_or_inactive_user',
            ]
        );

        jsonResponse(401, [
            'success' => false,
            'message' => 'Credenciais inválidas.',
            'errors' => [],
        ]);
    }

    $mfa = getUserMfaByUserId($pdo, (int) $user['id']);

    if ($mfa && (int) $mfa['is_enabled'] === 1) {
        $plainChallenge = generateRandomHexToken(32);
        $challengeHash = hashOpaqueToken($plainChallenge);
        $expiresAt = date('Y-m-d H:i:s', time() + 300);

        createLoginChallenge($pdo, (int) $user['id'], $challengeHash, $expiresAt, authClientIp());

        createAuditLog(
            $pdo,
            (int) $user['id'],
            (int) $user['id'],
            'mfa_login_challenge_created',
            'auth',
            (int) $user['id'],
            authClientIp(),
            [
                'email' => $email,
                'expires_at' => $expiresAt,
            ]
        );

        jsonResponse(202, [
            'success' => true,
            'message' => 'MFA necessário para concluir o login.',
            'data' => [
                'mfa_required' => true,
                'challenge_token' => $plainChallenge,
                'expires_at' => $expiresAt,
            ],
        ]);
    }

    $plainToken = generatePlainToken();
    $tokenHash = hashAccessToken($plainToken);
    $expiresAt = makeTokenExpiration((int) $config['token_ttl']);

    storeAuthToken($pdo, (int) $user['id'], $tokenHash, $expiresAt);
    clearLoginAttempts($pdo, $email, $ipAddress);

    createAuditLog(
        $pdo,
        (int) $user['id'],
        (int) $user['id'],
        'login_success',
        'auth',
        (int) $user['id'],
        authClientIp(),
        [
            'email' => $email,
            'mfa_used' => false,
        ]
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
    $record = findValidTokenRecord($pdo, $tokenHash);

    if ($record === null) {
        jsonResponse(401, [
            'success' => false,
            'message' => 'Token inválido ou expirado.',
            'errors' => [],
        ]);
    }

    $revoked = revokeTokenByHash($pdo, $tokenHash);

    if (!$revoked) {
        jsonResponse(401, [
            'success' => false,
            'message' => 'Token inválido ou já revogado.',
            'errors' => [],
        ]);
    }

    createAuditLog(
        $pdo,
        (int) $record['user_id'],
        (int) $record['user_id'],
        'logout',
        'auth',
        (int) $record['user_id'],
        authClientIp(),
        ['email' => $record['email'] ?? null]
    );

    jsonResponse(200, [
        'success' => true,
        'message' => 'Logout realizado com sucesso.',
    ]);
}