<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_repository.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/auth_validation.php';
require_once __DIR__ . '/auth_action_helpers.php';
require_once __DIR__ . '/auth_audit_repository.php';
require_once __DIR__ . '/auth_mfa.php';

function forgotPasswordAction(PDO $pdo): void
{
    $data = getJsonInput();
    $email = mb_strtolower(trim((string) ($data['email'] ?? '')));

    if ($email === '' || !validateEmailAddress($email)) {
        jsonResponse(422, [
            'success' => false,
            'message' => 'E-mail inválido.',
            'errors' => ['email' => 'Informe um e-mail válido.'],
        ]);
    }

    $user = findUserByEmail($pdo, $email);

    if ($user !== null && (int) $user['is_active'] === 1) {
        $plainToken = generateRandomHexToken(32);
        $tokenHash = hashOpaqueToken($plainToken);
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);

        createPasswordResetToken($pdo, (int) $user['id'], $tokenHash, $expiresAt, authClientIp());

        createAuditLog(
            $pdo,
            (int) $user['id'],
            (int) $user['id'],
            'password_reset_requested',
            'user',
            (int) $user['id'],
            authClientIp(),
            ['email' => $email]
        );

        jsonResponse(200, [
            'success' => true,
            'message' => 'Se o e-mail existir, um processo de recuperação foi iniciado.',
            'data' => [
                'reset_token' => $plainToken,
                'reset_url' => '/redefinir-senha?token=' . $plainToken,
            ],
        ]);
    }

    jsonResponse(200, [
        'success' => true,
        'message' => 'Se o e-mail existir, um processo de recuperação foi iniciado.',
        'data' => [],
    ]);
}

function resetPasswordAction(PDO $pdo): void
{
    $data = getJsonInput();
    $token = trim((string) ($data['token'] ?? ''));
    $password = (string) ($data['password'] ?? '');

    $errors = [];

    if ($token === '') {
        $errors['token'] = 'Token é obrigatório.';
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

    $record = findValidPasswordResetToken($pdo, hashOpaqueToken($token));

    if ($record === null) {
        jsonResponse(401, [
            'success' => false,
            'message' => 'Token de recuperação inválido ou expirado.',
            'errors' => [],
        ]);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    setUserPasswordHash($pdo, (int) $record['user_id'], $passwordHash);
    markPasswordResetTokenUsed($pdo, (int) $record['id']);
    revokeAllTokensByUserId($pdo, (int) $record['user_id']);

    createAuditLog(
        $pdo,
        (int) $record['user_id'],
        (int) $record['user_id'],
        'password_reset_completed',
        'user',
        (int) $record['user_id'],
        authClientIp(),
        ['email' => $record['email'] ?? null]
    );

    jsonResponse(200, [
        'success' => true,
        'message' => 'Senha redefinida com sucesso.',
        'data' => [],
    ]);
}