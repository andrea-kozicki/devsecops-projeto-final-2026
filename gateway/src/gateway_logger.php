<?php
declare(strict_types=1);

function gatewayLog(string $level, string $message, array $context = []): void
{
    $entry = [
        'timestamp' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
        'service' => 'gateway',
        'level' => strtoupper($level),
        'message' => $message,
        'context' => $context,
    ];

    $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($line === false) {
        $line = '{"service":"gateway","level":"ERROR","message":"Falha ao serializar log"}';
    }

    $logFile = getenv('GATEWAY_LOG_FILE') ?: '';

    if ($logFile !== '') {
        error_log($line . PHP_EOL, 3, $logFile);
        return;
    }

    error_log($line);
}

function gatewayLogInfo(string $message, array $context = []): void
{
    gatewayLog('INFO', $message, $context);
}

function gatewayLogWarning(string $message, array $context = []): void
{
    gatewayLog('WARNING', $message, $context);
}

function gatewayLogError(string $message, array $context = []): void
{
    gatewayLog('ERROR', $message, $context);
}