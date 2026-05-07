<?php
declare(strict_types=1);

function taskLog(string $level, string $message, array $context = []): void
{
    $level = strtoupper(trim($level));

    $entry = [
        'timestamp' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
        'service' => 'task-service',
        'level' => $level,
        'message' => $message,
        'context' => $context,
    ];

    $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($line === false) {
        $line = sprintf(
            '{"timestamp":"%s","service":"task-service","level":"ERROR","message":"Falha ao serializar log"}',
            (new DateTimeImmutable())->format(DateTimeInterface::ATOM)
        );
    }

    $logFile = getenv('TASK_LOG_FILE') ?: '';

    if ($logFile !== '') {
        error_log($line . PHP_EOL, 3, $logFile);
        return;
    }

    error_log($line);
}

function taskLogInfo(string $message, array $context = []): void
{
    taskLog('INFO', $message, $context);
}

function taskLogWarning(string $message, array $context = []): void
{
    taskLog('WARNING', $message, $context);
}

function taskLogError(string $message, array $context = []): void
{
    taskLog('ERROR', $message, $context);
}