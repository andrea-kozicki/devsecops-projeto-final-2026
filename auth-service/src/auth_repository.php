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