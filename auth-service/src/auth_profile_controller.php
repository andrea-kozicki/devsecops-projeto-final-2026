<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_repository.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/auth_validation.php';
require_once __DIR__ . '/auth_action_helpers.php';
require_once __DIR__ . '/auth_audit_repository.php';

function getProfile(PDO $pdo): void
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

    authJsonResponse(200, [
        'success' => true,
        'message' => 'Perfil carregado com sucesso.',
        'data' => authSanitizeUserOutput($record),
    ]);
}

function updateProfile(PDO $pdo): void
{
    $authenticatedUser = authRequireAuthenticatedUser($pdo);
    $data = authGetJsonInput();

    $name = array_key_exists('name', $data) ? trim((string) $data['name']) : null;
    $email = array_key_exists('email', $data) ? mb_strtolower(trim((string) $data['email'])) : null;
    $password = array_key_exists('password', $data) ? (string) $data['password'] : null;

    $errors = [];
    $changedFields = [];

    $safeName = null;
    $safeEmail = null;
    $safePasswordHash = null;

    if ($name !== null) {
        if ($name === '') {
            $errors['name'] = 'O nome é obrigatório.';
        } elseif (mb_strlen($name) > 120) {
            $errors['name'] = 'O nome deve ter no máximo 120 caracteres.';
        } elseif (authContainsHtmlMarkup($name)) {
            $errors['name'] = 'O nome não pode conter marcação HTML.';
        } else {
            $safeName = $name;
            $changedFields[] = 'name';
        }
    }

    if ($email !== null) {
        if ($email === '') {
            $errors['email'] = 'O e-mail é obrigatório.';
        } elseif (mb_strlen($email) > 150) {
            $errors['email'] = 'O e-mail deve ter no máximo 150 caracteres.';
        } elseif (!authValidateEmailAddress($email)) {
            $errors['email'] = 'Informe um e-mail válido.';
        } else {
            $existingUser = findUserByEmail($pdo, $email);

            if ($existingUser && (int) $existingUser['id'] !== (int) $authenticatedUser['id']) {
                $errors['email'] = 'E-mail já cadastrado.';
            } else {
                $safeEmail = $email;
                $changedFields[] = 'email';
            }
        }
    }

    if ($password !== null && $password !== '') {
        $passwordError = validateStrongPassword($password, true);

        if ($passwordError !== null) {
            $errors['password'] = $passwordError;
        } else {
            $safePasswordHash = password_hash($password, PASSWORD_DEFAULT);
            $changedFields[] = 'password';
        }
    }

    if (!empty($errors)) {
        authJsonResponse(422, [
            'success' => false,
            'message' => 'Dados inválidos.',
            'errors' => $errors,
        ]);
    }

    if (empty($changedFields)) {
        authJsonResponse(422, [
            'success' => false,
            'message' => 'Nenhum dado informado para atualização.',
            'errors' => [],
        ]);
    }

    $updatedUser = updateUserProfile(
        $pdo,
        (int) $authenticatedUser['id'],
        $safeName,
        $safeEmail,
        $safePasswordHash
    );

    createAuditLog(
        $pdo,
        (int) $authenticatedUser['id'],
        (int) $authenticatedUser['id'],
        'profile_updated',
        'user',
        (int) $authenticatedUser['id'],
        authClientIp(),
        ['fields' => $changedFields]
    );

    authJsonResponse(200, [
        'success' => true,
        'message' => 'Perfil atualizado com sucesso.',
        'data' => $updatedUser,
    ]);
}