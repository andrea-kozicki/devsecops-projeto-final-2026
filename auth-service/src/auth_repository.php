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

function createLoginChallenge(PDO $pdo, int $userId, string $challengeHash, string $expiresAt, string $ipAddress): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO login_challenges (user_id, challenge_hash, expires_at, ip_address)
         VALUES (:user_id, :challenge_hash, :expires_at, :ip_address)'
    );

    $stmt->execute([
        'user_id' => $userId,
        'challenge_hash' => $challengeHash,
        'expires_at' => $expiresAt,
        'ip_address' => $ipAddress,
    ]);

    return (int) $pdo->lastInsertId();
}

function findValidLoginChallenge(PDO $pdo, string $challengeHash): ?array
{
    $stmt = $pdo->prepare(
        'SELECT
            lc.id AS challenge_id,
            lc.user_id,
            lc.challenge_hash,
            lc.expires_at,
            lc.used,
            lc.used_at,
            lc.ip_address,
            u.id,
            u.name,
            u.email,
            u.role,
            u.is_active,
            u.created_at,
            u.updated_at
         FROM login_challenges lc
         INNER JOIN users u ON u.id = lc.user_id
         WHERE lc.challenge_hash = :challenge_hash
           AND lc.used = 0
           AND lc.expires_at > NOW()
           AND u.is_active = 1
         LIMIT 1'
    );

    $stmt->execute(['challenge_hash' => $challengeHash]);
    $record = $stmt->fetch();

    return $record ?: null;
}

function createPasswordResetToken(PDO $pdo, int $userId, string $tokenHash, string $expiresAt, string $ipAddress): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, ip_address)
         VALUES (:user_id, :token_hash, :expires_at, :ip_address)'
    );

    $stmt->execute([
        'user_id' => $userId,
        'token_hash' => $tokenHash,
        'expires_at' => $expiresAt,
        'ip_address' => $ipAddress,
    ]);

    return (int) $pdo->lastInsertId();
}

function findValidPasswordResetToken(PDO $pdo, string $tokenHash): ?array
{
    $stmt = $pdo->prepare(
        'SELECT
            prt.id,
            prt.user_id,
            prt.token_hash,
            prt.expires_at,
            prt.used,
            prt.used_at,
            prt.ip_address,
            u.email
         FROM password_reset_tokens prt
         INNER JOIN users u ON u.id = prt.user_id
         WHERE prt.token_hash = :token_hash
           AND prt.used = 0
           AND prt.expires_at > NOW()
         LIMIT 1'
    );

    $stmt->execute(['token_hash' => $tokenHash]);
    $record = $stmt->fetch();

    return $record ?: null;
}

function markPasswordResetTokenUsed(PDO $pdo, int $tokenId): void
{
    $stmt = $pdo->prepare(
        'UPDATE password_reset_tokens
         SET used = 1,
             used_at = NOW()
         WHERE id = :id'
    );

    $stmt->execute(['id' => $tokenId]);
}

function revokeAllTokensByUserId(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare(
        'UPDATE auth_tokens
         SET revoked = 1
         WHERE user_id = :user_id'
    );

    $stmt->execute(['user_id' => $userId]);
}

function upsertUserMfaSecret(PDO $pdo, int $userId, string $secret, bool $isEnabled): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO user_mfa (user_id, totp_secret, is_enabled)
         VALUES (:user_id, :totp_secret, :is_enabled)
         ON DUPLICATE KEY UPDATE
             totp_secret = VALUES(totp_secret),
             is_enabled = VALUES(is_enabled),
             updated_at = NOW()'
    );

    $stmt->execute([
        'user_id' => $userId,
        'totp_secret' => $secret,
        'is_enabled' => $isEnabled ? 1 : 0,
    ]);
}

function enableUserMfa(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare(
        'UPDATE user_mfa
         SET is_enabled = 1,
             updated_at = NOW()
         WHERE user_id = :user_id'
    );

    $stmt->execute(['user_id' => $userId]);
}

function disableUserMfa(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare(
        'UPDATE user_mfa
         SET is_enabled = 0,
             updated_at = NOW()
         WHERE user_id = :user_id'
    );

    $stmt->execute(['user_id' => $userId]);
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

function updateUserProfile(
    PDO $pdo,
    int $userId,
    ?string $name,
    ?string $email,
    ?string $passwordHash
): array {
    if ($name === null && $email === null && $passwordHash === null) {
        throw new InvalidArgumentException('Nenhum campo válido para atualização.');
    }

    $stmt = $pdo->prepare(
        'UPDATE users
         SET
             name = COALESCE(:name, name),
             email = COALESCE(:email, email),
             password_hash = COALESCE(:password_hash, password_hash),
             updated_at = NOW()
         WHERE id = :id'
    );

    $stmt->execute([
        'id' => $userId,
        'name' => $name,
        'email' => $email,
        'password_hash' => $passwordHash,
    ]);

    $updatedUser = findUserById($pdo, $userId);

    if (!$updatedUser) {
        throw new RuntimeException('Usuário não encontrado após atualização.');
    }

    return $updatedUser;
}

function listUsers(PDO $pdo, ?string $roleFilter = null): array
{
    if ($roleFilter !== null && !in_array($roleFilter, ['admin', 'user'], true)) {
        $roleFilter = null;
    }

    $stmt = $pdo->prepare(
        'SELECT
            id,
            name,
            email,
            role,
            is_active,
            created_at,
            updated_at
         FROM users
         WHERE (:role_filter IS NULL OR role = :role_value)
         ORDER BY created_at DESC, id DESC'
    );

    if ($roleFilter === null) {
        $stmt->bindValue(':role_filter', null, PDO::PARAM_NULL);
        $stmt->bindValue(':role_value', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':role_filter', $roleFilter, PDO::PARAM_STR);
        $stmt->bindValue(':role_value', $roleFilter, PDO::PARAM_STR);
    }

    $stmt->execute();

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

function getUserMfaByUserId(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT user_id, totp_secret, is_enabled, confirmed_at, created_at, updated_at
         FROM user_mfa
         WHERE user_id = :user_id
         LIMIT 1'
    );

    $stmt->execute(['user_id' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function markLoginChallengeUsed(PDO $pdo, int $challengeId): void
{
    $stmt = $pdo->prepare(
        'UPDATE login_challenges
         SET used = 1,
             used_at = NOW()
         WHERE id = :id'
    );

    $stmt->execute(['id' => $challengeId]);
}

function setUserPasswordHash(PDO $pdo, int $userId, string $passwordHash): void
{
    $stmt = $pdo->prepare(
        'UPDATE users
         SET password_hash = :password_hash,
             updated_at = NOW()
         WHERE id = :id'
    );

    $stmt->execute([
        'password_hash' => $passwordHash,
        'id' => $userId,
    ]);
}