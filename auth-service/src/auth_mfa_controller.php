<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_repository.php';
require_once __DIR__ . '/auth_token_service.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/auth_validation.php';
require_once __DIR__ . '/auth_action_helpers.php';
require_once __DIR__ . '/auth_audit_repository.php';
require_once __DIR__ . '/auth_mfa.php';

function mfaStatusAction(PDO $pdo): void
{
    $authenticatedUser = authRequireAuthenticatedUser($pdo);
    $mfa = getUserMfaByUserId($pdo, (int) $authenticatedUser['id']);

    authJsonResponse(200, [
        'success' => true,
        'message' => 'Status MFA carregado com sucesso.',
        'data' => [
            'enabled' => $mfa ? ((int) $mfa['is_enabled'] === 1) : false,
        ],
    ]);
}

function mfaSetupAction(PDO $pdo): void
{
    $authenticatedUser = authRequireAuthenticatedUser($pdo);

    $secret = generateBase32Secret();
    upsertUserMfaSecret($pdo, (int) $authenticatedUser['id'], $secret, false);

    $uri = buildOtpAuthUri((string) $authenticatedUser['email'], $secret, 'StudyBoard');

    createAuditLog(
        $pdo,
        (int) $authenticatedUser['id'],
        (int) $authenticatedUser['id'],
        'mfa_setup_started',
        'user',
        (int) $authenticatedUser['id'],
        authClientIp(),
        [
            'email' => $authenticatedUser['email'] ?? null,
        ]
    );

    authJsonResponse(200, [
        'success' => true,
        'message' => 'Configuração MFA iniciada.',
        'data' => [
            'secret' => $secret,
            'otpauth_url' => $uri,
        ],
    ]);
}

function mfaEnableAction(PDO $pdo): void
{
    $authenticatedUser = authRequireAuthenticatedUser($pdo);
    $data = authGetJsonInput();

    $code = trim((string) ($data['code'] ?? ''));
    $codeError = validateSixDigitCode($code);

    if ($codeError !== null) {
        authJsonResponse(422, [
            'success' => false,
            'message' => 'Código MFA inválido.',
            'errors' => ['code' => $codeError],
        ]);
    }

    $mfa = getUserMfaByUserId($pdo, (int) $authenticatedUser['id']);

    if (!$mfa) {
        authJsonResponse(404, [
            'success' => false,
            'message' => 'Configuração MFA não encontrada.',
            'errors' => [],
        ]);
    }

    if (!verifyTotpCode((string) $mfa['totp_secret'], $code)) {
        authJsonResponse(401, [
            'success' => false,
            'message' => 'Código MFA inválido.',
            'errors' => [],
        ]);
    }

    enableUserMfa($pdo, (int) $authenticatedUser['id']);

    createAuditLog(
        $pdo,
        (int) $authenticatedUser['id'],
        (int) $authenticatedUser['id'],
        'mfa_enabled',
        'user',
        (int) $authenticatedUser['id'],
        authClientIp(),
        [
            'email' => $authenticatedUser['email'] ?? null,
        ]
    );

    authJsonResponse(200, [
        'success' => true,
        'message' => 'MFA habilitado com sucesso.',
        'data' => [],
    ]);
}

function mfaDisableAction(PDO $pdo): void
{
    $authenticatedUser = authRequireAuthenticatedUser($pdo);
    $data = authGetJsonInput();

    $code = trim((string) ($data['code'] ?? ''));
    $codeError = validateSixDigitCode($code);

    if ($codeError !== null) {
        authJsonResponse(422, [
            'success' => false,
            'message' => 'Código MFA inválido.',
            'errors' => ['code' => $codeError],
        ]);
    }

    $mfa = getUserMfaByUserId($pdo, (int) $authenticatedUser['id']);

    if (!$mfa || (int) $mfa['is_enabled'] !== 1) {
        authJsonResponse(404, [
            'success' => false,
            'message' => 'MFA não está habilitado.',
            'errors' => [],
        ]);
    }

    if (!verifyTotpCode((string) $mfa['totp_secret'], $code)) {
        authJsonResponse(401, [
            'success' => false,
            'message' => 'Código MFA inválido.',
            'errors' => [],
        ]);
    }

    disableUserMfa($pdo, (int) $authenticatedUser['id']);

    createAuditLog(
        $pdo,
        (int) $authenticatedUser['id'],
        (int) $authenticatedUser['id'],
        'mfa_disabled',
        'user',
        (int) $authenticatedUser['id'],
        authClientIp(),
        [
            'email' => $authenticatedUser['email'] ?? null,
        ]
    );

    authJsonResponse(200, [
        'success' => true,
        'message' => 'MFA desabilitado com sucesso.',
        'data' => [],
    ]);
}

function verifyLoginMfaAction(PDO $pdo, array $config): void
{
    $data = authGetJsonInput();
    $challengeToken = trim((string) ($data['challenge_token'] ?? ''));
    $code = trim((string) ($data['code'] ?? ''));

    $errors = [];

    if ($challengeToken === '') {
        $errors['challenge_token'] = 'Challenge token é obrigatório.';
    }

    $codeError = validateSixDigitCode($code);
    if ($codeError !== null) {
        $errors['code'] = $codeError;
    }

    if (!empty($errors)) {
        authJsonResponse(422, [
            'success' => false,
            'message' => 'Dados inválidos.',
            'errors' => $errors,
        ]);
    }

    $record = findValidLoginChallenge($pdo, hashOpaqueToken($challengeToken));

    if ($record === null) {
        authJsonResponse(401, [
            'success' => false,
            'message' => 'Challenge MFA inválido ou expirado.',
            'errors' => [],
        ]);
    }

    $mfa = getUserMfaByUserId($pdo, (int) $record['user_id']);

    if (!$mfa || (int) $mfa['is_enabled'] !== 1) {
        authJsonResponse(401, [
            'success' => false,
            'message' => 'MFA não habilitado para este usuário.',
            'errors' => [],
        ]);
    }

    if (!verifyTotpCode((string) $mfa['totp_secret'], $code)) {
        createAuditLog(
            $pdo,
            (int) $record['user_id'],
            (int) $record['user_id'],
            'mfa_login_failed',
            'auth',
            (int) $record['user_id'],
            authClientIp(),
            [
                'email' => $record['email'] ?? null,
                'reason' => 'invalid_totp_code',
            ]
        );

        authJsonResponse(401, [
            'success' => false,
            'message' => 'Código MFA inválido.',
            'errors' => [],
        ]);
    }

    markLoginChallengeUsed($pdo, (int) $record['challenge_id']);

    $plainToken = generatePlainToken();
    $tokenHash = hashAccessToken($plainToken);
    $expiresAt = makeTokenExpiration((int) $config['token_ttl']);

    storeAuthToken($pdo, (int) $record['user_id'], $tokenHash, $expiresAt);

    createAuditLog(
        $pdo,
        (int) $record['user_id'],
        (int) $record['user_id'],
        'mfa_login_success',
        'auth',
        (int) $record['user_id'],
        authClientIp(),
        [
            'email' => $record['email'] ?? null,
            'mfa_used' => true,
        ]
    );

    authJsonResponse(200, [
        'success' => true,
        'message' => 'Login MFA realizado com sucesso.',
        'data' => [
            'access_token' => $plainToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt,
            'user' => authSanitizeUserOutput($record),
        ],
    ]);
}