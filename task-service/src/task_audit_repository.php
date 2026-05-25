<?php
declare(strict_types=1);

function createTaskAuditLog(
    PDO $pdo,
    int $actorUserId,
    ?int $taskId,
    string $eventType,
    string $ipAddress,
    array $details = []
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO task_audit_logs (
            actor_user_id,
            task_id,
            event_type,
            ip_address,
            details
        ) VALUES (
            :actor_user_id,
            :task_id,
            :event_type,
            :ip_address,
            :details
        )'
    );

    $stmt->execute([
        'actor_user_id' => $actorUserId,
        'task_id' => $taskId,
        'event_type' => $eventType,
        'ip_address' => $ipAddress,
        'details' => !empty($details)
            ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null,
    ]);
}

function listTaskAuditLogs(PDO $pdo, int $limit = 100): array
{
    $limit = max(1, min($limit, 500));

    $stmt = $pdo->query(
        "SELECT
            id,
            actor_user_id,
            task_id,
            event_type,
            ip_address,
            details,
            created_at
         FROM task_audit_logs
         ORDER BY id DESC
         LIMIT {$limit}"
    );

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$row) {
        $row['details'] = $row['details']
            ? json_decode($row['details'], true)
            : [];
    }

    return $rows;
}