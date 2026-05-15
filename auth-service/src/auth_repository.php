<?php
declare(strict_types=1);

function findUserByEmail(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, name, email, password_hash, role, is_active, created_at, updated_at
         FROM users
         WHERE email = :email
         LIMIT 1'
    );

    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function findUserById(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, name, email, role, is_active, created_at, updated_at
         FROM users
         WHERE id = :id
         LIMIT 1'
    );

    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function createUser(PDO $pdo, string $name, string $email, string $passwordHash, string $role = 'user'): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, role)
         VALUES (:name, :email, :password_hash, :role)'
    );

    $stmt->execute([
        'name' => $name,
        'email' => $email,
        'password_hash' => $passwordHash,
        'role' => $role,
    ]);

    return (int) $pdo->lastInsertId();
}

function storeAuthToken(PDO $pdo, int $userId, string $tokenHash, string $expiresAt): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO auth_tokens (user_id, token_hash, expires_at)
         VALUES (:user_id, :token_hash, :expires_at)'
    );

    $stmt->execute([
        'user_id' => $userId,
        'token_hash' => $tokenHash,
        'expires_at' => $expiresAt,
    ]);

    return (int) $pdo->lastInsertId();
}

function findValidTokenRecord(PDO $pdo, string $tokenHash): ?array
{
    $stmt = $pdo->prepare(
        'SELECT
            at.id AS token_id,
            at.user_id,
            at.token_hash,
            at.expires_at,
            at.revoked,
            at.last_used_at,
            u.id,
            u.name,
            u.email,
            u.role,
            u.is_active,
            u.created_at,
            u.updated_at
         FROM auth_tokens at
         INNER JOIN users u ON u.id = at.user_id
         WHERE at.token_hash = :token_hash
           AND at.revoked = 0
           AND at.expires_at > NOW()
           AND u.is_active = 1
         LIMIT 1'
    );

    $stmt->execute(['token_hash' => $tokenHash]);
    $record = $stmt->fetch();

    return $record ?: null;
}

function touchTokenUsage(PDO $pdo, int $tokenId): void
{
    $stmt = $pdo->prepare(
        'UPDATE auth_tokens
         SET last_used_at = NOW()
         WHERE id = :id'
    );

    $stmt->execute(['id' => $tokenId]);
}

function revokeTokenByHash(PDO $pdo, string $tokenHash): bool
{
    $stmt = $pdo->prepare(
        'UPDATE auth_tokens
         SET revoked = 1
         WHERE token_hash = :token_hash'
    );

    $stmt->execute(['token_hash' => $tokenHash]);

    return $stmt->rowCount() > 0;
}

function registerLoginAttempt(PDO $pdo, string $email, string $ipAddress): void
{
    $sql = 'INSERT INTO login_attempts (email, ip_address) VALUES (:email, :ip_address)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'email' => $email,
        'ip_address' => $ipAddress,
    ]);
}

function countRecentLoginAttempts(PDO $pdo, string $email, string $ipAddress, int $minutes = 15): int
{
    $sql = '
        SELECT COUNT(*)
        FROM login_attempts
        WHERE email = :email
          AND ip_address = :ip_address
          AND attempted_at >= (NOW() - INTERVAL :minutes MINUTE)
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':email', $email);
    $stmt->bindValue(':ip_address', $ipAddress);
    $stmt->bindValue(':minutes', $minutes, PDO::PARAM_INT);
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

function clearLoginAttempts(PDO $pdo, string $email, string $ipAddress): void
{
    $sql = 'DELETE FROM login_attempts WHERE email = :email AND ip_address = :ip_address';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'email' => $email,
        'ip_address' => $ipAddress,
    ]);
}

function updateUserProfile(PDO $pdo, int $userId, array $fields): array
{
    $allowed = ['name', 'email', 'password_hash'];
    $sets = [];
    $params = ['id' => $userId];

    foreach ($fields as $key => $value) {
        if (!in_array($key, $allowed, true)) {
            continue;
        }

        if ($key === 'password_hash') {
            $sets[] = 'password_hash = :password_hash';
            $params['password_hash'] = $value;
            continue;
        }

        $sets[] = "{$key} = :{$key}";
        $params[$key] = $value;
    }

    if (empty($sets)) {
        throw new InvalidArgumentException('Nenhum campo válido para atualização.');
    }

    $sets[] = 'updated_at = NOW()';

    $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $updatedUser = findUserById($pdo, $userId);

    if (!$updatedUser) {
        throw new RuntimeException('Usuário não encontrado após atualização.');
    }

    return $updatedUser;
}

function listUsers(PDO $pdo): array
{
    $sql = 'SELECT id, name, email, role, is_active, created_at, updated_at
            FROM users
            ORDER BY created_at DESC';

    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function updateUserActiveStatus(PDO $pdo, int $userId, bool $isActive): array
{
    $stmt = $pdo->prepare(
        'UPDATE users
         SET is_active = :is_active,
             updated_at = NOW()
         WHERE id = :id'
    );

    $stmt->execute([
        'is_active' => $isActive ? 1 : 0,
        'id' => $userId,
    ]);

    $user = findUserById($pdo, $userId);

    if (!$user) {
        throw new RuntimeException('Usuário não encontrado após atualização de status.');
    }

    return $user;
}