<?php
declare(strict_types=1);

function generatePlainToken(): string
{
    return bin2hex(random_bytes(32));
}

function hashAccessToken(string $plainToken): string
{
    return hash('sha256', $plainToken);
}

function makeTokenExpiration(int $ttlSeconds): string
{
    $expiresAt = (new DateTimeImmutable('now'))->modify(sprintf('+%d seconds', $ttlSeconds));
    return $expiresAt->format('Y-m-d H:i:s');
}