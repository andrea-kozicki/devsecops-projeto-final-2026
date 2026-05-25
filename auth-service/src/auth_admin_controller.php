<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_repository.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/auth_action_helpers.php';
require_once __DIR__ . '/auth_audit_repository.php';

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
        [
            'count' => count($users),
            'actor_email' => $authenticatedUser['email'] ?? null,
        ]
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
        [
            'email' => $updatedUser['email'],
            'actor_email' => $authenticatedUser['email'] ?? null,
        ]
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
        [
            'email' => $updatedUser['email'],
            'actor_email' => $authenticatedUser['email'] ?? null,
        ]
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
        [
            'count' => count($logs),
            'actor_email' => $authenticatedUser['email'] ?? null,
        ]
    );

    jsonResponse(200, [
        'success' => true,
        'message' => 'Auditoria carregada com sucesso.',
        'data' => $logs,
    ]);
}