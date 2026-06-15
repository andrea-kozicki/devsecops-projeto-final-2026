<?php
declare(strict_types=1);

function createAuditLog(
    PDO $pdo,
    ?int $actorUserId,
    ?int $targetUserId,
    string $eventType,
    string $entityType,
    ?int $entityId,
    string $ipAddress,
    array $details = []
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO audit_logs (
            actor_user_id,
            target_user_id,
            event_type,
            entity_type,
            entity_id,
            ip_address,
            details
        ) VALUES (
            :actor_user_id,
            :target_user_id,
            :event_type,
            :entity_type,
            :entity_id,
            :ip_address,
            :details
        )'
    );

    $stmt->execute([
        'actor_user_id' => $actorUserId,
        'target_user_id' => $targetUserId,
        'event_type' => $eventType,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'ip_address' => $ipAddress,
        'details' => !empty($details)
            ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null,
    ]);
}

function listAuditLogs(PDO $pdo, int|array $limitOrFilters = 100): array
{
    $filters = is_array($limitOrFilters)
        ? $limitOrFilters
        : ['limit' => $limitOrFilters];

    $limit = max(1, min((int) ($filters['limit'] ?? 100), 500));
    $where = [];
    $params = [];

    if (!empty($filters['user_id'])) {
        $where[] = '(al.actor_user_id = :user_id OR al.target_user_id = :user_id)';
        $params['user_id'] = (int) $filters['user_id'];
    }

    if (!empty($filters['date_from'])) {
        $where[] = 'al.created_at >= :date_from';
        $params['date_from'] = (string) $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $where[] = 'al.created_at < :date_to';
        $params['date_to'] = (string) $filters['date_to'];
    }

    $sql = "SELECT
            al.id,
            al.actor_user_id,
            al.target_user_id,
            al.event_type,
            al.entity_type,
            al.entity_id,
            al.ip_address,
            al.details,
            al.created_at,
            actor.name AS actor_name,
            actor.email AS actor_email,
            target.name AS target_name,
            target.email AS target_email
         FROM audit_logs al
         LEFT JOIN users actor ON actor.id = al.actor_user_id
         LEFT JOIN users target ON target.id = al.target_user_id";

    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= " ORDER BY al.id DESC LIMIT {$limit}";

    $stmt = $pdo->prepare($sql);

    foreach ($params as $name => $value) {
        $stmt->bindValue(':' . $name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $row['details'] = $row['details']
            ? json_decode($row['details'], true)
            : [];
    }

    return $rows;
}
