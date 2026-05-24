<?php
declare(strict_types=1);

function authClientIp(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}