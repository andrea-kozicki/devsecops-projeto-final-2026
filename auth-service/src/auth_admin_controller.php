<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_repository.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/auth_action_helpers.php';
require_once __DIR__ . '/auth_audit_repository.php';

function listUsersAction(PDO $pdo): void
{
    $authenticatedUser = authRequireAuthenticatedUser($pdo);

    if (($authenticatedUser['role'] ?? '') !== 'admin') {
        authJsonResponse(403, [
            'success' => false,
            'message' => 'Acesso negado.',
            'errors' => [],
        ]);
    }

    $roleFilter = filter_input(INPUT_GET, 'role', FILTER_UNSAFE_RAW);

    if (!is_string($roleFilter) || !in_array($roleFilter, ['admin', 'user'], true)) {
        $roleFilter = null;
    }

    $users = listUsers($pdo, $roleFilter);

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
            'role_filter' => $roleFilter ?? 'all',
            'actor_email' => $authenticatedUser['email'] ?? null,
        ]
    );

    authJsonResponse(200, [
        'success' => true,
        'message' => 'Usuários carregados com sucesso.',
        'data' => $users,
    ]);
}

function blockUserAction(PDO $pdo, int $targetUserId): void
{
    $authenticatedUser = authRequireAuthenticatedUser($pdo);

    if (($authenticatedUser['role'] ?? '') !== 'admin') {
        authJsonResponse(403, [
            'success' => false,
            'message' => 'Acesso negado.',
            'errors' => [],
        ]);
    }

    if ((int) $authenticatedUser['id'] === $targetUserId) {
        authJsonResponse(422, [
            'success' => false,
            'message' => 'Você não pode bloquear a própria conta.',
            'errors' => [],
        ]);
    }

    $targetUser = findUserById($pdo, $targetUserId);

    if (!$targetUser) {
        authJsonResponse(404, [
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

    authJsonResponse(200, [
        'success' => true,
        'message' => 'Usuário bloqueado com sucesso.',
        'data' => $updatedUser,
    ]);
}

function reactivateUserAction(PDO $pdo, int $targetUserId): void
{
    $authenticatedUser = authRequireAuthenticatedUser($pdo);

    if (($authenticatedUser['role'] ?? '') !== 'admin') {
        authJsonResponse(403, [
            'success' => false,
            'message' => 'Acesso negado.',
            'errors' => [],
        ]);
    }

    $targetUser = findUserById($pdo, $targetUserId);

    if (!$targetUser) {
        authJsonResponse(404, [
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

    authJsonResponse(200, [
        'success' => true,
        'message' => 'Usuário reativado com sucesso.',
        'data' => $updatedUser,
    ]);
}

function listAuditLogsAction(PDO $pdo): void
{
    $authenticatedUser = authRequireAuthenticatedUser($pdo);

    if (($authenticatedUser['role'] ?? '') !== 'admin') {
        authJsonResponse(403, [
            'success' => false,
            'message' => 'Acesso negado.',
            'errors' => [],
        ]);
    }

    $filters = buildAuditFiltersFromRequest();
    $logs = listAuditLogs($pdo, $filters);

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
            'filters' => $filters,
            'actor_email' => $authenticatedUser['email'] ?? null,
        ]
    );

    authJsonResponse(200, [
        'success' => true,
        'message' => 'Auditoria carregada com sucesso.',
        'data' => $logs,
    ]);
}

function buildAuditFiltersFromRequest(): array
{
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
    $limit = max(1, min($limit, 500));

    $filters = [
        'limit' => $limit,
    ];

    $userId = trim((string) ($_GET['user_id'] ?? ''));
    if ($userId !== '') {
        if (!ctype_digit($userId) || (int) $userId <= 0) {
            authJsonResponse(422, [
                'success' => false,
                'message' => 'Filtro de usuário inválido.',
                'errors' => ['user_id' => 'Informe um ID de usuário válido.'],
            ]);
        }

        $filters['user_id'] = (int) $userId;
    }

    $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
    if ($dateFrom !== '') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            authJsonResponse(422, [
                'success' => false,
                'message' => 'Data inicial inválida.',
                'errors' => ['date_from' => 'Use o formato AAAA-MM-DD.'],
            ]);
        }

        $filters['date_from'] = $dateFrom . ' 00:00:00';
    }

    $dateTo = trim((string) ($_GET['date_to'] ?? ''));
    if ($dateTo !== '') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            authJsonResponse(422, [
                'success' => false,
                'message' => 'Data final inválida.',
                'errors' => ['date_to' => 'Use o formato AAAA-MM-DD.'],
            ]);
        }

        $filters['date_to'] = (new DateTimeImmutable($dateTo . ' 00:00:00'))
            ->modify('+1 day')
            ->format('Y-m-d H:i:s');
    }

    return $filters;
}
